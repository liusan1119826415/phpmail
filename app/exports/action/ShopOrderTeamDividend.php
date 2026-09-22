<?php

namespace app\exports\action;

use app\backend\modules\goods\models\Category;
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\order\models\OrderGoods;
use app\backend\modules\order\models\VueOrder;
use Yunshop\Diyform\models\DiyformDataModel;
use Yunshop\Diyform\models\DiyformTypeModel;
use Yunshop\Diyform\models\OrderGoodsDiyForm;
use Yunshop\GoodsSource\common\models\GoodsSet;
use Yunshop\PackageDelivery\models\DeliveryOrder;
use Yunshop\TeamDividend\models\TeamDividendLevelModel;

class ShopOrderTeamDividend extends BaseAction
{
    public $type = 'ShopOrderTeamDividend';

    public $name = '订单（直推经销商）';

    public function preResult($header,$columns): array
    {
        $level_list = [];
        if (app('plugins')->isEnabled('team-dividend')) {
            $team_list = TeamDividendLevelModel::getList()->get();
            foreach ($team_list as $level) {
                $level_list[$level->id] = $level->level_name;
            }
        }
        $header = $level_list + $header;
        $columns = array_merge(array_keys($level_list), $columns);
        return [$header,$columns];
    }


    public function getData($request): \Generator
    {
        $code = $request['code'] ?? 'all';
        $search = $request['search'];
        $order_model = VueOrder::uniacid()->statusCode($code)->orders($search);
        if (request()->search['source_id']) {
            $set_goods_id = GoodsSet::where('source_id', request()->search['source_id'])->pluck('goods_id')->all();
            $order_ids = OrderGoods::uniacid()->whereIn('goods_id', $set_goods_id)->pluck('order_id')->unique()->all();
            $order_model->whereIn('yz_order.id', $order_ids);
        }
        $orders = $order_model->with(['discounts', 'deductions',
            'hasManyParentTeam' => function ($q) {
                if (app('plugins')->isEnabled('team-dividend')) {
                    $q->whereHas('hasOneTeamDividend')
                        ->with([
                            'hasOneTeamDividend' => function ($q) {
                                $q->with(['hasOneLevel']);
                            }
                        ])
                        ->with('hasOneMember')
//                        ->orderBy('id', 'desc')
                        ->orderBy('level', 'asc');
                } else {
                    $q->with('hasOneMember')
                        ->orderBy('level', 'asc');
                }
            },
            'orderInvoice']);
        if (app('plugins')->isEnabled('team-dividend')) {
            $team_list = TeamDividendLevelModel::getList()->get();
            foreach ($team_list as $level) {
                $level_name[] = $level->level_name;
                $levelId[] = $level->id;
            }
        }

        if (app('plugins')->isEnabled('goods-contribution')) {
            $orders->with([
                'hasOneGoodsContributionOrder' => function ($query) {
                    $query->select('order_id', 'amount', 'status');
                },
            ]);
        }

        $category = new Category();

        $export_row_data = [];
        foreach ($orders->get() as $key => $item) {
            $item = $item->toArray();
            $address = explode(' ', $item['address']['address']);
            $fistOrder = $item['has_many_first_order'] ? '首单' : '';
            $refundedCollect = \app\common\models\refund\RefundApply::getAfterSales($item['id'])
                ->orderBy('id', 'desc')
                ->get();
            $level = $this->getLevel($item, $levelId);
            if ($refundedCollect->isEmpty()) {
                $refund_name = '';
            } else {
                $refund_name = $refundedCollect->first()->part_refund == \app\common\models\refund\RefundApply::PART_REFUND ? '部分退款' : '';
            }

            //拼接多包裹快递信息
            $expressInfo = $this->getExpressString($item['expressmany']);
            $clerk_info = $this->getAuditor($item);

            $goodsInfo = $this->getGoods($item, '', $category);
            yield $key => $level + [
                    'id' => $item['id'],
                    'order_sn' => $item['order_sn'],
                    'pay_sn' => $item['has_one_order_pay']['pay_sn'],
                    'uid' => $item['belongs_to_member']['uid'],
                    'nickname' => $this->getNickname($item['belongs_to_member']['nickname']) ?: substr_replace(
                        $item['belongs_to_member']['mobile'],
                        '*******',
                        2,
                        7
                    ),
                    'address_name' => $item['address']['realname'],
                    'mobile' => $item['address']['mobile'],
                    'province' => !empty($address[0]) ? $address[0] : '',
                    'city' => !empty($address[1]) ? $address[1] : '',
                    'area' => !empty($address[2]) ? $address[2] : '',
                    'address' => $item['address']['address'],
                    'goods_title' => $goodsInfo['goods_title'],
                    'alias' => $goodsInfo['alias'],
                    'goods_sn' => $goodsInfo['goods_sn'],
                    'product_sn' => $goodsInfo['product_sn'],
                    'total' => $goodsInfo['total'],
                    'first_cate' => $goodsInfo['first_cate'],
                    'second_cate' => $goodsInfo['second_cate'],
                    'third_cate' => $goodsInfo['third_cate'],
                    'vip_price' => $goodsInfo['vip_price'],
                    'pay_type_name' => $item['pay_type_name'],
                    'deduction' => $this->getExportDiscount($item, 'deduction'),
                    'coupon' => $this->getExportDiscount($item, 'coupon'),
                    'enoughReduce' => $this->getExportDiscount($item, 'enoughReduce'),
                    'singleEnoughReduce' => $this->getExportDiscount($item, 'singleEnoughReduce'),
                    'goods_price' => $item['goods_price'],
                    'dispatch_price' => $item['dispatch_price'],
                    'price' => $item['price'],
                    'cost_price' => $goodsInfo['cost_price'],
                    'status_name' => $item['status_name'],
                    'create_time' => $item['create_time'],
                    'pay_time' => !empty(strtotime($item['pay_time'])) ? $item['pay_time'] : '',
                    'send_time' => !empty(strtotime($item['send_time'])) ? $item['send_time'] : '',
                    'finish_time' => !empty(strtotime($item['finish_time'])) ? $item['finish_time'] : '',
                    'company' => $expressInfo['company'],
                    'express_sn' => $expressInfo['sn'],
                    'remark' => $item['has_one_order_remark']['remark'],
                    'note' => $this->checkStr($item['note']),
                    'fistOrder' => $fistOrder,
                    'realname' => !empty($item['has_many_member_certified']['realname']) ? $item['has_many_member_certified']['realname'] : $item['belongs_to_member']['realname'],
                    'idcard' => !empty($item['has_many_member_certified']['idcard']) ? ' ' . $item['has_many_member_certified']['idcard'] : ' ' . $item['belongs_to_member']['idcard'],
                    'auditor' => $clerk_info['auditor'],
                    'additional' => $clerk_info['additional'],
                    'export_refund_name' => $this->getExportRefundName($item) ?: $refund_name,
                    'invoice_type' => $this->invoiceType($item['order_invoice']['invoice_type']),
                    // 发票类型
                    'rise_type' => ($item['order_invoice']['rise_type'] == 1) ? '个人' : '单位',
                    // 发票抬头
                    'collect_name' => empty($item['order_invoice']['collect_name']) ? '' : $item['order_invoice']['collect_name'],
                    // 单位/抬头名称
                    'gmf_taxpayer' => empty($item['order_invoice']['gmf_taxpayer']) ? $item['order_invoice']['company_number'] : $item['order_invoice']['gmf_taxpayer'],
                    // 税号
                    'content' => empty($item['order_invoice']['content']) ? '' : $item['order_invoice']['content'],
                    // 发票内容
                    'gmf_bank' => empty($item['order_invoice']['gmf_bank']) ? '' : $item['order_invoice']['gmf_bank'],
                    // 开户银行
                    'gmf_bank_admin' => empty($item['order_invoice']['gmf_bank_admin']) ? '' : $item['order_invoice']['gmf_bank_admin'],
                    // 银行账号
                    'gmf_address' => empty($item['order_invoice']['gmf_address']) ? '' : $item['order_invoice']['gmf_address'],
                    // 注册地址
                    'gmf_mobile' => empty($item['order_invoice']['gmf_mobile']) ? '' : $item['order_invoice']['gmf_mobile'],
                    // 注册电话
                    'col_name' => empty($item['order_invoice']['col_name']) ? '' : $item['order_invoice']['col_name'],
                    // 收票人姓名
                    'col_mobile' => empty($item['order_invoice']['col_mobile']) ? '' : $item['order_invoice']['col_mobile'],
                    // 收票人电话
                    'col_address' => empty($item['order_invoice']['col_address']) ? '' : $item['order_invoice']['col_address'],
                    // 收票人地址
                    'email' => empty($item['order_invoice']['email']) ? '' : $item['order_invoice']['email'],
                    // 邮箱
                    'delivery_day' => $item['address']['delivery_day'],
                    'delivery_time' => $item['address']['delivery_time'],

                    'goodsContribution' => bcadd($item['has_one_goods_contribution_order']['amount'], 0, 2),
                    'goodsContributionStatus' => $item['has_one_goods_contribution_order'] ? \Yunshop\GoodsContribution\models\PluginOrder::STATUS_NAME[$item['has_one_goods_contribution_order']['status']] : '',
                ];
        }
    }


