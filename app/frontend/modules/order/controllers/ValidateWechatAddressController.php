<?php

namespace app\frontend\modules\order\controllers;

use app\common\components\ApiController;
use app\common\exceptions\ShopException;
use app\common\models\Address;

class ValidateWechatAddressController extends ApiController
{
    public function index()
    {
        $this->validate(['address' => 'required']);

        $address = request('address');
        foreach ($address as $addressKey => $areaname) {
            if (!$areaname) {
                return $this->errorJson('地址不完整！');
            }
            $has_address = $this->hasAddress($areaname, $addressKey);
            if (!$has_address) {
                return $this->errorJson('地址无法自动匹配请手动修改');
            }
        }
        return $this->successJson();
    }

    /**
     * 验证地址是否存在
     * @param $areaname
     * @param $levelName
     * @return mixed
     * @throws ShopException
     */
    protected function hasAddress($areaname, $levelName)
    {
        $level = 0;
        switch ($levelName) {
            case 'province':
                $level = 1;
                break;
            case 'city':
                $level = 2;
                break;
            case 'district':
                $level = 3;
                break;
        }
        if ($level == 0) {
            throw new ShopException('非法数据！');
        }
        return Address::where('areaname', $areaname)->where('level', $level)->exists();
    }

}
