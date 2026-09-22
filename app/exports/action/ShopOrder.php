<?php

namespace app\exports\action;

use app\backend\modules\goods\models\Category;
use app\backend\modules\order\models\OrderGoods;
use app\backend\modules\order\models\VueOrder;
use app\common\models\refund\RefundApply;
use app\common\models\Store;
use app\exports\generator\ShopOrderFromGenerator;
use Yunshop\Diyform\models\DiyformDataModel;
use Yunshop\Diyform\models\DiyformTypeModel;
use Yunshop\Diyform\models\OrderGoodsDiyForm;
use Yunshop\GoodsSource\common\models\GoodsSet;
use Yunshop\PackageDelivery\models\DeliveryOrder;
use Yunshop\StoreCashier\common\models\CashierGoods;
use Yunshop\StoreCashier\common\models\StoreGoods;
use Yunshop\Supplier\common\models\SupplierGoods;

class ShopOrder extends BaseAction
{
    public $type = 'shopOrder';

    public $name = '订单（新）';

    public $ext = 'xlsx';

    public function preResult($header,$columns): array
    {
        if ($header['diyMember']) {
            $header_temp = [];
            $columns_temp = [];
            $formSet = json_decode(\Setting::get('shop.form'), true);
            $fieldForm = array_values(array_sort($formSet['form'], function ($value) {
                return $value['sort'];
            }));
            $diy_temp = [];
            foreach ($fieldForm as $form) {
                $diy_temp[$form['pinyin']] = $form['name'];
            }
            foreach ($header as $key => $value) {
                if ($key == 'diyMember') {
                    $header_temp = array_merge($header_temp, $diy_temp);
                    $columns_temp = array_merge($columns_temp, array_keys($diy_temp));
                } else {
                    $header_temp[$key] = $value;
                    $columns_temp[] = $key;
                }
            }
            $header = $header_temp;
            $columns = $columns_temp;
        }
        return [$header,$columns];
    }



