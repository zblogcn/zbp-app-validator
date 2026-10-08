<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 10/30/2017
 * Time: 23:56
 */

namespace Zsxsoft\AppValidator\Helpers;

use Cocur\BackgroundProcess\BackgroundProcess;
use RuntimeException;

class MeaningfulBackgroundProcess extends BackgroundProcess
{
    protected $command;
    protected $pid;

    public function __construct($command = null)
    {
        $this->command  = $command;
        $this->serverOS = $this->getOS();
        parent::__construct($command);
    }

    public function run($outputFile = '/dev/null', $append = false)
    {
        if ($this->command === null) {
            return;
        }
        switch ($this->getOS()) {
        case self::OS_WINDOWS:
            $startDir = ".";
            $descriptorspec = array(
              0 => array("pipe", "r"),
              1 => array("pipe", "w"),
            );
            $prog = proc_open("start /b \"\" " . $this->command, $descriptorspec, $pipes, $startDir, null);
            if (is_resource($prog)) {
                $ppid = proc_get_status($prog)['pid'];
            } else {
                throw new RuntimeException(
                    sprintf(
                        'Could not execute command "%s"', $this->command
                    )
                );
            }
            $cmd = "powershell -NoProfile -Command \"Get-CimInstance Win32_Process | Where-Object { \$_.ParentProcessId -eq $ppid } | Select-Object -ExpandProperty ProcessId\"";
            $childPid = trim(shell_exec($cmd));
            if (is_numeric($childPid) && (int)$childPid > 0) {
                $this->pid = (int)$childPid;
            } else {
                $this->pid = $ppid;
            }
            //shell_exec(sprintf('%s &', $this->command, $outputFile));
            break;
        case self::OS_NIX:
            $this->pid = (int) shell_exec(sprintf('%s %s %s 2>&1 & echo $!', $this->command, ($append) ? '>>' : '>', $outputFile));
            break;
        default:
            throw new RuntimeException(
                sprintf(
                    'Could not execute command "%s" because operating system "%s" is not supported by ' .
                        'Cocur\BackgroundProcess.', $this->command, PHP_OS
                )
            );
        }
    }

}