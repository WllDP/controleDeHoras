<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') {
    header('Location: index.php');
    exit;
}

$usuario = (string)$_SESSION['usuario'];

// Busca projetos do usuário
$stmt = $db->prepare('SELECT id, nome, created_at FROM projetos WHERE usuario = :u ORDER BY datetime(created_at) DESC, id DESC');
$stmt->execute([':u' => $usuario]);
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pausedProjects = [];
$stmt = $db->prepare('SELECT DISTINCT projeto FROM atividades_pausadas WHERE colaborador = :u AND projeto IS NOT NULL AND projeto != ""');
$stmt->execute([':u' => $usuario]);
$pausedProjects = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

ob_start();
?>

<!-- Form body -->
<div class="pt-2 px-8 pb-0 w-full max-w-[820px] min-h-[100px] mx-auto">
    <a
        href="logout.php"
        class="absolute top-2 left-2 h-8 w-8 inline-flex items-center justify-center rounded-lg no-drag
               text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition select-none"
        title="Sair"
        aria-label="Sair"
    ><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Projetos</h1>
            <p class="text-slate-600 mt-1">
                Usuário:
                <span class="font-semibold inline-block max-w-[260px] truncate align-bottom"
                      title="<?= htmlspecialchars($usuario) ?>">
                    <?= htmlspecialchars($usuario) ?>
                </span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
        <!-- Lista de projetos -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Selecione um projeto</h2>
                <button
                    type="button"
                    id="editProjectsBtn"
                    class="h-10 w-10 inline-flex items-center justify-center text-[#0b2a5b] hover:text-[#1f5a96] transition text-lg"
                >
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                </button>
            </div>

            <?php if (count($projetos) > 0): ?>
                <!-- Caixa com altura controlada para não “estourar” a janela -->
                <div class="border rounded-lg overflow-hidden bg-white project-list">
                    <div id="projectsList" class="max-h-[140px] overflow-y-auto overflow-x-hidden">
                        <?php foreach ($projetos as $p): ?>
                            <a
                             href="selecionar_projeto.php?nome=<?= urlencode($p['nome']) ?>"
                                data-project-id="<?= (int)$p['id'] ?>"
                                class="block px-4 py-3 hover:bg-slate-50 border-b last:border-b-0 transition project-divider">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2 min-w-0 max-w-[200px]">
                                        <div class="min-w-0 max-w-[200px] font-medium text-slate-800 truncate project-name"
                                             data-project-name="<?= htmlspecialchars($p['nome']) ?>"
                                             title="<?= htmlspecialchars($p['nome']) ?>">
                                            <?= htmlspecialchars($p['nome']) ?>
                                        </div>
                                        <?php if (isset($pausedProjects[$p['nome']])): ?>
                                            <i class="fa-solid fa-circle activity-paused-indicator text-xs" aria-hidden="true" title="Atividade em Pausa" aria-label="Atividade em Pausa"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 whitespace-nowrap">
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-slate-50 border rounded-lg p-4">
                    <p class="text-slate-700">Você ainda não tem projetos criados.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Criar novo projeto -->
        <div class="pt-4 border-t project-divider">
            <h2 class="text-sm font-semibold text-slate-700 mb-2">Criar novo projeto</h2>

            <form id="createProjectForm" action="criar_projeto.php" method="POST" class="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    name="projeto"
                    placeholder="Nome do projeto"
                    class="flex-1 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                    maxlength="80"
                    autocomplete="off"
                />
                <button
                    type="submit"
                    class="bg-[#0b2a5b] text-white px-4 py-2 rounded hover:bg-[#1f5a96] transition whitespace-nowrap"
                >
                    Criar e entrar
                </button>
            </form>
        </div>
    </div>
</div>

<style>
  @keyframes projectNamePulse {
    0% { color: #1f2937; background-color: rgba(59, 130, 246, 0); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    35% { color: #0b2a5b; background-color: rgba(59, 130, 246, 0.18); box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.18); }
    100% { color: #1f2937; background-color: rgba(59, 130, 246, 0); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
  }
  .project-name.edit-hint {
    border-radius: 6px;
    animation: projectNamePulse 900ms ease-in-out;
  }
  body[data-theme="dark"] #widget-root .project-list {
    --project-divider: #1f2937;
    border-color: var(--project-divider) !important;
    background: var(--app-card);
  }
  body[data-theme="dark"] #widget-root .project-divider {
    border-color: #1f2937 !important;
  }
