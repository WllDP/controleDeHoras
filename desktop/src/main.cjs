const { app, BrowserWindow, dialog, session, ipcMain, screen } = require("electron");
const { autoUpdater } = require("electron-updater");
const path = require("path");
const fs = require("fs");
const { spawn } = require("child_process");
const net = require("net");
const http = require("http");

const OPACITY_INTERVAL_MS = 16;

let phpProcess = null;
let downloadsSetup = false;
let nextDownloadPath = null;

let mainWindow = null;
let miniWindow = null;
let currentActivity = null;
let currentActivityId = null;
let currentPort = null;
let miniDismissed = false;
let windowOpacityTimer = null;
let miniOpacityTimer = null;
let miniReady = false;
let pendingMiniShow = false;
let miniPrimed = false;
let miniShowTimer = null;
let pendingMiniAction = null;
let allowClose = false;
let widgetHitbox = null;
let clickThroughTimer = null;
let lastIgnoreState = null;
let updaterReady = false;

// =====================================
// IPC: preparar o caminho do próximo download
// =====================================
ipcMain.handle("prepare-next-download-path", async (_event, suggestedName) => {
  const { canceled, filePath } = await dialog.showSaveDialog({
    title: "Salvar planilha",
    defaultPath: path.join(
      app.getPath("documents"),
      suggestedName || "controle_horas.xlsx"
    ),
    filters: [{ name: "Excel", extensions: ["xlsx"] }],
  });

  if (canceled || !filePath) return false;
  nextDownloadPath = filePath;
  return true;
});

// =====================================
// IPC: widget mode
// =====================================
ipcMain.on("app-close", () => {
  allowClose = true;
  closeMiniWindow();
  app.quit();
});
ipcMain.on("app-focus", () => {
  if (mainWindow) {
    mainWindow.show();
    mainWindow.focus();
  }
});
ipcMain.on("app-minimize", (_event, payload) => {
  const atividade = payload?.atividade;
  if (atividade && atividade.rodando) {
    currentActivity = atividade;
    if (atividade.id !== currentActivityId) {
      miniDismissed = false;
      currentActivityId = atividade.id;
    }
  }
  miniDismissed = false;

  openMiniIfNeeded();
  if (mainWindow) {
    if (windowOpacityTimer) clearInterval(windowOpacityTimer);
    const durationMs = 50;
    const start = Date.now();
    try { mainWindow.setOpacity(1); } catch {}
    windowOpacityTimer = setInterval(() => {
      if (!mainWindow || mainWindow.isDestroyed()) {
        clearInterval(windowOpacityTimer);
        windowOpacityTimer = null;
        return;
      }
      const elapsed = Date.now() - start;
      const t = Math.min(1, elapsed / durationMs);
      try { mainWindow.setOpacity(1 - t); } catch {}
      if (t >= 1) {
        clearInterval(windowOpacityTimer);
        windowOpacityTimer = null;
        try { mainWindow.setOpacity(0); } catch {}
        mainWindow.minimize();
      }
    }, OPACITY_INTERVAL_MS);
  }
});
ipcMain.on("activity-status", (_event, payload) => {
  if (payload) {
    currentActivity = payload;
    if (payload.id !== currentActivityId) {
      miniDismissed = false;
      currentActivityId = payload.id;
    }
  } else {
    currentActivity = null;
    currentActivityId = null;
    miniDismissed = false;
    closeMiniWindow();
  }

  if (miniWindow && currentActivity) {
    miniWindow.webContents.send("mini-data", currentActivity);
  }
});

