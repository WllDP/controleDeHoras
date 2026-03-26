<?php
require 'config.php';

// Se já está logado (usuário), vai direto para a lista de projetos
if (isset($_SESSION['usuario']) && $_SESSION['usuario'] !== '') {
    header('Location: projetos.php');
    exit;
}

// Auto-login por SQLite (lembrar usuÃ¡rio)
try {
    $stmt = $db->prepare('SELECT valor FROM app_settings WHERE usuario = :u AND chave = :k LIMIT 1');
    $stmt->execute([':u' => '', ':k' => 'remember_user']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['valor'])) {
        $_SESSION['usuario'] = (string)$row['valor'];
        unset($_SESSION['projeto']);
        header('Location: projetos.php');
        exit;
    }
} catch (Throwable $e) {
    // ignora se nÃ£o conseguir ler
}

$appVersion = (string)(getenv('APP_VERSION') ?: '');
$versionCandidates = [
    __DIR__ . '/package.json',
    __DIR__ . '/desktop/package.json',
    __DIR__ . '/../package.json',
    __DIR__ . '/../desktop/package.json',
    __DIR__ . '/../../package.json',
    __DIR__ . '/../../desktop/package.json',
];
if ($appVersion === '') {
    foreach ($versionCandidates as $versionPath) {
        if (!is_file($versionPath)) {
            continue;
        }
        $data = json_decode((string)file_get_contents($versionPath), true);
        if (is_array($data) && isset($data['version'])) {
            $appVersion = (string)$data['version'];
            break;
        }
    }
}

ob_start();
?>

<div data-widget-root class="relative p-8 w-full max-w-sm">
    <h1 class="text-2xl font-bold mb-6 text-center text-slate-800">
        Controle de Atividades
    </h1>

    <form action="login.php" method="POST" class="space-y-4" novalidate>
        <input
            type="text"
            name="nome"
            id="loginUserInput"
            placeholder="Nome de usuário"
            class="w-full border rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            autocomplete="username"
        />
        <input type="hidden" name="theme" id="themeInput" value="">
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 select-none">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
            Lembrar usuário
        </label>

        <button
            type="submit"
            class="w-full bg-red-500 text-white py-2 rounded-full hover:bg-red-600 transition"
        >
            Entrar
        </button>
    </form>

 <!--    <p class="text-xs text-slate-500 mt-4 text-center">
        Você entrará primeiro com o nome e depois selecionará (ou criará) um projeto.
    </p> -->
    <?php if ($appVersion !== ''): ?>
        <span class="absolute -bottom-2 right-1 text-xs text-slate-400 pointer-events-none">
            V <?= htmlspecialchars($appVersion) ?>
        </span>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Login';
$showMinimize = false;
$showThemeToggle = true;
$showStartupPrompt = true;
require 'layout.php';

?>
<script>
  (function () {
    const THEME_KEY = "controle_horas::theme";
    const input = document.getElementById("themeInput");
    const form = document.querySelector("form[action=\"login.php\"]");
    if (!input || !form) return;
    const userInput = document.getElementById("loginUserInput");
    const sync = () => {
      try {
        const saved = localStorage.getItem(THEME_KEY);
        input.value = (saved === "dark" || saved === "light") ? saved : "";
      } catch {}
    };
    sync();
    form.addEventListener("submit", (e) => {
      sync();
      if (!userInput) return;
      const nome = String(userInput.value || "").trim();
      if (!nome) {
        e.preventDefault();
        userInput.classList.remove("activity-input-attention");
        void userInput.offsetWidth;
        userInput.classList.add("activity-input-attention");
        userInput.focus();
      }
    });
  })();
</script>
