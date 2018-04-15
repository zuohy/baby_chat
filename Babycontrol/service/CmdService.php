<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace service;

use service\HttpService;

/**
 * websocket 请求服务
 * Class WebsocketService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class CmdService
{
    private static $_host = '';
    private static $_port = '';
    private static $_path = '';
    private static $_origin = false;
    private static $_connected = false;
    private static $_client = null;

    //设备控制命令参数
    private static $_cmdParams = array();
    private static $_cmdLayout = array(
    'id' => '',
    'method'  =>'',
    'params'  =>''
    );

    private static $cmdRet = array(
        'id' => '',
        'code'  =>'',
        'msg'  =>''
    );

    //web服务器api 地址
    private static $webServer = 'http://test.hqscs.com/wawa_web/index.php/phone/apiwawa/index';
    //设备服务器 地址
    private static $devServer = 'http://api.open.wowgotcha.com/openapi/v1/websocket_url/?appid=wow04d608ed68hk73092za1&binding_id=3&room_id=3';
/*
    public function __construct() { }

    public function __destruct()
    {
        $this->disconnect();
    }
*/
    private static function initCmd(){
        self::$_cmdLayout['id'] = '';
        self::$_cmdLayout['method'] = '';
        self::$_cmdLayout['params'] = '';

        //cmd params
        self::$_cmdParams = array();

        //ret msg
        self::$cmdRet['id'] = '';
        self::$cmdRet['code'] = '';
        self::$cmdRet['msg'] = '';
    }

    private static function buildCmd($cmd_method, $cmd_param=''){
        self::initCmd();


        switch($cmd_method){
            case 'insert_coins':
                self::$_cmdParams['out_trade_no'] = $cmd_param;

                self::$_cmdLayout['id'] = '123456';
                self::$_cmdLayout['method'] = 'insert_coins';
                self::$_cmdLayout['params'] = self::$_cmdParams;
                break;
            case 'control':
                self::$_cmdParams['operation'] = $cmd_param;

                self::$_cmdLayout['id'] = '123457';
                self::$_cmdLayout['method'] = 'control';
                self::$_cmdLayout['params'] = self::$_cmdParams;
                break;

            default:
                break;
        }

        $jsonCmd = json_encode(self::$_cmdLayout);
        return $jsonCmd;
    }


    /**
     * getWsUrl  获取 设备服务器websocket url 地址 60秒有效
     * @param string $host url 地址
     * @param string $port 命令参数
     * @param string $path 命令参数类型
     * @return bool|string
     */
    public static function getWsUrl($host, $port, $path, $origin = false)
    {

        self::$_port = $port;
        self::$_path = $path;
        self::$_origin = $origin;

        $jsonRet = HttpService::get(self::$devServer, [], 30, []);
        $stRet = json_decode($jsonRet);

        if($stRet->errcode != 0){

            return self::$_connected = false;
        }
        $stData = $stRet->data;
        $wsUrl = $stData->ws_url;

        self::$_host = $wsUrl;
        //"ws://ws1.open.wowgotcha.com:9090/play/7996d3e8ad7483d8d6c6d3475cd49265549a6430"

        self::$_connected = true;
        return self::$_host;
    }

    /**
     * getCmdCoins  获取投币命令结构
     * @param string $host url 地址

     * @return bool|string
     */
    public static function getCmdCoins($data)
    {

        $wsCmd = self::buildCmd('insert_coins', $data);
        return $wsCmd;
    }

    /**
     * getCmdControl  获取控制命令结构
     * @param string $host url 地址

     * @return bool|string
     */
    public static function getCmdControl($data)
    {

        $wsCmd = self::buildCmd('control', $data);
        return $wsCmd;
    }


    /**
     * userAuth  认证用户有效性
     * @param string $roomId
     * @param string $userId
     * @param string $price
     * @param string $key
     * @return bool|string
     */
    public static function userAuth($roomId, $userId, $price, $key='')
    {
        $webCmd = array(
            'type' => 'dev_user_auth',
            'user_id' => $userId,
            'room_id' => $roomId,
            'price' => $price,

        );

        $sendData = array(
            'json' => $webCmd
        );

        $jsonRet = HttpService::post(self::$webServer, $sendData);

        return $jsonRet;
    }

    /**
     * notifyCoins  通知web 服务器 设备投币成功或失败
     * @param string $roomId
     * @param string $userId
     * @param string $status   //投币状态
     * @param string $price   //投币价格
     * @param string $key
     * @return bool|string
     */
    public static function notifyCoins($roomId, $userId, $status, $price)
    {
        $webCmd = array(
            'type' => 'dev_notify_coins',
            'status' => $status,
            'user_id' => $userId,
            'room_id' => $roomId,
            'price' => $price,

        );

        $sendData = array(
            'json' => $webCmd
        );

        $jsonRet = HttpService::post(self::$webServer, $sendData);

        return $jsonRet;
    }

    /**
     * notifyResult  通知web 服务器 抓取结果
     * @param string $roomId
     * @param string $userId
     * @param string $isCatch   //抓取结果
     * @param string $orderId   //消费订单ID
     * @return bool|string
     */
    public static function notifyResult($roomId, $userId, $isCatch, $orderId)
    {
        $webCmd = array(
            'type' => 'dev_notify_result',
            'is_catch' => $isCatch,
            'user_id' => $userId,
            'room_id' => $roomId,
            'order_id' => $orderId
        );

        $sendData = array(
            'json' => $webCmd
        );

        $jsonRet = HttpService::post(self::$webServer, $sendData);

        return $jsonRet;
    }

    public static function checkConnection()
    {

        return self::$_connected;
    }


    public static function disconnect()
    {
        self::$_connected = false;
        //close web socket
    }


    private function _generateRandomString($length = 10, $addSpaces = true, $addNumbers = true)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"??$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for($i = 0; $i < $length; $i++)
        {
            $useChars[] = $characters[mt_rand(0, strlen($characters)-1)];
        }
        // add spaces and numbers:
        if($addSpaces === true)
        {
            array_push($useChars, ' ', ' ', ' ', ' ', ' ', ' ');
        }
        if($addNumbers === true)
        {
            array_push($useChars, rand(0,9), rand(0,9), rand(0,9));
        }
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, $length);
        return $randomString;
    }


}


