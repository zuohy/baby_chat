<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2016~2018 贵州华宇信息科技有限公司 [  ]
// +----------------------------------------------------------------------
// | 官方网站:
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | github开源项目：
// +----------------------------------------------------------------------

namespace service;

use service\ErrorCode;
use service\LogService;

/**
 * 房间管理服务
 * Class RoomService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class RoomService
{
    public static $isWsConnected = 0;   //0 未链接，1 链接 timer
    public static $wsPos = 0;
    public static $devWsInfo = array(
        'dev_room_id' => '',
        'ws_obj' => '',     //设备控制ws 链接对象
    );
    public static $wsList = array();     //key =0 设备链接对象列表

    public static $devRoomInfo = array(
        'user_id' => '',
        'dev_room_id' => '',
        'dev_status' => '',
        //'dev_tag' => '',
        //'dev_pic' => '',
        'game_timer' => '',  //游戏操作定时器ID
        'order_id' => '',   //消费订单ID 由web 服务器生成，用于保存抓取结果记录 和消费订单记录
        'ws_obj' => '',     //设备控制ws 链接对象
    );

    public static $deviceList = array();   //key =dev_room_id  服务器以设备房间 为核心列表 更新设备状态

    public static $roomInfo = array(
        'room_id' => '',
        //'topic' => '',
        'status' => '',
        //'tag' => '',
        'price' => '',
        //'room_pic' => '',
        //'gift_id' => '',
    );
    public static $roomList = array();  //key =dev_room_id

    //$memberInfo  $giftInfo  未使用
    public static $memberInfo = array(    //当前成员信息
        'room_id' => '',
        'dev_room_id' => '',
        'user_id' => '',
        'name' => '',
        'pic' => '',
        'user_status' => '',
        'v_user_type' => '',
        'v_client_type' => '',
        'c_client_id' => '',
    );
    public static $memberList = array();

    public static $giftInfo = array(
        'gift_id' => '',
        'gift_pic_show' => '',
        'gift_pic_1' => '',
        'gift_pic_2' => '',
        'gift_pic_3' => '',
        'gift_pic_4' => '',
        'gift_pic_5' => '',
        'gift_name' => '',
        'describe' => '',
    );

    public static $gameTimer = 0;  //游戏操作定时器ID
    public static $coinsOrderId = '';  //消费订单ID  由web 服务器生成，用于保存抓取结果记录 和消费订单记录
    //////////////////////////start baby_control function//////////////////////////////////////////
    //////////////////////////only used in baby_control server//////////////////////////////////////////

    /**
     * devSetRoomInfo   保存房间信息
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $price
     * @param string $devRoomId 房间设备ID
     * @return bool|string
     */
    public static function devSetRoomInfo($roomId='', $userId='', $price=0, $devRoomId){
        self::$roomInfo['room_id'] = $roomId;
        self::$roomInfo['price'] = $price;

        self::$devRoomInfo['dev_room_id'] = $devRoomId;
        self::$devRoomInfo['user_id'] = $userId;

        LogService::writeLog('info', 'RoomService', '|devSetRoomInfo|'
            . ' room_id=' . self::$roomInfo['room_id'] . ' user_id=' . self::$devRoomInfo['user_id']
            . ' dev_room_id=' . self::$devRoomInfo['dev_room_id']);

        //加入设备列表
        self::$deviceList[$devRoomId] = self::$devRoomInfo;
        self::$roomList[$devRoomId] = self::$roomInfo;

        self::devUpdateRoomStatus($devRoomId, ErrorCode::BABY_ROOM_STATUS_ON);

    }

    /**
     * devSetRoomInfo   更新房间状态
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devUpdateRoomStatus($devRoomId, $roomStatus){

        if( ErrorCode::BABY_ROOM_STATUS_ON == $roomStatus ){
            self::$roomInfo['status'] = ErrorCode::BABY_ROOM_STATUS_ON;     //房间空闲
            self::$devRoomInfo['dev_status'] = ErrorCode::BABY_ROOM_STATUS_ON;  //进入房间
        }elseif ( ErrorCode::BABY_ROOM_STATUS_BUSY == $roomStatus ){
            self::$roomInfo['status'] = ErrorCode::BABY_ROOM_STATUS_BUSY;     //房间忙碌
            self::$devRoomInfo['dev_status'] = ErrorCode::BABY_ROOM_STATUS_BUSY;   //正在游戏
        }

        $tmpRoomInfo = isset(self::$roomList[$devRoomId]) ? self::$roomList[$devRoomId] : '';
        if( $tmpRoomInfo ){
            self::$roomList[$devRoomId]['status'] = self::$roomInfo['status'];
        }else{
            LogService::writeLog('error', 'RoomService', '|devUpdateRoomStatus|' . 'room list not found dev_room_id=' . $devRoomId
                . ' room_status=' . self::$roomInfo['status'] . ' user_status=' . self::$devRoomInfo['dev_status']);
        }
        $tmpDevInfo = isset(self::$deviceList[$devRoomId]) ? self::$deviceList[$devRoomId] : '';
        if( $tmpDevInfo ){
            self::$deviceList[$devRoomId]['dev_status'] = self::$devRoomInfo['dev_status'];
        }else{
            LogService::writeLog('error', 'RoomService', '|devUpdateRoomStatus|' . 'device list not found dev_room_id=' . $devRoomId
                . ' room_status=' . self::$roomInfo['status'] . ' user_status=' . self::$devRoomInfo['dev_status']);
        }

        LogService::writeLog('info', 'RoomService', '|devUpdateRoomStatus|'
            . ' room_status=' . self::$roomInfo['status'] . ' user_status=' . self::$devRoomInfo['dev_status']);

    }

    /**
     * devInitRoomStatus   初始化房间状态信息
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devInitRoomStatus(){
        self::$gameTimer = 0;
        self::$coinsOrderId = '';

        foreach(self::$devWsInfo as $key => $value){
            self::$devWsInfo[$key] = '';
        }
        foreach(self::$roomInfo as $key => $value){
            self::$roomInfo[$key] = '';
        }
        foreach(self::$devRoomInfo as $key => $value){
            self::$devRoomInfo[$key] = '';
        }

        foreach(self::$memberInfo as $key => $value){
            self::$memberInfo[$key] = '';
        }
        foreach(self::$giftInfo as $key => $value){
            self::$giftInfo[$key] = '';
        }

    }


    /**
     * devSetRoomTimer   设置房间timer
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devSetRoomTimer($devRoomId, $timer){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devSetRoomTimer|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        self::$deviceList[$devRoomId]['game_timer'] = $timer;
    }

    /**
     * devSetRoomWs   获取房间timer
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devGetRoomTimer($devRoomId){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devGetRoomTimer|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        $timerId = self::$deviceList[$devRoomId]['game_timer'];
        return $timerId;
    }

    /**
     * devSetRoomOrder   设置房间消费订单
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devSetRoomOrder($devRoomId, $orderId){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devSetRoomTimer|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        self::$deviceList[$devRoomId]['order_id'] = $orderId;
    }

    /**
     * devGetRoomOrder   获取房间消费订单
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devGetRoomOrder($devRoomId){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devGetRoomTimer|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        $orderId = self::$deviceList[$devRoomId]['order_id'];
        return $orderId;
    }

    /**
     * devSetRoomWsObj   设置房间设备ws链接  一个房间同一时间只有一个ws 链接可用
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devSetRoomWsObj($devRoomId, $wsObj){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devSetRoomWsObj|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        self::$deviceList[$devRoomId]['ws_obj'] = $wsObj;
    }

    /**
     * devGetRoomWsObj   获取房间设备ws 对象
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devGetRoomWsObj($devRoomId){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devGetRoomTimer|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }
        $wsId = self::$deviceList[$devRoomId]['ws_obj'];
        return $wsId;
    }


    /**
     * devGetRoomDev   获取房间设备信息
     * @param string $devRoomId 房间ID
     * @param string $data
     * @return bool|string
     */
    public static function devGetRoomDev($devRoomId){
        $tmpDevInfo = self::$deviceList[$devRoomId];
        if( empty($tmpDevInfo) ){
            LogService::writeLog('error', 'RoomService', '|devGetRoomDev|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }

        return $tmpDevInfo;
    }

    /**
     * devGetRoomInfo   获取房间信息
     * @param string $devRoomId 房间ID
     * @param string $data
     * @return bool|string
     */
    public static function devGetRoomInfo($devRoomId){
        $tmpRoomInfo = self::$roomList[$devRoomId];
        if( empty($tmpRoomInfo) ){
            LogService::writeLog('error', 'RoomService', '|devGetRoomInfo|'
                . ' dev_room_id=' . $devRoomId . ' not found');
            return '';
        }

        return $tmpRoomInfo;
    }

    //////////////////////////end baby_control function//////////////////////////////////////////


    ///////////////////////////////start ws obj//////////////////////////////////////////////////////
    /**
     * wsAddRoomWs   设置房间ws 对象
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function wsAddRoomWs($devRoomId, $wsObj){

        self::$devWsInfo['ws_obj'] = $wsObj;
        self::$devWsInfo['dev_room_id'] = $devRoomId;

        self::$wsList[self::$wsPos] = self::$devWsInfo;

        //一个设备同一个时间，只有一个WS链接可用, 保存在dev list中
        self::devSetRoomWsObj($devRoomId, $wsObj);
        LogService::writeLog('info', 'RoomService', '|wsAddRoomWs|'
            . ' dev_room_id=' . self::$devWsInfo['dev_room_id'] . ' wsPos=' . self::$wsPos);

        self::$wsPos++;
    }

    /**
     * wsRemoveRoomWs   根据ws 对象删除 ws 列表元素
     * @param string $wsObj
     * @param string $data
     * @return bool|string
     */
    public static function wsRemoveRoomWs($wsObj){
        $devRoomId = '';

        foreach(self::$wsList as $id => $info){

            if( $info['ws_obj'] == $wsObj){
                $devRoomId = $info['dev_room_id'];
                unset(self::$wsList[$id]);
                break;
            }
        }

        LogService::writeLog('info', 'RoomService', '|wsRemoveRoomWs|'
            . ' dev_room_id=' . $devRoomId . ' wsPos=' . $id);
        return $devRoomId;
    }

    /**
     * wsGetRoomId   根据ws 对象获取设备房间ID
     * @param string $wsObj
     * @param string $data
     * @return bool|string
     */
    public static function wsGetRoomId($wsObj){
        $devRoomId = '';

        foreach(self::$wsList as $id => $info){

            if( $info['ws_obj'] == $wsObj){
                $devRoomId = $info['dev_room_id'];
                break;
            }
        }

        LogService::writeLog('info', 'RoomService', '|wsGetRoomId|'
            . ' dev_room_id=' . $devRoomId . ' wsPos=' . $id);
        return $devRoomId;
    }

    /**
     * wsGetRoomWs   根据房间设备ID 获取对象 暂时不用 一个设备同一个时间，只有一个WS链接可用
     * @param string $devRoomId
     * @param string $data
     * @return bool|string
     */
    public static function wsGetRoomWs($devRoomId){
        $wsObj = '';

        foreach(self::$wsList as $id => $info){

            if( $info['dev_room_id'] == $devRoomId){
                $wsObj = $info['ws_obj'];
                break;
            }
        }
        LogService::writeLog('info', 'RoomService', '|wsGetRoomWs|'
            . ' dev_room_id=' . $devRoomId . ' wsPos=' . $id);
        return $wsObj;
    }

///////////////////////////////end ws obj//////////////////////////////////////////////////////


}