    public function getData($request): \Generator
    {
        $order_model = $this->getOrderModel($request);
        if ($request['source_id'] && app('plugins')->isEnabled('goods-source')) {
            $set_goods_id = GoodsSet::where('source_id', $request['source_id'])->pluck('goods_id')->all();
            $order_ids = OrderGoods::uniacid()->whereIn('goods_id', $set_goods_id)->pluck('order_id')->unique()->all();
            $order_model->whereIn('yz_order.id', $order_ids);
        }
        $orders = $order_model->with(['discounts', 'deductions', 'orderInvoice'])->orderBy(
            'yz_order.id', 'desc'
        );
        if (app('plugins')->isEnabled('package-delivery')) {
            $orders->with([
                'hasOnePackageDeliveryOrder' => function ($query) {
                    $query->select('order_id', 'buyer_name', 'buyer_mobile');
                }
            ]);
        }
        if (app('plugins')->isEnabled('package-deliver')) {
            $orders->with([
                'hasOnePackageDeliverOrder' => function ($query) {
                    $query->with([
                        'hasOneDeliver' => function ($query) {
                            $query->select('id', 'deliver_name', 'deliver_mobile', 'realname');
                        }
                    ])->select('deliver_id', 'order_id', 'deliver_name');
                }
            ]);
        }
        if (app('plugins')->isEnabled('goods-source')) {
            $orders->with([
                'hasManyOrderGoods' => function ($query) {
                    $query->with([
                        'goodsSource' => function ($query) {
                            $query->with(['source']);
                        }
                    ]);
                }
            ]);
        }

        if (app('plugins')->isEnabled('commission')) {
            $orders->with([
                'hasManyCommission' => function ($query) {
                    $query->select('ordertable_id', 'hierarchy', 'commission')->orderBy('hierarchy', 'asc');
                }
            ]);
        }

        if (app('plugins')->isEnabled('store-cashier')) {
            $orders->with([
                'hasOneStoreOrder',
                'hasOneCashierOrder'
            ]);
        }


        if (app('plugins')->isEnabled('goods-contribution')) {
            $orders->with([
                'hasOneGoodsContributionOrder' => function ($query) {
                    $query->select('order_id', 'amount', 'status');
                },
            ]);
        }

        if (app('plugins')->isEnabled('yz-supply')) {
            $middleground_configuration_ids = \Yunshop\YzSupply\models\MiddlegroundConfiguration::uniacid()->where('status', 1)->pluck('id');
            foreach ($middleground_configuration_ids as $id) {
                try {
                    $suppliers = (new \Yunshop\YzSupply\services\CloudRequestService($id))->getSuppliers();
                } catch (\Exception $e) {
                    //调整为中台接口异常不影响导出
                    \Log::debug('订单导出中台配置项ID：' . $id . '配置错误,错误信息：' . $e->getMessage());
                    continue;
                }

                if ($suppliers['code'] > 0) {
                    continue;
                }
                $yzSupplyForm[$id] = array_column($suppliers['data'], 'name', 'id');
            }
        }
        $orders->with(['hasManyRefundApply' => function ($query) {
            $query->select(['id', 'order_id', 'uid', 'refund_type', 'status',
                'refund_sn', 'part_refund', 'price', 'apply_price', 'freight_price', 'other_price', 'price',
            ])->where('status', '>=', RefundApply::COMPLETE);
        }]);
        $orders->with([
            'hasOneMemberShopInfo' => function ($query) {
                $query->select('member_id', 'member_form','parent_id')
                    ->with(['hasOneParent:uid,nickname']);
            }
        ]);
        $category = new Category();

        foreach ($orders->get() as $key => $item) {
            $item = $item->toArray();
            $address = explode(' ', $item['address']['address']);
            $fistOrder = $item['has_many_first_order'] ? '首单' : '';
            if ($item['has_many_refund_apply']) {
                $refund_name = '';
            } else {
                $refund_name = $item['has_many_refund_apply'][0]['part_refund'] == \app\common\models\refund\RefundApply::PART_REFUND ? '部分退款' : '';
            }
            //拼接多包裹快递信息
            $expressInfo = $this->getExpressString($item['expressmany']);

            $clerk_info = $this->getAuditor($item);
            $form = $this->getFormDataByOderId($item);
            $diyMemberForm = $this->getDiyMemberForm($item);
            $yzSupplyForm = [];
            $goodsId = $goodsTitle = $goodsShort = $goodsOption = $goodsCode = $goodsBarcode = $goodsNum = $refundNum = $lastNum = $firstCategory = $secondCategory = $thirdCategory = $memberPrice = $goodsCostPrice = $profit = $goodsSource = $supplierName = $storeName = $diyForm = $goodsSku = [];
            foreach ($item['has_many_order_goods'] as $v) {
                $goodsRefundedTotal = $v['after_sales']['complete_quantity'];
                $cate = [];
                foreach ($v['goods']['belongs_to_categorys'] as $val) {
                    $cate[] = $category->getCateOrderByLevel($val['category_ids']);
                }
                if (empty($cate)) {
                    $cate = [['', '', '']];
                }
                $goodsId[] = $v['goods_id'];
                $goodsTitle[] = $v['title'];
                $goodsShort[] = $v['goods']['alias'];
                $goodsOption[] = $v['goods_option_title'];
                $goodsCode[] = $v['goods_sn'];
                $goodsBarcode[] = $v['product_sn'];
                $goodsNum[] = $v['total'];
                $refundNum[] = $goodsRefundedTotal;
                $lastNum[] = max($v['total'] - $goodsRefundedTotal, 0);
                $memberPrice[] = $v['vip_price'];
                $goodsCostPrice[] = $v['goods_cost_price'];
                $profit[] = $v['vip_price'] - $v['goods_cost_price'];
                $goodsSource[] = $v['goods_source']['source']['source_name'] ?: "";
                $supplierName[] = $this->getExportYzSupplySupplyName($v['goods_id'], $yzSupplyForm, $item['plugin_id']) ?: '';
                $storeName[] = $this->getExportStoreName($v['goods_id'], $item['plugin_id']) ?: '';
                $diyForm[] = $form[$v['goods_id']] ?: '';
                $first = $second = $third = [];
                foreach ($cate as $vv) {
                    $first[] = $vv[0];
                    $second[] = $vv[1];
                    $third[] = $vv[2];
                }
                $firstCategory[] = $first;
                $secondCategory[] = $second;
                $thirdCategory[] = $third;
                $goodsSku[] =  $v['goods']['sku'];
            }

            yield $key => [
                //订单信息
                'id' => $item['id'],
                'orderSn' => $item['order_sn'],
                'paySn' => $item['has_one_order_pay']['pay_sn'],
                'payType' => $item['pay_type_name'],
                'discountPrice' => $this->getExportDiscount($item, 'deduction'),
                'couponDiscount' => $this->getExportDiscount($item, 'coupon'),
                'allDiscount' => $this->getExportDiscount($item, 'enoughReduce'),
                'oneDiscount' => $this->getExportDiscount($item, 'singleEnoughReduce'),
                'productSubtotal' => $item['goods_price'],
                'freight' => $item['dispatch_price'],
                'price' => $item['price'],
                'refundPrice' => collect($item['has_many_refund_apply'])->sum('price'),
                'lastPrice' => max($item['price'] - collect($item['has_many_refund_apply'])->sum('price'), 0),
                'status' => $item['status_name'],
                'refundStatus' => $this->getExportRefundName($item) ?: $refund_name,
                'createTime' => $item['create_time'],
                'payTime' => !empty(strtotime($item['pay_time'])) ? $item['pay_time'] : '',
                'sendTime' => !empty(strtotime($item['send_time'])) ? $item['send_time'] : '',
                'receiptTime' => !empty(strtotime($item['finish_time'])) ? $item['finish_time'] : '',
                'expressCompany' => $expressInfo['company'],
                'expressSn' => $expressInfo['sn'],
                'orderRemark' => $item['has_one_order_remark']['remark'],
                'remark' => $this->checkStr($item['note']),
                'firstOrder' => $fistOrder,
                'underwriter' => $clerk_info['auditor'],
                'addition' => $clerk_info['additional'],
                'remindStatus' => $this->getExportRefundName($item) ?: $refund_name,
                'deliveryDate' => $item['address']['delivery_day'],
                'deliveryTime' => $item['address']['delivery_time'],

                //商品信息
                'goodsId' => $goodsId ?: [''],
                'goodsTitle' => $goodsTitle ?: [''],
                'goodsShort' => $goodsShort ?: [''],
                'goodsOption' => $goodsOption ?: [''],
                'goodsCode' => $goodsCode ?: [''],
                'goodsBarcode' => $goodsBarcode ?: [''],
                'goodsNum' => $goodsNum ?: [''],
                'refundNum' => $refundNum ?: [''],
                'lastNum' => $lastNum ?: [''],
                'firstCategory' => $firstCategory ?: [['']],
                'secondCategory' => $secondCategory ?: [['']],
                'thirdCategory' => $thirdCategory ?: [['']],
                'memberPrice' => $memberPrice ?: [''],
                'goodsCostPrice' => $goodsCostPrice ?: [''],
                'profit' => $profit ?: [''],
                'diyForm' => $diyForm ?: [''],
                'goodsSource' => $goodsSource ?: [''],
                'supplierName' => $supplierName ?: [''],
                'storeName' => $storeName ?: [''],
                'goodsSku' => $goodsSku ?: [''],

                //用户信息
                'memberId' => $item['uid'],
                'nickname' => $this->getNickname($item['belongs_to_member']['nickname']) ?: substr_replace(
                    $item['belongs_to_member']['mobile'],
                    '*******',
                    2,
                    7
                ),
                'mobile' => $item['belongs_to_member']['mobile'],
                'consigneeName' => $this->getExportRealName($item),
                'consigneeMobile' => $this->getExportMobile($item),
                'province' => !empty($address[0]) ? $address[0] : '',
                'city' => !empty($address[1]) ? $address[1] : '',
                'area' => !empty($address[2]) ? $address[2] : '',
                'address' => $item['address']['address'],
                'realName' => $item['has_many_member_certified']['realname'] ?: $item['belongs_to_member']['realname'],
                'idCard' => ' ' . $item['belongs_to_member']['idcard'],
                'parent' => $item['has_one_member_shop_info']['has_one_parent']['nickname'] ? : "",

                //其他信息
                'pickName' => $this->getExportDeliverName($item),
                'pickPerson' => $this->getExportDeliverOwnerName($item),
                'pickMobile' => $this->getExportDeliverOwnerMobile($item),
                'shopPoint' => $this->getShopPoint($item),
                'firstCommission' => $this->getFirstCommission($item),
                'secondCommission' => $this->getSecondCommission($item),

                //发票
                'invoiceType' => $this->invoiceType($item['order_invoice']['invoice_type']),
                'invoice' => $item['order_invoice']['rise_type'] == 1 ? '个人' : '单位',
                'invoiceCompany' => empty($item['order_invoice']['collect_name']) ? '' : $item['order_invoice']['collect_name'],
                'taxCode' => empty($item['order_invoice']['gmf_taxpayer']) ? $item['order_invoice']['company_number'] : $item['order_invoice']['gmf_taxpayer'],
                'invoiceContent' => empty($item['order_invoice']['content']) ? '' : $item['order_invoice']['content'],
                'openBank' => empty($item['order_invoice']['gmf_bank']) ? '' : $item['order_invoice']['gmf_bank'],
                'accountBank' => empty($item['order_invoice']['gmf_bank_admin']) ? '' : $item['order_invoice']['gmf_bank_admin'],
                'registerAddress' => empty($item['order_invoice']['gmf_address']) ? '' : $item['order_invoice']['gmf_address'],
                'registerMobile' => empty($item['order_invoice']['gmf_mobile']) ? '' : $item['order_invoice']['gmf_mobile'],
                'invoiceName' => empty($item['order_invoice']['col_name']) ? '' : $item['order_invoice']['col_name'],
                'invoiceMobile' => empty($item['order_invoice']['col_name']) ? '' : $item['order_invoice']['col_mobile'],
                'invoiceAddress' => empty($item['order_invoice']['col_address']) ? '' : $item['order_invoice']['col_address'],
                'invoiceEmail' => empty($item['order_invoice']['email']) ? '' : $item['order_invoice']['email'],

                'goodsContribution' => bcadd($item['has_one_goods_contribution_order']['amount'], 0, 2),
                'goodsContributionStatus' => $item['has_one_goods_contribution_order'] ? \Yunshop\GoodsContribution\models\PluginOrder::STATUS_NAME[$item['has_one_goods_contribution_order']['status']] : '',
            ] + $diyMemberForm;
        }
    }

