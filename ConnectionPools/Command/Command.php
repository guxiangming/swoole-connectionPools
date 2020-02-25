<?php



namespace App\ConnectionPools\Command;

use \App\ConnectionPools\Log\Log;
use  function \App\ConnectionPools\Common\initConfig;
use \App\ConnectionPools\Exception\ConnectionPoolsException;

class Command
{
    //默认配置位置
    private $configPath=__DIR__.'/../Conf/';
    
    /**
     * 运行
     *
     * @param array $argv
     *
     * @throws \Connection\ConnectionException
     */
    public function run(array $argv)
    {
        $command = count($argv) >= 2 ? $argv[1] : false;
        //配置获取
        $this ->settingConfig($argv);
        //解析指令
        $this ->commandHandler($command);
    }

    /**
     * 设置配置文件 -c
     *
     * @param array $argv
     *
     * @throws \Connection\ConnectionException
     */
    protected function settingConfig(array $argv)
    {
        $configPath =$this->configPath;
        //指定配置文件
        $configKey  = array_search('-c', $argv) ?: array_search('--config', $argv);
        if ($configKey) {
            if (!isset($argv[$configKey + 1])) {
                throw new ConnectionPoolsException('ERROR: config配置参数错误!');
            }
            $configPath = $argv[$configKey + 1];
        }
        if (file_exists($configPath)) {
            define('CONFIG_PATH', realpath($configPath));
            // 后期切换数据库查询
            define('CONFIG', initConfig(CONFIG_PATH));
        } else {
            throw new ConnectionPoolsException('ERROR: ' . $configPath . ' No such file or directory!');
        }
    }

    /**
     * 处理命令
     *
     * @param string $command
     */
    protected function commandHandler(string $command)
    {
        $serverCommand = new ServerCommand();

        if ('-h' == $command || '--help' == $command) {
            echo $serverCommand->desc, PHP_EOL;
            return;
        }

        if ('-v' == $command || '--version' == $command) {
            echo $serverCommand->logo, PHP_EOL;
            return;
        }

        if (!$command || !method_exists($serverCommand, $command)) {
            echo $serverCommand->usage, PHP_EOL;
            return;
        }
        Log::fileWrite("Command-Command-commandHandler");
        // PhpHelper::call([$serverCommand, $command]);
        if (version_compare(SWOOLE_VERSION, '4.0', '>=')) {
            // dump($serverCommand);exit;
            $ret = call_user_func_array([$serverCommand,$command], []);
        } else {
            $ret = \Swoole\Coroutine::call_user_func_array([$serverCommand,$command], []);
        }
        return $ret;
    }
}
