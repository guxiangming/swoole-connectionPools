<?php

namespace App\ConnectionPools\Handler\Frontend;


interface FrontendQueryHandler
{
    public function query(string $sql);
}