ipcMain.on("mini-action", (_event, action) => {
  if (mainWindow && !mainWindow.isDestroyed()) {
    const currentUrl = mainWindow.webContents.getURL() || "";
    const onDashboard = currentUrl.includes("dashboard.php");
    if (!onDashboard && currentPort) {
      pendingMiniAction = action;
      const targetUrl = `http://127.0.0.1:${currentPort}/dashboard.php`;
      if (currentUrl !== targetUrl) {
        mainWindow.loadURL(targetUrl);
      }
      mainWindow.webContents.once("did-finish-load", () => {
        if (pendingMiniAction) {
          mainWindow.webContents.send("mini-action", pendingMiniAction);
          pendingMiniAction = null;
        }
      });
    } else {
      mainWindow.webContents.send("mini-action", action);
    }
  }
  if (action === "stop") {
    if (mainWindow && !mainWindow.isDestroyed()) {
      if (mainWindow.isMinimized()) {
        mainWindow.restore();
      }
      mainWindow.show();
      mainWindow.focus();
    }
    closeMiniWindow();
  }
});

ipcMain.on("mini-close", () => {
  miniDismissed = true;
  closeMiniWindow();
});

// =====================================
// Auto-launch (iniciar com o sistema)
// =====================================
function getAutoLaunchState() {
  if (process.platform !== "win32") return false;
  try {
    return Boolean(app.getLoginItemSettings().openAtLogin);
  } catch {
    return false;
  }
}

function setAutoLaunchState(enabled) {
  if (process.platform !== "win32") return false;
  try {
    app.setLoginItemSettings({
      openAtLogin: Boolean(enabled),
      path: process.execPath,
      args: [],
    });
    return getAutoLaunchState();
  } catch {
    return getAutoLaunchState();
  }
}

ipcMain.handle("get-auto-launch", () => getAutoLaunchState());
ipcMain.handle("set-auto-launch", (_event, enabled) => setAutoLaunchState(enabled));
ipcMain.on("set-ignore-mouse", (_event, ignore) => {
  if (!mainWindow || mainWindow.isDestroyed()) return;
  try {
    mainWindow.setIgnoreMouseEvents(Boolean(ignore), { forward: true });
  } catch {}
});
ipcMain.on("set-widget-hitbox", (_event, rect) => {
  if (!rect || typeof rect !== "object") return;
  const x = Number(rect.x);
  const y = Number(rect.y);
  const w = Number(rect.width);
  const h = Number(rect.height);
  if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(w) || !Number.isFinite(h)) return;
  widgetHitbox = { x, y, width: w, height: h };
});

// =====================================
// Updates (GitHub Releases)
// =====================================
function setupAutoUpdater() {
  if (!app.isPackaged || updaterReady) return;
  updaterReady = true;
  autoUpdater.autoDownload = false;

  autoUpdater.on("update-available", (info) => {
    mainWindow?.webContents.send("update-available", info);
  });
  autoUpdater.on("update-not-available", (info) => {
    mainWindow?.webContents.send("update-not-available", info);
  });
  autoUpdater.on("error", (err) => {
    const message = err?.message || String(err || "Erro ao buscar atualizações");
    mainWindow?.webContents.send("update-error", message);
  });
  autoUpdater.on("download-progress", (progress) => {
    mainWindow?.webContents.send("update-progress", progress);
  });
  autoUpdater.on("update-downloaded", (info) => {
    mainWindow?.webContents.send("update-downloaded", info);
  });
}

ipcMain.handle("check-for-updates", async () => {
  if (!app.isPackaged) return { status: "dev" };
  setupAutoUpdater();
  try {
    await autoUpdater.checkForUpdates();
    return { status: "checking" };
  } catch (err) {
    const message = err?.message || String(err || "Erro ao buscar atualizações");
    mainWindow?.webContents.send("update-error", message);
    return { status: "error", message };
  }
});
ipcMain.handle("download-update", async () => {
  if (!app.isPackaged) return { status: "dev" };
  setupAutoUpdater();
  await autoUpdater.downloadUpdate();
  return { status: "downloading" };
});
ipcMain.handle("quit-and-install", async () => {
  if (!app.isPackaged) return { status: "dev" };
  autoUpdater.quitAndInstall();
  return { status: "restarting" };
});

// Resize suave e centralizado
let resizeLock = false;
let lastSize = { w: 0, h: 0 };
let resizeUnlockTimer = null;
let userMovedWindow = false;
let suppressMoveFlag = false;

