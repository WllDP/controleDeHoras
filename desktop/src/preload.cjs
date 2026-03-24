const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("DesktopAPI", {
  prepararDestinoDownload: (suggestedName) =>
    ipcRenderer.invoke("prepare-next-download-path", suggestedName),

  getAutoLaunch: () => ipcRenderer.invoke("get-auto-launch"),
  setAutoLaunch: (enabled) => ipcRenderer.invoke("set-auto-launch", enabled),

  requestWidgetResize: (payload) => ipcRenderer.send("widget-resize", payload),
  closeApp: () => ipcRenderer.send("app-close"),
  focusApp: () => ipcRenderer.send("app-focus"),
  minimizeApp: (payload) => ipcRenderer.send("app-minimize", payload),
  setActivityStatus: (payload) => ipcRenderer.send("activity-status", payload),
  onMiniAction: (handler) => ipcRenderer.on("mini-action", (_event, action) => handler(action)),
  onAppRequestClose: (handler) => ipcRenderer.on("app-request-close", () => handler()),
  onDownloadStatus: (handler) =>
    ipcRenderer.on("download-status", (_event, payload) => handler(payload)),
});

function getWidgetRoot() {
  return (
    document.getElementById("widget-root") ||
    document.querySelector("[data-widget-root]")
  );
}

function getBodyPadding() {
  const cs = window.getComputedStyle(document.body);
  const pt = parseFloat(cs.paddingTop) || 0;
  const pr = parseFloat(cs.paddingRight) || 0;
  const pb = parseFloat(cs.paddingBottom) || 0;
  const pl = parseFloat(cs.paddingLeft) || 0;
  return { pt, pr, pb, pl };
}

function requestResize() {
  const root = getWidgetRoot();
  if (!root) return;

  const r = root.getBoundingClientRect();
  const pad = getBodyPadding();

  const width = Math.ceil(r.width + pad.pl + pad.pr);
  const height = Math.ceil(r.height + pad.pt + pad.pb);

  window.DesktopAPI?.requestWidgetResize?.({
    width,
    height,
    animate: true,
  });
}

function burstResize() {
  // mede várias vezes, porque Tailwind/fontes podem alterar o layout depois
  const times = [0, 50, 120, 250, 400, 650, 900, 1300, 1800];
  times.forEach((t) => setTimeout(requestResize, t));

  // se as fontes forem carregadas depois, mede novamente
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(() => {
      requestResize();
      setTimeout(requestResize, 200);
    }).catch(() => {});
  }

  // mede em 2 frames seguidos (layout settle)
  requestAnimationFrame(() => requestAnimationFrame(requestResize));
}

window.addEventListener("DOMContentLoaded", () => {
  burstResize();

  const root = getWidgetRoot();
  if (root && "ResizeObserver" in window) {
    const ro = new ResizeObserver(() => {
      // debounce leve
      clearTimeout(window.__resizeTimer);
      window.__resizeTimer = setTimeout(requestResize, 60);
    });
    ro.observe(root);
  }

  // reforço depois do load (recursos externos)
  window.addEventListener("load", () => {
    burstResize();
  });
});
