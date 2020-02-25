<?php
/*
 * @Description: global function
 * @Author: czm
 * @Date: 2019-04-02 18:05:39
 * @LastEditTime: 2019-05-09 11:48:19
 */
namespace App\ConnectionPools\Common;

use App\ConnectionPools\MysqlPacket\Util\CharsetUtil;

/**
 * 初始化配置文件 后期更改为数据库查询
 *
 * @param string $dir
 *
 * @return array
 *
 * @throws Exception
 */
function initConfig(string $dir)
{
        $config = [];
        $dir = realpath($dir);
        if (!is_dir($dir)) {
            throw new \RuntimeException('Cannot find config dir.');
        }
        //匹配文件
        $paths = glob($dir . '/*.json');
        foreach ($paths as $path) {
            $item = json_decode(file_get_contents($path), true);
            if (is_array($item)) {
                $config = array_merge($config, $item);
            } else {
                throw new \InvalidArgumentException('Invalid config.');
            }
        }

        if (!isset($config['server']['host'])) {
            $config['server']['host'] = '0.0.0.0';
        }
        if (!isset($config['server']['port'])) {
            //获取
            $config['server']['port'] = 3366;
        }
        //计算worker_num
        $config['server']['swoole']['worker_num'] = swoole_cpu_num()*4;     
        
        if (!isset($config['server']['swoole']['task_worker_num'])) {
            $config['server']['swoole']['task_worker_num'] = swoole_cpu_num()*10;        
        }

        // if (!isset($config['server']['swoole']['reactor_num'])) {
        //     $config['server']['swoole']['reactor_num'] = swoole_cpu_num();        
        // }

        //非复杂场景切换base
        $config['server']['mode'] = SWOOLE_PROCESS;
        $config['server']['sock_type'] = SWOOLE_SOCK_TCP;
        $config['server']['swoole_client_sock_setting']['sock_type'] = SWOOLE_SOCK_TCP;
        

        //生成日志目录 改写权限0644
        if (isset($config['server']['logs']['config']['service']['log_path'])) {
            mk_log_dir($config['server']['logs']['config']['service']['log_path']);
        } else {
            throw new \InvalidArgumentException('配置错误:logs.config.service.log_path 配置项不存在!');
        }
        if (isset($config['server']['logs']['config']['mysql']['log_path'])) {
            mk_log_dir($config['server']['logs']['config']['mysql']['log_path']);
        } else {
            throw new \InvalidArgumentException('配置错误:logs.config.mysql.log_path 配置项不存在!');
        }
        if (isset($config['server']['swoole']['log_file'])) {
            mk_log_dir($config['server']['swoole']['log_file']);
        } else {
            throw new \InvalidArgumentException('配置错误:swoole.log_file 配置项不存在!');
        }
        if (isset($config['server']['swoole']['pid_file'])) {
            mk_log_dir($config['server']['swoole']['pid_file']);
        } else {
            throw new \InvalidArgumentException('配置错误:swoole.pid_file 配置项不存在!');
        }

        return $config;
    }

    /**
     * 创建日志目录.
     *
     * @param string $path 
     */
    function mk_log_dir(string &$path)
    {
        $path = str_replace('ROOT', ROOT, $path);
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0644, true);
        }
    }

    
    /**
     * 无符号16位右移.
     *
     * @param int $x    要进行操作的数字
     * @param int $bits 右移位数
     *
     * @return int
     */
    function shr16(int $x, int $bits)
    {
        return ((2147483647 >> ($bits - 1)) & ($x >> $bits)) > 255 ? 255 : ((2147483647 >> ($bits - 1)) & ($x >> $bits));
    }

    /**
     * 获取 string.
     *
     * @param array $bytes
     *
     * @return string
     */
    function getString(array $bytes)
    {
        return implode(array_map('chr', $bytes));
    }

/**
 * 处理粘包问题.
 *
 * @param string $data
 * @param bool $auth
 * @param int $headerLength 是否认证通过
 * @param bool $isClient 是否是客户端
 * @param string $halfPack 半包体
 *
 * @return array
 */
