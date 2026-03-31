<?php
$dbTheme = '';
try {
  if (isset($db)) {
    if (!empty($_SESSION['usuario'])) {
      $stmt = $db->prepare('SELECT valor FROM app_settings WHERE usuario = :u AND chave = :k LIMIT 1');
      $stmt->execute([':u' => (string)$_SESSION['usuario'], ':k' => 'theme']);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && ($row['valor'] === 'dark' || $row['valor'] === 'light')) {
        $dbTheme = $row['valor'];
      }
    }
    if ($dbTheme === '') {
      $stmt = $db->prepare('SELECT valor FROM app_settings WHERE usuario = :u AND chave = :k LIMIT 1');
      $stmt->execute([':u' => '', ':k' => 'theme']);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && ($row['valor'] === 'dark' || $row['valor'] === 'light')) {
        $dbTheme = $row['valor'];
      }
    }
  }
} catch (Throwable $e) {
  $dbTheme = '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer" />

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'App') ?></title>

  <link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" />

  <link rel="stylesheet"
      href="https://cdn-uicons.flaticon.com/2.4.0/uicons-bold-straight/css/uicons-bold-straight.css" />

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.lordicon.com/lordicon.js"></script>

  <style>
    :root {
      --app-bg: #f1f5f9;
      --app-card: #ffffff;
      --app-text: #0f172a;
      --app-muted: #64748b;
      --app-border: #e2e8f0;
      --app-input-bg: #ffffff;
      --app-input-text: #0f172a;
      --app-input-border: #cbd5e1;
      --app-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2);
      --app-modal-bg: #ffffff;
      --app-modal-shadow: 0 18px 40px -18px rgba(15, 23, 42, 0.35);
      --app-overlay: rgba(15, 23, 42, 0.45);

      --scroll-track: #f1f5f9;
      --scroll-thumb: #94a3b8;
      --scroll-thumb-hover: #64748b;
      --scroll-shadow: rgba(15, 23, 42, 0.18);
      --scrollbar-slip: 10px;
    }
    body[data-theme="dark"] {
      --app-bg: #0b1220;
      --app-card: #111827;
      --app-text: #e2e8f0;
      --app-muted: #94a3b8;
      --app-border: #1f2937;
      --app-input-bg: #0f172a;
      --app-input-text: #e2e8f0;
      --app-input-border: #334155;
      --app-shadow: 0 25px 50px -12px rgba(2, 6, 23, 0.55);
      --app-modal-bg: #0f172a;
      --app-modal-shadow: 0 22px 50px -22px rgba(2, 6, 23, 0.85);
      --app-overlay: rgba(2, 6, 23, 0.7);

      --scroll-track: #0b1220;
      --scroll-thumb: #334155;
      --scroll-thumb-hover: #475569;
      --scroll-shadow: rgba(2, 6, 23, 0.5);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .theme-transition,
    .theme-transition * {
      transition: background-color 220ms ease, color 220ms ease,
                  border-color 220ms ease, box-shadow 220ms ease;
    }
    .theme-transition *::-webkit-scrollbar-track,
    .theme-transition *::-webkit-scrollbar-thumb {
      transition: background-color 220ms ease, box-shadow 220ms ease;
    }
    .page-fade-in {
      animation: fadeInUp 80ms linear both;
    }
    @keyframes runningPulse {
      0% { color: #16a34a; }
      50% { color: #0f7a33; }
      100% { color: #0bc029; }
    }
    .activity-running-indicator {
      animation: runningPulse 1.2s ease-in-out infinite alternate;
    }
    @keyframes pausedPulse {
      0% { color: #f59e0b; }
      100% { color: #fbbf24; }
    }
    .activity-paused-indicator {
      color: #f59e0b;
      animation: pausedPulse 1.2s ease-in-out infinite alternate;
    }
    @keyframes exportIconIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
    }
    .activity-export-icon {
      color: #cbd5e1;
      font-size: 0.75rem;
      transition: color 520ms ease;
    }
    .activity-exported {
      color: #16a34a;
    }
    @keyframes exportToGreen {
      from { color: #cbd5e1; }
      to { color: #16a34a; }
    }
    .activity-export-fade {
      animation: exportToGreen 520ms ease;
    }
    .activity-export-appear {
      animation: exportIconIn 420ms ease-out;
    }
    .activity-row {
      transition: transform 180ms ease, opacity 180ms ease;
      will-change: transform;
    }
    .activity-row-enter {
      opacity: 0;
      transform: translateY(4px);
    }
    body[data-theme="dark"] #widget-root .activity-table-container {
      border-color: #1f2937 !important;
    }
    body[data-theme="dark"] #widget-root .activity-table-container .activity-row {
      border-color: #1f2937 !important;
    }
    .activity-table-container {
      scrollbar-gutter: stable;
      position: relative;
      background: var(--app-card);
    }
    .activity-table-container.no-scroll {
      scrollbar-gutter: auto;
      overflow-y: hidden;
    }
    .activity-table-container::after {
      content: "";
      position: absolute;
      top: 0;
      right: 0;
      width: var(--scrollbar-slip);
      height: 100%;
      background: var(--app-card);
      opacity: 0;
      pointer-events: none;
    }
    @keyframes scrollbarSlip {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(-100%); opacity: 0; }
    }
    .activity-table-container.scroll-slip::after {
      opacity: 1;
      animation: scrollbarSlip 240ms ease;
    }
    * {
      scrollbar-width: thin;
      scrollbar-color: var(--scroll-thumb) var(--scroll-track);
    }
    *::-webkit-scrollbar {
      width: 10px;
      height: 10px;
      background-color: var(--scroll-track);
    }
    *::-webkit-scrollbar-track {
      background-color: var(--scroll-track);
      border-radius: 12px;
      box-shadow: inset 0 0 6px var(--scroll-shadow);
      transition: background-color 220ms ease, box-shadow 220ms ease;
    }
    *::-webkit-scrollbar-thumb {
      background-color: var(--scroll-thumb);
      border-radius: 12px;
      box-shadow: inset 0 0 6px var(--scroll-shadow);
      transition: background-color 220ms ease, box-shadow 220ms ease;
    }
    *::-webkit-scrollbar-thumb:hover {
      background-color: var(--scroll-thumb-hover);
    }
    .activity-name {
      display: inline-block;
      transition: transform 180ms ease;
      will-change: transform;
    }
    @keyframes activityInputNudge {
      0% { transform: translateX(0); }
      20% { transform: translateX(-3px); }
      40% { transform: translateX(3px); }
      60% { transform: translateX(-2px); }
      80% { transform: translateX(2px); }
      100% { transform: translateX(0); }
    }
    @keyframes activityInputGlow {
      0% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0); background-color: rgba(254, 242, 242, 0); }
      30% { box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.75); background-color: rgba(254, 226, 226, 0.65); }
      100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0); background-color: rgba(254, 242, 242, 0); }
    }
    .activity-input-attention {
      animation: activityInputNudge 380ms ease-in-out, activityInputGlow 900ms ease-in-out;
    }
    html, body {
      background: transparent !important;
      margin: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    body {
      display: flex;
      align-items: center;
      justify-content: center;

      /* ✅ folga visual para sombra + cantos */
      padding: 0;
      box-sizing: border-box;
      font-family: "Montserrat", sans-serif;
    }
    #widget-root {
      margin: 0 !important;
    }
    #widget-root {
      background: var(--app-card) !important;
      color: var(--app-text);
      box-shadow: var(--app-shadow) !important;
    }
    #widget-root .text-slate-900,
    #widget-root .text-slate-800,
    #widget-root .text-slate-700 {
      color: var(--app-text) !important;
    }
    #widget-root .text-slate-600,
    #widget-root .text-slate-500,
    #widget-root .text-slate-400,
    #widget-root .text-slate-300 {
      color: var(--app-muted) !important;
    }
    #widget-root .bg-white:not(button):not(a),
    #widget-root .bg-slate-50:not(button):not(a),
    #widget-root .bg-slate-100:not(button):not(a),
    #widget-root .bg-slate-200:not(button):not(a) {
      background-color: var(--app-card) !important;
    }
    #widget-root .border,
    #widget-root .border-slate-100,
    #widget-root .border-slate-200,
    #widget-root .border-slate-300 {
      border-color: var(--app-border) !important;
    }
    #widget-root input,
    #widget-root select,
    #widget-root textarea {
      background-color: var(--app-input-bg) !important;
      color: var(--app-input-text) !important;
      border-color: var(--app-input-border) !important;
    }
    #widget-root input::placeholder,
    #widget-root textarea::placeholder {
      color: color-mix(in srgb, var(--app-muted) 70%, transparent);
      opacity: 1;
    }
    #widget-root .app-modal-box {
      background-color: var(--app-modal-bg) !important;
      color: var(--app-text);
      border: 0;
      box-shadow: var(--app-modal-shadow);
    }
    #modalBox {
      background-color: var(--app-modal-bg) !important;
      color: var(--app-text) !important;
      border: 0;
      box-shadow: var(--app-modal-shadow);
    }
    #modalBox .text-slate-800 {
      color: var(--app-text) !important;
    }
    #appModalOverlay,
    #startupModalOverlay,
    #modalOverlay {
      background-color: var(--app-overlay) !important;
    }
    body[data-theme="dark"] #widget-root .hover\:bg-slate-50:hover,
    body[data-theme="dark"] #widget-root .hover\:bg-slate-100:hover,
    body[data-theme="dark"] #widget-root .hover\:bg-slate-200:hover {
      background-color: #1e293b !important;
    }
    body[data-theme="dark"] #appModalCancel {
      background-color: #1f2937 !important;
      color: #e2e8f0 !important;
    }
    body[data-theme="dark"] #widget-root #appModalCancel:hover {
      background-color: #374151 !important;
    }
    body[data-theme="dark"] #startupModalCancel {
      background-color: #1f2937 !important;
      color: #e2e8f0 !important;
    }
    body[data-theme="dark"] #startupModalCancel:hover {
      background-color: #374151 !important;
    }
    body[data-theme="dark"] .startup-modal-cancel {
      background-color: #1f2937;
      color: #e2e8f0;
    }
    body[data-theme="dark"] .startup-modal-cancel:hover {
      background-color: #374151;
    }
    body[data-theme="dark"] #widget-root .btn-update-planilha {
      background-color: #2E3A4A !important;
    }
    body[data-theme="dark"] #widget-root .btn-update-planilha:hover {
      background-color: #4D607A !important;
    }
    body[data-theme="dark"] #widget-root .btn-nova-planilha {
      background-color: #0b2a5b !important;
    }
    body[data-theme="dark"] #widget-root .btn-nova-planilha:hover {
      background-color: #1f5a96 !important;
    }
    body[data-theme="dark"] #widget-root .btn-criar-entrar {
      background-color: #0b2a5b !important;
    }
    body[data-theme="dark"] #widget-root .btn-criar-entrar:hover {
      background-color: #1f5a96 !important;
    }
    body[data-theme="dark"] #widget-root .project-item:hover {
      background-color: #1e293b !important;
    }
    body[data-theme="dark"] #widget-root .edit-projects-disabled {
      color: #1f2937 !important;
    }
    .drag-region { -webkit-app-region: drag; }
    .no-drag { -webkit-app-region: no-drag; }
    .modal-message {
      max-width: 230px;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
      word-break: break-word;
    }
    .modal-message.has-icon {
      display: block;
      -webkit-line-clamp: unset;
      -webkit-box-orient: unset;
    }
    .app-modal-overlay {
      opacity: 0;
      pointer-events: none;
      transition: opacity 260ms ease;
    }
    .app-modal-overlay.is-open {
      opacity: 1;
      pointer-events: auto;
    }
    .app-modal-box {
      opacity: 0;
      transform: scale(0.97);
      transition: opacity 260ms ease, transform 260ms ease;
    }
    .app-modal-overlay.is-open .app-modal-box {
      opacity: 1;
      transform: scale(1);
    }
    .app-modal-box.app-modal-compact {
      width: 240px;
      padding: 12px;
      text-align: center;
    }
    .app-modal-box.app-modal-compact .modal-message {
      max-width: 200px;
      margin: 0 auto;
    }
    .startup-modal-cancel {
      background-color: #f1f5f9;
      color: var(--app-text);
      transition: background-color 140ms ease, color 140ms ease;
    }
    .startup-modal-cancel:hover {
      background-color: #e2e8f0;
    }
    .app-top-actions {
      position: absolute;
      top: 8px;
      right: 8px;
      z-index: 30;
    }
    .settings-popover {
      position: absolute;
      top: calc(100% + 8px);
      right: 0;
      min-width: 180px;
      background: var(--app-card);
      color: var(--app-text);
      border: 1px solid var(--app-border);
      border-radius: 12px;
      box-shadow: var(--app-shadow);
      padding: 10px;
      z-index: 40;
      animation: fadeInUp 90ms ease-out both;
    }
    .settings-popover-title {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--app-muted);
      margin-bottom: 8px;
    }
    .settings-options {
      display: grid;
      gap: 6px;
    }
    .settings-separator {
      height: 1px;
      background: color-mix(in srgb, var(--app-border) 85%, transparent);
      margin: 8px 0;
    }
    .settings-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-size: 0.82rem;
      color: var(--app-muted);
    }
    .settings-toggle {
      --toggle-w: 38px;
      --toggle-h: 20px;
      --toggle-knob: 14px;
      --toggle-pad: 3px;
      width: var(--toggle-w);
      height: var(--toggle-h);
      flex: 0 0 var(--toggle-w);
      border-radius: 999px;
      background: #cbd5e1;
      position: relative;
      appearance: none;
      border: 0;
      padding: 0;
      box-sizing: border-box;
      display: inline-flex;
      align-items: center;
      justify-content: flex-start;
      overflow: hidden;
      transition: background-color 180ms ease;
    }
    .settings-toggle::after {
      content: "";
      position: absolute;
      top: 50%;
      left: var(--toggle-pad);
      width: var(--toggle-knob);
      height: var(--toggle-knob);
      border-radius: 50%;
      background: #ffffff;
      box-shadow: 0 3px 8px rgba(15, 23, 42, 0.2);
      transform: translateY(-50%);
      transition: left 180ms ease;
    }
    .settings-toggle.is-on {
      background: #1f5a96;
    }
    .settings-toggle.is-on::after {
      left: calc(100% - var(--toggle-knob) - var(--toggle-pad));
    }
    .settings-option {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 0.85rem;
      color: var(--app-muted);
      background: transparent;
      border: 1px solid transparent;
      transition: background-color 140ms ease, border-color 140ms ease, color 140ms ease;
    }
    .settings-option:hover {
      background: color-mix(in srgb, var(--app-muted) 10%, transparent);
    }
    .settings-option.is-active {
      background: color-mix(in srgb, #2563eb 16%, transparent);
      border-color: color-mix(in srgb, #2563eb 45%, transparent);
      color: color-mix(in srgb, var(--app-text) 90%, #2563eb);
    }
    .startup-modal-box {
      width: 320px;
      padding: 16px;
      text-align: left;
      position: relative;
    }
    .startup-modal-title {
      font-size: 0.95rem;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--app-text);
      text-align: center;
    }
    .startup-modal-message {
      font-size: 0.85rem;
      color: var(--app-muted);
      line-height: 1.4;
      text-align: justify;
    }
    .startup-modal-close {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--app-muted);
      background: transparent;
      transition: color 140ms ease, background-color 140ms ease, opacity 140ms ease;
    }
    .startup-modal-close:hover {
      color: var(--app-muted);
      background: color-mix(in srgb, var(--app-muted) 12%, transparent);
    }
    .startup-toggle {
      margin-top: 14px;
      width: 56px;
      height: 30px;
      border-radius: 999px;
      background: #cbd5e1;
      position: relative;
      transition: background-color 180ms ease;
      margin-left: auto;
      margin-right: auto;
    }
    .startup-toggle::after {
      content: "";
      position: absolute;
      top: 4px;
      left: 4px;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: #ffffff;
      box-shadow: 0 4px 10px rgba(15, 23, 42, 0.25);
      transition: transform 180ms ease;
    }
    .startup-toggle.is-on {
      background: #2563eb;
    }
    .startup-toggle.is-on::after {
      transform: translateX(26px);
    }
  </style>
</head>

<body>
  <div
    id="widget-root"
    data-widget-root
    class="bg-white rounded-2xl shadow-2xl overflow-hidden page-fade-in"
    style="position: relative; <?= htmlspecialchars($widgetStyle ?? '', ENT_QUOTES) ?>"
  >
    <div class="h-10 w-full drag-region"></div>

    <div class="app-top-actions inline-flex items-center gap-1 no-drag relative" style="position: absolute; top: 8px; right: 8px;">
      <?php if ($showThemeToggle ?? false): ?>
        <div class="relative">
          <button
            type="button"
            id="settingsToggle"
            class="h-8 w-8 inline-flex items-center justify-center rounded-lg
                   text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition select-none"
            title="Configurações"
            aria-label="Configurações"
            aria-expanded="false"
          ><i class="fa-solid fa-gear" aria-hidden="true"></i></button>

          <div
            id="settingsPopover"
            class="settings-popover hidden"
            role="dialog"
            aria-label="Configurações"
          >
            <div class="settings-row">
              <span>Modo escuro</span>
              <button
                type="button"
                id="themeSettingsToggle"
                class="settings-toggle"
                aria-pressed="false"
              ></button>
            </div>
            <div class="settings-separator" role="separator" aria-hidden="true"></div>
            <div class="settings-row">
              <span>Iniciar com o sistema</span>
              <button
                type="button"
                id="startupSettingsToggle"
                class="settings-toggle"
                aria-pressed="false"
              ></button>
            </div>
            <div class="settings-separator" role="separator" aria-hidden="true"></div>
            <button
              type="button"
              id="checkUpdatesBtn"
              class="settings-option"
            >
              Buscar atualizações
            </button>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($showMinimize ?? true): ?>
        <button
          type="button"
          onclick="window.handleMinimize ? window.handleMinimize() : window.DesktopAPI?.minimizeApp?.()"
          class="h-8 w-8 inline-flex items-center justify-center rounded-lg
                 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition select-none"
          title="Minimizar"
          aria-label="Minimizar"
        ><i class="fa-solid fa-down-left-and-up-right-to-center" aria-hidden="true"></i></button>
      <?php endif; ?>

      <button
        type="button"
      onclick="window.handleClose ? window.handleClose() : window.DesktopAPI?.closeApp?.()"
        class="h-8 w-8 inline-flex items-center justify-center rounded-lg
               text-slate-400 hover:text-red-600 hover:bg-slate-100 transition select-none"
        title="Fechar"
        aria-label="Fechar"
      ><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>

    <!-- ✅ SEM max-h e SEM overflow-auto aqui (isso estava “achatando” a tela) -->
    <div class="px-6 pb-6 overflow-hidden">
      <?= $content ?>
    </div>

    <div id="appModalOverlay"
         class="app-modal-overlay absolute inset-0 flex items-center justify-center bg-slate-900/40 hidden z-50">
      <div id="appModalBox" class="app-modal-box bg-white rounded-xl shadow-xl w-[320px] p-4 text-slate-800">
        <div id="appModalMessage" class="text-sm modal-message"></div>
        <div class="mt-4 flex justify-center gap-2">
          <button id="appModalCancel"
                  class="px-3 py-1.5 rounded bg-slate-100 hover:bg-slate-200 transition hidden">
            Cancelar
          </button>
          <button id="appModalOk"
                  class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 transition">
            OK
          </button>
        </div>
      </div>
    </div>

    <?php if ($showStartupPrompt ?? false): ?>
      <div id="startupModalOverlay"
           class="app-modal-overlay absolute inset-0 flex items-center justify-center bg-slate-900/40 hidden z-40">
        <div id="startupModalBox" class="app-modal-box startup-modal-box bg-white rounded-xl shadow-xl text-slate-800">
          <button id="startupModalClose" class="startup-modal-close" type="button" aria-label="Fechar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
          <div class="startup-modal-title">Atenção</div>
          <div class="startup-modal-message">
            Permitir que o Controle de Horas inicie junto com o seu sistema?
          </div>
          <div class="mt-4 flex justify-center gap-2">
            <button id="startupModalCancel"
                    class="startup-modal-cancel px-3 py-1.5 rounded transition">
              Cancelar
            </button>
            <button id="startupModalOk"
                    class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 transition">
              OK
            </button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    (function () {
      const THEME_KEY = "controle_horas::theme";
      const DB_THEME = "<?= htmlspecialchars($dbTheme, ENT_QUOTES) ?>";
      const body = document.body;
      const settingsToggle = document.getElementById("settingsToggle");
      const settingsPopover = document.getElementById("settingsPopover");
      const dragRegion = document.querySelector(".drag-region");
      const themeSettingsToggle = document.getElementById("themeSettingsToggle");
      const widgetRoot = document.getElementById("widget-root");
      const TRANSITION_CLASS = "theme-transition";

      function reportWidgetHitbox() {
        if (!window.DesktopAPI?.setWidgetHitbox || !widgetRoot) return;
        const r = widgetRoot.getBoundingClientRect();
        const sx = Number(window.screenX ?? window.screenLeft ?? 0);
        const sy = Number(window.screenY ?? window.screenTop ?? 0);
        const rect = {
          x: Math.round(sx + r.left),
          y: Math.round(sy + r.top),
          width: Math.round(r.width),
          height: Math.round(r.height),
        };
        window.DesktopAPI.setWidgetHitbox(rect);
      }

      if (widgetRoot && window.DesktopAPI?.setWidgetHitbox) {
        reportWidgetHitbox();
        setInterval(reportWidgetHitbox, 200);
        if ("ResizeObserver" in window) {
          const ro = new ResizeObserver(() => reportWidgetHitbox());
          ro.observe(widgetRoot);
        }
        window.addEventListener("resize", reportWidgetHitbox);
      }

      function syncThemeToggle(theme) {
        if (!themeSettingsToggle) return;
        const isDark = theme === "dark";
        themeSettingsToggle.classList.toggle("is-on", isDark);
        themeSettingsToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
      }

      function applyTheme(theme) {
        if (theme === "dark") {
          body.setAttribute("data-theme", "dark");
        } else {
          body.removeAttribute("data-theme");
        }
        syncThemeToggle(theme);
        window.DesktopAPI?.setTheme?.(theme);
      }

      function getStoredTheme() {
        try {
          if (DB_THEME === "dark" || DB_THEME === "light") return DB_THEME;
          const saved = localStorage.getItem(THEME_KEY);
          if (saved === "dark" || saved === "light") return saved;
        } catch {}
        return "light";
      }

      function persistTheme(theme) {
        try {
          fetch("set_theme.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "theme=" + encodeURIComponent(theme),
            keepalive: true,
          }).catch(() => {});
        } catch {}
      }

      function setStoredTheme(theme) {
        try {
          localStorage.setItem(THEME_KEY, theme);
        } catch {}
      }

      const initialTheme = getStoredTheme();
      applyTheme(initialTheme);
      setStoredTheme(initialTheme);

      function setThemeWithTransition(theme) {
        body.classList.add(TRANSITION_CLASS);
        widgetRoot?.classList.add(TRANSITION_CLASS);
        applyTheme(theme);
        setStoredTheme(theme);
        persistTheme(theme);
        window.setTimeout(() => {
          body.classList.remove(TRANSITION_CLASS);
          widgetRoot?.classList.remove(TRANSITION_CLASS);
        }, 260);
      }

      if (settingsToggle && settingsPopover) {
        settingsToggle.addEventListener("click", (event) => {
          event.stopPropagation();
          const isOpen = !settingsPopover.classList.contains("hidden");
          settingsPopover.classList.toggle("hidden", isOpen);
          settingsToggle.setAttribute("aria-expanded", (!isOpen).toString());
        });
        settingsPopover.addEventListener("click", (event) => {
          event.stopPropagation();
        });
        document.addEventListener("pointerdown", (event) => {
          if (settingsPopover.classList.contains("hidden")) return;
          if (settingsToggle.contains(event.target) || settingsPopover.contains(event.target)) return;
          settingsPopover.classList.add("hidden");
          settingsToggle.setAttribute("aria-expanded", "false");
        });
        if (dragRegion) {
          dragRegion.addEventListener("pointerdown", () => {
            if (settingsPopover.classList.contains("hidden")) return;
            settingsPopover.classList.add("hidden");
            settingsToggle.setAttribute("aria-expanded", "false");
          });
        }
      }

      if (themeSettingsToggle) {
        themeSettingsToggle.addEventListener("click", () => {
          const next = themeSettingsToggle.classList.contains("is-on") ? "light" : "dark";
          setThemeWithTransition(next);
        });
      }

      const overlay = document.getElementById("appModalOverlay");
      const msg = document.getElementById("appModalMessage");
      const okBtn = document.getElementById("appModalOk");
      const cancelBtn = document.getElementById("appModalCancel");
      const box = document.getElementById("appModalBox");
      const startupOverlay = document.getElementById("startupModalOverlay");
      const startupClose = document.getElementById("startupModalClose");
      const startupOk = document.getElementById("startupModalOk");
      const startupCancel = document.getElementById("startupModalCancel");
      const startupSettingsToggle = document.getElementById("startupSettingsToggle");
      const STARTUP_KEY = "controle_horas::startup_auto_launch";
      const checkUpdatesBtn = document.getElementById("checkUpdatesBtn");
      let updateChecking = false;

      let overlayHideTimer = null;
      function cancelOverlayHide() {
        if (overlayHideTimer) {
          clearTimeout(overlayHideTimer);
          overlayHideTimer = null;
        }
      }
      function nextModalToken() {
        if (!overlay) return null;
        const token = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        overlay.dataset.modalToken = token;
        return token;
      }
      function isTokenActive(token) {
        return Boolean(overlay && token && overlay.dataset.modalToken === token);
      }
      function scheduleOverlayHide(token) {
        if (!overlay) return;
        if (!isTokenActive(token)) return;
        overlay.classList.remove("is-open");
        cancelOverlayHide();
        overlayHideTimer = setTimeout(() => {
          if (isTokenActive(token)) {
            overlay.classList.add("hidden");
          }
          overlayHideTimer = null;
        }, 150);
      }
      function beginModal(onForceClose) {
        if (!overlay) return { token: null, close: () => {} };
        if (overlay.__modalState && typeof overlay.__modalState.forceClose === "function") {
          overlay.__modalState.forceClose({ keepOpen: true });
        }
        const token = nextModalToken();
        let closed = false;
        const close = (opts = {}) => {
          if (closed) return;
          closed = true;
          if (overlay.__modalState && overlay.__modalState.token === token) {
            overlay.__modalState = null;
          }
          if (!opts.keepOpen) {
            scheduleOverlayHide(token);
          }
        };
        overlay.__modalState = {
          token,
          forceClose: (opts = {}) => {
            close(opts);
            if (typeof onForceClose === "function") {
              onForceClose();
            }
          },
        };
        return { token, close };
      }

      function showConfirm(message) {
        if (!overlay || !msg || !okBtn || !cancelBtn) {
          return Promise.resolve(window.confirm(message));
        }

        let modal = null;
        msg.textContent = message;
        msg.title = message;
        msg.classList.remove("has-icon");
        msg.classList.add("text-center");
        if (box) {
          box.classList.add("app-modal-compact");
        }
        okBtn.classList.remove("hidden");
        cancelBtn.classList.remove("hidden");
        overlay.classList.remove("hidden");
        requestAnimationFrame(() => {
          overlay.classList.add("is-open");
        });

        return new Promise(resolve => {
          let resolved = false;
          const cleanup = (result, forced = false) => {
            if (resolved) return;
            resolved = true;
            modal?.close({ keepOpen: forced });
            okBtn.onclick = null;
            cancelBtn.onclick = null;
            resolve(result);
          };

          modal = beginModal(() => cleanup(false, true));
          okBtn.onclick = () => cleanup(true);
          cancelBtn.onclick = () => cleanup(false);
          setTimeout(() => okBtn.focus(), 0);
        });
      }

      function showIconInfo(message) {
        if (!overlay || !msg || !okBtn || !cancelBtn) {
          window.alert(message);
          return;
        }

        const modal = beginModal();
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

        setTimeout(() => {
          modal.close();
        }, iconDurationMs);
      }

      function hasRunningActivity() {
        try {
          for (let i = 0; i < localStorage.length; i += 1) {
            const key = localStorage.key(i) || "";
            if (!key.startsWith("controle_horas::")) continue;
            const raw = localStorage.getItem(key);
            if (!raw) continue;
            const parsed = JSON.parse(raw);
            const atividades = Array.isArray(parsed.atividades) ? parsed.atividades : [];
            if (atividades.some(a => a && a.rodando)) return true;
          }
        } catch {}
        return false;
      }

      async function getStartupPreference() {
        try {
          if (window.DesktopAPI?.getAutoLaunch) {
            const value = await window.DesktopAPI.getAutoLaunch();
            if (typeof value === "boolean") return value;
          }
        } catch {}
        try {
          return localStorage.getItem(STARTUP_KEY) === "true";
        } catch {}
        return false;
      }

      async function setStartupPreference(enabled) {
        try {
          localStorage.setItem(STARTUP_KEY, enabled ? "true" : "false");
        } catch {}
        try {
          if (window.DesktopAPI?.setAutoLaunch) {
            const value = await window.DesktopAPI.setAutoLaunch(enabled);
            if (typeof value === "boolean") return value;
          }
        } catch {}
        return enabled;
      }

      function updateStartupUI(enabled) {
        if (startupSettingsToggle) {
          startupSettingsToggle.classList.toggle("is-on", enabled);
          startupSettingsToggle.setAttribute("aria-pressed", enabled ? "true" : "false");
        }
      }

      if (startupSettingsToggle || startupOk || startupCancel) {
        (async () => {
          const enabled = await getStartupPreference();
          updateStartupUI(enabled);
          if (startupOverlay && startupClose && !enabled) {
            setTimeout(() => {
              startupOverlay.classList.remove("hidden");
              requestAnimationFrame(() => {
                startupOverlay.classList.add("is-open");
              });
            }, 1000);
          }
        })();

        if (startupSettingsToggle) {
          startupSettingsToggle.addEventListener("click", async () => {
            const next = !startupSettingsToggle.classList.contains("is-on");
            const actual = await setStartupPreference(next);
            updateStartupUI(actual);
          });
        }

        if (startupOverlay) {
          const closeStartup = () => {
            startupOverlay.classList.remove("is-open");
            setTimeout(() => {
              startupOverlay.classList.add("hidden");
            }, 150);
          };

          if (startupOk) {
            startupOk.addEventListener("click", async () => {
              const actual = await setStartupPreference(true);
              updateStartupUI(actual);
              closeStartup();
            });
          }

          if (startupCancel) {
            startupCancel.addEventListener("click", closeStartup);
          }

          if (startupClose) {
            startupClose.addEventListener("click", closeStartup);
          }
        }
      }

      if (checkUpdatesBtn && window.DesktopAPI?.checkForUpdates) {
        const resetButton = () => {
          checkUpdatesBtn.disabled = false;
          checkUpdatesBtn.textContent = "Buscar atualizações";
        };

        checkUpdatesBtn.addEventListener("click", async () => {
          if (updateChecking) return;
          updateChecking = true;
          checkUpdatesBtn.disabled = true;
          checkUpdatesBtn.textContent = "Verificando...";
          const res = await window.DesktopAPI.checkForUpdates().catch(() => null);
          if (res && res.status === "dev") {
            updateChecking = false;
            resetButton();
            showIconInfo("Nenhuma atualização disponível.");
            return;
          }
          setTimeout(() => {
            updateChecking = false;
            resetButton();
          }, 4000);
        });

        window.DesktopAPI.onUpdateNotAvailable?.(() => {
          updateChecking = false;
          resetButton();
          showIconInfo("Nenhuma atualização disponível.");
        });
      }


      window.handleClose = async function () {
        const message = hasRunningActivity()
          ? "Existe uma atividade em andamento. Deseja fechar o app?"
          : "Deseja fechar o app?";
        const ok = await showConfirm(message);
        if (!ok) return;
        window.DesktopAPI?.closeApp?.();
      };

      if (window.DesktopAPI?.onAppRequestClose) {
        window.DesktopAPI.onAppRequestClose(async () => {
          const message = hasRunningActivity()
            ? "Existe uma atividade em andamento. Deseja fechar o app?"
            : "Deseja fechar o app?";
          const ok = await showConfirm(message);
          if (!ok) return;
          window.DesktopAPI?.closeApp?.();
        });
      }
    })();
  </script>
</body>
</html>
