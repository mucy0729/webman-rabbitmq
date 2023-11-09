<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanRabbitMQBuilder extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-builder';
    protected static $defaultDescription = 'Create and initialize a workbunny/webman-rabbitmq Builder. ';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'builder name');
        $this->addArgument('count', InputArgument::REQUIRED, 'builder count');
        $this->addOption('delayed', 'd', InputOption::VALUE_NONE, 'Delayed mode');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $count = $input->getArgument('count');
        $delayed = $input->getOption('delayed');

        list($name, $namespace, $file) = $this->getFileInfo($name, $delayed);

        $this->initBuilder($name, $namespace, (int)$count, $file, $output);

        return self::SUCCESS;
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param int $count
     * @param string $file
     * @param OutputInterface $output
     * @return void
     */
    protected function initBuilder(string $name, string $namespace, int $count, string $file, OutputInterface $output)
    {
        if(file_exists($process = config_path() . '/plugin/workbunny/webman-rabbitmq/process.php')){
            $processConfig = file_get_contents($process);
            $config = config('plugin.workbunny.webman-rabbitmq.process', []);
            $processName = str_replace('\\', '.', $className = "$namespace\\$name");

            if(!isset($config[$processName])){
                file_put_contents($process, preg_replace_callback('/(];)(?!.*\1)/',
                    function () use ($processName, $className, $count){
                        return <<<EOF
    '$processName' => [
        'handler' => \\$className::class,
        'count'   => $count
    ],
];
EOF;
                    }, $processConfig,1));

                $this->createBuilder($name, $namespace, $file);
                $output->writeln("<info>Builder {$name} created successfully. </info>");
                return;
            }
            $output->writeln("<error>Builder {$name} failed to create: Config already exists. </error>");
            return;
        }
        $output->writeln("<error>Builder {$name} failed to create: plugin/workbunny/webman-rabbitmq/process.php does not exist. </error>");
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param string $file
     * @return void
     */
    protected function createBuilder(string $name, string $namespace, string $file)
    {
        $delayed = (substr($name, -strlen('Delayed')) === 'Delayed')
            ? 'protected bool $delayed = true;'
            : 'protected bool $delayed = false;';

        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $command_content = <<<doc
<?php
declare(strict_types=1);

namespace $namespace;

use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\FastBuilder;

class $name extends FastBuilder
{
    // QOS 大小
    protected int \$prefetch_size = 0;
    // QOS 数量
    protected int \$prefetch_count = 0;
    // QOS 是否全局
    protected bool \$is_global = false;
    // 是否延迟队列
    $delayed
    // 消费回调
    public function handler(BunnyMessage \$message, BunnyChannel \$channel, BunnyClient \$client): string
    {
        // TODO 消费需要的回调逻辑
        var_dump('请重写 $name::handler() ');
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}
doc;
        if(!file_exists($file)){
            file_put_contents($file, $command_content);
        }
    }

}