function clampWindowSize(width, height) {
  const display = screen.getPrimaryDisplay();
  const area = display?.workAreaSize || { width: 520, height: 420 };
  const maxW = Math.min(900, Math.round(area.width));
  const maxH = Math.min(700, Math.round(area.height));
  return {
    width: Math.max(320, Math.min(maxW, Math.round(Number(width)))),
    height: Math.max(240, Math.min(maxH, Math.round(Number(height)))),
  };
}

ipcMain.on("widget-resize", (_event, payload) => {
  if (!mainWindow) return;
  if (!payload || !payload.width || !payload.height) return;
  if (resizeLock) return;

  const size = clampWindowSize(payload.width, payload.height);
  const w = size.width;
  const h = size.height;
  const animate = payload.animate !== false;

  // evita loop: se já está no mesmo tamanho, ignora
  if (Math.abs(w - lastSize.w) <= 1 && Math.abs(h - lastSize.h) <= 1) return;
  lastSize = { w, h };

  // centraliza baseado no centro atual
  const bounds = mainWindow.getBounds();
  const nextBounds = { width: w, height: h };
  if (userMovedWindow) {
    nextBounds.x = bounds.x;
    nextBounds.y = bounds.y;
  } else {
    const cx = bounds.x + Math.round(bounds.width / 2);
    const cy = bounds.y + Math.round(bounds.height / 2);
    nextBounds.x = Math.round(cx - w / 2);
    nextBounds.y = Math.round(cy - h / 2);
  }

  resizeLock = true;
  clearTimeout(resizeUnlockTimer);

  suppressMoveFlag = true;
  try {
    mainWindow.setBounds(nextBounds, animate);
  } catch {
    try {
      mainWindow.setBounds(nextBounds);
    } catch {}
  }

  // destrava depois de um curto período
  resizeUnlockTimer = setTimeout(() => {
    resizeLock = false;
    suppressMoveFlag = false;
  }, 140);
});

// =====================================
// Paths (DEV vs BUILD)
// =====================================
function getAppRoot() {
  // BUILD: o webapp vai para <instalado>/resources/www
  if (app.isPackaged) return path.join(process.resourcesPath, "www");

  // DEV: root do projeto (controle-horas)
  return path.resolve(__dirname, "..", "..");
}

function getPhpExe() {
  const base = app.isPackaged ? process.resourcesPath : path.resolve(__dirname, "..");
  return path.join(base, "resources", "php", "win", "php.exe");
}

function getPhpIni() {
  const base = app.isPackaged ? process.resourcesPath : path.resolve(__dirname, "..");
  return path.join(base, "resources", "php", "win", "php.ini");
}

// =====================================
// Networking helpers
// =====================================
function getFreePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.unref();
    srv.on("error", reject);
    srv.listen(0, "127.0.0.1", () => {
      const { port } = srv.address();
      srv.close(() => resolve(port));
    });
  });
}

function waitForHttpReady(url, timeoutMs = 15000) {
  const started = Date.now();

  return new Promise((resolve, reject) => {
    const tick = () => {
      const req = http.get(url, (res) => {
        res.resume();
        resolve();
      });

      req.on("error", () => {
        if (Date.now() - started > timeoutMs) {
          reject(new Error(`Timeout esperando servidor subir em: ${url}`));
          return;
        }
        setTimeout(tick, 250);
      });
    };

    tick();
  });
}

// =====================================
// Downloads: salva no path preparado pelo renderer
// =====================================
function setupDownloads() {
  if (downloadsSetup) return;
  downloadsSetup = true;

  session.defaultSession.on("will-download", (_event, item) => {
    const shouldNotify = Boolean(nextDownloadPath);
    if (nextDownloadPath) {
      item.setSavePath(nextDownloadPath);
      nextDownloadPath = null;
    } else {
      const fallbackDir = path.join(app.getPath("documents"), "Controle de Horas");
      if (!fs.existsSync(fallbackDir)) fs.mkdirSync(fallbackDir, { recursive: true });
      item.setSavePath(path.join(fallbackDir, item.getFilename()));
    }

    const finalPath = item.getSavePath?.() || "";

    item.once("done", (_e, state) => {
      if (!shouldNotify) return;
      if (state === "completed") {
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.webContents.send("download-status", {
            state: "completed",
            path: finalPath,
          });
        }
      } else if (state !== "cancelled") {
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.webContents.send("download-status", {
            state: "failed",
            error: state,
            path: finalPath,
          });
        }
      }
    });
  });
}

