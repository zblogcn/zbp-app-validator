<?php
/**
 * Created by PhpStorm.
 * User: sx
 * Date: 2017/10/23
 * Time: 19:39
 */

namespace Zsxsoft\AppValidator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zsxsoft\AppValidator\Helpers\Logger;
use Zsxsoft\AppValidator\Helpers\TempHelper;
use Zsxsoft\AppValidator\Tasks\RunBrowser as Task;
use Zsxsoft\AppValidator\Wrappers\ZBPWrapper;

class RunBrowser extends Command
{

    protected function configure()
    {
        $this
            ->setName('browser')
            ->setDescription('Run the browser to scan JavaScript errors or take screenshots');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new Task())->run();
        return 0;
    }
}