    public function getLevel($member, $levelId)
    {
        $data = [];
        foreach ($levelId as $k => $value) {
            foreach ($member['has_many_parent_team'] as $key => $parent) {
                if ($parent['has_one_team_dividend']['has_one_level']['id'] == $value) {
                    $data[$value] = $parent['has_one_member']['nickname'] . ' ' . $parent['has_one_member']['realname'] . ' ' . $parent['has_one_member']['mobile'];
                    break;
                }
            }
            $data[$value] = $data[$value] ?: '';
        }

        return $data;
    }

    protected function getExpressString($express)
    {
        $company = array_column($express, 'express_company_name');
        $sn = array_column($express, 'express_sn');
        $companyStr = '';
        $snStr = '';
        if (count($company) == 1) {
            $companyStr = $company[0];
        } else {
            foreach ($company as $val) {
                $companyStr .= '【' . $val . '】 ';
            }
        }
        if (count($sn) == 1) {
            $snStr = $sn[0];
        } else {
            foreach ($sn as $val) {
                $snStr .= '【' . $val . '】 ';
            }
        }
        return ['company' => $companyStr, 'sn' => $snStr];
    }

    //订单导出核销员显示
    protected function getAuditor($order)
    {
        //自提点
        if ($order['dispatch_type_id'] == \app\common\models\DispatchType::PACKAGE_DELIVER && app('plugins')->isEnabled(
                'package-deliver'
            )) {
            $deliverOrder = \Yunshop\PackageDeliver\model\PackageDeliverOrder::where('order_id', $order['id'])
                ->with(['hasOneDeliver', 'hasOneDeliverClerk'])
                ->first();

            $deliver_name = $deliverOrder->deliver_id . '-' . $deliverOrder->hasOneDeliver->deliver_name;

            if ($deliverOrder->hasOneDeliverClerk) {
                $package_deliver_name = "[{$deliverOrder->hasOneDeliver->deliver_name}]" . $deliverOrder->hasOneDeliverClerk->realname . "[{$deliverOrder->hasOneDeliverClerk->uid}]";
            } elseif ($order['status'] == 3) {
                $package_deliver_name = '后台确认';
            } else {
                $package_deliver_name = '';
            }

            return ['auditor' => $package_deliver_name, 'additional' => $deliver_name];
        }

        //商城自提
        if ($order['dispatch_type_id'] == \app\common\models\DispatchType::PACKAGE_DELIVERY && app(
                'plugins'
            )->isEnabled('package-delivery')) {
            $deliveryOrder = DeliveryOrder::where('order_id', $order['id'])
                ->first();


            $shopName = \Setting::get('shop.shop.name') ?: '自营';

            if ($deliveryOrder->hasOneClerk) {
                $package_deliver_name = "[{$shopName}]" . $deliveryOrder->hasOneClerk->nickname . "[{$deliveryOrder->hasOneClerk->uid}]";
            } elseif ($order['status'] == 3) {
                $package_deliver_name = '后台确认';
            } else {
                $package_deliver_name = '';
            }
            return ['auditor' => $package_deliver_name, 'additional' => $shopName];
        }


        //门店自提
        if ($order['dispatch_type_id'] == \app\common\models\DispatchType::SELF_DELIVERY && app('plugins')->isEnabled(
                'store-cashier'
            )) {
            $storeOrder = \Yunshop\StoreCashier\common\models\StoreOrder::where('order_id', $order['id'])->first();

            if ($storeOrder->hasOneClerkMember) {
                $package_deliver_name = "[{$storeOrder->hasOneStore->store_name}]" . $storeOrder->hasOneClerkMember->nickname . "[{$storeOrder->hasOneClerkMember->uid}]";
            } elseif ($order['status'] == 3) {
                $package_deliver_name = '后台确认';
            } else {
                $package_deliver_name = '';
            }
            return [
                'auditor' => $package_deliver_name,
                'additional' => $storeOrder->hasOneStore->id . '-' . $storeOrder->hasOneStore->store_name
            ];
        }


        return ['auditor' => '', 'additional' => ''];
    }

