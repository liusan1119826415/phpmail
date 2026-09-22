<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/24
 * Time: 14:21
 */

namespace app\frontend\modules\payment\services\page\reward;


use app\frontend\modules\payment\services\page\PaymentPageInterface;

abstract class PayPageRewardInterface
{

    protected $amount;
    /**
     * @var PaymentPageInterface
     */
    public $page;

    public $priority = 0;

    public $code;

    public function __construct(PaymentPageInterface $page)
    {
        $this->page = $page;
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

    /**
     * @return \app\common\models\Order
     */
    public function getOrder()
    {
        return $this->page->getOrder();
    }

    public function unifiedFormat()
    {
        return [
            'code' => $this->getCode(),
            'title' => $this->getTitle(),
            'icon' => $this->getIcon(),
            'amount' => $this->getAmount(),
            'desc' => $this->getDesc(),
            'is_after_pay'=> $this->afterPayGive(),
            'unify_string' => $this->unifyString(),
            'url' => $this->getUrl(),
            'mini_Url' => $this->getMiniUrl(),
            'data'=> $this->arrayData(),
        ];
    }

    public function unifyString()
    {
        return "{$this->getAmount()} {$this->getTitle()}";
    }

    public function arrayData()
    {
        return [];
    }

    public function getAmount()
    {
        if (!isset($this->amount)) {
            $this->amount = $this->_getAmount();
        }

        return $this->amount;
    }

    /**
     * @return float
     */
    abstract function _getAmount();

    /**
     * @return boolean
     */
    abstract function whetherHave();

    /**
     * @return string
     */
    abstract function getTitle();

    /**
     * @return string
     */
    abstract function getDesc();


    /**
     * 图标
     * @return string
     */
    abstract public function getIcon();

    /**
     * H5页面路由
     * @return string
     */
    abstract public function getUrl();

    /**
     * 小程序链接
     * @return string
     */
     abstract public function getMiniUrl();

    /**
     * @return integer 0 or 1
     */
    abstract function afterPayGive();


}