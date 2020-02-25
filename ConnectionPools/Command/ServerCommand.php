<?php


namespace App\ConnectionPools\Command;
use \App\ConnectionPools\Exception\ConnectionPoolsException;
use \App\ConnectionPools\Log\Log;
class ServerCommand
{
    public $logo;
    public $desc;
    public $usage;
    public $serverSetting = [];

    public function __construct()
    {
        $this->logo  = "HTMS-CONNECTION-POOLS". PHP_EOL ;
        $this->desc  = $this->logo .": Solving database connection overhead developed in 2019";
        $this->usage = $this->logo .":|start|stop|restart|status|reload|only use mysql More support please look forward to ";
    }

    /**
     * 启动服务
     *
     * @throws \ErrorException
     */
    public function start()
    {
        // 是否正在运行
        if ($this->isRunning()) {
            throw new ConnectionPoolsException("The server have been running! (PID: {$this->serverSetting['masterPid']})");
        }
        echo $this->logo, PHP_EOL;
        echo 'Server starting ...', PHP_EOL;
        Log::fileWrite("Command-ServerCommand-start");
        //启动服务
        new \App\ConnectionPools\ConnectionServer();
    }

    /**
     * 停止服务 常常失效
     */
    public function stop()
    {
        if (!$this->isRunning()) {
            throw new ConnectionPoolsException("ERROR: The server is not running! cannot stop!");
        }

        echo 'Connection is stopping ...', PHP_EOL;
        Log::fileWrite("Command-ServerCommand-stop");
        $result = function () {
            // 获取master进程ID
            $masterPid = $this->serverSetting['masterPid'];
            // 使用swoole_process::kill代替posix_kill
            \swoole_process::kill($masterPid);
            //设定十秒 检测
            $timeout = 10;
            $startTime = time();
            while (true) {
                // Check the process status
                if (\swoole_process::kill($masterPid, 0)) {
                    // 判断是否超时
                    if (time() - $startTime >= $timeout) {
                        return false;
                    }
                    //10毫秒再检测
                    usleep(10000);
                    continue;
                }

                return true;
            }
        };

        // 停止失败
        if (!$result()) {
            throw new ConnectionPoolsException('Connection shutting down failed!');
        }
        // 删除pid文件
        @unlink(CONFIG['server']['swoole']['pid_file']);
        echo 'Connection has been shutting down.', PHP_EOL;
        Log::fileWrite("Command-ServerCommand-stop['Connection has been shutting down']");
    }

    /**
     * 重启服务
     */
    public function restart()
    {
        Log::fileWrite("Command-ServerCommand-restart");
        // 是否已启动
        if ($this->isRunning()) {
            $this->stop();
        }
        $this->start();
    }

    /**
     * 平滑重启 刷新连接池5-16 待完善
     */
    public function reload()
    {
        // 是否已启动
        if (!$this->isRunning()) {
            echo 'The server is not running! cannot reload', PHP_EOL;
            return;
        }

        echo 'Server is reloading...', PHP_EOL;
        posix_kill($this->serverSetting['managerPid'], SIGUSR1);
        echo 'Server reload success', PHP_EOL;
        Log::fileWrite("Command-ServerCommand-reload");
    }

    /**
     * 服务状态
     */
    public function status()
    {
        // 是否已启动
        if ($this->isRunning()) {
            echo 'The server is running', PHP_EOL;
        } else {
            echo 'The server is not running', PHP_EOL;
        }
    }

    /**
     * 判断服务是否在运行中.
     *
     * @return bool
     */
    public function isRunning()
    {
        $masterIsLive = false;
        $pFile = CONFIG['server']['swoole']['pid_file'];
        // 判断pid文件是否存在
        if (file_exists($pFile)) {
            // 获取pid文件内容
            $pidFile = file_get_contents($pFile);
            $pids = explode(',', $pidFile);
            $this->serverSetting['masterPid'] = $pids[0];
            $this->serverSetting['managerPid'] = $pids[1];
            //检测主控进程是否存在
            $masterIsLive = $this->serverSetting['masterPid'] && @posix_kill($this->serverSetting['managerPid'], 0);
        }
        return $masterIsLive;
    }
}