    public function getFormDataByOderId($order_id)
    {
        $result = [];
        $set = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.order.order_detail.diyform');
        if (!$set) {
            return $result;
        }
        $orderGoods = \app\common\models\OrderGoods::where('order_id', $order_id)->get()->toArray();
        $orderGoodsIds = array_column($orderGoods, 'id');
        $orderGoods = array_column($orderGoods, null, 'id');
        $diyForms = OrderGoodsDiyForm::whereIn('order_goods_id', $orderGoodsIds)->get()->toArray();
        $dataIds = array_column($diyForms, 'diyform_data_id');
        $diyForms = array_column($diyForms, null, 'diyform_data_id');
        $datas = DiyformDataModel::whereIn('id', $dataIds)->get()->toArray();
        $item = [];
        foreach ($datas as $detail) {
            if ($detail) {
                $form = DiyformTypeModel::find($detail['form_id']);
            }
            $fields = iunserializer($form['fields']);
            foreach ($detail['form_data'] as $k => $v) {
                if ($fields[$k]['data_type'] == 5) {
                    continue;
                }
                if (is_array($v)) {
                    $v = implode(',', $v);
                }
                $item[] = $fields[$k]['tp_name'] . ':' . $v;
            }
            $result[$orderGoods[$diyForms[$detail['id']]['order_goods_id']]['goods_id']] = implode("\r\n", $item);
        }

        return $result;
    }


