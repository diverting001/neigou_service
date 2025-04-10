<?php

namespace App\Api\Model\Order;

class OrderRefundLog
{

    /*
     * @todo 订单退款日志
     */
    public static function SaveLog($log_data)
    {
        if (empty($log_data)) {
            return false;
        }
        $sql = "INSERT INTO `server_order_refund_log` (`" . implode('`,`',
                array_keys($log_data)) . "`)VALUES(" . implode(',', array_fill(0, count($log_data), '?')) . ")";
        $res = app('api_db')->insert($sql, array_values($log_data));
        return $res;
    }

}
