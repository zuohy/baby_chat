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
use \Workerman\Worker;
/**
 * 日志管理服务
 * Class LogService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class LogService
{

    /**
     * devSetRoomInfo   保存房间信息
     * @param string $type 类型
     * @param string $server 服务类型
     * @param string $msg 日志内容
     * @return bool|string
     */
    public static function writeLog($type, $server, $msg){
        $logTitle = 'baby_control: ';
        $logType = '|' . $type . '| ';
        $logServer = '#' . $server . '# ';
        $logMsg = $logTitle . $logType . $logServer . $msg;
        \Workerman\Worker::log($logMsg);

    }

}