    protected function getNickname($nickname)
    {
        if (substr($nickname, 0, strlen('=')) === '=') {
            $nickname = '，' . $nickname;
        }
        return $nickname;
    }


    protected function getExportDiscount($order, $key)
    {
        $export_discount = [
            'deduction' => 0,    //抵扣金额
            'coupon' => 0,    //优惠券优惠
            'enoughReduce' => 0,  //全场满减优惠
            'singleEnoughReduce' => 0,    //单品满减优惠
        ];

        foreach ($order['discounts'] as $discount) {
            if ($discount['discount_code'] == $key) {
                $export_discount[$key] = $discount['amount'];
            }
        }

        if (!$export_discount['deduction']) {
            foreach ($order['deductions'] as $k => $v) {
                $export_discount['deduction'] += $v['amount'];
            }
        }

        return $export_discount[$key];
    }

    // 过滤运算符
    public function checkStr($str, $fill = ' ')
    {
        if (!$str) {
            return $str;
        }
        $arr = ['='];
        foreach ($arr as $v) {
            if (strpos($str, $v) === 0) {
                return $fill . $str;
            }
        }

        return $str;
    }

    protected function getExportRefundName($orderArray)
    {
        if ($orderArray['manual_refund_log']) {
            return '退款并关闭';
        }

        if ($orderArray['has_one_refund_apply'] && $orderArray['has_one_refund_apply']['status'] >= \app\common\models\refund\RefundApply::COMPLETE) {
            if ($orderArray['has_one_refund_apply']['part_refund'] == \app\common\models\refund\RefundApply::ORDER_CLOSE) {
                return '退款并关闭';
            }

            return $orderArray['has_one_refund_apply']['refund_type_name'];
        }

        return '';
    }