</style>

<script>
  (() => {
    const editBtn = document.getElementById("editProjectsBtn");
    const list = document.getElementById("projectsList");
    if (!editBtn || !list) return;

    let editing = false;
    const changedProjects = new Map();
    let alertHideTimer = null;
    function nextModalToken(overlay) {
      if (!overlay) return null;
      const token = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      overlay.dataset.modalToken = token;
      return token;
    }
    function isTokenActive(overlay, token) {
      return Boolean(overlay && token && overlay.dataset.modalToken === token);
    }
    function showProjectAlert(message) {
      const overlay = document.getElementById("appModalOverlay");
      const msg = document.getElementById("appModalMessage");
      const okBtn = document.getElementById("appModalOk");
      const cancelBtn = document.getElementById("appModalCancel");
      const box = document.getElementById("appModalBox");
      if (!overlay || !msg || !okBtn || !cancelBtn) {
        window.alert(message);
        return;
      }

      if (overlay.__modalState && typeof overlay.__modalState.forceClose === "function") {
        overlay.__modalState.forceClose({ keepOpen: true });
      }

      const token = nextModalToken(overlay);
      const iconDurationMs = 1600;
      const icon = `
        <div class="flex flex-col items-center gap-2 text-center">
          <div class="text-sm text-slate-800">${message}</div>
          <lord-icon
            src="https://cdn.lordicon.com/yfxqzclt.json"
            trigger="in"
            delay="0"
            state="in-error"
            colors="primary:#e83a30"
            style="width:40px;height:40px"
          ></lord-icon>
        </div>
      `;

      if (alertHideTimer) {
        clearTimeout(alertHideTimer);
        alertHideTimer = null;
      }

      msg.innerHTML = icon;
      msg.title = message;
      msg.classList.add("has-icon", "text-center");
      if (box) box.classList.add("app-modal-compact");
      overlay.classList.remove("is-open");
      overlay.classList.add("hidden");
      void overlay.offsetWidth;
      cancelBtn.classList.add("hidden");
      okBtn.classList.add("hidden");
      overlay.classList.remove("hidden");
      requestAnimationFrame(() => {
        overlay.classList.add("is-open");
      });

      alertHideTimer = setTimeout(() => {
        if (!isTokenActive(overlay, token)) return;
        overlay.classList.remove("is-open");
        setTimeout(() => {
          if (isTokenActive(overlay, token)) {
            overlay.classList.add("hidden");
          }
        }, 150);
      }, iconDurationMs);

      overlay.__modalState = {
        token,
        forceClose: (opts = {}) => {
          if (alertHideTimer) {
            clearTimeout(alertHideTimer);
            alertHideTimer = null;
          }
          if (overlay.__modalState && overlay.__modalState.token === token) {
            overlay.__modalState = null;
          }
          if (opts.keepOpen) return;
          if (isTokenActive(overlay, token)) {
            overlay.classList.remove("is-open");
            setTimeout(() => {
              if (isTokenActive(overlay, token)) {
                overlay.classList.add("hidden");
              }
            }, 150);
          }
        },
      };
    }

    function setEditingState(next) {
      editing = next;
      editBtn.innerHTML = editing
        ? '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>'
        : '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>';
      editBtn.classList.toggle("text-emerald-600", editing);
      editBtn.classList.toggle("hover:text-emerald-700", editing);
      editBtn.classList.toggle("text-[#0b2a5b]", !editing);
      editBtn.classList.toggle("hover:text-[#1f5a96]", !editing);

      if (editing) {
        list.querySelectorAll(".project-name").forEach((el) => {
          el.classList.remove("edit-hint");
          void el.offsetWidth;
          el.classList.add("edit-hint");
        });
      }
    }

    function finishEdit(nameEl, link, newName) {
      const id = link.dataset.projectId;
      const original = nameEl.dataset.originalName || "";
      const finalName = newName || original;
      nameEl.removeAttribute("data-editing");
      nameEl.textContent = finalName;
      nameEl.dataset.projectName = finalName;
      nameEl.title = finalName;
      if (newName && newName !== original) {
        changedProjects.set(id, finalName);
      } else {
        changedProjects.delete(id);
      }
    }

    function cancelEditing() {
      changedProjects.forEach((_nome, id) => {
        const link = list.querySelector(`[data-project-id="${id}"]`);
        if (!link) return;
        const nameEl = link.querySelector("[data-project-name]");
        if (!nameEl) return;
        const original = nameEl.dataset.originalName || nameEl.textContent.trim();
        nameEl.textContent = original;
        nameEl.dataset.projectName = original;
        nameEl.title = original;
        link.href = `selecionar_projeto.php?nome=${encodeURIComponent(original)}`;
      });
      changedProjects.clear();
      setEditingState(false);
    }

    list.addEventListener("click", (e) => {
      const link = e.target.closest("[data-project-id]");
      if (!link || !editing) return;
      e.preventDefault();

      const nameEl = link.querySelector("[data-project-name]");
      if (!nameEl || nameEl.dataset.editing === "true") return;

      const original = nameEl.textContent.trim();
      nameEl.dataset.originalName = original;
      nameEl.dataset.editing = "true";
      nameEl.textContent = "";

      const input = document.createElement("input");
      input.type = "text";
      input.value = original;
      input.maxLength = 80;
      input.className = "w-full border rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500";
      nameEl.appendChild(input);
      input.focus();
      input.select();

      input.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter") {
          ev.preventDefault();
          input.blur();
          if (editing) {
            setTimeout(() => editBtn.click(), 0);
          }
        } else if (ev.key === "Escape") {
          input.value = original;
          input.blur();
        }
      });

      input.addEventListener("blur", () => {
        const nextName = input.value.trim();
        finishEdit(nameEl, link, nextName);
      });
    });

    editBtn.addEventListener("click", async () => {
      if (!editing) {
        setEditingState(true);
        return;
      }

      if (changedProjects.size === 0) {
        setEditingState(false);
        return;
      }

      editBtn.disabled = true;
      editBtn.innerHTML = '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>';

      const updates = Array.from(changedProjects.entries()).map(([id, nome]) => {
        const payload = new URLSearchParams();
        payload.set("id", id);
        payload.set("nome", nome);
        payload.set("ajax", "1");
        return fetch("editar_projeto.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: payload.toString(),
        }).then(async (res) => {
          if (res.status === 409) {
            throw new Error("Nome de projeto em uso");
          }
          const data = await res.json().catch(() => null);
          if (!res.ok || !data || !data.ok) {
            const msg = data?.message || "Não foi possível salvar o projeto.";
            throw new Error(msg);
          }
          return { id, nome };
        });
      });

      try {
        const results = await Promise.all(updates);
        results.forEach(({ id, nome }) => {
          const link = list.querySelector(`[data-project-id="${id}"]`);
          if (!link) return;
          link.href = `selecionar_projeto.php?nome=${encodeURIComponent(nome)}`;
          const nameEl = link.querySelector("[data-project-name]");
          if (!nameEl) return;
          nameEl.dataset.projectName = nome;
          nameEl.title = nome;
          nameEl.textContent = nome;
          changedProjects.delete(id);
        });
        setEditingState(false);
      } catch (err) {
        const msg = err?.message || "Não foi possível salvar o projeto.";
        showProjectAlert(msg);
        cancelEditing();
      } finally {
        editBtn.disabled = false;
        setEditingState(editing);
      }
    });

    const createForm = document.getElementById("createProjectForm");
    if (createForm && window.fetch) {
      createForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const input = createForm.querySelector('input[name="projeto"]');
        const submitBtn = createForm.querySelector('button[type="submit"]');
        const nome = input ? input.value.trim() : "";
        if (!nome) {
          createForm.submit();
          return;
        }

        if (submitBtn) submitBtn.disabled = true;

        const payload = new URLSearchParams();
        payload.set("projeto", nome);
        payload.set("ajax", "1");

        try {
          const res = await fetch("criar_projeto.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest",
              Accept: "application/json",
            },
            body: payload.toString(),
          });

          if (res.status === 409) {
            showProjectAlert("Nome de projeto em uso");
            return;
          }

          const data = await res.json().catch(() => null);
          if (res.ok && data?.ok) {
            window.location.href = data.redirect || "dashboard.php";
            return;
          }

          const msg = data?.message || "Nao foi possivel criar o projeto.";
          showProjectAlert(msg);
        } catch (_err) {
          showProjectAlert("Nao foi possivel criar o projeto.");
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    }
  })();
</script>

<?php
$content = ob_get_clean();
$title = 'Projetos';
$showThemeToggle = true;
require 'layout.php';
