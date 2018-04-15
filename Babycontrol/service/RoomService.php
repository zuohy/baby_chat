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
    public static $roomInfo = array(
        'room_id' => '',
        'topic' => '',
        'status' => '',
        'tag' => '',
        'price' => '',
        'room_pic' => '',
        'gift_id' => '',
    );
    public static $memberInfo = array(    //当前成员信息
        'room_id' => '',
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
     * @param string $data
     * @return bool|string
     */
    public static function devSetRoomInfo($roomId='', $userId='', $price=0){
        self::$roomInfo['room_id'] = $roomId;
        self::$memberInfo['user_id'] = $userId;
        self::$roomInfo['price'] = $price;

        //self::$roomInfo['status'] = 1;     //房间空闲
        //self::$memberInfo['user_status'] = 2;   //进入房间
        LogService::writeLog('info', 'RoomService', '|devSetRoomInfo|'
            . ' room_id=' . self::$roomInfo['room_id'] . ' user_id=' . self::$memberInfo['user_id'] = $userId);

        self::devUpdateRoomStatus(1);


    }

    /**
     * devSetRoomInfo   更新房间状态
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devUpdateRoomStatus($roomStatus){

        if(1 == $roomStatus){
            self::$roomInfo['status'] = 1;     //房间空闲
            self::$memberInfo['user_status'] = 2;  //进入房间
        }elseif (2 == $roomStatus){
            self::$roomInfo['status'] = 2;     //房间忙碌
            self::$memberInfo['user_status'] = 3;   //正在游戏
        }

        LogService::writeLog('info', 'RoomService', '|devUpdateRoomStatus|'
            . ' room_status=' . self::$roomInfo['status'] . ' user_status=' . self::$memberInfo['user_status']);

    }

    /**
     * devClearRoomStatus   清楚房间状态信息
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data
     * @return bool|string
     */
    public static function devClearRoomStatus(){

        self::$gameTimer = 0;
        self::$coinsOrderId = '';
        foreach(self::$roomInfo as $key => $value){
            self::$roomInfo[$key] = '';
        }
        foreach(self::$memberInfo as $key => $value){
            self::$memberInfo[$key] = '';
        }
        foreach(self::$giftInfo as $key => $value){
            self::$giftInfo[$key] = '';
        }
        self::devUpdateRoomStatus(1);
    }
    //////////////////////////end baby_control function//////////////////////////////////////////

}


