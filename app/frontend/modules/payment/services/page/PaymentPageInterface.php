<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/19
 * Time: 17:52
 */

namespace app\frontend\modules\payment\services\page;

use app\common\helpers\Url;
use app\common\models\Goods;
use app\common\models\Order;
use app\common\models\OrderPay;
use app\common\modules\shop\ShopConfig;
use app\frontend\modules\payment\services\page\reward\PayPageRewardInterface;
use \Illuminate\Support\Collection;
use Yunshop\PayMarket\models\PageConfig;

abstract class PaymentPageInterface
{
    /**
     * @var Order
     */
    public $order;

    public $priority;

    public $code;

    /**
     * @var PageConfig
     */
    public $pageConfig;


    public $module = [
        'head'=> 'topModule',
        'order_info' => 'orderModule',
        'reward_list' => 'rewardModule',
        'carousel' => 'carouselModule',
        'goods' => 'goodsModule',
    ];

    abstract public function usable();

    abstract public function orderInfo();

    abstract public function goodsModule();

    abstract public function defaultDetailJumpLink();

    abstract public function defaultDetailJumpMinLink();


    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function setPriority($priority = 0)
    {
        $this->priority = $priority;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setUniqueCode($code)
    {
        $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function isFirstPage()
    {
        return $this->usable();
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    public function getShopId()
    {
        return 0;
    }

    protected function selectType()
    {
        return $this->code;
    }

    public function pluginSet()
    {
        if (!app('plugins')->isEnabled('pay-market')) {
            return null;
        }

        if (!isset($this->pageConfig)) {

            if ($this->getShopId()) {
                $this->pageConfig = PageConfig::concrete($this->selectType(), [0,$this->getShopId()]);
            } else {
                $this->pageConfig = PageConfig::concrete($this->selectType(), $this->getShopId());
            }

        }
        return $this->pageConfig;
    }

    public function configSet($key,$default = '')
    {
        if (is_null($this->pluginSet())) {
            return $default;
        }
        return $this->pluginSet()->getSet($key, $default);
    }




    public function topModule()
    {
        $data = [
            [
                'name' => $this->configSet('head_button_one',__('order.check_order')),
                'h5_link' => $this->configSet('head_button_one_link', $this->defaultDetailJumpLink()),
                'min_link' => $this->configSet('head_button_one_min_link', $this->defaultDetailJumpMinLink()),
            ],
            [
                'name' => $this->configSet('head_button_two',__('order.return_index')),
                'h5_link' => $this->configSet('head_button_two_link', $this->defaultHomeIndexJumpLink()),
                'min_link' => $this->configSet('head_button_two_min_link', '/packageG/index/index'),
            ],
        ];
        return $data;
    }

    public function carouselModule()
    {
        $carousel_array =  $this->configSet('carousel_array', []);

        $list = array_map(function ($item){
            if ($item['pic']) {
                $item['pic_url'] = yz_tomedia($item['pic']);
                unset($item['pic']);
                return $item;
            }
            return [];
        },$carousel_array);

        return array_values(array_filter($list));
    }

    public function rewardModule()
    {
        $rewardList = $this->rewardConfig()->map(function (PayPageRewardInterface $rewardClass) {
            if ($rewardClass->whetherHave()) {
               return $rewardClass->unifiedFormat();
            }
            return null;
        })->filter()->values();

        return $rewardList->toArray();
    }

    /**
     * @return Collection
     */
    public function rewardConfig()
    {
        $configs = ShopConfig::current()->get('shop-foundation.pay-page.reward');
        return collect($configs)->map(function ($configItem) {
            $class = call_user_func($configItem['class'], $this);
            $class->setUniqueCode($configItem['code']);
            return $class;
        })->sortByDesc(function ($class) {
            return $class->priority;
        });

    }

    public function orderModule()
    {
        $data = $this->orderInfo();

        $data = array_merge($data, [
            'order_sn'=> $this->getOrder()->order_sn,
            'price'=> $this->getOrder()->price,
            'pay_type_name' => $this->getOrder()->pay_type_name,
            'page_type' => $this->getCode(),
            'plugin_id' => $this->getOrder()->plugin_id,
            'order_id' => $this->getOrder()->id,
            'uid'=> $this->getOrder()->uid,
        ]);

        return $data;
    }



    public function externalParam()
    {
      return [];
    }

    public function openModuleKeys()
    {
        return $this->configSet('show_keys', array_keys($this->module));
    }

    final public function publicParam()
    {
        $data = array_merge($this->externalParam(), [
            'page_type' => $this->getCode(),
            'plugin_id' => $this->getOrder()->plugin_id,
            'order_id' => $this->getOrder()->id,
            'uid'=> $this->getOrder()->uid,
            'order_status'=> $this->getOrder()->status, // -1 已关闭 0 未支付 1 已支付
        ]);

        return $data;
    }

    public function toData()
    {
        $data = [];
        foreach ($this->module as $key => $method) {
            $data[$key] = $this->moduleData($key);
        }

        $data['public_param'] = $this->publicParam();

        return $data;
    }

    public function moduleData($key)
    {
        if (!in_array($key,$this->openModuleKeys())) {
            return [];
        }
        return $this->{$this->module[$key]}();
    }

    public function defaultHomeIndexJumpLink()
    {
        return Url::absoluteApp('home');
    }
}