    protected function invoiceType($type)
    {
        $result = '';
        if ($type == '0' || empty($type)) {
            $result = '电子发票';
        }
        if ($type == 1) {
            $result = '纸质发票';
        }
        if ($type == 2) {
            $result = '专用发票';
        }
        return $result;
    }


    protected function getGoods($order, $key, $category = '')
    {
        if (empty($category)) {
            $category = new Category();
        }
        $goods_title = '';
        $alias = '';
        $goods_sn = '';
        $total = '';
        $weight = '';
        $vip_price = '';
        $product_sn = '';
        $cost_price = 0;
        $firstCate = '';
        $secondCate = '';
        $thirdCate = '';
        foreach ($order['has_many_order_goods'] as $goods) {
            $res_title = $goods['title'];
            $res_title = str_replace('-', '，', $res_title);
            $res_title = str_replace('+', '，', $res_title);
            $res_title = str_replace('/', '，', $res_title);
            $res_title = str_replace('*', '，', $res_title);
            $res_title = str_replace('=', '，', $res_title);

            if ($goods['goods_option_title']) {
                $res_title .= '[' . $goods['goods_option_title'] . ']';
            }
            $order_goods = OrderGoods::find($goods['id']);
            if ($order_goods->goods_option_id) {
                $goods_option = GoodsOption::find($order_goods->goods_option_id);
                if ($goods_option) {
                    $weight .= '【' . $goods['total'] * $goods_option->weight . 'g】';
                    $goods_sn .= '【' . $goods_option->goods_sn . '】';
                }
            } else {
                $weight .= '【0g】';
                $goods_sn .= '【' . $goods['goods_sn'] . '】';
            }
            $product_sn .= '【' . $goods['product_sn'] . '】';
            $goods_title .= '【' . $res_title . '*' . $goods['total'] . '】';
            $alias .= '【' . $goods['goods']['alias'] . '】';
            $total .= '【' . $goods['total'] . '】';
            $vip_price .= '【' . $goods['vip_price'] . '】';
            $cost_price += $goods['goods_cost_price'];
            $cateList = [];
            foreach ($goods['goods']['belongs_to_categorys'] as $k => $v) {
                $cateList[] = $category->getCateOrderByLevel($v['category_ids']);
            }
            $firstCateTemp = array_column($cateList, 0);
            $secondCateTemp = array_column($cateList, 1);
            $thirdCateTemp = array_column($cateList, 2);
            foreach ($firstCateTemp as $temp) {
                $firstCate .= '【' . $temp . '】';
            }
            foreach ($secondCateTemp as $temp) {
                $secondCate .= '【' . $temp . '】';
            }
            foreach ($thirdCateTemp as $temp) {
                $thirdCate .= '【' . $temp . '】';
            }
        }
        $res = [
            'goods_title' => $goods_title,
            'alias' => $alias,
            'goods_sn' => $goods_sn,
            'product_sn' => $product_sn,
            'total' => $total,
            'weight' => $weight,
            'vip_price' => $vip_price,
            'cost_price' => $cost_price,
            'first_cate' => $firstCate,
            'second_cate' => $secondCate,
            'third_cate' => $thirdCate,
        ];
        if (!$key) {
            return $res;
        }
        return $res[$key];
    }

}
