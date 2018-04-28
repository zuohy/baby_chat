<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 * websocket client for control devices server
 */
use \Workerman\Worker;
use \Workerman\Autoloader;
use \Workerman\Connection\AsyncTcpConnection;
use service\CmdService;
use service\ErrorCode;
use service\RoomService;
use service\LogService;
use Workerman\Lib\Timer;

$g_to = '';
$g_con_id = '';      //http 链接id
$g_ws_obj = null;   //ws 链接对象
$retHttpMsg = array('code' => '0', 'type' => '', 'msg' => 'ok', 'data' => '');


// 证书
$context = array(
    'ssl' => array(
        'local_cert'  => '/usr/local/lnmp/nginx/conf/cert.pem', // 也可以是crt文件
        'local_pk'    => '/usr/local/lnmp/nginx/conf/cert.key',
        'verify_peer' => false,
    )
);
// WebSocket client
$httpServer = new Worker("http://0.0.0.0:2100", $context);
// 设置transport开启ssl，变成http+SSL即https
//$httpServer->transport = 'ssl';
// WebServer进程数量
$httpServer->count = 1;
// WebServer进程数量
$httpServer->name = 'BabyControl';

$httpServer->onWorkerStart = function ($con_id) {
    \Workerman\Worker::log('http server started');
    LogService::writeLog('info', 'http', '===server started===');
};
$httpServer->onMessage = function($con_id, $data){
    global $retHttpMsg;

    //$_POST = $_POST ? $_POST : $_GET;
    $jPack = @$_POST["json"] ? @$_POST["json"] : @$_GET["json"];
    LogService::writeLog('info', 'http', 'rev msg: ' . $jPack);

    if (get_magic_quotes_gpc()) {
        $jPack = stripcslashes($jPack);//如果开启了php转义，取消转义
    }

    $jsonObj = json_decode($jPack);
    if( (json_last_error() != JSON_ERROR_NONE)
        || $jPack == ''){
        $cmdType = '';
    }else{
        $cmdType = $jsonObj->type;
    }

    LogService::writeLog('info', 'http', 'msg type: ' . $cmdType);
    $retHttpMsg = _initRetMsg();
    $retHttpMsg['type'] = $cmdType;
    // 推送数据的url格式
    switch($cmdType) {
        case 'publish':
            //投币 建立链接
            global $g_to;
            global $g_con_id;
            global $g_ws_obj;
            global $retHttpMsg;

            $g_con_id = $con_id;  //http 链接对象
            $g_to = $jsonObj->to;
            $userId = isset($jsonObj->user_id) ? $jsonObj->user_id : '';
            $roomId = isset($jsonObj->room_id) ? $jsonObj->room_id : '';
            $devRoomId = isset($jsonObj->dev_room_id) ? $jsonObj->dev_room_id : '';
            $price = isset($jsonObj->price) ? $jsonObj->price : 0; //投币金额

            LogService::writeLog('info', 'http', '===publish message start===');
            LogService::writeLog('info', 'http', '|publish| user_id:' . $userId . ' room_id=' . $roomId . ' dev_room_id=' . $devRoomId);

            //认证用户，是否可以投币
            $retAuth = CmdService::userAuth($roomId, $userId, $price, $devRoomId, '');
            LogService::writeLog('info', 'web', 'user auth return=' . $retAuth);
            $retObj = json_decode($retAuth);
            $retCode = isset($retObj->code) ? $retObj->code : '';
            if($retCode != ErrorCode::CODE_OK){
                $retHttpMsg['code'] = $retCode;
                $retHttpMsg['msg'] = $retObj->msg;//ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, $retCode);
                LogService::writeLog('error', 'http', '|publish| userAuth failed');
                break;
            }

            //根据 房间id，获取设备appid用于建立链接

            //获取设备服务器ws 地址
            $devAddress = CmdService::getWsUrl('','',''); //暂时只有一台机器 appid 固定
            LogService::writeLog('info', 'ws', 'device address=' . $devAddress);

            //保存房间信息 和成员信息
            //设置互斥变量，避免并发链接导致dev_room_id 错误
            if(ErrorCode::CODE_OK != RoomService::$isWsConnected){
                $retHttpMsg['code'] = ErrorCode::E_NOT_SUPPORT;
                $retHttpMsg['msg'] = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, ErrorCode::E_NOT_SUPPORT);
                LogService::writeLog('error', 'http', '|publish| ===ws need to wait moment===');
                break;
            }

            //初始化房间设备信息 状态
            RoomService::devInitRoomStatus();
            RoomService::devSetRoomInfo($roomId, $userId, $price, $devRoomId);

            // 以websocket协议连接远程websocket服务器
            $ws_connection = new AsyncTcpConnection($devAddress);

            //设置ws 连接3秒超时机制 避免并发链接导致dev_room_id 错误
            RoomService::$isWsConnected = 1;
            $timePara = array($devRoomId);
            RoomService::$isWsConnected = Timer::add(3, function($devRoomId){
                Timer::del(RoomService::$isWsConnected);
                RoomService::$isWsConnected = ErrorCode::CODE_OK; //重置互斥变量
                LogService::writeLog('error', 'timer', 'ws connection timeout' . ' dev_room_id=' . $devRoomId);

            }, $timePara, false);

            // 连上后发送 投币命令
            $ws_connection->onConnect = function ($connection) {
                LogService::writeLog('info', 'ws', 'rev onConnect msg');
                global $g_to;
                global $g_ws_obj;
                $g_ws_obj = $connection;

                Timer::del(RoomService::$isWsConnected);
                RoomService::$isWsConnected = ErrorCode::CODE_OK; //重置互斥变量

                //保存ws 链接对象
                $tmpRoomId = RoomService::$devRoomInfo['dev_room_id'];
                RoomService::wsAddRoomWs($tmpRoomId, $connection);

                LogService::writeLog('info', 'ws', 'sever connected!!!');
                LogService::writeLog('info', 'ws', 'to:' . $g_to);

                //发送投币命令
                $cmdCon = CmdService::getCmdCoins('16025821436281');
                LogService::writeLog('info', 'ws', 'cmd coins: ' . $cmdCon);
                $connection->send($cmdCon);

            };

            // 远程websocket服务器发来消息时
            $ws_connection->onMessage = function($connection, $data){

                LogService::writeLog('info', 'ws', 'rev onMessage msg=' . $data);
                $wsRetObj = json_decode($data);
                $wsCmdType = '';
                $wsCmdId = '';
                if( (json_last_error() != JSON_ERROR_NONE)
                    || $wsRetObj == ''){
                    $wsCmdType = '';
                    $wsCmdId = '';
                }else{
                    if( isset($wsRetObj->method) ){
                        $wsCmdType = $wsRetObj->method;
                    }
                    if( isset($wsRetObj->id) ){
                        $wsCmdId = $wsRetObj->id;
                    }

                }

                //根据ws 对象，获取dev_room_id
                $tmpDevRoomId = RoomService::wsGetRoomId($connection);
                $tmpDevInfo = RoomService::devGetRoomDev($tmpDevRoomId);
                $tmpRoomInfo = RoomService::devGetRoomInfo($tmpDevRoomId);
                $userId = $tmpDevInfo['user_id'];
                $roomId = $tmpRoomInfo['room_id'];
                $price = $tmpRoomInfo['price'];

                //由于ws_obj 为链接对象，无法打印，所以去掉ws_obj后打印
                $tmpDevInfo['ws_obj'] = '--';
                $logDevInfo = json_encode($tmpDevInfo);
                $logRoomInfo = json_encode($tmpRoomInfo);
                LogService::writeLog('info', 'ws', 'get room devRoomId=' . $tmpDevRoomId
                    . ' devInfo=' . $logDevInfo . ' roomInfo=' . $logRoomInfo);


                //处理ws client 请求返回的消息
                //处理投币消息
                switch($wsCmdId){
                    case '123456':
                        //$userId = RoomService::$memberInfo['user_id'];
                        //$roomId = RoomService::$roomInfo['room_id'];
                        //$price = RoomService::$roomInfo['price'];
                        LogService::writeLog('info', 'ws', 'ret msg |insert_coins| user_id=' . $userId .  ' room_id=' . $roomId . ' price=' . $price );
                        //投币返回消息
                        if( isset($wsRetObj->errmsg) ){
                            LogService::writeLog('error', 'ws', 'ret msg |insert_coins|  id=' . $wsRetObj->id .
                                ' ws ret code=' . $wsRetObj->errcode .
                                ' ws ret msg=' . $wsRetObj->errmsg);
                            $retNotify = CmdService::notifyCoins($roomId, $userId, $wsRetObj->errmsg, $price, $tmpDevRoomId);
                            LogService::writeLog('error', 'web', 'ret msg |insert_coins| notify coins=' . $retNotify);

                            //更新房间状态为空闲
                            RoomService::devUpdateRoomStatus($tmpDevRoomId, ErrorCode::BABY_ROOM_STATUS_ON);
                        }else{
                            //投币成功 通知web 服务器扣费 更新游戏状态
                            LogService::writeLog('info', 'ws', 'ret msg |insert_coins| success ');

                            //通知web 服务器 进行扣费
                            $retNotify = CmdService::notifyCoins($roomId, $userId, ErrorCode::CODE_OK, $price, $tmpDevRoomId);
                            LogService::writeLog('info', 'web', 'notify coins=' . $retNotify);

                            //更新房间状态为忙碌
                            RoomService::devUpdateRoomStatus($tmpDevRoomId, ErrorCode::BABY_ROOM_STATUS_BUSY);

                            //设置定时器
                            $timePara = array($roomId, $userId, ErrorCode::E_DEV_GAME_TIME_OUT, $price, $tmpDevRoomId);

                            RoomService::$gameTimer = Timer::add(60, function($roomId, $userId, $errorCode, $price, $tmpDevRoomId){
                                $msgInfo = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_ERROR, $errorCode);
                                LogService::writeLog('error', 'timer', $msgInfo);

                                //游戏操作失败 通知web 服务器更新状态
                                $tmpOrderId = RoomService::devGetRoomOrder($tmpDevRoomId);
                                $retNotify = CmdService::notifyResult($roomId, $userId, ErrorCode::BABY_CATCH_FAIL, $tmpOrderId);//RoomService::$coinsOrderId
                                LogService::writeLog('error', 'web', '|timer| game catch result=' . $retNotify);
                                //更新房间状态为空闲
                                RoomService::devUpdateRoomStatus($tmpDevRoomId, ErrorCode::BABY_ROOM_STATUS_ON);

                            }, $timePara, false);

                            //保存timer 到 device list
                            LogService::writeLog('info', 'timer', 'game timer open ' . RoomService::$gameTimer);
                            RoomService::devSetRoomTimer($tmpDevRoomId, RoomService::$gameTimer);
                        }

                        //保存消费订单ID
                        $notifyObj = json_decode($retNotify);
                        $notifyData = isset($notifyObj->data) ? $notifyObj->data : '';
                        RoomService::$coinsOrderId = isset($notifyData->order_id) ? $notifyData->order_id : '';
                        RoomService::devSetRoomOrder($tmpDevRoomId, RoomService::$coinsOrderId);
                        LogService::writeLog('info', 'ws', 'ret msg |insert_coins| save coinsOrderId=' . RoomService::$coinsOrderId);

                        $tmpDevInfo = RoomService::devGetRoomDev($tmpDevRoomId);
                        //由于ws_obj 为链接对象，无法打印，所以去掉ws_obj后打印
                        $tmpDevInfo['ws_obj'] = '--';
                        $logDevInfo = json_encode($tmpDevInfo);
                        LogService::writeLog('info', 'ws', 'ret msg |insert_coins| devInfo=' . $logDevInfo);
                        break;
                    case '123457':
                        //控制返回消息
                        if( isset($wsRetObj->errmsg) ){
                            LogService::writeLog('error', 'ws', '|123457| ret id=' . $wsRetObj->id .
                                ' ws ret code=' . $wsRetObj->errcode .
                                ' ws ret msg=' . $wsRetObj->errmsg);
                        }else{
                            //控制设备消息返回， 成功情况什么都不做

                        }
                        break;
                    default:
                        break;

                }

                //处理ws 服务器主动 推送的消息
                switch($wsCmdType){
                    case 'room_ready':
                        //设备房间正常
                        LogService::writeLog('info', 'ws', 'handle msg |room_ready| success ');
                        break;
                    case 'game_result':

                        LogService::writeLog('info', 'ws', 'handle msg |game_result|');
                        //重置操作 定时器
                        $tmpTimer = RoomService::devGetRoomTimer($tmpDevRoomId);
                        Timer::del($tmpTimer);
                        LogService::writeLog('info', 'timer', 'game timer close ' . $tmpTimer);

                        //更新房间状态为空闲
                        RoomService::devUpdateRoomStatus($tmpDevRoomId, ErrorCode::BABY_ROOM_STATUS_ON);

                        //抓取结果
                        $cmdParam = $wsRetObj->params;
                        $isCatch = $cmdParam->is_catch;
                        if( ErrorCode::BABY_CATCH_SUCCESS == $isCatch ){
                            $catchResult =  ErrorCode::BABY_CATCH_SUCCESS;
                        }else{
                            $catchResult =  ErrorCode::BABY_CATCH_FAIL;
                        }
                        //通知web 服务器 抓取结果
                        $tmpOrderId = RoomService::devGetRoomOrder($tmpDevRoomId);
                        $retNotify = CmdService::notifyResult($roomId, $userId, $catchResult, $tmpOrderId);
                        LogService::writeLog('info', 'web', 'game catch result=' . $retNotify);

                        if(ErrorCode::CODE_OK == $isCatch){    //0 为失败  1 为成功
                            //catch failed  anythings
                            LogService::writeLog('info', 'ws', 'handle msg |game_result| catch failed: '
                                . $isCatch . ' catchResult=' . $catchResult);
                        }else{
                            //catch success
                            LogService::writeLog('info', 'ws', 'handle msg |game_result| catch success: '
                                . $isCatch . ' catchResult=' . $catchResult);
                        }

                        break;
                    /*case 'insert_coins':
                        //投币返回消息
                        if( isset($wsRetObj->errmsg) ){
                            writeLog('error', ' ws ret id=' . $wsRetObj->id .
                                     ' ws ret code=' . $wsRetObj->errcode .
                                     ' ws ret msg=' . $wsRetObj->errmsg);
                        }else{
                            //投币成功 通知web 服务器扣费 更新游戏状态
                            writeLog('info', 'ws rev |insert_coins| success ');
                            $retAuth = CmdService::userAuth('','','','');
                            writeLog('info', 'user info=' . $retAuth);
                        }
                        break;*/
                    /*case 'control':
                        //控制返回消息
                        if( isset($wsRetObj->errmsg) ){
                            writeLog('error', ' ws ret id=' . $wsRetObj->id .
                                     ' ws ret code=' . $wsRetObj->errcode .
                                     ' ws ret msg=' . $wsRetObj->errmsg);
                        }else{
                            //控制设备消息返回， 成功情况什么都不做

                        }
                        break;*/

                    default:
                        //其它消息
                        break;
                } //switch($wsCmdType){

                LogService::writeLog('info', 'ws', 'ret msg end ' . 'wsCmdId |' . $wsCmdId . '|  handle msg end ' . 'wsCmdType |' . $wsCmdType . '|');
            };

            // 连接上发生错误时，一般是连接远程websocket服务器失败错误
            $ws_connection->onError = function($connection, $code, $msg){
                LogService::writeLog('error', 'ws', 'rev onError msg');
                //$tmpDevRoomId = RoomService::wsGetRoomId($connection);
                $tmpDevRoomId = RoomService::wsRemoveRoomWs($connection);
                LogService::writeLog('info', 'ws', 'onError devRoomId=' . $tmpDevRoomId);

                Timer::del(RoomService::$isWsConnected);
                RoomService::$isWsConnected = ErrorCode::CODE_OK; //重置互斥变量
                LogService::writeLog('error', 'ws', 'rev onError msg=' . $msg);
            };

            // 当连接远程websocket服务器的连接断开时
            $ws_connection->onClose = function($connection){
                LogService::writeLog('info', 'ws', 'rev onClose msg');

                //$tmpDevRoomId = RoomService::wsGetRoomId($connection);
                $tmpDevRoomId = RoomService::wsRemoveRoomWs($connection);
                LogService::writeLog('info', 'ws', 'onClose devRoomId=' . $tmpDevRoomId);

                Timer::del(RoomService::$isWsConnected);
                RoomService::$isWsConnected = ErrorCode::CODE_OK; //重置互斥变量
                LogService::writeLog('inof', 'ws', 'connection closed');

            };
            // 设置好以上各种回调后，执行连接操作
            $ws_connection->connect();

            break;
        case 'control':
            LogService::writeLog('info', 'http', '===control message start===');
            //global $g_ws_obj;
            $devRoomId = isset($jsonObj->dev_room_id) ? $jsonObj->dev_room_id : '';
            LogService::writeLog('info', 'http', 'dev_room_id=' . $devRoomId . ' control to: ' . $jsonObj->to);

            $ws_obj = RoomService::devGetRoomWsObj($devRoomId);
            if( empty($ws_obj) ){
                LogService::writeLog('error', 'ws', 'ws obj is null');
                break;
            }
            $cmdCon = CmdService::getCmdControl($jsonObj->to);
            LogService::writeLog('info', 'ws', 'control cmd: ' . $cmdCon);
            $ws_obj->send($cmdCon);
            break;
    }; //switch

    $httpRet = json_encode($retHttpMsg);
    LogService::writeLog('info', 'http', '===return cmd=== ' . $httpRet);

    return $con_id->send($httpRet);
}; //$httpServer->onMessage

//记录workmen 日志
function writeLog($type, $msg){
    $logTitle = 'baby_control: ';
    $logType = '|' . $type . '| ';
    $logMsg = $logTitle . $logType . $msg;
    \Workerman\Worker::log($logMsg);
    //var_dump($g_ws_obj);
}
/**
 * 初始化返回消息结构
 * @return array
 */
function _initRetMsg()
{

    $retHttpMsg['code'] = ErrorCode::CODE_OK;
    $retHttpMsg['type'] = '';
    $retHttpMsg['msg'] = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, ErrorCode::CODE_OK);
    $retHttpMsg['data'] = '';
    return $retHttpMsg;
}

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

