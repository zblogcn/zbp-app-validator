const { app, BrowserWindow } = require('electron')
const { join } = require('path')
const remoteMain = require('@electron/remote/main')

remoteMain.initialize()

let mainWindow = null

app.on('ready', () => {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false
    }
  })
  remoteMain.enable(mainWindow.webContents)
  mainWindow.loadFile(join(__dirname, 'index.html'))
  mainWindow.on('closed', () => {
    mainWindow = null
  })
})

app.on('window-all-closed', () => {
  app.quit()
})
