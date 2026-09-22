<?php
/**
 * Created by PhpStorm.
 * User: 17812
 * Date: 2020/4/28
 * Time: 10:07
 */

namespace app\frontend\modules\member\controllers;

use app\common\components\ApiController;

class ServiceController extends ApiController
{

    public function api()
    {
        $pluginType = request()->input('plugin');

        if ($pluginType == 'store') {
            $store_id = intval(request()->input('store_id'));

            $customer_data = $this->store_set($store_id, request()->input('type'));
            if (!$customer_data['mark']) {
                $store_service = \Yunshop\StoreCashier\store\models\StoreService::where("store_id",$store_id)->first();
                if ($store_service) {
                    $customer_data['cservice'] = request()->input('type') == 2 ? $store_service['mini_service'] : $store_service['service'];
                }
            }

        } else {
            $customer_data = $this->index();

        }

        $customer_data['cservice'] = $customer_data['cservice'] ? : $this->getShopSet();
        unset($customer_data['mark']);
        return $this->successJson('customer service', $customer_data);
    }

    /**
     * 获取商品默认客服配置
     * @return mixed
     */
    public function getShopSet()
    {
        if (request()->type == 2) {
            $cservice = \Setting::get('shop.shop')['cservice_mini'] ?: '';
        } else {
            $cservice = \Setting::get('shop.shop')['cservice'];
        }
        return $cservice;
    }

    public function index()
    {
        $res = [];
        if (app('plugins')->isEnabled('customer-service')) {
            $set = array_pluck(\Setting::getAllByGroup('customer-service')->toArray(), 'value', 'key');
            if ($set['is_open'] == 1) {
                if (request()->type == 2) {
                    $arr = $this->miniSetting($set);
                }else{
                    $arr = $this->getSetting($set);
                }
                $res = $arr;
            }
        }
        return $res;
    }

    public function supplier_set($uid,$type)
    {
        $res = ['mark'=>false];
        if (app('plugins')->isEnabled('customer-service')) {
            $set = array_pluck(\Setting::getAllByGroup('customer-service')->toArray(), 'value', 'key');
            if ($set['is_open'] == 1) {
                $supplierSet = \Setting::get('plugin.supplier.customer[' . $uid . ']');
                if($supplierSet['is_open'] == 1)
                {
                    if ($type == 2) {
                        $arr = $this->miniSetting($supplierSet);
                    }else{
                        $arr = $this->getSetting($supplierSet);
                    }
                }else{
                    if ($type == 2) {
                        $arr = $this->miniSetting($set);
                    }else{
                        $arr = $this->getSetting($set);
                    }
                }
                $res = array_merge($arr,['mark'=>true]);
            }
        }
        return $res;
    }

    public function store_set($store_ID,$type)
    {
        $res = ['mark'=>false];
        if (app('plugins')->isEnabled('customer-service')) {
            $set = array_pluck(\Setting::getAllByGroup('customer-service')->toArray(), 'value', 'key');
            if ($set['is_open'] == 1) {
                $storeSet = \Setting::get('plugin.store.customer[' . $store_ID . ']');
                if($storeSet['is_open'] == 1) {
                    if ($type == 2) {
                        $arr = $this->miniSetting($storeSet);
                    }else{
                        $arr = $this->getSetting($storeSet);
                    }
                }else{
                    if ($type == 2) {
                        $arr = $this->miniSetting($set);
                    }else{
                        $arr = $this->getSetting($set);
                    }
                }
                $res = array_merge($arr,['mark'=>true]);
            }
        }
        return $res;
    }
    
    public function getSetting($set)
    {
         return [
             'cservice'=>$set['link'],
             'service_QRcode' => yz_tomedia($set['QRcode']),
             'service_mobile' => $set['mobile']
         ];
    }

    public function miniSetting($set)
    {
        return [
            'cservice'=>$set['mini_link'],
            'customer_open'=>$set['mini_open'],
            'service_QRcode' => yz_tomedia($set['mini_QRcode']),
            'service_mobile' => $set['mini_mobile']
        ];
    }
}