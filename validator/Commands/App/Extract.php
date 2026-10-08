<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/23
 * Time: 19:39
 */

namespace Zsxsoft\AppValidator\Commands\App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class Extract extends Command
{

    protected function configure()
    {
        $this
            ->setName('app:extract')
            ->setDescription('Extract a app from .zba file')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The Path of ZBA File'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appPath = $input->getArgument("path");
        if (!file_exists($appPath)) {
            Logger::error("$appPath not found or unreadable");
            return 1;
        }
        $appId = ZBPWrapper::installApp($appPath);
        if ($appId == false) {
            Logger::error("Extract $appPath failed");
            return 1;
        }
        Logger::info("Extracted $appId");
        return 0;
    }
}
