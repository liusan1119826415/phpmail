<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/5/17
 * Time: 16:19
 */

namespace app\frontend\modules\cart\controllers;

use app\common\components\ApiController;
use app\common\components\BaseController;
use app\common\events\cart\PreGoodsVerifyEvent;
use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\Goods;
use app\common\models\Street;
use app\frontend\models\Member;
use app\frontend\modules\cart\models\DeliveryAddress;
use app\frontend\models\MemberCart;
use app\frontend\modules\member\services\MemberService;
use app\frontend\repositories\MemberAddressRepository;
use Illuminate\Support\Facades\Redis;

class PreGoodsVerifyController extends ApiController
{
    public function index()
    {
        if (!request()->input('goods_id')) {
            throw new AppException('参数错误');
        }

        if (!request()->input('address')) {
            throw new AppException('地址参数错误');
        }

        $goods_params = [
            'goods_id' => intval(request()->input('goods_id')),
            'total' => intval(request()->input('total',1)),
            'option_id' => intval(request()->input('option_id',0)),
            'member_id' => \YunShop::app()->getMemberId(),
        ];

        $deliveryAddress =  $this->getDeliveryAddress();

        /**
         * @var MemberCart $memberCart
         */
        $memberCart = new MemberCart($goods_params);

        $memberCart->setRelation('member', Member::current());
        $memberCart->setRelation('deliveryAddress',$deliveryAddress);

        //$memberCart->validate();

        event(new PreGoodsVerifyEvent($memberCart, request()));

        return $this->successJson('验证通过');
    }

    protected function requestAddress()
    {
        $address = request()->input('address');

        if (is_string($address)) {
            $address = json_decode(urldecode($address), true);
        }
        
        if (empty($address)) {
            $address = app(MemberAddressRepository::class)->getStaticModel()
                ->where('uid',\YunShop::app()->getMemberId())->first();
        } else {
            $address = app(MemberAddressRepository::class)->fill($address);
        }

        return $address;
    }

    /**
     * 获取用户配送地址模型
     * @return mixed
     * @throws AppException
     */
    protected function getDeliveryAddress()
    {
        $member_address = $this->requestAddress();
        $orderAddress = new DeliveryAddress();
        $orderAddress->mobile = $member_address->mobile;

//        if ($member_address->mobile && $member_address->country_code) {
//            MemberService::mobileValidate([
//                'mobile' => $member_address->mobile,
//                'state' => $member_address->country_code,
//            ]);
//        }
        if ($member_address->province) {
            $orderAddress->province_id = $member_address->province_id ?: Address::where('areaname', 'like', $member_address->province . '%')->where('level', 1)->value('id');
        } else {
            $orderAddress->province_id = $member_address->province_id ?: null;
        }
        $orderAddress->city_id = $member_address->city_id ?: Address::where('areaname', $member_address->city)->where('parentid', $orderAddress->province_id)->value('id');
        $orderAddress->district_id = $member_address->district_id ?: Address::where('areaname', $member_address->district)->where('parentid', $orderAddress->city_id)->value('id');
        $orderAddress->address = implode(' ', [$member_address->province, $member_address->city, $member_address->district, $member_address->address]);

        //todo 修复前端传street等于空字符串时会报错
        if (!empty($member_address->street) && $member_address->street != '其他') {
            $orderAddress->street_id = Street::where('areaname', $member_address->street)->where('parentid', $orderAddress->district_id)->value('id');
            if (!isset($orderAddress->street_id)) {
                throw new AppException('收货地址有误请重新保存收货地址',['address_error'=> 404]);
            }
            $orderAddress->street = $member_address->street;
            $orderAddress->address = implode(' ', [$member_address->province, $member_address->city, $member_address->district, $orderAddress->street, $member_address->address]);
        }
        $orderAddress->realname = $member_address->username;
        $orderAddress->province = $member_address->province;
        $orderAddress->city = $member_address->city;
        $orderAddress->district = $member_address->district;
        $orderAddress->longitude = $member_address->longitude ?: '';
        $orderAddress->latitude = $member_address->latitude ?: '';
        $orderAddress->country_code = $member_address->country_code ?: '';
        return $orderAddress;
    }
}