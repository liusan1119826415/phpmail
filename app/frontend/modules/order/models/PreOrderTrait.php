<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/13
 * Time: 下午4:14
 */

namespace app\frontend\modules\order\models;


use app\common\events\order\BeforeOrderCreateEvent;
use app\common\exceptions\AppException;
use app\common\models\Goods;
use app\common\models\goods\ReturnAddress;
use app\common\models\project\Logistics;
use app\frontend\modules\orderGoods\models\PreOrderGoodsCollection;

/**
 * Trait PreOrderTrait
 * @package app\frontend\modules\order\models
 * @property PreOrderGoodsCollection orderGoods
 */
trait PreOrderTrait
{
    /**
     * 订单插入数据库,触发订单生成事件
     * @return mixed
     * @throws AppException
     */
    public function generate($order_main_id)
    {
        event(new BeforeOrderCreateEvent($this));
        $this->beforeSaving();
        $this->parent_id = $order_main_id;
        unset($this->contact_info, $this->delivery_address,$this->service_link);
        $this->save();
        $this->afterSaving();
        $result = $this->push();
        if ($result === false) {
            throw new AppException('订单相关信息保存失败');
        }
        return $this->id;
    }


    /**
     * 统计商品总数
     * @return int
     */
    protected function getGoodsTotal()
    {
        //累加所有商品数量
        $result = $this->orderGoods->sum(function ($aOrderGoods) {
            return $aOrderGoods->total;
        });

        return $result;
    }

    /**
     * 统计订单商品成交金额
     * @return int
     */
    public function getOrderGoodsPrice()
    {
        return $this->goods_price = $this->orderGoods->getPrice();
    }

    /**
     * 统计订单商品会员价金额
     * @return int
     */
    public function getVipOrderGoodsPrice()
    {
        //订单禁用优惠返回，商品现价
        if ($this->isDiscountDisable()) {
            return $this->orderGoods->getPrice();
        }

        return $this->orderGoods->getVipPrice();
    }


    /**
     * 统计订单商品原价
     * @return int
     */
    public function getGoodsPrice()
    {
        return $this->orderGoods->getGoodsPrice();
    }

    /**
     * 计算预付款物流运费
     * @return float
     */
    public function getPrepaidFreight()
    {
        $volume = $this->getTotalVolume();
        $supplier_id = Goods::where('id',$this->orderGoods->first()->goods->id)->value('supp_id');
        $return_address = ReturnAddress::where('supplier_id',$supplier_id)->where('is_default',1)->where('is_refund',2)->first();
        $Logistics = Logistics::where('province_id',$return_address->province_id)->where('status',1)->first();
        $freight_data = unserialize($Logistics->freight);
        $freight_collection = collect($freight_data);
        $match = $freight_collection->firstWhere('id', $this->orderAddress->province_id);
        if($match){
            $unit_volume = $match['volume'];
            $unit_price = $match['price'];

            $freight_price = ceil($volume / $unit_volume) * $unit_price;
            return $freight_price;
        }else{
            return 0;
        }


    }



    /**
     * 安装费用
     * @return float
     */
    public function getPrepaidInstallFee()
    {
       return $this->orderGoods->getTotalInstallFee();
    }

    /**
     * 获取总体积
     * @return float
     */
    public function getTotalVolume()
    {
          return $this->orderGoods->getTotalVolume();
    }



    public function getPriceAttribute()
    {
        return $this->getPrice();
    }

    public function getDispatchPriceAttribute()
    {
        return $this->getDispatchAmount();
    }
}