(() => {
  const Module = require('module')
  const origRequire = Module._load
  Module._load = function (request, parent, isMain) {
    if (request === 'vue') { // element-ui will load Vue..
      request = 'vue/dist/vue.common.js'
    }
    return origRequire(request, parent, isMain)
  }
})();

(() => {
  const Vue = require('vue')
  const ElementUI = require('element-ui')
  const { Terminal } = require('@xterm/xterm')
  const { FitAddon } = require('@xterm/addon-fit')

  const { dialog, getCurrentWindow, process, shell } = require('@electron/remote')
  const { spawn, exec, spawnSync } = require('child_process')
  // const fontManager = require('font-manager')
  const { join } = require('path')
  const { tmpdir } = require('os')
  const { readFileSync, writeFileSync, existsSync, mkdirSync } = require('fs')
  const rootPath = join(__dirname, '../../')
  const configPath = join(rootPath, '/config.json')
  const config = require('../shared/config')
  const defaultConfig = JSON.parse(readFileSync(join(rootPath, '/config.default.json'), 'utf-8'))
  const currentWindow = getCurrentWindow()
  const argv = process.argv
  // const fontFamilies = fontManager.getAvailableFontsSync().map(p => p.family)
  // const monoFonts = ['Source Code Pro', 'Noto Mono', 'DejaVu Sans Mono', 'Monospace', 'Consolas'].filter(p => fontFamilies.includes(p))

  const fitAddon = new FitAddon()
  const term = new Terminal({ scrollback: 5000 })
  term.loadAddon(fitAddon)
  // 按真实渲染的字形尺寸计算行列数，保证终端画布不超出滚动条所在容器
  const calculateTermSize = () => fitAddon.fit()
  const saveConfig = (newConfig, input) => {
    const savingConfig = JSON.parse(readFileSync(configPath, 'utf-8'))
    const keys = ['builtinServer', 'host', 'zbpPath']
    let saveFlag = false
    if (newConfig.builtinServer && !savingConfig.builtinServer) {
      saveFlag = true
      keys.map(key => {
        savingConfig[key] = defaultConfig[key]
      })
    } else if (!newConfig.builtinServer) {
      saveFlag = true
      keys.map(key => {
        savingConfig[key] = newConfig[key]
      })
    }
    // 记住上次使用的 PHP 路径和待审核应用，下次打开时自动填入
    if (input && (input.phpPath !== savingConfig.phpPath || input.appPath !== savingConfig.lastAppPath)) {
      saveFlag = true
      savingConfig.phpPath = input.phpPath
      savingConfig.lastAppPath = input.appPath
    }
    if (saveFlag) {
      writeFileSync(configPath, JSON.stringify(savingConfig), 'utf-8')
    }
  }

  // 审核输出保存到 logs 目录，每次审核单独一个文件（文件名含时间与应用标识）
  const logDir = join(rootPath, 'logs')
  let auditLogBuffer = null
  const writeAudit = text => {
    term.write(text)
    if (auditLogBuffer !== null) {
      auditLogBuffer.push(text)
    }
  }
  const saveAuditLog = appLabel => {
    if (auditLogBuffer === null) return
    const text = auditLogBuffer.join('')
      // eslint-disable-next-line no-control-regex
      .replace(/\x1b\[[0-9;?]*[A-Za-z]/g, '') // 去掉 ANSI 颜色/控制序列
      .replace(/\r/g, '')
      .trim()
    auditLogBuffer = null
    if (text === '') return
    try {
      mkdirSync(logDir, { recursive: true })
      const d = new Date()
      const pad = n => String(n).padStart(2, '0')
      const ts = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
      const base = String(appLabel || '').split(/[\\/]/).pop().replace(/\.(zba|gzba)$/i, '')
      const safeName = base.replace(/[\\/:*?"<>|\x00-\x1f]/g, '_').trim().slice(0, 40) || 'unknown'
      const file = join(logDir, `${ts}_${safeName}.txt`)
      writeFileSync(file, `===== ${d.toLocaleString('zh-CN')}  ${appLabel || '未知应用'} =====\n` + text + '\n', 'utf-8')
      term.write(`\r\n\x1b[90m本次审核输出已保存到 ${file}\x1b[0m\r\n`)
    } catch (e) {
      term.write(`\r\n\x1b[1;31m审核输出保存失败：${e.message}\x1b[0m\r\n`)
    }
  }

  // 准备 CA 证书：内置 cacert.pem + Windows 系统根证书（企业网关/杀毒软件 HTTPS 解密场景），
  // 通过 PHP_INI_SCAN_DIR 注入所有 PHP 子进程
  let caEnvPrepared = false
  const prepareCaEnv = env => {
    const certPath = join(rootPath, 'resources/ssl/cacert.pem')
    if (!existsSync(certPath)) return
    env.CURL_CA_BUNDLE = certPath
    env.SSL_CERT_FILE = certPath
    if (process.platform !== 'win32' || caEnvPrepared) {
      if (caEnvPrepared) {
        env.PHP_INI_SCAN_DIR = caEnvPrepared.phpIniScanDir
        env.CURL_CA_BUNDLE = caEnvPrepared.bundlePath
        env.SSL_CERT_FILE = caEnvPrepared.bundlePath
      }
      return
    }
    try {
      const caDir = join(tmpdir(), 'zbp-app-validator-ca')
      mkdirSync(caDir, { recursive: true })
      const bundlePath = join(caDir, 'ca-bundle.pem')
      let bundle = readFileSync(certPath, 'utf-8')
      // 导出 Windows 受信任根证书
      const ps = spawnSync('powershell.exe', [
        '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command',
        "$ErrorActionPreference='SilentlyContinue';" +
        "foreach($s in 'Cert:\\LocalMachine\\Root','Cert:\\CurrentUser\\Root'){" +
        'Get-ChildItem $s | ForEach-Object {' +
        '$d=$_.Export([Security.Cryptography.X509Certificates.X509ContentType]::Cert);' +
        "'-----BEGIN CERTIFICATE-----';" +
        '[Convert]::ToBase64String($d,1);' +
        "'-----END CERTIFICATE-----'}}"
      ], { encoding: 'utf8', windowsHide: true, timeout: 30000 })
      if (ps.status === 0 && ps.stdout.includes('BEGIN CERTIFICATE')) {
        bundle += '\r\n' + ps.stdout.replace(/^\uFEFF/, '')
      }
      writeFileSync(bundlePath, bundle, 'utf-8')
      const iniPath = join(caDir, 'zz-cacert.ini')
      const pemPath = bundlePath.replace(/\\/g, '/')
      writeFileSync(iniPath, `curl.cainfo = "${pemPath}"\r\nopenssl.cafile = "${pemPath}"\r\n`, 'utf-8')
      const base = env.PHP_INI_SCAN_DIR || ''
      const scan = (base ? base + ';' : '') + caDir
      env.PHP_INI_SCAN_DIR = scan
      env.CURL_CA_BUNDLE = bundlePath
      env.SSL_CERT_FILE = bundlePath
      caEnvPrepared = { phpIniScanDir: scan, bundlePath }
    } catch (e) {
      // 导出失败时退化为仅使用内置证书
    }
  }

  currentWindow.setIcon(join(rootPath, 'resources/Logo.png'))
  window.term = term
  window.addEventListener('resize', calculateTermSize)

  Vue.use(ElementUI)
  const app = new Vue({
    el: '#app',
    data: {
      config,
      input: {
        phpPath: config.phpPath || '',
        appPath: config.lastAppPath || ''
      },
      disableAuditButton: false
    },
    mounted () {
      if (argv.length >= 2) {
        this.input.appPath = argv[2]
      }
      term.open(document.getElementById('terminal'), false)
//      if (monoFonts.length > 0) {
//        term.setOption('fontFamily', monoFonts[0])
//      }
      calculateTermSize()
      term.writeln('Terminal...')
    },
    methods: {
      openBrowser (url) {
        shell.openExternal(url)
      },
      startLauncher (arg) {
        exec(join(rootPath, 'launcher') + ` ${arg}`)
      },
      browsePHPPath () {
        dialog.showOpenDialog(currentWindow, {
          filters: [
            {name: 'PHP Executable (php.exe, php)', extensions: ['php.exe', 'php']},
            {name: 'All Files', extensions: ['*']}
          ]
        }).then(result => {
          if (!result.canceled && result.filePaths.length) {
            this.input.phpPath = result.filePaths[0]
          }
        })
      },
      browseAppId () {
        dialog.showOpenDialog(currentWindow, {
          filters: [
            {name: 'zba file (.zba, .gzba)', extensions: ['zba', 'gzba']}
          ]
        }).then(result => {
          if (!result.canceled && result.filePaths.length) {
            this.input.appPath = result.filePaths[0]
          }
        })
      },
      browseZBPPath () {
        dialog.showOpenDialog(currentWindow, {
          properties: ['openDirectory']
        }).then(result => {
          if (!result.canceled && result.filePaths.length) {
            this.config.zbpPath = result.filePaths[0]
          }
        })
      },
      doAudit () {
        saveConfig(this.config, this.input)
        const phpPath = this.input.phpPath.trim() === '' ? 'php' : this.input.phpPath
        this.disableAuditButton = true
        term.clear()
        auditLogBuffer = []
        const childEnv = {
          ...process.env,
          term: 'xterm'
        }
        prepareCaEnv(childEnv)
        const run = cmd => new Promise(resolve => {
          exec(cmd, { cwd: rootPath, env: childEnv }, (err, stdout, stderr) => resolve({ err, stdout, stderr }))
        })
        const fail = msg => {
          writeAudit(`\x1b[1;31m${msg}\x1b[0m\r\n`)
          saveAuditLog(this.input.appPath)
          this.disableAuditButton = false
        }
        const startAudit = () => {
          const p = spawn(`"${phpPath}" checker start "${this.input.appPath}"`, {
            cwd: rootPath,
            env: childEnv,
            shell: true
          })
          p.stdout.on('data', buf => writeAudit(buf.toString().replace(/\n/g, '\r\n')))
          p.stderr.on('data', buf => writeAudit(buf.toString().replace(/\n/g, '\r\n')))
          p.on('exit', () => {
            saveAuditLog(this.input.appPath)
            this.disableAuditButton = false
          })
        }
        (async () => {
          writeAudit('正在检查 PHP 环境...\r\n')
          const mods = await run(`"${phpPath}" -m`)
          if (mods.err) {
            return fail(`无法运行 PHP：${phpPath}\r\n请检查「PHP 路径」是否指向正确的 php.exe。`)
          }
          const loaded = mods.stdout.toLowerCase().split(/\r?\n/)
          const required = ['curl', 'openssl', 'mbstring', 'simplexml', 'pdo_sqlite', 'sqlite3']
          const missing = required.filter(m => !loaded.includes(m))
          if (missing.length) {
            const ini = await run(`"${phpPath}" --ini`)
            const loadedIni = (ini.stdout.match(/Loaded Configuration File:\s*(.+)/) || [])[1] || '未加载（请确认 php.ini 与 php.exe 在同一目录）'
            const extDir = await run(`"${phpPath}" -r "echo ini_get('extension_dir');"`)
            const warnings = (mods.stderr || '').trim()
            return fail(
              `当前 PHP 缺少必需扩展：${missing.join(', ')}\r\n` +
              `加载的配置文件：${loadedIni.trim()}\r\n` +
              `扩展目录 extension_dir：${extDir.stdout.trim() || '未设置'}\r\n` +
              `请确认：1) 编辑的是上面这个 php.ini；2) extension_dir 指向 PHP 目录下的 ext；3) 对应 extension 已启用；4) ext 目录中存在相应 DLL。\r\n` +
              (warnings ? `PHP 加载信息：${warnings}` : '')
            )
          }
          writeAudit('正在测试网络连通性...\r\n')
          const probe = "$c=curl_init('https://update.zblogcn.com/');curl_setopt($c,CURLOPT_RETURNTRANSFER,1);curl_setopt($c,CURLOPT_NOBODY,1);curl_setopt($c,CURLOPT_TIMEOUT,10);curl_exec($c);echo json_encode(array('errno'=>curl_errno($c),'error'=>curl_error($c),'http'=>curl_getinfo($c,CURLINFO_HTTP_CODE)));"
          const net = await run(`"${phpPath}" -r "${probe}"`)
          let info = null
          try { info = JSON.parse(net.stdout.trim().match(/\{.*\}/s)[0]) } catch (e) {}
          if (net.err || !info || info.errno !== 0) {
            const hints = {
              6: 'DNS 解析失败，请检查网络/DNS 设置。',
              7: '连接被拒绝，目标站点不可达，请检查网络或代理。',
              28: '连接超时，请检查网络或代理。',
              35: 'SSL/TLS 握手失败。',
              51: '证书校验失败（错误 51）。',
              60: '证书校验失败：本机可能有网关/杀毒软件在解密 HTTPS，且其根证书未被 PHP 信任；程序已尝试合并系统证书库，仍失败请联系管理员。',
              77: '证书库文件无法读取。'
            }
            const detail = info
              ? `curl 错误 ${info.errno}：${info.error || hints[info.errno] || '未知错误'}`
              : (net.stderr || net.stdout || '无法获取错误详情').trim()
            return fail(
              `无法连接 update.zblogcn.com。\r\n${detail}\r\n` +
              (hints[info && info.errno] || '如使用了代理/VPN，请确认其允许命令行程序访问网络（可配置 HTTPS_PROXY 环境变量）。')
            )
          }
          startAudit()
        })()
      }
    }
  })

})()
