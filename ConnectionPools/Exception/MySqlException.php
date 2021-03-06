<?php

namespace App\ConnectionPools\Exception;
use Exception;
class MySqlException extends Exception
{
    public function __construct($message = null, Exception $previous = null, $code = 0)
    {
        $this->message=$message;
        // parent::__construct(400, $message ?: 'The version given was unknown or has no registered routes.', $previous, [], $code);
    }

    public function errorMessage()
    {
        $sprintf=sprintf('%s (%s:%s)', trim($this->getMessage()), $this->getFile(), $this->getLine());
        Log::fileWrite($sprintf,'mysql');
        return $sprintf;
    }

    public function render()
    {
        return $this->message;
    }
}
