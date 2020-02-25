<?php

namespace App\ConnectionPools\MysqlPool;


class MySQLException extends \Exception
{
    public function errorMessage()
    {
        $sprintf=sprintf('%s (%s:%s)', trim($this->getMessage()), $this->getFile(), $this->getLine());
        Log::fileWrite($sprintf,'mysql');
        return $sprintf;
    }
}
