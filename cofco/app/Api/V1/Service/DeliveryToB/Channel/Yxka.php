<?php

namespace App\Api\V1\Service\DeliveryToB\Channel;

use App\Api\Model\DeliveryToB\Delivery as DeliveryModel;

class Yxka extends ADelivery
{

    public function GetRule()
    {
        $_model = new DeliveryModel();
        $row = $_model->find(array('identifying' => 'YXKA'));
        try {
            return $row[0]->expression;
        } catch (\Exception $e) {
            return array();
        }
    }

}
