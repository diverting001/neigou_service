<?php

namespace App\Api\Model\AfterSale\V2;

class AfterSale
{

    private static $_channel_name = 'service';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function getList($page = 1, $limit = 50, $filter = array(), $order = array())
    {
        $where_data = self::WhereAnalysis($filter);

        $order_by = $order ? $order . ', id desc' : 'id desc';

        $offset = ($page - 1) * $limit;
        $offsetLimit = $offset . ',' . $limit;

        $sql = "select * from server_after_sales where 1 {$where_data['str']} order by {$order_by} limit {$offsetLimit}";
        $after_sale_list_obj = app('api_db')->select($sql, $where_data['bindings']);

        $after_sale_list = [];
        foreach ($after_sale_list_obj as $obj) {
            $after_sale_list[] = get_object_vars($obj);
        }

        return $after_sale_list ? $after_sale_list : array();
    }

    public function find($filter = array(), $order = array())
    {
        $where_data = self::WhereAnalysis($filter);
        $order_by = $order ? $order . ', id desc' : 'id desc';
        $sql = "select * from server_after_sales where 1 {$where_data['str']} order by {$order_by}";
        $after_sale_list_obj = app('api_db')->selectOne($sql, $where_data['bindings']);

        return !empty($after_sale_list_obj) ? get_object_vars($after_sale_list_obj) : [];
    }

    public function getCount($filter = array())
    {
        $where_data = self::WhereAnalysis($filter);
        $sql = "select count(*) count from server_after_sales where 1 {$where_data['str']}";
        $total_count_obj = app('api_db')->selectOne($sql, $where_data['bindings']);
        return $total_count_obj->count ? $total_count_obj->count : 0;
    }

    public function getListByRootPid($root_pid){
        $return = array();
        $after_sale_list = $this->_db->table('server_after_sales')->where('root_pid',$root_pid)->orderBy('id', 'desc')->lockForUpdate()->get()->toArray();
        if(empty($after_sale_list)){
            return $return;
        }

        foreach ($after_sale_list as $item) {
            $return[$item->after_sale_bn] = get_object_vars($item);
        }
        return $return;
    }

    /*
    * 写入数据
    */
    public function create($data)
    {
        return $this->_db->table('server_after_sales')->insert($data);
    }

    public function publishCreateMessage($after_sale_bn)
    {
        $mq = new \Neigou\AMQP();
        $routing_key = 'aftersale.status.create';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'after_sale_bn' => $after_sale_bn,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        \Neigou\Logger::General('after_sale_create_log_v2', array(
            'data' => $send_data,
            'reason' =>$res
        ));
        return $res;
    }


    /*
     * @todo 解析where条件
     */
    protected static function WhereAnalysis($where)
    {
        $where_data = [
            'str' => '',
            'bindings' => []
        ];
        if (empty($where)) {
            return $where_data;
        }
        foreach ($where as $field => $value) {
            switch ($value['type']) {
                case 'eq':
                    $where_data['str'] .= ' and (' . $field . ' = :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'neq':
                    $where_data['str'] .= ' and (' . $field . ' != :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'between':
                    $where_data['str'] .= ' and (' . $field . ' >= :' . $field . '_egt and ' . $field . ' <= :' . $field . '_elt)';
                    $where_data['bindings'][$field . '_egt'] = $value['value']['egt'];
                    $where_data['bindings'][$field . '_elt'] = $value['value']['elt'];
                    break;
                case 'in':
                    $in_data = [];
                    foreach ($value['value'] as $k => $data) {
                        $in_data[$field . '_' . $k] = "{$data}";
                    }
                    $where_data['str'] .= ' and ( ' . $field . ' in (:' . implode(',:', array_keys($in_data)) . '))';
                    $where_data['bindings'] = array_merge($where_data['bindings'], $in_data);
                    break;
                case 'not_in':
                    $in_data = [];
                    foreach ($value['value'] as $k => $data) {
                        $in_data[$field . '_' . $k] = "{$data}";
                    }
                    $where_data['str'] .= ' and ( ' . $field . ' not in (:' . implode(',:', array_keys($in_data)) . '))';
                    $where_data['bindings'] = array_merge($where_data['bindings'], $in_data);
                    break;
                case 'like':
                    $where_data['str'] .= ' and (' . $field . ' LIKE :' . $field . ')';
                    $fieldWord = "%" . $value['value'] . "%";
                    $where_data['bindings'][$field] = $fieldWord;
                    break;
                case 'egt':
                    $where_data['str'] .= ' and (' . $field . ' >= :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'elt':
                    $where_data['str'] .= ' and (' . $field . ' <= :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'gt':
                    $where_data['str'] .= ' and (' . $field . ' > :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
                case 'lt':
                    $where_data['str'] .= ' and (' . $field . ' < :' . $field . ')';
                    $where_data['bindings'][$field] = $value['value'];
                    break;
            }
        }
        return $where_data;
    }


    /*
 * 实际售后原因
 */
    public static function afterSaleActualReasonType()
    {
        return array(
            1  => '无理由退货（退回我司）',
            2  => '无理由退货（退回商家）',
            3  => '客户手机号、地址留错',
            4  => '保价订单',
            5  => '商家发错货',
            6  => '少件/漏发',
            7  => '未按约定时间发货',
            8  => '商品与页面描述不符',
            9  => '收到商品破损、有污渍',
            10 => '质量问题',
            11 => '生鲜产品不新鲜（严重化冻、腐烂、变质、蛋糕变形破损）',
            12 => '物流异常（物流停滞、转运异常、拆单发货、丢件、物流退件）',
            13 => '物流管控停发',
            14 => '临期食品',
            15 => '质疑商品真伪',
        );
    }
}
