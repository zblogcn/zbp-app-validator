<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/29
 * Time: 15:07
 */

namespace Zsxsoft\AppValidator\Tasks;

use Cocur\BackgroundProcess\BackgroundProcess;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\PHPHelper;
use Zsxsoft\AppValidator\Helpers\MeaningfulBackgroundProcess;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Wrappers\Config;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;
use Zsxsoft\W3CValidator\W3CValidator;

class StartPipe
{
    public function run($appId, $exit = true)
    {
        $type = 'plugin';

        $this->_startProcess('project:start --start-server=0');
        // Start server in background
        $process = new MeaningfulBackgroundProcess($this->_getCommandLine('server:start'));
        $process->run();
        if (preg_match('/\.g?zba$/', $appId)) {
            $this->_startProcess('app:extract', $appId);
            $appId = file_get_contents(TempHelper::getPath('/extracted'));
        }

        $zbp = ZBPWrapper::getZbp();
        $app = ZBPWrapper::loadApp($appId);
        $type = $app->type;

        $this->_startProcess('scan:variable', $appId);
        $this->_startProcess('scan:phpcc', $appId);

        if ($type == 'theme') {
            $this->_startProcess('app:login');
            $this->_startProcess('app:theme:change', $appId);
            $this->_startProcess('scan:code', $appId);
            $this->_startProcess('browser');
            $this->_startProcess('scan:w3c');
        } else {
            $this->_startProcess('scan:code', $appId);
        }

        $this->_done($exit);

    }

    private function _done($exit = true)
    {
        if ($exit) {
            $this->_startProcess('project:end');
        }
    }

    private function _startProcess($command, $argument = '')
    {
        $return = 0;
        passthru($this->_getCommandLine($command, $argument), $return);
        if ($return !== 0) {
            $this->_done(true);
            exit;
        }
    }

    private function _getCommandLine($command, $argument = '')
    {
        $path = escapeshellarg(ROOT_PATH . DIRECTORY_SEPARATOR . 'checker');
        $arg = $argument === '' ? '' : escapeshellarg($argument);
        return PHPHelper::getBinary() . " $path $command $arg";
    }



}