function packageSplit(string $data, bool $auth, int $headerLength = 4, bool $isClient = false, string &$halfPack = '')
{
    if ($halfPack !== '') {
        $data = $halfPack . $data;
    }
    $dataLen = strlen($data);
    if ($headerLength == 3) {
        $dataLen -= 1;
    }
    if ($dataLen < 4) {
        return [];
    }
    $packageLen = getPackageLength($data, 0, $headerLength);
    if ($dataLen == $packageLen) {
        $halfPack = '';
        return [$data];
    } elseif ($dataLen < $packageLen) {
        $halfPack = $data;
        return [];
    } else {
        $halfPack = '';
    }
    $packages = [];
    $split = function ($data, &$packages, $step = 0) use (&$split, $headerLength, $isClient) {
        if (isset($data[$step]) && 0 != ord($data[$step])) {
            $packageLength = getPackageLength($data, $step, $headerLength);
            if ($isClient) {
                $packageLength ++;
            }
            $packages[] = substr($data, $step, $packageLength);
            $split($data, $packages, $step + $packageLength);
        }
    };
    if ($auth) {
        $split($data, $packages);
    } else {
        $packageLength = getPackageLength($data, 0, 3) + 1;
        $packages[] = substr($data, 0, $packageLength);
        if (isset($data[$packageLength]) && 0 != ord($data[$packageLength])) {
            $split($data, $packages, $packageLength);
        }
    }

    return $packages;
}


/**
 * 获取包长
 *
 * @param string $data
 * @param int    $step
 * @param int    $offset
 *
 * @return int
 */
function getPackageLength(string $data, int $step, int $offset)
{
    $i = ord($data[$step]);
    $i |= ord($data[$step + 1]) << 8;
    $i |= ord($data[$step + 2]) << 16;
    if ($offset >= 4) {
        $i |= ord($data[$step + 3]) << 24;
    }

    return $i + $offset;
}
/**
 * 获取bytes 数组.
 *
 * @param $data
 *
 * @return array
 */
function getBytes(string $data)
{
    $bytes = [];
    $count = strlen($data);
    for ($i = 0; $i < $count; ++$i) {
        $byte = ord($data[$i]);
        $bytes[] = $byte;
    }

    return $bytes;
}

/**
 * 数组复制.
 *
 * @param $array
 * @param $start
 * @param $len
 *
 * @return array
 */
function array_copy(array $array, int $start, int $len)
{
    return array_slice($array, $start, $len);
}

/**
 * 对数据进行编码转换.
 *
 * @param array/string $data   数组
 * @param string $output 转换后的编码
 *
 * @return array|null|string|string[]
 */
function array_iconv($data, string $output = 'utf-8')
{
    $output = CharsetUtil::charsetToEncoding($output);
    $encode_arr = ['UTF-8', 'ASCII', 'GBK', 'GB2312', 'BIG5', 'JIS', 'eucjp-win', 'sjis-win', 'EUC-JP'];
    $encoded = mb_detect_encoding($data, $encode_arr);

    if (!is_array($data)) {
        return mb_convert_encoding($data, $output, $encoded);
    } else {
        foreach ($data as $key => $val) {
            $key = array_iconv($key, $output);
            if (is_array($val)) {
                $data[$key] = array_iconv($val, $output);
            } else {
                $data[$key] = mb_convert_encoding($data, $output, $encoded);
            }
        }

        return $data;
    }

    
}
/**
 * 转换长度.
 *
 * @param int $size
 * @param int $length
 *
 * @return array
 */
function getMysqlPackSize(int $size, int $length = 3)
{
    $sizeData[] = $size & 0xff;
    $sizeData[] = shr16($size & 0xff << 8, 8);
    $sizeData[] = shr16($size & 0xff << 16, 16);
    if ($length > 3) {
        $sizeData[] = shr16($size & 0xff << 24, 24);
    }
    return $sizeData;
}

function startsWith($haystack, $needle)
{
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle)
{
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}


function curl($curlRequest, $timeout = 10){

    if (isset(CONFIG['server']['logs']['http_url'])) {
        $url=CONFIG['server']['logs']['http_url'].$curlRequest['route'];
    } else {
        throw new \InvalidArgumentException('配置错误:http_url错误!');
    }

    $params=$curlRequest['params'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    // 要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);//超时限制
    switch ($curlRequest['method']){
        case "GET" :
         $url=$url.'?'.http_build_query($params);
         curl_setopt($ch, CURLOPT_HTTPGET, true);break;
        case "POST": curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));break;
        case "FILE": curl_setopt($ch, CURLOPT_POST,true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    // 要求结果为字符串且输出到屏幕上
                curl_setopt($ch, CURLOPT_POST,1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            break;
        case "PUT" : curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));break;
        case "PATCH": curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));break;
        case "DELETE":curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));break;
        default:curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));break;
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    $output = curl_exec($ch);
    // $open=fopen(__DIR__."/log.txt",'w+');
    // fwrite($open, json_encode($_SERVER));
    // fclose($open);
    $httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $output;
}
