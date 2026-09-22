<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/3
 * Time: 下午2:35
 */

namespace app\frontend\modules\goods\services;


use app\common\models\Goods;
use app\framework\Database\Eloquent\Collection;
use app\frontend\modules\goods\price\PriceModuleBase;

class GoodsService
{
    /**
     * @var Goods
     */
    public $goods;

    public function __construct(Goods $goods)
    {
        $this->goods = $goods;
    }

    public function getFrontendWidgetConfig(): array
    {
        return [
            'goods_list.base' => GoodsBaseOtherTemplate::class,
        ];
    }

    public function otherData()
    {

        $config =  $this->getFrontendWidgetConfig();

        $collection = collect($config)->map(function ($class) {
            $otherClass = new $class($this->goods);
            return $otherClass;
        });

        $collection->each(function (GoodsBaseOtherTemplate $otherClass) {

            $otherClass->mergeExtraData();
        });

//        //特殊商品
//        if (app('plugins')->isEnabled('special-goods')) {
//            $this->goods->is_special = \Yunshop\SpecialGoods\services\SpecialGoodsShop::isSpecial(
//                $this->goods->id,
//                $this->goods->plugin_id
//            );
//        }
//
//        if (app('plugins')->isEnabled('pass-price') && \Setting::get('pass-price.set.plugin_enable')) {
//            $this->goods->pass_price = [
//                'price' => bcmul($this->goods->price, \Setting::get('pass-price.set.pass_price'), 2),
//                'name'  => \Setting::get('pass-price.set.diy_name') . '价',
//            ];
//        }
    }

    public function getPriceModuleConfig()
    {
        return [
            [
                'priority'=> 0,
                'class' => \app\frontend\modules\goods\price\DefaultPriceModule::class,
            ],
            [
                'priority'=> 1,
                'class' => \app\frontend\modules\goods\price\PointPriceModule::class,
            ],
            [
                'priority'=> 0,
                'class' => \app\frontend\modules\goods\price\VipPriceModule::class,
            ],
        ];
    }

    public function priceModule()
    {
        $configs =  $this->getPriceModuleConfig();

        $priceModuleClass = collect($configs)->map(function ($configItem) {
            return new $configItem['class']($this->goods,$configItem['priority']);
        })->groupBy(function (PriceModuleBase $class) {
            return $class->group();
        })->map(function ($group) {
            return $group->sortByDesc(function (PriceModuleBase $class) {
                return $class->getPriority();
            })->first(function (PriceModuleBase $class) {
                return $class->isDisplay();
            });
        })->filter();


        $this->goods->price_module = $priceModuleClass->map(function (PriceModuleBase $class) {
                return $class->priceFormat();
        })->values()->toArray();
    }
}