<?php

namespace app\common\modules\widget;


class Widget
{
    /**
     * @var self
     */
    static $current;

    private $items;

    /**
     * Income constructor.
     */
    public function __construct()
    {
        self::$current = $this;
    }

    static public function current()
    {
        if (!isset(self::$current)) {
            return new static();
        }
        return self::$current;
    }

    private function _getItems()
    {
        $result = [
            'vue-goods' => [
                'base' => [
                    'title' => '基础信息',
                    'class' => \app\backend\modules\goods\widget\GoodsWidget::class,
                ],
//                'param' => [
//                    'title' => '属性',
//                    'class' =>   \app\backend\modules\goods\widget\ParamWidget::class,
//                ],
                /*'describe' => [
                    'title' => '商品描述',
                    'class' =>   \app\backend\modules\goods\widget\DescribeWidget::class,
                ],*/
                'option' => [
                    'title' => '商品规格',
                    'class' =>   \app\backend\modules\goods\widget\OptionWidget::class,
                ],
                'dispatch' => [
                    'title' => '配送',
                    'class' =>   \app\backend\modules\goods\widget\DispatchWidget::class,
                ],
                'discount' => [
                    'title' => '折扣',
                    'class' =>   \app\backend\modules\goods\widget\DiscountWidget::class,
                ],
                'sale' => [
                    'title' => '营销',
                    'class' =>\app\backend\modules\goods\widget\SaleWidget::class,
                ],
                'notice' => [
                    'title' => '消息通知',
                    'class' =>\app\backend\modules\goods\widget\NoticeWidget::class,
                ],
                'imageLink' => [
                    'title' => '主图按钮',
                    'class' =>   \app\backend\modules\goods\widget\ImageLinkWidget::class,
                ],
                'filtering' => [
                    'title' => '商品标签',
                    'class' =>\app\backend\modules\goods\widget\FilteringWidget::class,
                ],
                'service' => [
                    'title' => '服务提供',
                    'class' =>\app\backend\modules\goods\widget\ServiceWidget::class,
                ],
                'div_from' => [
                    'title' => '表单',
                    'class' =>\app\backend\modules\goods\widget\DivFromWidget::class,
                ],
                'share' => [
                    'title' => '分享关注',
                    'class' => \app\backend\modules\goods\widget\ShareWidget::class,
                ],
                'privilege' => [
                    'title' => '权限',
                    'class' => \app\backend\modules\goods\widget\PrivilegeWidget::class,
                ],
                'coupon' => [
                    'title' => '优惠券',
                    'class' => \app\backend\modules\goods\widget\CouponWidget::class,
                ],
                'limitbuy' => [
                    'title' => '限时购',
                    'class' => \app\backend\modules\goods\widget\LimitBuyWidget::class,
                ],
                'invite_page' => [
                    'title' => '邀请页面',
                    'class' => \app\backend\modules\goods\widget\InvitePageWidget::class,
                ],
                'advertising' => [
                    'title' => '广告宣传语',
                    'class' => \app\backend\modules\goods\widget\AdvertisingWidget::class,
                ],
//                'spec_info' => [
//                    'title' => '规格信息',
//                    'class' => \app\backend\modules\goods\widget\SpecInfoWidget::class,
//                ],
                'trade_set' => [
                    'title' => '交易设置',
                    'class' => \app\backend\modules\goods\widget\TradeSetWidget::class,
                ],
                'contact_tel' => [
                    'title' => '联系电话',
                    'class' => \app\backend\modules\goods\widget\ContactTelWidget::class,
                ],
            ],
            'vue-withdraw' => [
                'balance'=> [
                    'title' => '余额提现',
                    'class' => \app\backend\modules\withdraw\widget\BalanceWithdrawWidget::class,
                ],
                'income'=> [
                    'title' => '收入提现基础设置',
                    'class' => \app\backend\modules\withdraw\widget\IncomeWithdrawWidget::class,
                ],
                'notice'=> [
                    'title' => '收入提现通知',
                    'class' => \app\backend\modules\withdraw\widget\NoticeWithdrawWidget::class,
                ],
            ],
            'withdraw' => [
                'income' => [
                    'title' => '收入提现基础设置',
                    'class' => 'app\backend\widgets\finance\IncomeWidget',
                ],
                'notice' => [
                    'title' => '收入提现通知',
                    'class' => 'app\backend\widgets\finance\WithdrawNoticeWidget',
                ]
            ],
            'member' => [

            ],
            //vue统一订单详情页显示挂件
            'order_detail' => [
                'order_tax_fees' => [
                    'title' => '含税',
                    'class' => 'app\backend\widgets\order\detail\TaxFeesWidget',
                ],
            ],
            'lang' => [
                'shop' => [
                    'title' => '语言设置',
                    'class' => \app\backend\widgets\lang\ShopLangWidget::class,
                ],
            ],
            'pay_set' => [
                'weChat' => [
                    'title' => '微信支付',
                    'class' => \app\backend\modules\setting\payment\WechatPayWidget::class,
                ],
                'alipay' => [
                    'title' => '支付宝支付',
                    'class' => \app\backend\modules\setting\payment\AlipayPayWidget::class,
                ],
                'integrationPay' => [
                    'title' => '聚合支付',
                    'class' => \app\backend\modules\setting\payment\IntegrationPayWidget::class,
                ],
                'payBehalf' => [
                    'title' => '找人代付',
                    'class' => \app\backend\modules\setting\payment\PayBehalfPayWidget::class,
                ],
                'balance' => [
                    'title' => '余额支付',
                    'class' => \app\backend\modules\setting\payment\BalanceWidget::class,
                ],
                'cashOnDelivery' => [
                    'title' => '货到付款',
                    'class' => \app\backend\modules\setting\payment\CashOnDelivery::class,
                ],
                'bankTransfer' => [
                    'title' => '银行转账',
                    'class' => \app\backend\modules\setting\payment\BankTransferPayWidget::class,
                ],
                'other' => [
                    'title' => '其他设置',
                    'class' => \app\backend\modules\setting\payment\OtherPayWidget::class,
                ],
            ],
        ];
        $plugins = app('plugins')->getEnabledPlugins('*');
        foreach ($plugins as $plugin) {
            foreach ($plugin->app()->getWidgetItems() as $key => $item) {
                array_set($result, $key, $item);
            }
        }

        return $result;
    }

    public function getItems()
    {
        if (!isset($this->items)) {
            $this->items = $this->_getItems();
        }
        return $this->items;
    }

    public function getItem($key)
    {
        return array_get($this->getItems(), $key);
    }

    public function clearItems()
    {
        $this->items = null;
    }
}
