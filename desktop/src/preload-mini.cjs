const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("MiniAPI", {
  onData: (handler) => ipcRenderer.on("mini-data", (_event, payload) => handler(payload)),
  sendAction: (action) => ipcRenderer.send("mini-action", action),
  close: () => ipcRenderer.send("mini-close"),
});
