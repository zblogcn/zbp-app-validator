<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2019/1/22
 * Time: 2:47 PM
 */
namespace Zsxsoft\AppValidator\Commands\App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Tasks\ScanGlobalVariables;
use Zsxsoft\AppValidator\Wrappers\AppCenterWrapper;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class Login extends Command
{

    protected function configure()
    {
        $this
            ->setName('app:login')
            ->setDescription('Login AppCenter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        AppCenterWrapper::login();
        return 0;
    }
}
