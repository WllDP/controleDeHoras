(() => {
  "use strict";

  // -----------------------------
  // Contexto (usuário + projeto)
  // -----------------------------
  function getContext() {
    const ctx = window.APP_CONTEXT || {};
    const usuario = String(ctx.usuario || "").trim();
    const projeto = String(ctx.projeto || "").trim();
    return { usuario, projeto };
  }

  function cacheKey() {
    const { usuario, projeto } = getContext();
    return `controle_horas::${usuario}::${projeto}`;
  }

  function loadCache() {
    try {
      const raw = localStorage.getItem(cacheKey());
      if (!raw) return { atividades: [], exportedIds: [] };

      const parsed = JSON.parse(raw);
      const atividades = Array.isArray(parsed.atividades) ? parsed.atividades : [];
      const exportedIds = Array.isArray(parsed.exportedIds) ? parsed.exportedIds : [];

      return {
        atividades: atividades
          .filter(a => a && typeof a === "object")
          .map(a => ({
            id: Number(a.id) || Date.now(),
            dbId: Number(a.dbId) || null,
            nome: String(a.nome || ""),
            inicio: typeof a.inicio === "string" ? a.inicio : new Date(a.inicio || Date.now()).toISOString(),
            tempo: Number(a.tempo) || 0,
            rodando: Boolean(a.rodando),
            pausada: Boolean(a.pausada),
            lastTick: Number(a.lastTick) || null,
            exportada: Boolean(a.exportada),
          }))
          .filter(a => a.nome.trim() !== ""),
        exportedIds: exportedIds.map(x => Number(x)).filter(n => Number.isFinite(n)),
      };
    } catch {
      return { atividades: [], exportedIds: [] };
    }
  }

  function saveCache() {
    const payload = {
      atividades,
      exportedIds: Array.from(exportedIds),
    };
    localStorage.setItem(cacheKey(), JSON.stringify(payload));
  }

  function activityKey(atividade) {
    const nome = String(atividade?.nome || "").trim();
    const inicioMs = Date.parse(atividade?.inicio || "");
    const tempo = Number(atividade?.tempo) || 0;
    const safeInicio = Number.isFinite(inicioMs) ? inicioMs : 0;
    return `${nome}||${safeInicio}||${tempo}`;
  }

  function loadDbActivities() {
    if (!Array.isArray(window.DB_ATIVIDADES)) return [];
    return window.DB_ATIVIDADES
      .filter(a => a && typeof a === "object")
      .map(a => ({
        id: Number(a.id) || Date.now(),
        dbId: Number.isFinite(Number(a.dbId)) && Number(a.dbId) > 0
          ? Number(a.dbId)
          : (Boolean(a.pausada) ? null : (Number(a.id) || null)),
        nome: String(a.nome || ""),
        inicio: typeof a.inicio === "string" ? a.inicio : new Date(a.inicio || Date.now()).toISOString(),
        tempo: Number(a.tempo) || 0,
        rodando: Boolean(a.rodando),
        pausada: Boolean(a.pausada),
        lastTick: Number(a.lastTick) || null,
        exportada: Boolean(a.exportada),
      }))
      .filter(a => a.nome.trim() !== "");
  }

  function mergeDbActivities() {
    const dbAtividades = loadDbActivities();
    if (!dbAtividades.length) return;

    const existing = new Map(atividades.map(a => [activityKey(a), a]));
    const existingById = new Map(atividades.map(a => [a.id, a]));
    let changed = false;

    dbAtividades.forEach(a => {
      if (!a || typeof a !== "object") {
        return;
      }
      const key = activityKey(a);
      const local = existingById.get(a.id) || existing.get(key);
      if (!local) {
        atividades.push(a);
        existing.set(key, a);
        existingById.set(a.id, a);
        changed = true;
      } else if (a.exportada && !local.exportada) {
        local.exportada = true;
        changed = true;
      }
      const target = local || a;
      if (a.pausada && !target.pausada) {
        target.pausada = true;
        target.rodando = false;
        target.lastTick = null;
        const tempoDb = Number(a.tempo) || 0;
        if (tempoDb > target.tempo) {
          target.tempo = tempoDb;
        }
        changed = true;
      }
    });

    if (changed) {
      saveCache();
    }
  }

  // -----------------------------
  // Estado
  // -----------------------------
  let { atividades, exportedIds } = (() => {
    const loaded = loadCache();
    return {
      atividades: loaded.atividades,
      exportedIds: new Set(loaded.exportedIds),
    };
  })();

  let timer = null;
  let exportando = false;
  let exportDownloadPendingAt = 0;
  let exportToastArmed = false;
  let pendingExportIds = new Set();
  const recentlyStoppedIds = new Set();
  const recentlyExportedIds = new Set();

  // -----------------------------
  // UI helpers
  // -----------------------------
  function buildMiniPayload(atividade) {
    if (!atividade) return null;
    return {
      id: atividade.id,
      nome: atividade.nome,
      tempo: atividade.tempo,
      pausada: Boolean(atividade.pausada),
      lastTick: atividade.lastTick,
      rodando: Boolean(atividade.rodando),
    };
  }

  function notifyActivityStatus() {
    const ativa = atividadeEmAndamento();
    if (window.DesktopAPI?.setActivityStatus) {
      window.DesktopAPI.setActivityStatus(ativa ? buildMiniPayload(ativa) : null);
    }
  }
  function showAlert(message) {
    showModal({ message, confirm: false });
  }

  function hintActivityInput(input) {
    if (!input) return;
    input.classList.remove("activity-input-attention");
    void input.offsetWidth;
    input.classList.add("activity-input-attention");
    input.focus();
    setTimeout(() => {
      input.classList.remove("activity-input-attention");
    }, 1000);
  }

  function showIconAlert(message) {
    const iconDurationMs = 1600;
    const icon = `
      <div class="flex flex-col items-center gap-2 text-center">
        <div class="text-sm text-slate-800">${escapeHtml(message)}</div>
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
    showModal({
      message,
      confirm: false,
      html: true,
      autoCloseMs: iconDurationMs,
      hideOk: true,
      content: icon,
    });
  }

  function confirmWithFocus(message) {
    return showModal({ message, confirm: true });
  }

  function nextModalToken(overlay) {
    if (!overlay) return null;
    const token = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    overlay.dataset.modalToken = token;
    return token;
  }

  function isTokenActive(overlay, token) {
    return Boolean(overlay && token && overlay.dataset.modalToken === token);
  }

  function showModal({ message, confirm, html = false, autoCloseMs = 0, hideOk = false, content = "" }) {
    const overlay = document.getElementById("appModalOverlay");
    const msg = document.getElementById("appModalMessage");
    const okBtn = document.getElementById("appModalOk");
    const cancelBtn = document.getElementById("appModalCancel");
    const box = document.getElementById("appModalBox");
    if (!overlay || !msg || !okBtn || !cancelBtn) {
      return Promise.resolve(confirm ? window.confirm(message) : (window.alert(message), true));
    }

    if (overlay.__modalState && typeof overlay.__modalState.forceClose === "function") {
      overlay.__modalState.forceClose({ keepOpen: true });
    }

    const token = nextModalToken(overlay);
    msg.title = message;
    msg.classList.toggle("has-icon", html);
    msg.classList.toggle("text-center", html || confirm);
    if (box) box.classList.toggle("app-modal-compact", html || confirm);
    if (html) {
      msg.innerHTML = content || escapeHtml(message);
    } else {
      msg.textContent = message;
    }
    cancelBtn.classList.toggle("hidden", !confirm);
    okBtn.classList.toggle("hidden", hideOk || autoCloseMs > 0);
    overlay.classList.remove("hidden");
    requestAnimationFrame(() => {
      overlay.classList.add("is-open");
    });

    return new Promise(resolve => {
      let autoTimer = null;
      let closed = false;
      const cleanup = (result) => {
        if (closed) return;
        closed = true;
        if (autoTimer) clearTimeout(autoTimer);
        okBtn.onclick = null;
        cancelBtn.onclick = null;
        if (overlay.__modalState && overlay.__modalState.token === token) {
          overlay.__modalState = null;
        }
        if (isTokenActive(overlay, token)) {
          overlay.classList.remove("is-open");
          setTimeout(() => {
            if (isTokenActive(overlay, token)) {
              overlay.classList.add("hidden");
            }
          }, 150);
        }
        resolve(result);
      };

      okBtn.onclick = () => cleanup(true);
      cancelBtn.onclick = () => cleanup(false);

      // fallback: ESC cancela quando for confirm
      const onKey = (e) => {
        if (e.key === "Escape" && confirm) {
          document.removeEventListener("keydown", onKey);
          cleanup(false);
        }
      };
      document.addEventListener("keydown", onKey, { once: true });

      if (!okBtn.classList.contains("hidden")) {
        setTimeout(() => okBtn.focus(), 0);
      }

      if (!confirm && autoCloseMs > 0) {
        autoTimer = setTimeout(() => cleanup(true), autoCloseMs);
      }

      overlay.__modalState = {
        token,
        forceClose: (opts = {}) => {
          if (opts.keepOpen) {
            if (autoTimer) clearTimeout(autoTimer);
            okBtn.onclick = null;
            cancelBtn.onclick = null;
            if (overlay.__modalState && overlay.__modalState.token === token) {
              overlay.__modalState = null;
            }
            resolve(false);
            closed = true;
            return;
          }
          cleanup(false);
        },
      };
    });
  }

  function formatar(segundos) {
    const h = String(Math.floor(segundos / 3600)).padStart(2, "0");
    const m = String(Math.floor((segundos % 3600) / 60)).padStart(2, "0");
    const s = String(segundos % 60).padStart(2, "0");
    return `${h}:${m}:${s}`;
  }

  function renderizar() {
    const tbody = document.getElementById("listaAtividades");
    if (!tbody) return;
    const tableContainer = tbody.closest(".activity-table-container");

    const prevNameRects = new Map();
    const prevRowRects = new Map();
    tbody.querySelectorAll("tr.activity-row[data-id]").forEach(el => {
      const id = el.getAttribute("data-id");
      if (!id) return;
      prevRowRects.set(id, el.getBoundingClientRect());
    });
    tbody.querySelectorAll(".activity-name[data-id]").forEach(el => {
      const id = el.getAttribute("data-id");
      if (!id) return;
      prevNameRects.set(id, el.getBoundingClientRect());
    });

    tbody.innerHTML = "";

    atividades
      .slice()
      .sort((a, b) => {
        if (a.rodando !== b.rodando) return a.rodando ? -1 : 1;
        return b.id - a.id;
      })
      .forEach(a => {
        const tr = document.createElement("tr");
        tr.className = "border-t activity-row";
        tr.setAttribute("data-id", String(a.id));
        if (!prevRowRects.has(String(a.id))) {
          tr.classList.add("activity-row-enter");
        }
        let statusIcon = "";
        if (a.rodando) {
          statusIcon = '<i class="fa-solid fa-circle fa-fade activity-running-indicator text-xs" aria-hidden="true"></i>';
        } else if (a.pausada) {
          statusIcon = '<i class="fa-solid fa-circle activity-paused-indicator text-xs" aria-hidden="true"></i>';
        } else {
          const exported = exportedIds.has(a.id) || a.exportada === true;
          const exportedClass = exported ? "activity-exported" : "";
          const appearClass = recentlyStoppedIds.has(a.id) ? "activity-export-appear" : "";
          const fadeClass = recentlyExportedIds.has(a.id) ? "activity-export-fade" : "";
          const exportTitle = exported ? "Exportada" : "Não Exportada";
          statusIcon = `<i class="fa-solid fa-file-export activity-export-icon ${exportedClass} ${appearClass} ${fadeClass}" title="${exportTitle}" aria-label="${exportTitle}"></i>`;
          if (recentlyStoppedIds.has(a.id)) {
            setTimeout(() => recentlyStoppedIds.delete(a.id), 700);
          }
          if (recentlyExportedIds.has(a.id)) {
            setTimeout(() => recentlyExportedIds.delete(a.id), 700);
          }
        }
        const nameHtml = `<span class="activity-name truncate" data-id="${a.id}">${escapeHtml(a.nome)}</span>`;
        const activityHtml =
          a.rodando || a.pausada
            ? `${statusIcon}${nameHtml}`
            : `${nameHtml}${statusIcon}`;
        tr.innerHTML = `
          <td class="p-2">
            <span class="inline-flex items-center gap-2 max-w-[180px] truncate" title="${escapeHtml(a.nome)}">
              ${activityHtml}
            </span>
          </td>
          <td class="p-2 font-mono">${formatar(a.tempo)}</td>
          <td class="p-2 text-right">
            <button data-id="${a.id}" class="text-red-500 hover:underline">Excluir</button>
          </td>
        `;
        tbody.appendChild(tr);
      });

    if (prevRowRects.size) {
      const rows = Array.from(tbody.querySelectorAll("tr.activity-row[data-id]"));
      rows.forEach(el => {
        const id = el.getAttribute("data-id");
        if (!id || !prevRowRects.has(id)) return;
        const prev = prevRowRects.get(id);
        const next = el.getBoundingClientRect();
        const dx = prev.left - next.left;
        const dy = prev.top - next.top;
        if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return;
        el.style.transition = "none";
        el.style.transform = `translate(${dx}px, ${dy}px)`;
        el.getBoundingClientRect();
      });
      requestAnimationFrame(() => {
        rows.forEach(el => {
          if (!el.style.transform) return;
          el.style.transition = "";
          el.style.transform = "";
        });
      });
    }

    if (prevNameRects.size) {
      const names = Array.from(tbody.querySelectorAll(".activity-name[data-id]"));
      names.forEach(el => {
        const id = el.getAttribute("data-id");
        if (!id || !prevNameRects.has(id)) return;
        const prev = prevNameRects.get(id);
        const next = el.getBoundingClientRect();
        const dx = prev.left - next.left;
        const dy = prev.top - next.top;
        if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return;
        el.style.transition = "none";
        el.style.transform = `translate(${dx}px, ${dy}px)`;
        el.getBoundingClientRect();
      });
      requestAnimationFrame(() => {
        names.forEach(el => {
          if (!el.style.transform) return;
          el.style.transition = "";
          el.style.transform = "";
        });
      });
    }

    requestAnimationFrame(() => {
      tbody.querySelectorAll(".activity-row-enter").forEach(el => {
        el.classList.remove("activity-row-enter");
      });
    });

    if (tableContainer) {
      const updateScrollGutter = () => {
        const hasScroll = tableContainer.scrollHeight - tableContainer.clientHeight > 4;
        const prevHasScroll = tableContainer.dataset.hasScroll === "1";
        tableContainer.classList.toggle("no-scroll", !hasScroll);
        if (!prevHasScroll && hasScroll) {
          tableContainer.classList.add("scroll-slip");
          clearTimeout(tableContainer._scrollSlipTimer);
          tableContainer._scrollSlipTimer = setTimeout(() => {
            tableContainer.classList.remove("scroll-slip");
          }, 260);
        }
        tableContainer.dataset.hasScroll = hasScroll ? "1" : "0";
      };
      if (tableContainer._scrollGutterTimer) {
        clearTimeout(tableContainer._scrollGutterTimer);
      }
      tableContainer._scrollGutterTimer = setTimeout(updateScrollGutter, 220);
    }

    tbody.querySelectorAll("button[data-id]").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = Number(btn.getAttribute("data-id"));
        remover(id);
      });
    });
  }

  function setVisibility(btn, visible) {
    if (!btn) return;
    btn.classList.toggle("opacity-0", !visible);
    btn.classList.toggle("pointer-events-none", !visible);
    btn.classList.toggle("scale-95", !visible);
    btn.classList.toggle("opacity-100", visible);
    btn.classList.toggle("scale-100", visible);
    if (visible) {
      btn.removeAttribute("aria-hidden");
    } else {
      btn.setAttribute("aria-hidden", "true");
    }
  }

  function setButtonState(state) {
    const playBtn = document.getElementById("playBtn");
    const stopBtn = document.getElementById("stopBtn");
    const pauseBtn = document.getElementById("pauseBtn");
    const resumeBtn = document.getElementById("resumeBtn");
    const runningControls = document.getElementById("runningControls");
    if (!playBtn || !stopBtn || !pauseBtn || !resumeBtn || !runningControls) return;

    const running = state === "running";
    const paused = state === "paused";

    setVisibility(playBtn, !running && !paused);
    setVisibility(runningControls, running || paused);
    setVisibility(pauseBtn, running);
    setVisibility(resumeBtn, paused);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function mostrarModal(message = "Atividade iniciada") {
    const overlay = document.getElementById("modalOverlay");
    const box = document.getElementById("modalBox");
    if (!overlay || !box) return;

    const iconDurationMs = 1600;
    box.innerHTML = `
      <div class="flex flex-col items-center gap-2 text-center">
        <div class="text-sm text-slate-800">${escapeHtml(message)}</div>
        <lord-icon
          src="https://cdn.lordicon.com/fspidoxv.json"
          trigger="in"
          delay="0"
          state="in-assignment"
          colors="primary:#16a34a"
          style="width:40px;height:40px"
        ></lord-icon>
      </div>
    `;
    box.title = "";
    overlay.classList.remove("hidden");

    setTimeout(() => {
      box.classList.remove("opacity-0", "scale-95");
      box.classList.add("opacity-100", "scale-100");
    }, 10);

    setTimeout(() => {
      box.classList.remove("opacity-100", "scale-100");
      box.classList.add("opacity-0", "scale-95");

      setTimeout(() => {
        overlay.classList.add("hidden");
      }, 300);
    }, iconDurationMs);
  }

  function showToast(message, { html = false } = {}) {
    const overlay = document.getElementById("modalOverlay");
    const box = document.getElementById("modalBox");
    if (!overlay || !box) return;

    if (html) {
      box.innerHTML = message;
    } else {
      box.textContent = message;
    }

    overlay.classList.remove("hidden");

    setTimeout(() => {
      box.classList.remove("opacity-0", "scale-95");
      box.classList.add("opacity-100", "scale-100");
    }, 10);

    setTimeout(() => {
      box.classList.remove("opacity-100", "scale-100");
      box.classList.add("opacity-0", "scale-95");

      setTimeout(() => {
        overlay.classList.add("hidden");
      }, 300);
    }, 1200);
  }

  // -----------------------------
  // Lógica de atividades
  // -----------------------------
  function atividadeRodando() {
    return atividades.find(a => a.rodando);
  }

  function atividadePausada() {
    return atividades.find(a => a.pausada);
  }

  function atividadeEmAndamento() {
    return atividadeRodando() || atividadePausada();
  }

  function iniciarTimerSePreciso() {
    if (timer) return;
    timer = setInterval(() => {
      const ativa = atividadeRodando();
      if (!ativa) return;

      const now = Date.now();
      if (!ativa.lastTick) {
        ativa.lastTick = now;
        saveCache();
        return;
      }

      const deltaMs = Math.max(0, now - ativa.lastTick);
      const deltaS = Math.floor(deltaMs / 1000);
      if (deltaS > 0) {
        ativa.tempo += deltaS;
        ativa.lastTick += deltaS * 1000;
        saveCache();
        renderizar();
      }
    }, 1000);
  }

  function play() {
    exportToastArmed = false;
    const pausada = atividadePausada();
    if (pausada) {
      const input = document.getElementById("atividade");
      const nome = String(input?.value || "").trim();
      if (nome) {
        showIconAlert("Finalize ou retome a atividade atual antes de iniciar outra.");
        return false;
      }
      return retomar(pausada);
    }

    if (atividadeRodando()) {
      showIconAlert("Finalize a atividade atual antes de iniciar outra.");
      return false;
    }

    const input = document.getElementById("atividade");
    const nome = String(input?.value || "").trim();
    if (!nome) {
      hintActivityInput(input);
      return false;
    }

    mostrarModal();

    const nova = {
      id: Date.now(),
      nome,
      inicio: new Date().toISOString(),
      tempo: 0,
      rodando: true,
      lastTick: Date.now(),
      exportada: false,
    };

    atividades.push(nova);
    saveCache();

    iniciarTimerSePreciso();
    renderizar();
    setButtonState("running");
    notifyActivityStatus();

    if (input) input.value = "";
    return true;
  }

  function pausar() {
    exportToastArmed = false;
    const ativa = atividadeRodando();
    if (!ativa) return false;

    const now = Date.now();
    if (ativa.lastTick) {
      const deltaMs = Math.max(0, now - ativa.lastTick);
      ativa.tempo += Math.floor(deltaMs / 1000);
    }

    ativa.rodando = false;
    ativa.pausada = true;
    ativa.lastTick = null;

    saveCache();
    persistPausedActivity(ativa).catch((e) => console.warn("Falha ao salvar pausa", e));
    renderizar();
    setButtonState("paused");
    notifyActivityStatus();
    return true;
  }

  function retomar(atividade) {
    exportToastArmed = false;
    const alvo = atividade || atividadePausada();
    if (!alvo) return false;

    alvo.rodando = true;
    alvo.pausada = false;
    alvo.lastTick = Date.now();

    saveCache();
    clearPausedActivity(alvo).catch((e) => console.warn("Falha ao limpar pausa", e));
    iniciarTimerSePreciso();
    renderizar();
    setButtonState("running");
    notifyActivityStatus();
    return true;
  }

  function stop() {
    exportToastArmed = false;
    const ativa = atividadeRodando() || atividadePausada();
    if (!ativa) return false;

    if (ativa.rodando) {
      const now = Date.now();
      if (ativa.lastTick) {
        const deltaMs = Math.max(0, now - ativa.lastTick);
        ativa.tempo += Math.floor(deltaMs / 1000);
      }
    }

    ativa.rodando = false;
    ativa.pausada = false;
    ativa.lastTick = null;

    saveCache();
    clearPausedActivity(ativa).catch((e) => console.warn("Falha ao limpar pausa", e));
    recentlyStoppedIds.add(ativa.id);
    persistActivity(ativa).catch(() => {
      showAlert("NÃ£o foi possÃ­vel salvar a atividade no banco.");
    });
    renderizar();
    setButtonState("stopped");
    notifyActivityStatus();
    return true;
  }

  async function remover(id) {
    const alvo = atividades.find(a => a.id === id);
    if (!alvo) return;

    if (alvo.rodando || alvo.pausada) {
      showIconAlert("Finalize a atividade antes de excluir.");
      return;
    }

    const ok = await confirmWithFocus(`Excluir a atividade ${alvo.nome}?`);
    if (!ok) return;

    if (Number.isFinite(Number(alvo.dbId))) {
      try {
        const payload = new URLSearchParams();
        payload.set("id", String(alvo.dbId));
        const res = await fetch("excluir_registro.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: payload.toString(),
        });
        if (!res.ok) {
          throw new Error(`Falha ao excluir registro (${res.status})`);
        }
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) {
          throw new Error("Falha ao excluir registro");
        }
      } catch (e) {
        console.error(e);
        showAlert("Não foi possível excluir a atividade no banco.");
        return;
      }
    }

    atividades = atividades.filter(a => a.id !== id);
    exportedIds.delete(id);
    saveCache();
    renderizar();
    notifyActivityStatus();
  }

  // -----------------------------
  // Helpers exportação (compatibilidade PHP)
  // -----------------------------
  function pad2(n) {
    return String(n).padStart(2, "0");
  }

  function formatLocalDateTime(dateOrIso) {
    const d = dateOrIso instanceof Date ? dateOrIso : new Date(dateOrIso || Date.now());
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())} ${pad2(d.getHours())}:${pad2(
      d.getMinutes()
    )}:${pad2(d.getSeconds())}`;
  }

  async function persistActivity(atividade) {
    if (!atividade || !atividade.inicio) return;

    const inicioDate = new Date(atividade.inicio);
    const fimDate = new Date(inicioDate.getTime() + (Number(atividade.tempo) || 0) * 1000);

    const payload = new URLSearchParams();
    payload.set("atividade", atividade.nome);
    payload.set("inicio", formatLocalDateTime(inicioDate));
    payload.set("fim", formatLocalDateTime(fimDate));
    payload.set("inicio_iso", inicioDate.toISOString());
    payload.set("fim_iso", fimDate.toISOString());
    payload.set("ajax", "1");

    const res = await fetch("salvar_registro.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: payload.toString(),
    });

    if (!res.ok) {
      throw new Error(`Falha ao salvar registro (${res.status})`);
    }

    const data = await res.json().catch(() => null);
    if (data && data.ok && Number.isFinite(Number(data.id))) {
      atividade.dbId = Number(data.id);
      saveCache();
    }
  }

  async function persistPausedActivity(atividade) {
    if (!atividade) return;

    const payload = new URLSearchParams();
    payload.set("app_id", String(atividade.id));
    payload.set("atividade", atividade.nome);
    payload.set("inicio_iso", atividade.inicio);
    payload.set("tempo_segundos", String(Number(atividade.tempo) || 0));
    payload.set("ajax", "1");

    const res = await fetch("salvar_pausa.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: payload.toString(),
    });

    if (!res.ok) {
      throw new Error(`Falha ao salvar pausa (${res.status})`);
    }
  }

  async function clearPausedActivity(atividade) {
    if (!atividade) return;

    const payload = new URLSearchParams();
    payload.set("app_id", String(atividade.id));
    payload.set("ajax", "1");

    const res = await fetch("remover_pausa.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: payload.toString(),
    });

    if (!res.ok) {
      throw new Error(`Falha ao limpar pausa (${res.status})`);
    }
  }

  // Retorna { data: "DD/MM/YYYY", hora: "HH:MM:SS", iso: original }
  function normalizarInicio(inicioIsoOrAny) {
    const d = new Date(inicioIsoOrAny || Date.now());
    const data = `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()}`;
    const hora = `${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
    return { data, hora, iso: typeof inicioIsoOrAny === "string" ? inicioIsoOrAny : d.toISOString() };
  }

  // -----------------------------
  // Exportação
  // -----------------------------
  async function baixarPlanilha({ modo }) {
    if (exportando) return;
    exportando = true;

    try {
      if (atividadeRodando() || atividadePausada()) {
        showIconAlert("Finalize a atividade antes de exportar.");
        return;
      }

      // Snapshot de tempo se tiver rodando
      const ativa = atividadeRodando();
      if (ativa) {
        const now = Date.now();
        if (ativa.lastTick) {
          const deltaMs = Math.max(0, now - ativa.lastTick);
          ativa.tempo += Math.floor(deltaMs / 1000);
          ativa.lastTick = now;
        }
      }

      const selecionadas =
        modo === "atualizar" ? atividades.filter(a => !exportedIds.has(a.id)) : atividades.slice();

      if (selecionadas.length === 0) {
        if (modo === "atualizar") {
          showIconAlert("Nenhuma nova atividade para atualizar na planilha.");
        } else {
          showIconAlert("Nenhuma atividade para exportar.");
        }
        return;
      }

      // Monta payload com aliases para o PHP (evita “modelo vazio”)
      const payload = selecionadas.map(a => {
        const ini = normalizarInicio(a.inicio);
        return {
          id: a.id,
          dbId: a.dbId ?? null,
          nome: a.nome,
          atividade: a.nome,
          descricao: a.nome,
          data: ini.data,
          inicio: ini.hora,
          inicio_iso: ini.iso,
          tempo: a.tempo,
          duracao: a.tempo,
        };
      });

      // Escolha do destino (Electron): prepara o próximo download
      const suggested = `controle_horas_${getContext().usuario}_${getContext().projeto}.xlsx`;
      if (window.DesktopAPI?.prepararDestinoDownload) {
        const ok = await window.DesktopAPI.prepararDestinoDownload(suggested);
        if (!ok) return; // cancelou
      }

      // Dispara o download via POST (Content-Disposition do PHP)
      exportDownloadPendingAt = Date.now();
      exportToastArmed = true;
      pendingExportIds = new Set(selecionadas.map(a => a.id));
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "exportar.php";
      form.style.display = "none";
      form.target = "_self";

      const inputModo = document.createElement("input");
      inputModo.type = "hidden";
      inputModo.name = "modo";
      inputModo.value = modo;

      const inputDados = document.createElement("input");
      inputDados.type = "hidden";
      inputDados.name = "dados";
      inputDados.value = JSON.stringify(payload);

      form.appendChild(inputModo);
      form.appendChild(inputDados);

      document.body.appendChild(form);
      form.submit();
      form.remove();

      if (!window.DesktopAPI?.onDownloadStatus) {
        pendingExportIds.forEach(id => {
          exportedIds.add(id);
          recentlyExportedIds.add(id);
          const atividade = atividades.find(a => a.id === id);
          if (atividade) atividade.exportada = true;
        });
        pendingExportIds = new Set();
        saveCache();
      }

      renderizar();
    } finally {
      // libera o lock mesmo se der return/erro
      setTimeout(() => {
        exportando = false;
      }, 800);
    }
  }

  async function atualizarPlanilha() {
    try {
      await baixarPlanilha({ modo: "atualizar" });
    } catch (e) {
      console.error(e);
      showAlert("Não foi possível atualizar a planilha. Verifique e tente novamente.");
    }
  }

  async function novaPlanilha() {
    try {
      // nova planilha = arquivo novo no servidor
      await baixarPlanilha({ modo: "nova" });
    } catch (e) {
      console.error(e);
      showAlert("Não foi possível gerar uma nova planilha. Verifique e tente novamente.");
    }
  }

  // -----------------------------
  // Excluir projeto (remove cache local + servidor e desloga)
  // -----------------------------
  async function excluirProjeto() {
    const { usuario, projeto } = getContext();
    if (!usuario || !projeto) {
      window.location.href = "index.php";
      return;
    }

    const ativa = atividadeRodando() || atividadePausada();
    if (ativa) {
      showIconAlert("Finalize a atividade antes de excluir o projeto.");
      return;
    }

    const ok = await confirmWithFocus(`Excluir o projeto ${projeto} em usuário(a) ${usuario}?`);
    if (!ok) return;

    try {
      localStorage.removeItem(cacheKey());
    } catch (e) {
      console.warn("Falha ao remover cache local", e);
    }

    window.location.href = "excluir_projeto.php";
  }

  // -----------------------------
  // Inicialização
  // -----------------------------
  function init() {
    mergeDbActivities();
    const ativa = atividadeRodando();
    if (ativa) {
      ativa.lastTick = Date.now();
      saveCache();
      iniciarTimerSePreciso();
    }

    renderizar();
    if (atividadeRodando()) {
      setButtonState("running");
    } else if (atividadePausada()) {
      setButtonState("paused");
    } else {
      setButtonState("stopped");
    }
    notifyActivityStatus();

    const logoutLink = document.getElementById("logoutLink");
    if (logoutLink) {
      logoutLink.addEventListener("click", (e) => {
        if (atividadeRodando()) {
          e.preventDefault();
          showIconAlert("Finalize a atividade antes de sair.");
        }
      });
    }

    const backLink = document.getElementById("backLink");
    if (backLink) {
      backLink.addEventListener("click", (e) => {
        if (atividadeRodando()) {
          e.preventDefault();
          showIconAlert("Finalize a atividade antes de voltar.");
        }
      });
    }

    const activityInput = document.getElementById("atividade");
    if (activityInput) {
      activityInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          play();
        }
      });
    }

    if (window.DesktopAPI?.onDownloadStatus) {
      window.DesktopAPI.onDownloadStatus((payload) => {
        if (!payload) return;
        if (!exportDownloadPendingAt || !exportToastArmed) return;
        if (Date.now() - exportDownloadPendingAt > 120000) {
          exportDownloadPendingAt = 0;
          exportToastArmed = false;
          return;
        }
        if (atividadeEmAndamento()) {
          exportDownloadPendingAt = 0;
          exportToastArmed = false;
          return;
        }
        if (payload.state === "completed") {
          const msg = `
            <div class="flex flex-col items-center gap-2">
              <div class="text-sm font-semibold">Planilha salva com sucesso!</div>
              <i class="fa-solid fa-circle-check fa-beat text-2xl text-emerald-600" aria-hidden="true"></i>
            </div>
          `;
          pendingExportIds.forEach(id => {
            exportedIds.add(id);
            recentlyExportedIds.add(id);
            const atividade = atividades.find(a => a.id === id);
            if (atividade) atividade.exportada = true;
          });
          pendingExportIds = new Set();
          saveCache();
          renderizar();
          showToast(msg, { html: true });
          exportDownloadPendingAt = 0;
          exportToastArmed = false;
        } else if (payload.state === "failed") {
          const erro = payload.error ? ` (${payload.error})` : "";
          const destino = payload.path ? ` DiretÃ³rio: ${payload.path}` : "";
          showAlert(`NÃ£o foi possÃ­vel salvar a planilha${erro}.${destino}`);
          pendingExportIds = new Set();
          exportDownloadPendingAt = 0;
          exportToastArmed = false;
        }
      });
    }
  }

  // Expondo funções para o HTML
  window.play = play;
  window.pausar = pausar;
  window.retomar = retomar;
  window.stop = stop;
  window.atualizarPlanilha = atualizarPlanilha;
  window.novaPlanilha = novaPlanilha;
  window.excluirProjeto = excluirProjeto;
  window.handleMinimize = () => {
    if (!window.DesktopAPI?.minimizeApp) return false;
    const ativa = atividadeEmAndamento();
    const payload = ativa ? { atividade: buildMiniPayload(ativa) } : null;
    window.DesktopAPI.minimizeApp(payload);
    return true;
  };

  if (window.DesktopAPI?.onMiniAction) {
    window.DesktopAPI.onMiniAction((action) => {
      if (action === "pause") {
        pausar();
      } else if (action === "resume") {
        retomar();
      } else if (action === "stop") {
        stop();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
