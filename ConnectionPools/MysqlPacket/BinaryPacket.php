<?php

namespace App\ConnectionPools\MysqlPacket;



/**
 * MySql包 外层结构.
 *
 * @Author Louis Livi <574747417@qq.com>
 */
class BinaryPacket extends MySQLPacket
{
    public static $OK = 1;
    public static $ERROR = 2;
    public static $HEADER = 3;
    public static $FIELD = 4;
    public static $FIELD_EOF = 5;
    public static $ROW = 6;
    public static $PACKET_EOF = 7;
    public $data;

    public function calcPacketSize()
    {
        return null == $this->data ? 0 : count($this->data);
    }

    protected function getPacketInfo()
    {
        return 'MySQL Binary Packet';
    }
}