// =====================================
// PHP server
// =====================================
async function startPhpServer(port) {
  const appRoot = getAppRoot();
  const phpExe = getPhpExe();
  const phpIni = getPhpIni();

  if (!fs.existsSync(appRoot)) throw new Error(`Pasta do appRoot não existe: ${appRoot}`);
  if (!fs.existsSync(path.join(appRoot, "index.php"))) throw new Error(`index.php não encontrado em: ${appRoot}`);
  if (!fs.existsSync(phpExe)) throw new Error(`php.exe não encontrado em: ${phpExe}`);
  if (!fs.existsSync(phpIni)) throw new Error(`php.ini não encontrado em: ${phpIni}`);

  const args = ["-c", phpIni, "-S", `127.0.0.1:${port}`, "-t", appRoot];

  phpProcess = spawn(phpExe, args, {
    cwd: appRoot,
    windowsHide: true,
    env: {
      ...process.env,
      APP_VERSION: app.getVersion(),
      CONTROLE_HORAS_CACHE: path.join(app.getPath("userData"), "excel-cache"),
    },
  });

  phpProcess.stdout.on("data", (d) => console.log("[PHP]", d.toString()));
  phpProcess.stderr.on("data", (d) => console.error("[PHP ERR]", d.toString()));
  phpProcess.on("exit", (code, signal) => console.log("[PHP] exit", { code, signal }));

  await waitForHttpReady(`http://127.0.0.1:${port}/index.php`);
}