    public function getOrderModel($request)
    {
        $code = $request['code'] ?? 'all';
        $search = $request['search'];
        return VueOrder::uniacid()->statusCode($code)->orders($search);
    }

    protected function getDiyMemberForm($item)
    {
        $formSet = json_decode(\Setting::get('shop.form'), true);
        $fieldForm = array_values(array_sort($formSet['form'], function ($value) {
            return $value['sort'];
        }));

        $diyFieldData = [];
        //会员姓名后添加自定义字段
        foreach ($fieldForm as $form) {
            $memberForm = json_decode($item['has_one_member_shop_info']['member_form'],true);
            $memberForm = array_column($memberForm,'value','pinyin');
            $diyFieldData[$form['pinyin']] = $memberForm[$form['pinyin']];
        }
        return $diyFieldData;

    }


    protected function getShopPoint($item)
    {
        $shop_fee = '';
        if ($item['plugin_id'] == 31) {
            $shop_fee = $item['has_one_cashier_order']['fee'];
        }
        if ($item['plugin_id'] == 32) {
            $shop_fee = $item['has_one_store_order']['fee'];
        }
        return $shop_fee;
    }

    protected function getFirstCommission($item)
    {
        return $item['has_many_commission'][0]['commission'] ?: 0;

    }

    protected function getSecondCommission($item)
    {
        return $item['has_many_commission'][1]['commission'] ?: 0;
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

    public function getFormDataByOderId($order)
    {
        $result = [];
        $set = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.order.order_detail.diyform');
        if (!$set) {
            return $result;
        }
        $orderGoods = $order['has_many_order_goods'];
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

    protected function getExportYzSupplySupplyName($goods_id, $yzSupplyForm, $pluginId)
    {
        if (app('plugins')->isEnabled('supplier') && $pluginId == 92) {
            $data = SupplierGoods::where('goods_id', $goods_id)->first();
            return $data->hasOneSupplier->username ?: '';
        }
        if (app('plugins')->isEnabled('yz-supply')) {
            $data = \Yunshop\YzSupply\models\YzGoods::uniacid()->select(
                'middleground_configuration_id',
                'shop_id'
            )->where('goods_id', $goods_id)->first();

            return $yzSupplyForm[$data->middleground_configuration_id][$data->shop_id] ?: '';
        }
        return '';
    }

    protected function getExportStoreName($goods_id, $pluginId)
    {
        if (app('plugins')->isEnabled('store-cashier') && $pluginId == 32) {
            $data = StoreGoods::where('goods_id', $goods_id)->first();
            return $data->store->store_name ?: '';
        }
        if (app('plugins')->isEnabled('store-cashier') && $pluginId == 31) {
            $store_name = Store::where('cashier_id', $goods_id)->value('store_name');
            return $store_name ?: '';
        }
    }

    protected function getNickname($nickname)
    {
        if (substr($nickname, 0, strlen('=')) === '=') {
            $nickname = '，' . $nickname;
        }
        return $nickname;
    }

    protected function getExportRealName($order)
    {
        $name = $order['has_one_package_delivery_order'] ? $order['has_one_package_delivery_order']['buyer_name'] : $order['address']['realname'];
        return $name ?: '';
    }

    protected function getExportMobile($order)
    {
        $mobile = $order['has_one_package_delivery_order'] ? $order['has_one_package_delivery_order']['buyer_mobile'] : $order['address']['mobile'];
        return $mobile ?: '';
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

    protected function getExportDeliverName($order)
    {
        $return = '';
        if ($order['has_one_package_delivery_order']) {
            $return = \Setting::get('shop.contact.store_name');
        } elseif ($order['has_one_package_deliver_order']) {
            $return = $order['has_one_package_deliver_order']['deliver_name'] ?: $order['has_one_package_deliver_order']['has_one_deliver']['deliver_name'];
        }
        return $return ?: '';
    }

    protected function getExportDeliverOwnerName($order)
    {
        $return = '';
        if ($order['has_one_package_delivery_order']) {
            $return = '商城自提';
        } elseif ($order['has_one_package_deliver_order']) {
            $return = $order['has_one_package_deliver_order']['has_one_deliver']['realname'];
        }
        return $return ?: '';
    }

    protected function getExportDeliverOwnerMobile($order)
    {
        $return = '';
        if ($order['has_one_package_delivery_order']) {
            $return = \Setting::get('shop.contact.phone');
        } elseif ($order['has_one_package_deliver_order']) {
            $return = $order['has_one_package_deliver_order']['has_one_deliver']['deliver_mobile'];
        }
        return $return ?: '';
    }

    public function getFromGenerator($data)
    {
        return new ShopOrderFromGenerator($data);
    }
}
