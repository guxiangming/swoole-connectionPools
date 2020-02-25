<?php

namespace App\ConnectionPools\Exception;
use Exception;
use App\ConnectionPools\Log\Log;
class ConnectionPoolsException extends Exception
{
    public function __construct($message = null, Exception $previous = null, $code = 0)
    {
        $this->message=$message;
        // parent::__construct(400, $message ?: 'The version given was unknown or has no registered routes.', $previous, [], $code);
    }

    public function errorMessage()
    {
 
        $sprintf=sprintf('%s (%s:%s)', trim($this->getMessage()), $this->getFile(), $this->getLine());
        Log::fileWrite($sprintf);
        return $sprintf;
    }

    
}
