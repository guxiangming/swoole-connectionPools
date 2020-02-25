<?php
namespace App\ConnectionPools\Log;

use Swoole\Coroutine;
use function App\ConnectionPools\Common\curl;
/**
 * 日志类.
 */
class Log 
{
    
    protected $logPath;
    protected $logFile;

    public function __construct()
    {
        try{
            //mysql 日志
            $this->logPath_mysql = CONFIG['server']['logs']['config']['mysql']['log_path'];
            $this->logFile_mysql = CONFIG['server']['logs']['config']['mysql']['log_file'];
            //system 日志
            $this->logPath_service=CONFIG['server']['logs']['config']['service']['log_path'];
            $this->logFile_service=CONFIG['server']['logs']['config']['service']['log_file'];
        }catch(\Throwable $t){
            throw new \InvalidArgumentException('日志参数配置错误！');
        }
    }
    /**
     * 写文件 上报文件  mysql.log system.log
     * @param string $messageText 文本信息
     */
    public static function fileWrite($messageText,$type='service')
    {
        //是否上传http 
        if(isset(CONFIG['server']['logs']['http_upload'])&&CONFIG['server']['logs']['http_upload']){
            (new self)->write($messageText,$type);
        }else{
            $logFile=(new self)->getLogFile($type);
            $prefix="[".date("Y-m-d H:i:s")."] ";
            $postfix=PHP_EOL;
            if (Coroutine::getuid() > 0) {
                    // 协程写
                    go(function () use ($logFile, $messageText,$prefix,$postfix) {
                
                    
                    $res = Coroutine::writeFile($logFile, $prefix.$messageText.$postfix, FILE_APPEND);
                    if ($res === false) {
                        throw new \InvalidArgumentException("Unable to append to log file: {$logFile}");
                    }
                    });
            } else {
                $fp = fopen($logFile, 'a');
                if ($fp === false) {
                    throw new \InvalidArgumentException("Unable to append to log file: {$logFile}");
                }
        
                fwrite($fp, $prefix.$messageText.$postfix);
                fclose($fp);
            }
        }
       
    }


    /**
     * 获取日志文件名称.
     *
     * @return string
     */
    private  function getLogFile($type)
    {
        if($type=='service'){
            $path=$this->logPath_service;
            $file=$this->logFile_service;
        }else if($type=='mysql'){
            $path=$this->logPath_mysql;
            $file=$this->logFile_mysql;
        }
        $path=$path.'/'.date("Ymd");
        if(!file_exists( $path)){

            mkdir( $path, 0644, true);
          
        }
        // 计算日志目录格式
        return sprintf('%s/%s', $path, $file);
    }

    //传入信息
    protected function write($messageText,$type)
    {
        $data = [
            'Instance'=>gethostname(),
            'ServerName' =>'ConnectionPools',
            'Message' => $type,
            'Context' => $messageText,
            'Channel' =>gethostname(),
            'Level' =>400,
            'LevelName' => "",
            'RemoteAddr' =>$_SERVER['HTTP_X_FORWARDED_FOR']??'检测代理服务', 
            'UserAgent' => $_SERVER['HTTP_USER_AGENT']??'no',
            'CreatedBy' => 'CP', 
            'CreatedAt' => date('Y-m-d H:i:s'),
            'UpdatedAt' => date('Y-m-d H:i:s'),
        ];
        //信息上报center
        curl(['method'=>'POST','params'=>['data'=>$data,'encrypt'=>md5('XMRuIAOOKRq8k6')],'route'=>'/api/log/report']);
    }

    


}
