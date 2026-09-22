<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/5/23
 * Time: 下午3:55
 */

namespace app\frontend\modules\orderGoods\taxFee;

use app\common\models\goods\GoodsOptionModel;
use app\frontend\models\GoodsOption;
use app\frontend\models\orderGoods\PreOrderGoodsChildren;
use app\frontend\models\orderGoods\PreOrderGoodsDiscount;
use app\frontend\models\orderGoods\PreOrderGoodsTaxFee;
use app\frontend\modules\order\models\PreOrder;
use app\common\modules\orderGoods\models\PreOrderGoods;

abstract class BaseTaxFee
{
    /**
     * @var PreOrder
     */
    protected $orderGoods;
    /**
     * 税费名
     * @var string
     */
    protected $name;
    /**
     * 税费码
     * @var
     */
    protected $code;
    /**
     * @var float
     */
    private $amount;
    protected $weight;

    public function __construct(PreOrderGoods $orderGoods)
    {
        $this->orderGoods = $orderGoods;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取总金额
     * @return float|int
     */
    public function getAmount()
    {
        if (isset($this->amount)) {
            return $this->amount;
        }

        $this->amount = $this->_getAmount();
        if ($this->amount) {
            // 将抵扣总金额保存在订单优惠信息表中
            $preOrderGoodsTaxFee = new PreOrderGoodsTaxFee([
                'uniacid' => \YunShop::app()->uniacid,
                'fee_code' => $this->getCode(),
                'amount' => $this->amount ?: 0,
                'name' => $this->getName(),
            ]);
            $preOrderGoodsTaxFee->setOrderGoods($this->orderGoods);
        }


        //组合商品

        if ($this->orderGoods->goods->productType == 5) {
            $old_option = unserialize($this->orderGoods->goods->old_option);
            // 检查 unserialize 是否成功
            if ($old_option === false || empty($old_option)) {
                return;
            }
            // 提取所有的 option_id
            $optionIds = array_column($old_option, 'option_id');

            // 查询商品选项信息
            $goods_options = GoodsOption::whereIn('id', $optionIds)->with(['goods'])
                ->get(['id', 'thumb', 'product_price', 'goods_id', 'title', 'structure','product_model']);

            // 创建一个映射数组，将 num 和 components 信息关联到对应的 option_id
            $optionDataMap = [];
            foreach ($old_option as $option) {
                $components = $option['components'];

                // 为每个组件查询对应的 component_id
                $new_components = [];
                foreach ($components as $kk => $item) {
                    $componentId = GoodsOptionModel::where('option_id', $option['option_id'])
                        ->where('name', $item['component_name'])
                        ->value('id');
                    $new_components[$kk]['color_id'] = $item['color_id'];
                    $new_components[$kk]['component_id'] = $componentId;

                }

                $optionDataMap[$option['option_id']] = [
                    'num' => $option['num'],
                    'components' => $new_components
                ];
            }

            // 遍历商品选项，创建预订单商品子项

            foreach ($goods_options as $option) {

                $optionData = $optionDataMap[$option->id] ?? [
                        'num' => 1,
                        'components' => []
                    ];
                $in_data = [
                    'uniacid' => \YunShop::app()->uniacid,
                    'title' => $option->goods->title,
                    'thumb' => $option->thumb,
                    'price' =>$option->product_price,
                    'goods_price' => $option->product_price,
                    'goods_id' => $option->goods_id,
                    'total' => $optionData['num'],
                    'goods_option_title' => $option->title,
                    'goods_option_price' => $option->product_price,
                    'structure' => $option->structure,
                    'productType' => $option->goods->productType,
                    'type' => $option->goods->type2,
                    'product_sn'=>$option->product_model,
                    'components' => json_encode($optionData['components']),
                    'goods_option_id' => $option->id,
                    'floor_id'=> $this->orderGoods->floor_id,
                    'project_id'=> $this->orderGoods->project_id,
                    'space_id'=> $this->orderGoods->space_id,
                ];

                $preOrderGoodsChildren = new PreOrderGoodsChildren($in_data);

                $preOrderGoodsChildren->setOrderGoods($this->orderGoods);
            }
        }

        return $this->amount ?: 0;
    }

    /**
     * @return float
     */
    abstract protected function _getAmount();

}