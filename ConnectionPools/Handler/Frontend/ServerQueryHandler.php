<?php

namespace App\ConnectionPools\Handler\Frontend;

use App\ConnectionPools\Parser\ServerParse;

class ServerQueryHandler implements FrontendQueryHandler
{
    public function query(string $sql)
    {
        $rs = ServerParse::parse($sql);

        return $rs & 0xff;
    }
}
