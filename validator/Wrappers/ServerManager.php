<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/29
 * Time: 9:48
 */

namespace Zsxsoft\AppValidator\Wrappers;

use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\StaticInstance;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Helpers\ZBPHelper;
use Zsxsoft\AppValidator\Helpers\PHPHelper;

class ServerManager
{
    use StaticInstance;
    protected $pid = '';
    protected $pidPath = '';

    public function __construct()
    {
        $this->pidPath = TempHelper::getPath('/php.pid');
        $this->loadPid();
    }

    protected function loadPid()
    {

        if (file_exists($this->pidPath)) {
            $this->pid = file_get_contents($this->pidPath);
        } else {
            $this->pid = '';
        }
    }

    protected function start()
    {
        if (!Config::get('builtinServer')) {
            return;
        }
        $this->stop();
        $pipes = [];
        $listenAddress = Config::get('listenAddress');
        $proc = proc_open(
            PHPHelper::getBinary() . ' -S ' . $listenAddress,
            [
                0 => ["pipe", "r"],
                1 => ['file', TempHelper::getPath('/server-output.txt'), 'w'],
                2 => ['file', TempHelper::getPath('/server-error.txt'), 'w'],
            ],
            $pipes, ZBPHelper::getPath() . DIRECTORY_SEPARATOR, null,
            [
                'bypass_shell' => true
            ]
        );
        if (!is_resource($proc)) {
            Logger::error('Start PHP Server failed!');
            exit;
        }
        $status = proc_get_status($proc);
        $pid = $status['pid'];
        Logger::info("Started PHP Server(PID={$pid}) at $listenAddress");
        $this->pid = $pid;
        file_put_contents($this->pidPath, $pid);
        while (true) {
            if (!proc_get_status($proc)['running']) {
                break;
            }
            sleep(5);
        }
    }

    protected function stop()
    {
        if (!Config::get('builtinServer')) {
            return;
        }
        $this->loadPid();
        if ($this->pid == '') {
            return;
        }
        if (function_exists('posix_kill')) {
            posix_kill($this->pid, 9 /* SIGKILL */);
        } else if (DIRECTORY_SEPARATOR === '\\') {
            shell_exec("taskkill.exe /pid {$this->pid} /f");
        } else {
            shell_exec("kill -9 {$this->pid}");
        }

        @unlink($this->pidPath);
    }

}