// =====================================
// Window (Widget)
// =====================================
function createMainWindow(url) {
  const initialSize = clampWindowSize(820, 640);
  mainWindow = new BrowserWindow({
    width: initialSize.width,
    height: initialSize.height,
    center: true,

    frame: false,        // ✅ remove borda padrão
    transparent: true,   // ✅ deixa o rounded visível
    backgroundColor: "#00000000",

    // ✅ importante para resize via setBounds funcionar bem
    resizable: false,
    maximizable: false,
    minimizable: true,
    fullscreenable: false,
    // sombra bonita
    hasShadow: true,

    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.resolve(__dirname, "preload.cjs"),
    },
  });

  mainWindow.removeMenu();
  mainWindow.setResizable(false);
  mainWindow.on("close", (event) => {
    if (allowClose) return;
    event.preventDefault();
    try {
      if (mainWindow && !mainWindow.isDestroyed()) {
        if (mainWindow.isMinimized()) {
          mainWindow.restore();
        }
        mainWindow.show();
        mainWindow.focus();
        mainWindow.webContents.send("app-request-close");
        return;
      }
    } catch {}

    const running = Boolean(currentActivity && currentActivity.rodando);
    const result = dialog.showMessageBoxSync(mainWindow, {
      type: "warning",
      buttons: ["Cancelar", "Fechar"],
      defaultId: 0,
      cancelId: 0,
      message: running
        ? "Existe uma atividade em andamento. Deseja fechar o app?"
        : "Deseja fechar o app?",
    });
    if (result === 1) {
      allowClose = true;
      closeMiniWindow();
      app.quit();
    }
  });
  mainWindow.on("closed", () => {
    closeMiniWindow();
    mainWindow = null;
  });
  mainWindow.on("restore", () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      if (windowOpacityTimer) clearInterval(windowOpacityTimer);
      try { mainWindow.setOpacity(0); } catch {}
      const durationMs = 50;
      const start = Date.now();
      windowOpacityTimer = setInterval(() => {
        if (!mainWindow || mainWindow.isDestroyed()) {
          clearInterval(windowOpacityTimer);
          windowOpacityTimer = null;
          return;
        }
        const elapsed = Date.now() - start;
        const t = Math.min(1, elapsed / durationMs);
        try { mainWindow.setOpacity(t); } catch {}
        if (t >= 1) {
          clearInterval(windowOpacityTimer);
          windowOpacityTimer = null;
          try { mainWindow.setOpacity(1); } catch {}
        }
      }, OPACITY_INTERVAL_MS);
    }
  });
  mainWindow.on("move", () => {
    if (suppressMoveFlag) return;
    userMovedWindow = true;
  });
  mainWindow.on("minimize", () => {
    openMiniIfNeeded();
  });
  mainWindow.on("restore", () => {
    closeMiniWindow();
  });
  mainWindow.on("focus", () => {
    closeMiniWindow();
  });

  mainWindow.webContents.on("did-fail-load", (_event, errorCode, errorDescription, validatedURL) => {
    console.error("[APP] did-fail-load", { errorCode, errorDescription, validatedURL });
  });

  mainWindow.loadURL(url);

  if (clickThroughTimer) clearInterval(clickThroughTimer);
  clickThroughTimer = setInterval(() => {
    if (!mainWindow || mainWindow.isDestroyed()) return;
    if (mainWindow.isMinimized()) return;
    if (!widgetHitbox) {
      if (lastIgnoreState !== false) {
        lastIgnoreState = false;
        try { mainWindow.setIgnoreMouseEvents(false, { forward: true }); } catch {}
      }
      return;
    }

    const point = screen.getCursorScreenPoint();
    const inside =
      point.x >= widgetHitbox.x &&
      point.x <= widgetHitbox.x + widgetHitbox.width &&
      point.y >= widgetHitbox.y &&
      point.y <= widgetHitbox.y + widgetHitbox.height;
    const nextIgnore = !inside;
    if (lastIgnoreState !== nextIgnore) {
      lastIgnoreState = nextIgnore;
      try { mainWindow.setIgnoreMouseEvents(nextIgnore, { forward: true }); } catch {}
    }
  }, 80);

  if (app.isPackaged) {
    setupAutoUpdater();
  }
}

function closeMiniWindow() {
  if (miniWindow && !miniWindow.isDestroyed()) {
    pendingMiniShow = false;
    if (miniShowTimer) {
      clearTimeout(miniShowTimer);
      miniShowTimer = null;
    }
    if (miniOpacityTimer) clearInterval(miniOpacityTimer);
    const durationMs = 160;
    const start = Date.now();
    miniOpacityTimer = setInterval(() => {
      if (!miniWindow || miniWindow.isDestroyed()) {
        clearInterval(miniOpacityTimer);
        miniOpacityTimer = null;
        return;
      }
      const elapsed = Date.now() - start;
      const t = Math.min(1, elapsed / durationMs);
      try { miniWindow.setOpacity(1 - t); } catch {}
      if (t >= 1) {
        clearInterval(miniOpacityTimer);
        miniOpacityTimer = null;
        try { miniWindow.setOpacity(0); } catch {}
        miniWindow.hide();
      }
    }, OPACITY_INTERVAL_MS);
  }
}

