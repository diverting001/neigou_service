<?php

namespace App\Api\V2\Controllers\CustomerCare;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\CustomerCare\V2\AfterSale as AfterSaleLogic;
use App\Api\Logic\CustomerCare\V2\AfterSaleCheck;
use App\Api\Model\AfterSale\V2\AfterSale as AfterSaleModel;
use Illuminate\Http\Request;

class AfterSaleController extends BaseController
{

    /**
     * 获取多售后单
     */
    public function GetList(Request $request)
    {
        $params = $this->getContentArray($request);

        $after_sale_logic = new AfterSaleLogic();
        $check_result = $after_sale_logic->check_list_condition($params);
        if (!$check_result) {
            $this->setErrorMsg('参数错误非法');
            return $this->outputFormat($params, 20001);
        }

        $format_data = $after_sale_logic->format_condition($params);

        $result = $after_sale_logic->getAfterSaleList($format_data);

        $this->setErrorMsg('success');
        return $this->outputFormat($result, 0);
    }

    /**
     * 获取单条售后单
     * @param Request $request
     * @return array
     */
    public function Find(Request $request)
    {
        $params = $this->getContentArray($request);

        $after_sale_logic = new AfterSaleLogic();
        $check_result = $after_sale_logic->check_list_condition($params);
        if (!$check_result) {
            $this->setErrorMsg('参数错误非法');
            return $this->outputFormat($params, 20001);
        }

        $format_data = $after_sale_logic->format_condition($params);
        $result = $after_sale_logic->find($format_data);

        $this->setErrorMsg('success');
        return $this->outputFormat($result, 0);
    }

    /**
     * 创建售后单
     */
    public function Create(Request $request){
        $params = $this->getContentArray($request);
        $after_sale_logic = new AfterSaleLogic();

        $error = '';
        $check_logic = new AfterSaleCheck();
        $check = $check_logic->checkCreateParams($params,$error);
        if(!$check){
            $this->setErrorMsg($error);
            return $this->outputFormat($params, 20001);
        }

        $order_info = $check_logic->checkOrderInfo($params['order_id'],$error);

        if(!$order_info){
            $this->setErrorMsg($error);
            return $this->outputFormat($params, 20002);
        }

        //根订单->解决同一SKU拆单为两单
        $root_order_info = $check_logic->checkOrderInfo( $order_info['root_pid'], $error );
        if ( !$root_order_info )
        {
            $this->setErrorMsg( $error );
            return $this->outputFormat( $params, 20002 );
        }

        app('db')->beginTransaction();

        $legal_check = $check_logic->checkCreateLegal($params,$order_info,$root_order_info,$error);
        if(!$legal_check){
            app('db')->rollback();
            $this->setErrorMsg($error);
            return $this->outputFormat($params, 20003);
        }

        $after_sale_bn = $after_sale_logic->create($params,$order_info,$error);
        if(!$after_sale_bn){
            app('db')->rollback();
            $this->setErrorMsg($error);
            return $this->outputFormat($params, 20004);
        }

        app('db')->commit();
        $afterSaleMdl = new AfterSaleModel();
        $afterSaleMdl->publishCreateMessage($after_sale_bn);
        $this->setErrorMsg('success');
        return $this->outputFormat(['after_sale_bn'=>$after_sale_bn], 0);
    }

    /**
     * Notes:售后-实际售后原因类型
     * User: mazhenkang
     * Date: 2022/9/5 13:10
     * @param Request $request
     * @return array
     */
    public function getActualReasonType(Request $request)
    {
        $params = $this->getContentArray($request);

        $after_sale_logic = new AfterSaleLogic();
        $result = $after_sale_logic->getActualReasonType($params);

        $this->setErrorMsg('success');
        return $this->outputFormat($result, 0);
    }
}
