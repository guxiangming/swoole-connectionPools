<?php
namespace App\ConnectionPools;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine;

use \App\ConnectionPools\Log\Log; 
use \App\ConnectionPools\Exception\ConnectionPoolsException;

abstract class BasePoolsServer extends BasePools
    {
        protected $host;
        protected $port;
        protected $mode;
        protected $sock_type;
        protected $set=[];
        protected $server;

        protected $connectReadState = [];
        protected $connectHasTransaction = [];
        protected $connectHasAutoCommit = [];
        //创建swoole 进程
        public function __construct()
        {
            try {
                $this->host=CONFIG['server']['host'];
                //多进程监听
                $this->port=explode(',',CONFIG['server']['port']);
                $this->mode=CONFIG['server']['mode'];
                $this->sock_type=CONFIG['server']['sock_type'];
                //配置检验        
                $this->set=CONFIG['server']['swoole'];            
            } catch (ConnectionPoolsException $exception) {
                Log::fileWrite("BasePoolsServer-__construst[检验配置信息]");
                throw new ConnectionPoolsException('config [swoole] is not found !');
            }   
            
            //暴露server对象 做异步投递
            $this->server=new \swoole_server(
                $this->host,
                $this->port[0],
                $this->mode,
                $this->sock_type
            );
            if (count($this->port) > 1) {
                for ($i = 1; $i < count($this->port); ++$i) {
                    $this->server->addListener(
                        $this->host,
                        $this->port[$i],
                        $this->sock_type
                    );
                }
            }
            
            $this->server->set($this->set);
            $this->server->on('connect', [$this, 'onConnect']);
            $this->server->on('receive', [$this, 'onReceive']);
            $this->server->on('close', [$this, 'onClose']);
            $this->server->on('task', [$this, 'onTask']);
            $this->server->on('start', [$this, 'onStart']);
            $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
            $this->server->on('ManagerStart', [$this, 'onManagerStart']);

            $result = $this->server->start();
            // dump($result);exit;
            if ($result) {
                Log::fileWrite("BasePoolsServer-__construst[swoole start]");
            } else {
                
                Log::fileWrite("BasePoolsServer-__construst[swoole 启动失败]");
                throw new ConnectionPoolsException("ERROR: Server start failed!");
            }
            
        }

        protected function onConnect(\swoole_server $server,int $fd,int $reactorId){
        }

        protected function onReceiver(\swoole_server $server,int $fd,int $reactor_Id,string $data){

        }

        public function onWorkerStart(\swoole_server $server, int $worker_id){}

        //不得执行其他操作 只记录记录主进程ID 进程管理ID
        public function onStart(\swoole_server $server){
            // dump(1);exit;
            \file_put_contents(CONFIG['server']['swoole']['pid_file'],$server->master_pid.','.$server->manager_pid);
            Log::fileWrite("BasePoolsServer-onStart");
        }
        /**
         * 进程管理
         */
        public function onManagerStart(\swoole_server $server){
            Log::fileWrite("BasePoolsServer-onManagerStart");
        }

        public function onTask(){}

        /**
         * 客户端关闭 销毁连接池
         */
        protected function onClose(\swoole_server $server,int $fd){
            $cid=Coroutine::getuid();
            if($cid&&isset(self::$pools[$cid])){
                unset(self::$pools[$cid]);
            }

        }
    
    }