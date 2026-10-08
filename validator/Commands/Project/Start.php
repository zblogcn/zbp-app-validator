<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/22
 * Time: 16:46
 */

namespace Zsxsoft\AppValidator\Commands\Project;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Wrappers\ServerManager;
use Zsxsoft\AppValidator\Wrappers\ZBPInstaller;

class Start extends Command
{

    protected function configure()
    {
        $this
            ->setName('project:start')
            ->setDescription('Clean the temporary and start a new check project')
            ->addOption(
                'start-server',
                null,
                InputOption::VALUE_OPTIONAL,
                'Start PHP test server',
                true
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Logger::info('Starting a new check project...');
        // 先停掉上次可能残留的服务器进程，避免占用 tmp\web 导致清理失败
        ServerManager::stop();
        TempHelper::createTemp();
        ZBPInstaller::createEmptyEnvironment();
        if ($input->getOption('start-server')) {
            ServerManager::start();
        }
        return 0;
    }
}