function ensureMiniWindow() {
  if (!currentPort) return;
  if (miniWindow && !miniWindow.isDestroyed()) return;

  const display = screen.getPrimaryDisplay();
  const area = display?.workArea || { x: 0, y: 0, width: 800, height: 600 };
  const width = 230;
  const height = 92;
  const x = Math.max(area.x + 8, area.x + area.width - width - 16);
  const y = area.y + 16;

  miniWindow = new BrowserWindow({
    width,
    height,
    x,
    y,
    frame: false,
    transparent: true,
    backgroundColor: "#00000000",
    opacity: 0,
    resizable: false,
    maximizable: false,
    minimizable: false,
    fullscreenable: false,
    alwaysOnTop: true,
    skipTaskbar: true,
    hasShadow: true,
    show: false,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.resolve(__dirname, "preload-mini.cjs"),
    },
  });

  miniWindow.setAlwaysOnTop(true, "screen-saver");
  try { miniWindow.setOpacity(0); } catch {}
  miniWindow.removeMenu();
  miniWindow.on("closed", () => {
    miniWindow = null;
    miniReady = false;
    pendingMiniShow = false;
    miniPrimed = false;
    if (miniShowTimer) {
      clearTimeout(miniShowTimer);
      miniShowTimer = null;
    }
  });
  miniReady = false;
  miniPrimed = false;

  const miniUrl = `http://127.0.0.1:${currentPort}/mini.html`;
  miniWindow.loadURL(miniUrl);
  miniWindow.webContents.once("did-finish-load", () => {
    miniReady = true;
    if (currentActivity) {
      miniWindow.webContents.send("mini-data", currentActivity);
    }
    if (pendingMiniShow) {
      pendingMiniShow = false;
      if (miniShowTimer) clearTimeout(miniShowTimer);
      miniShowTimer = setTimeout(() => {
        miniShowTimer = null;
        showMiniWindow();
      }, 200);
      return;
    }
    primeMiniWindow();
  });
}

function primeMiniWindow() {
  if (!miniWindow || miniWindow.isDestroyed() || miniPrimed) return;
  miniPrimed = true;
  try { miniWindow.setOpacity(0); } catch {}
  miniWindow.showInactive();
  try { miniWindow.setOpacity(0); } catch {}
  setTimeout(() => {
    if (!miniWindow || miniWindow.isDestroyed()) return;
    try { miniWindow.setOpacity(0); } catch {}
    if (!pendingMiniShow) {
      miniWindow.hide();
    }
  }, 60);
}

function showMiniWindow() {
  if (!miniWindow || miniWindow.isDestroyed()) return;
  if (miniOpacityTimer) clearInterval(miniOpacityTimer);

  const durationMs = 200;
  try { miniWindow.setOpacity(0); } catch {}
  miniWindow.showInactive();
  try { miniWindow.setOpacity(0); } catch {}
  miniPrimed = true;

  setTimeout(() => {
    if (!miniWindow || miniWindow.isDestroyed()) return;
    const start = Date.now();
    miniOpacityTimer = setInterval(() => {
      if (!miniWindow || miniWindow.isDestroyed()) {
        clearInterval(miniOpacityTimer);
        miniOpacityTimer = null;
        return;
      }
      const elapsed = Date.now() - start;
      const t = Math.min(1, elapsed / durationMs);
      try { miniWindow.setOpacity(t); } catch {}
      if (t >= 1) {
        clearInterval(miniOpacityTimer);
        miniOpacityTimer = null;
        try { miniWindow.setOpacity(1); } catch {}
      }
    }, OPACITY_INTERVAL_MS);
  }, 0);
}

function openMiniIfNeeded() {
  if (!currentActivity || miniDismissed || !currentPort) return;
  ensureMiniWindow();
  if (miniWindow && !miniWindow.isDestroyed()) {
    if (currentActivity) {
      miniWindow.webContents.send("mini-data", currentActivity);
    }
    if (!miniReady) {
      pendingMiniShow = true;
      return;
    }
    if (miniShowTimer) clearTimeout(miniShowTimer);
    miniShowTimer = setTimeout(() => {
      miniShowTimer = null;
      showMiniWindow();
    }, 500);
  }
}

// =====================================
// Boot
// =====================================
async function boot() {
  try {
    setupDownloads();

    const port = await getFreePort();
    currentPort = port;
    await startPhpServer(port);

    const url = `http://127.0.0.1:${port}/index.php`;
    createMainWindow(url);
    ensureMiniWindow();
  } catch (err) {
    dialog.showErrorBox("Erro ao iniciar o app", String(err?.stack || err));
    app.quit();
  }
}

app.whenReady().then(boot);

app.on("before-quit", () => {
  if (phpProcess && !phpProcess.killed) {
    try { phpProcess.kill(); } catch {}
  }
});

app.on("window-all-closed", () => {
  if (process.platform !== "darwin") app.quit();
});
