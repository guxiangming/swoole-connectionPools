<?php

namespace App\ConnectionPools;

use Swoole\Coroutine;

/*
 * @Description: 连接池状态管理
 * @Author: czm
 * @Date: 2019-04-28 15:19:23
 */
class Pools
{
    protected static $pools=[];

    public static function get(string $key){
        $cid=Coroutine::getuid();
        //-1 不在协程环境
        if($cid<0){
            return null;
        }
        if(isset(self::$pools[$cid][$key])){
            return self::$pools[$cid][$key];
        }
        return null;
    }

    public static function set(string $key,$item){
        $cid=Coroutine::getuid();
        //-1 不在协程环境
        if($cid>0){
            self::$pools[$cid][$key]=$item;
            return true;
        }

        return false;
    }

    public static function delete(string $key=null){
        $cid=Coroutine::getuid();
        if($cid&&$key){
            unset(self::$pools[$cid][$key]);
        }
        return false;
    }
}