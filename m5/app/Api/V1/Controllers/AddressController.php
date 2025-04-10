<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Address\Address;
use App\Api\Model\Region\Region;
use Illuminate\Http\Request;
use App\Api\Logic\Address as AddressLogic;
use Exception;
use Illuminate\Support\Facades\Validator;

class AddressController extends BaseController
{
    public function __construct()
    {
        $this->address_model = new Address();
    }

    /*
     * 创建地址
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);
        if (!is_array($param) || empty($param['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        //获取极限值最多20条
        $count = $this->address_model->count($param['member_id']);
        $mostAddrNum = 40;
        if ($count >= $mostAddrNum) {
            $this->setErrorMsg('最多不能超过'.$mostAddrNum.'条地址');
            return $this->outputFormat($param, 10002);
        }

        $time = time();
        $insert = array(
            'member_id' => $param['member_id'],
            'name' => $param['name'],
            'area' => $param['area'],
            'addr' => $param['addr'],
            'deliver_area' => $param['deliver_area'],
            'deliver_addr' => $param['deliver_addr'],
            'deliver_json' => $param['deliver_json'],
            'zip' => $param['zip'],
            'mobile' => $param['mobile'],
            'day' => empty($param['day']) ? '任意日期' : $param['day'],
            'time' => empty($param['time']) ? '任意时间段' : $param['time'],
            'def_addr' => empty($param['def_addr']) ? 0 : 1,
            'create_time' => empty($param['create_time']) ? $time : $param['create_time'],
            'update_time' => empty($param['update_time']) ? $time : $param['update_time'],
        );

        //判断是否重复
        $search = array(
            'member_id' => $param['member_id'],
            'name' => $param['name'],
            'area' => $param['area'],
            'addr' => $param['addr'],
            'deliver_area' => $param['deliver_area'],
            'deliver_addr' => $param['deliver_addr'],
            'deliver_json' => $param['deliver_json'],
            'zip' => $param['zip'],
            'mobile' => $param['mobile'],
        );
        $isRepeat = $this->address_model->search($search);
        if ($isRepeat) {
            $this->setErrorMsg('地址重复');
            return $this->outputFormat($search, 10003);
        }

        $res = $this->address_model->create($insert);
        if ($res === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat($insert, 10004);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(array('increment_id' => $res), 0);
    }

    /*
     * 修改地址
     */
    public function update(Request $request)
    {
        $param = $this->getContentArray($request);
        if (!is_array($param) || empty($param['server_address_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 20001);
        }

        $time = time();

        if (isset($param['name'])) {
            $save['name'] = $param['name'];
        }
        if (isset($param['area'])) {
            $save['area'] = $param['area'];
            list($tag, $areaAddr, $id) = explode(':', $param['area']);
            $regionModel = new Region();
            $region = $regionModel->getRegionInfo($id);
            if (empty($region)) {
                $this->setErrorMsg('地址过期请重新编辑');
                return $this->outputFormat($param, 20003);
            }
            $lastArea = $regionModel->getChildRegion($id);
            $areaIdStr = $region['region_path'].','.$region['p_region_id'].",";
            $areaIdArr = array_unique(array_filter(explode(',', $areaIdStr)));
            $regionAllArr = $regionModel->getRegionInfo($areaIdArr);

            $pathIdArr = array_filter(explode(',', $regionAllArr[$id]['region_path']));
            $regionStr = '';
            foreach ($pathIdArr as $regionId) {
                $regionStr .= $regionAllArr[$regionId]['local_name'].'/';
            }
            if (!empty($lastArea) || $areaAddr !== trim($regionStr, "/")) {
                $this->setErrorMsg('地址过期请重新编辑');
                return $this->outputFormat($regionStr, 20003);
            }
        }
        if (isset($param['addr'])) {
            $save['addr'] = $param['addr'];
        }
        if (isset($param['deliver_area'])) {
            $save['deliver_area'] = $param['deliver_area'];
        }
        if (isset($param['deliver_addr'])) {
            $save['deliver_addr'] = $param['deliver_addr'];
        }
        if (isset($param['deliver_json'])) {
            $save['deliver_json'] = $param['deliver_json'];
        }
        if (isset($param['zip'])) {
            $save['zip'] = $param['zip'];
        }
        if (isset($param['mobile'])) {
            $save['mobile'] = $param['mobile'];
        }
        if (isset($param['day'])) {
            $save['day'] = $param['day'];
        }
        if (isset($param['time'])) {
            $save['time'] = $param['time'];
        }

        if (isset($param['def_addr'])) {
            $save['def_addr'] = empty($param['def_addr']) ? 0 : 1;
        }

        $save['update_time'] = empty($param['update_time']) ? $time : $param['update_time'];

        $res = $this->address_model->update($param['server_address_id'], $save);
        if ($res === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 20002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

    /*
     * 删除地址
     */
    public function delete(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id']) || empty($param['server_address_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 30001);
        }

        $res = $this->address_model->delete($param['server_address_id'], $param['member_id']);
        if ($res === false) {
            $this->setErrorMsg('提交失败');
            return $this->outputFormat(null, 30002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(null, 0);
    }

    /*
     * 获取单条
     */
    public function getRow(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['server_address_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 40001);
        }

        $res = $this->address_model->getRow($param['server_address_id']);
        $res = $this->isValidAddresses([$res])[0];

        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 获取列表
     */
    public function getList(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 50001);
        }

        $res = $this->address_model->getList($param['member_id']);
        $res = $this->isValidAddresses($res);

        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 搜索
     */
    public function search(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 50001);
        }

        foreach ($param as $k => $v) {
            if (empty($v)) {
                unset($param[$k]);
            }
        }

        $res = $this->address_model->search($param);
        $res = $this->isValidAddresses($res);

        $this->setErrorMsg('success');
        return $this->outputFormat($res, 0);
    }

    /*
     * 获取用户地址条数
     */
    public function memberAddrCount(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['member_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 50001);
        }

        $res = $this->address_model->count($param['member_id']);

        $this->setErrorMsg('success');
        return $this->outputFormat(array('addr_count' => $res), 0);
    }

    /**
     * 验证收货地址是否可用
     *
     * @param array $addresses
     * @return array
     */
    protected function isValidAddresses($addresses)
    {
        if (empty($addresses)) {
            return $addresses;
        }
        $regionModel = new Region();
        $regionIds = array();
        // 先取出地址列表的region id
        foreach ($addresses as $key => $address) {
            $region = substr($address['area'], strpos($address['area'], ':', 9) + 1);
            if (empty($region)) {
                $addresses[$key]['is_valid'] = false;
                continue;
            }
            $addresses[$key]['region_id'] = (int)$region;
            $regionIds[] = $region;
        }
        // 去重
        $regionIds = array_unique($regionIds);

        $lastArea = $regionModel->getChildRegionIds($regionIds);
        $existsRegions = $regionModel->getRegionInfo($regionIds);
        $existsRegionIds = array();
        $areaIdStr = '';
        foreach ($existsRegions as $region) {
            $existsRegionIds[] = $region['region_id'];
            $areaIdStr .= $region['region_path'] . ',' . $region['p_region_id'] . ",";
        }
        $areaIdArr = array_unique(array_filter(explode(',', $areaIdStr)));
        $regionAllArr = $regionModel->getRegionInfo($areaIdArr);
        foreach ($addresses as $key => $address) {
            if (isset($address['is_valid'])) {
                continue;
            }
            // 判断地区是否存在
            $addresses[$key]['is_valid'] = isset($address['region_id']) && in_array(
                    $address['region_id'],
                    $existsRegionIds
                );
            if ($addresses[$key]['is_valid']) {
                list($tag, $areaAddr, $id) = explode(':', $address['area']);
                $pathIdArr = array_filter(explode(',', $regionAllArr[$id]['region_path']));
                $regionStr = '';
                foreach ($pathIdArr as $regionId) {
                    $regionStr .= $regionAllArr[$regionId]['local_name'].'/';
                }
                //如果用户地址与地址库地址名称匹配 & 是最后一级地址则有效
                if (!isset($lastArea[$id]) && $areaAddr === trim($regionStr, "/")) {
                    $addresses[$key]['area_valid'] = 1;
                } else {
                    $addresses[$key]['area_valid'] = 0;
                }
            } else {
                $addresses[$key]['area_valid'] = 0;
            }
        }

        return $addresses;
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function getSuggestLocationList(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['region']) || empty($param['keyword'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 50001);
        }

        $param['keyword'] = str_replace(['(', ')', '#', '"', '\''], ['（', '）', '', '',''], $param['keyword']);

        $platform = !empty($param['platform']) ? $param['platform'] : 'all'; // 平台
        $partner = !empty($param['partner']) ? $param['partner'] : config('neigou.ADDRESS_SUGGESTION_PLATFORM'); // 定位服务方

        try {
            $addressLogic = new AddressLogic();
            $res = $addressLogic->placeSuggestion($param['region'], $param['keyword'], $platform, $partner);

            if (!empty($res) && $res['status'] == 0) {
                $this->setErrorMsg('success');
                return $this->outputFormat($res, 0);
            }
        } catch (Exception $e) {
            $this->setErrorMsg($e->getMessage());
            return $this->outputFormat([], 400);
        }

        $this->setErrorMsg('失败 ' . $res['message'] ?? '');
        return $this->outputFormat([], $res['status'] ?? 100010);
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function getLocationKey(Request $request)
    {
        $addressLogic = new AddressLogic();

        $res = $addressLogic->getLocation();
        if ($res) {
            $this->setErrorMsg('success');
            return $this->outputFormat($res, 0);
        }
        $this->setErrorMsg('失败');
        return $this->outputFormat([], 100010);
    }

    /**
     * 获取经纬度对应的地区[调用三方逆地理编码接口]
     * @param  Request  $request
     * @return array
     */
    public function getRegionByLocation(Request $request)
    {
        $param = $this->getContentArray($request);

        $validator = Validator::make($param, [
            'lon' => 'required',
            'lat' => 'required',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $addressLogic = new AddressLogic();

        $res = $addressLogic->getRegionByLocation($param['lon'], $param['lat']);
        if ($res) {
            $this->setErrorMsg('success');
            return $this->outputFormat($res, 0);
        }
        $this->setErrorMsg('失败');
        return $this->outputFormat([], 100010);
    }

    /**
     * @param  Request  $request
     * @return array
     */
    public function getDistanceByLocation(Request $request)
    {
        $param = $this->getContentArray($request);
        $validator = Validator::make($param, [
            'from' => 'array',
            'to' => 'array',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }

        $addressLogic = new AddressLogic();

        $res = $addressLogic->getDistanceByLocation($param['from'], $param['to']);
        if ($res) {
            $this->setErrorMsg('success');
            return $this->outputFormat($res, 0);
        }
        $this->setErrorMsg('失败');
        return $this->outputFormat([], 100010);
    }

    /**
     * 根据经纬度获取百度省市区地址信息 longitude and latitude;
     * @param  Request  $request
     * @return array
     */
    public function getBaiduAddressByLonAndLat(Request $request)
    {
        $param = $this->getContentArray($request);

        $validator = Validator::make($param, [
            'lon' => 'required',
            'lat' => 'required',
        ]);
        if ($validator->fails()) {
            $this->setErrorMsg($validator->errors()->getMessages());
            return $this->outputFormat([], 400);
        }
        $v_lat = is_numeric($param['lat']) && $param['lat'] >= -90 && $param['lat'] <= 90;
        if (!$v_lat) {
            $this->setErrorMsg('lat 不合法');
            return $this->outputFormat([], 100011);
        }
        $v_lon = is_numeric($param['lon']) && $param['lon'] >= -180 && $param['lon'] <= 180;
        if (!$v_lon) {
            $this->setErrorMsg('lon 不合法');
            return $this->outputFormat([], 100012);
        }

        $addressLogic = new AddressLogic();

        $res = $addressLogic->getBaiduAddressByLonAndLat($param['lon'], $param['lat']);
        if ($res) {
            $this->setErrorMsg('success');
            return $this->outputFormat($res, 0);
        }
        $this->setErrorMsg('失败');
        return $this->outputFormat([], 100010);
    }
}
