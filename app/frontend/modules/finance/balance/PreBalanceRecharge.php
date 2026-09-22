<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/12
 * Time: 15:25
 */

namespace app\frontend\modules\finance\balance;

use app\common\exceptions\AppException;
use app\common\models\BaseModel;
use app\common\models\finance\BalanceRechargeRequest;
use app\frontend\modules\finance\services\BalanceRechargeSetService;
use app\frontend\modules\finance\services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use app\common\models\Member;
use app\frontend\modules\finance\models\BalanceRecharge;
use Illuminate\Support\Collection;

class PreBalanceRecharge extends BalanceRecharge
{

    /**
     * @var Member
     */
    public $member;
    /**
     * @var Request
     */
    public $request;


    public $rechargeRecord;

    /**
     * @var PriceCalculator
     */
    public $priceCalculator;


    public $attributes;

    public function init(Member $member, Request $request)
    {
        $this->setRequest($request);
        $this->setMember($member);

        $this->beforeCalculate();

        $this->initAttributes();

        $this->afterCalculate();


        return $this;
    }

    public function beforeCalculate()
    {

    }

    public function afterCalculate()
    {

    }

    public function initAttributes()
    {
        $attributes = [
            'uniacid' => \YunShop::app()->uniacid,
            'money' => $this->getRechargeAmount(),
            'new_money' => $this->getRechargeAmount() + $this->old_money,
            'actual_pay' => $this->getActualPayAmount(),
            'ordersn' =>  createNo('RV'),
            'type' => $this->getPayType(),
            'status' => BalanceRecharge::PAY_STATUS_ERROR,
            'remark' => '会员前端充值',
        ];

        $this->fill($attributes);
    }

    public function getCalculator()
    {
        if (!isset($this->priceCalculator)) {
            $this->priceCalculator = new PriceCalculator($this);
        }
        return $this->priceCalculator;
    }

    public function getActualPayAmount()
    {
        return $this->getCalculator()->getActualPayAmount();
    }

    public function getPayType()
    {
        return intval($this->getRequest()->input('pay_type'));
    }

    public function getRechargeAmount()
    {
        $change_money = $this->getRequest()->input('recharge_money');
        return bankerRounding($change_money);
    }


    /**
     * @return bool|void
     * @throws AppException
     */
    public function beforeSaving()
    {
        //验证支付限制单笔最大充值金额
        $verify = (new BalanceRechargeSetService())->verifyRecharge($this->getPayType(), $this->getRechargeAmount());
        if ($verify !== true) {
            throw new AppException($verify);
        }

        $this->setAttribute('ordersn', BalanceRecharge::createOrderSn('RV', 'ordersn'));

        $this->setRequestLog($this->getRequest()->input());

        $this->loadPluginsRelations();
    }

    public function afterSaving()
    {

    }

    //订单插件关系验证和关系保存
    protected function loadPluginsRelations()
    {
        $relations = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.balance.recharge-save-relations');
        foreach ($relations as $relation) {

            if ($relation['class']) {
                $relationModel = call_user_func($relation['class'], []);
                $relationModel->setOrder($this);
                if ($relationModel->validateSave()) {
                    $this->setRelation($relation['key'], $relationModel);
                }
            }
        }
    }

    /**
     * 这里保存请求参数，记录余额充值参让了哪些活动
     * @param array $input
     */
    public function setRequestLog(array $input)
    {
        $requestParam = new BalanceRechargeRequest(['uniacid' => \YunShop::app()->uniacid]);
        $requestParam->request = $input;
        $requestParam->request_ip = $this->request->getClientIp();
        $this->setRelation('rechargeRequest', $requestParam);

    }

    /**
     * @param $member
     */
    public function setMember($member)
    {
        $this->member = $member;
        $this->member_id = $this->member->uid;
        $this->old_money = $this->member->credit2 ?: 0;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        if (!isset($this->request)) {
            $this->request = request();
        }
        return $this->request;
    }

    /**
     * 关联优惠抵扣
     * @return mixed
     */
    public function getDiscounts()
    {
        if (!$this->getRelation('discounts')) {
            $this->setRelation('discounts', $this->newCollection());
        }
        return $this->discounts;
    }

    /**
     * 关联优惠抵扣
     * @return mixed
     */
    public function getCoupons()
    {
        if (!$this->getRelation('coupons')) {
            $this->setRelation('coupons', $this->newCollection());
        }
        return $this->coupons;
    }


    public function generate()
    {

        try {
            DB::beginTransaction();

            $this->beforeSaving();
            $this->save();
            $this->afterSaving();
            $result = $this->push();

            if ($result === false) {
                throw new AppException('余额充值相关信息保存失败');
            }

            DB::commit();

            return $this;
        } catch (\Exception $e) {

            DB::rollBack();
            \Log::error('recharge-record-save-error:'.$e->getMessage().' error file in '.$e->getFile().'('.$e->getLine().')');
            throw new AppException($e->getMessage());
        }
    }

    /**
     * @var array 需要批量更新的字段
     */
    private $batchSaveRelations = ['discounts', 'coupons'];

    /**
     * 保存关联模型
     * @return bool
     * @throws \Exception
     */
    public function push()
    {
        foreach ($this->relations as $models) {
            $models = $models instanceof Collection
                ? $models->all() : [$models];
            /**
             * @var BaseModel $model
             */
            foreach (array_filter($models) as $model) {
                if (!isset($model->recharge_id) && $model->hasColumn('recharge_id')) {
                    $model->recharge_id = $this->id;
                }

            }
        }

        /**
         * 一对一关联模型保存
         */
        $relations = array_except($this->relations, $this->batchSaveRelations);
        foreach ($relations as $models) {
            $models = $models instanceof Collection
                ? $models->all() : [$models];

            foreach (array_filter($models) as $model) {
                if (!$model->push()) {
                    return false;
                }
            }
        }
        /**
         * 多对多关联模型保存
         */
        $this->insertRelations($this->batchSaveRelations);

        return true;
    }

    /**
     * 保存每一种 多对多的关联模型集合
     * @param array $relations
     */
    private function insertRelations($relations = [])
    {

        foreach ($relations as $relation) {
            if ($this->$relation->isNotEmpty()) {
                $this->saveManyRelations($relation);
            }
        }
    }

    /**

     * 保存一种 多对多的关联模型集合
     * @param $relation
     */
    private function saveManyRelations($relation)
    {
        $attributeItems = $this->$relation->map(function (BaseModel $relation) {
            $relation->updateTimestamps();

            $beforeSaving = $relation->beforeSaving();
            if ($beforeSaving === false) {
                return [];
            }
            return $relation->getAttributes();
        });
        $attributeItems = collect($attributeItems)->filter();

        $this->$relation->first()->insert($attributeItems->toArray());
        /**
         * @var Collection $ids
         */
        $ids = $this->$relation()->pluck('id');
        $this->$relation->each(function (BaseModel $item) use ($ids) {
            $item->id = $ids->shift();
            $item->afterSaving();
        });
    }
}