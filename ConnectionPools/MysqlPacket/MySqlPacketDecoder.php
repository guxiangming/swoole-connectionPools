<?php

namespace App\ConnectionPools\MysqlPacket;

use function App\ConnectionPools\Common\getBytes;
use App\ConnectionPools\Log\Log;
use App\ConnectionPools\MysqlPacket\Util\ByteUtil;
use App\ConnectionPools\App\ConnectionPoolsException;


class MySqlPacketDecoder
{
    private $packetHeaderSize = 4;
    private $maxPacketSize = 16777216;

    /**
     * MySql外层结构解包.
     *
     * @param string $data
     *
     * @return \App\ConnectionPools\MysqlPacket\BinaryPacket
     * @throws \App\ConnectionPools\App\ConnectionPoolsException
     */
    public function decode(string $data)
    {
        $data = getBytes($data);
        // 4 bytes:3 length + 1 packetId
        if (count($data) < $this->packetHeaderSize) {
            throw new App\ConnectionPoolsException('Packet is empty');
        }
        $packetLength = ByteUtil::readUB3($data);
//        // 过载保护
        if ($packetLength > $this->maxPacketSize) {
            throw new App\ConnectionPoolsException('Packet size over the limit ' . $this->maxPacketSize);
        }
        $packetId = $data[3];
//        if (in.readableBytes() < packetLength) {
//            // 半包回溯
//            in.resetReaderIndex();
//            return;
//        }
        $packet = new BinaryPacket();
        $packet->packetLength = $packetLength;
        $packet->packetId = $packetId;
        // data will not be accessed any more,so we can use this array safely
        $packet->data = $data;
        if (null == $packet->data || 0 == count($packet->data)) {
            throw new App\ConnectionPoolsException('get data errorMessage,packetLength=' . $packet->packetLength);
        }

        return $packet;
    }
}
