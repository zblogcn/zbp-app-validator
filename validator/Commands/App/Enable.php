<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/23
 * Time: 19:39
 **/

namespace Zsxsoft\AppValidator\Commands\App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class Enable extends Command
{

    protected function configure()
    {
        $this
            ->setName('app:enable')
            ->setDescription('Enable installed app')
            ->addArgument(
                'appId',
                InputArgument::REQUIRED,
                'App ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appId = $input->getArgument("appId");
        $app = ZBPWrapper::loadApp($appId);
        ZBPWrapper::enablePlugin();
        return 0;
    }
}
