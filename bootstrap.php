<?php
    require_once __DIR__ . '/vendor/autoload.php';    
    use App\ConnectionPools\Log\Log;

    define('ROOT', realpath(__DIR__ ));
    //数据库界限
    define('DB_DELIMITER', 'CP');
    
    set_error_handler('error_handler', E_ALL | E_STRICT);
    /**
     * 处理异常上报
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    function error_handler(int $errno, string $errstr, string $errfile, int $errline)
    {
        ob_start();
        echo "errno: [$errno]".PHP_EOL;
        echo "errstr: $errstr".PHP_EOL;
        echo "Error on line $errline in $errfile".PHP_EOL;
        $log=ob_get_clean();
        Log::fileWrite($log);
    }
    //check php and swoole version
    if(version_compare(PHP_VERSION,'7.0','<')&&extension_loaded('soole')){
        ob_start();
        echo 'version error';
        $log=ob_get_clean();
        Log::fileWrite($log);
    }
    //start pools php start --help
    (new \App\ConnectionPools\Command\Command())->run($argv);
