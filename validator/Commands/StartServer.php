<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/22
 * Time: 16:46
 */

namespace Zsxsoft\AppValidator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Wrappers\ServerManager;
use Zsxsoft\AppValidator\Wrappers\ZBPInstaller;

class StartServer extends Command
{

    protected function configure()
    {
        $this
            ->setName('server:start')
            ->setDescription('Start the builtin server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ServerManager::start();
        return 0;
    }
}