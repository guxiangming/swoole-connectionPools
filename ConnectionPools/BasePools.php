<?php

namespace App\ConnectionPools;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine;
use \App\ConnectionPools\Log\Log; 
use function App\ConnectionPools\Common\array_iconv;
use \App\ConnectionPools\Exception\ConnectionPoolsException;
use \App\ConnectionPools\MysqlPool\MySQLException;
use \App\ConnectionPools\MysqlPacket\ErrorPacket;

class BasePools extends Pools
{
    /**
     * 携程执行处理异常.
     * @param $function
     */
    protected static function go(\Closure $function){
        $cid=Coroutine::getuid();
        if (-1 !== $cid) {
            $pools = self::$pools[$cid] ?? false;
        } else {
            $pools = false;
        }
        go(function () use ($cid,$function,$pools) {
            try {
                if ($pools) {
                    self::$pools[$cid] = $pools;
                }
                $function();
                if ($pools) {
                    unset(self::$pools[$cid]);
                }
            } catch (\Exception $e) {
                throw new ConnectionPoolsException($e);
            } 
        });
    }

    /**
     * 格式化配置项
     * @param array $config
     * @return array
     * @throws 
     */
    public function parseDbConfig(array $config)
    {
        
        $config = $config['database'] ?? [];
        //遍历DB信息
        foreach ($config['databases'] as $key => $database) {
            //获取对应数据库配置
            if (isset($config['serverInfo'][$database['serverInfo']])) {        
                //遍历所有数据库配置 serverInfo
                foreach ($config['serverInfo'][$database['serverInfo']] as $s_key => $value) {

                    if (isset($config['account'][$value['account']])) {
                        //分布式解析读取 暂时只能获取其中一个 4-28
                        $host = &$config['serverInfo'][$database['serverInfo']][$s_key]['host'];
                        if (is_array($host)) {
                            $host = $host[array_rand($host)];
                        }
                        //根据DB dbname 匹配出 账户名、 连接数等信息
                        if (!isset($config['databases'][$s_key])) {
                            $config['databases'][$s_key] = $config['databases'][$key];
                            $config['databases'][$s_key]['serverInfo'] =
                                $config['serverInfo'][$database['serverInfo']][$s_key];
                            $config['databases'][$s_key]['serverInfo']['account'] =
                                $config['account'][$value['account']];
                        }
                        //重组mysql配置信息
                        $config['databases'][$s_key.DB_DELIMITER.$key] = $config['databases'][$key];
                        $config['databases'][$s_key.DB_DELIMITER.$key]['serverInfo'] =$config['serverInfo'][$database['serverInfo']][$s_key];
                        $config['databases'][$s_key.DB_DELIMITER.$key]['serverInfo']['account'] =$config['account'][$value['account']];
                    } else {
                        throw new ConnectionPoolsException('Config serverInfo->' . $s_key . '->account is not exists!');
                    }
                }

            } else {
                throw new ConnectionPoolsException('Config serverInfo key ' . $database['serverInfo'] . 'is not exists!');
            }
            //去除冗余配置
            unset($config['databases'][$key]);
        }
        return $config['databases'];
    }
    /**
     * 协程pop
     *
     * @param $chan
     * @param int $timeout
     *
     * @return bool
     */
    protected static function coPop(Channel $chan, int $timeout = 0)
    {
        if (version_compare(swoole_version(), '4.0.3', '>=')) {
            return $chan->pop($timeout);
        } else {
            if (0 == $timeout) {
                return $chan->pop();
            } else {
                $writes = [];
                $reads = [$chan];
                $result = $chan->select($reads, $writes, $timeout);
                if (false === $result || empty($reads)) {
                    return false;
                }
                $readChannel = $reads[0];
                return $readChannel->pop();
            }
        }
    }

    protected static function writeErrMessage(int $id, string $msg, int $errno = 0, $sqlState = 'HY000')
    {
        $err = new ErrorPacket();
        $err->packetId = $id;
        if ($errno) {
            $err->errno = $errno;
        }
        $err->sqlState = $sqlState;
        $err->message  = array_iconv($msg);

        return $err->write();
    }
}