<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/23
 * Time: 19:39
 */

namespace Zsxsoft\AppValidator\Commands\Scanner;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Tasks\ValidateW3C as Task;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class ValidateW3C extends Command
{

    protected function configure()
    {
        $this
            ->setName('scan:w3c')
            ->setDescription('Validate W3C Standard for theme');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // $appId = $input->getArgument("appId");
        // $app = ZBPWrapper::loadApp($appId);
        // if ($app->type !== 'theme') return;
        (new Task())->run();
        return 0;
    }
}