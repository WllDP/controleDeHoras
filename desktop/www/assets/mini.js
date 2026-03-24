(() => {
  "use strict";

  const THEME_KEY = "controle_horas::theme";

  function applyTheme(theme) {
    if (theme === "dark") {
      document.body.setAttribute("data-theme", "dark");
    } else {
      document.body.removeAttribute("data-theme");
    }
  }

  async function loadTheme() {
    try {
      const res = await fetch("get_theme.php", { cache: "no-store" });
      const data = await res.json().catch(() => null);
      if (data && (data.theme === "dark" || data.theme === "light")) {
        applyTheme(data.theme);
        return;
      }
    } catch {}

    try {
      const saved = localStorage.getItem(THEME_KEY);
      if (saved === "dark" || saved === "light") {
        applyTheme(saved);
        return;
      }
    } catch {}
    applyTheme("light");
  }

  loadTheme();
  window.addEventListener("storage", (event) => {
    if (event.key === THEME_KEY) {
      applyTheme(event.newValue === "dark" ? "dark" : "light");
    }
  });

  let activity = null;
  let timerId = null;

  const nameEl = document.getElementById("miniActivity");
  const timeEl = document.getElementById("miniTime");
  const pauseBtn = document.getElementById("miniPause");
  const resumeBtn = document.getElementById("miniResume");

  function formatar(segundos) {
    const h = String(Math.floor(segundos / 3600)).padStart(2, "0");
    const m = String(Math.floor((segundos % 3600) / 60)).padStart(2, "0");
    const s = String(segundos % 60).padStart(2, "0");
    return `${h}:${m}:${s}`;
  }

  function calcTempo() {
    if (!activity) return 0;
    const base = Number(activity.tempo) || 0;
    if (!activity.rodando) return base;
    const last = Number(activity.lastTick) || Date.now();
    const delta = Math.max(0, Math.floor((Date.now() - last) / 1000));
    return base + delta;
  }

  function setPaused(paused) {
    if (!pauseBtn || !resumeBtn) return;
    pauseBtn.classList.toggle("hidden", paused);
    resumeBtn.classList.toggle("hidden", !paused);
  }

  function render() {
    if (!activity) return;
    nameEl.textContent = activity.nome || "Atividade";
    timeEl.textContent = formatar(calcTempo());
    setPaused(!activity.rodando);
  }

  function startTimer() {
    if (timerId) return;
    timerId = setInterval(render, 1000);
  }

  function setActivity(payload) {
    if (!payload) return;
    activity = payload;
    render();
    startTimer();
  }

  pauseBtn.addEventListener("click", () => {
    setPaused(true);
    window.MiniAPI?.sendAction?.("pause");
  });
  resumeBtn.addEventListener("click", () => {
    setPaused(false);
    window.MiniAPI?.sendAction?.("resume");
  });
  document.getElementById("miniStop").addEventListener("click", () => {
    window.MiniAPI?.sendAction?.("stop");
  });
  document.getElementById("miniClose").addEventListener("click", () => {
    window.MiniAPI?.close?.();
  });

  if (window.MiniAPI?.onData) {
    window.MiniAPI.onData(setActivity);
  }

})();
