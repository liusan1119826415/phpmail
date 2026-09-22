<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/1
 * Time: 9:56
 */

namespace app\backend\modules\order\controllers;

use app\backend\modules\goods\models\Category;
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\member\models\MemberParent;
use app\backend\modules\order\models\VueOrder;
use app\backend\modules\order\models\OrderGoods;
use app\backend\modules\order\services\OrderViewService;
use app\common\components\BaseController;

use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\models\Order;
use app\common\models\refund\RefundApply;
use app\common\services\ExportService;
use app\common\services\member\level\LevelUpgradeService;
use app\common\services\OrderExportService;
use app\exports\traits\ExportTrait;
use app\frontend\modules\order\services\OrderService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Yunshop\Diyform\models\DiyformDataModel;
use Yunshop\Diyform\models\DiyformTypeModel;
use Yunshop\Diyform\models\OrderGoodsDiyForm;
use Yunshop\GoodsSource\common\models\GoodsSet;
use Yunshop\GoodsSource\common\models\GoodsSource;
use Yunshop\MorePrinter\common\services\OrderPrintService;
use Yunshop\PackageDelivery\models\DeliveryOrder;
use Yunshop\Printer\common\services\NewPrintingService;
use Yunshop\StoreCashier\common\models\StoreDelivery;
use Yunshop\Supplier\common\models\SupplierGoods;
use Yunshop\TeamDividend\models\TeamDividendLevelModel;
use Yunshop\Exhelper\common\models\ExhelperPanel;
use Yunshop\Exhelper\common\models\ExhelperSys;
use Yunshop\Exhelper\common\models\SendUser;


class OrderListController extends BaseController
{

    use ExportTrait;


    /**
     * 页码
     */
    const PAGE_SIZE = 10;


    protected $exportRoute = 'order.order-list.index';

    /**
     * @var VueOrder
     */
    protected $orderModel;

    public function preAction()
    {
        parent::preAction();
    }

    protected function getOrder()
    {
        $model = VueOrder::uniacid();

        return $model;
    }

    protected function setOrderModel()
    {
        $search = request()->input('search');
        $code = request()->input('code');
        return $this->getOrder()->statusCode($code)->orders($search);
    }

    protected function orderModel()
    {
        if (isset($this->orderModel)) {
            return $this->orderModel;
        }

        return $this->orderModel = $this->setOrderModel();
    }


    protected function getData($code = '')
    {
        $data = [
            'code'          => $code,
            'listUrl'       => '', //订单查询路由
            'commonPartUrl' => '', //订单查询路由
            'exportUrl'     => '', //订单导出路由
            'detailUrl'     => '', //订单详情
        ];
        $source_status = false;
        $source_is_open = \Setting::get('plugin.goods_source.is_open');
        if (app('plugins')->isEnabled('goods-source') && (is_null($source_is_open) || $source_is_open)) {
            $source_status = true;
        }
        if ($source_status) {
            $source_list = GoodsSource::uniacid()->select(['id', 'source_name'])->get()->toArray();
        } else {
            $source_list = new Collection();
        }
        if ($extraData = $this->mergeExtraData()) {
            $data = array_merge($data, $extraData);
        }

        //插件参数
        $extraParam = $this->mergeExtraParam();
        $data['extraParam'] = $extraParam ?: [];
        $data['is_source_open'] = $source_status ? 1 : 0;
        $data['source_list'] = $source_list ?: [];

        $data['expressCompanies'] = \app\common\repositories\ExpressCompany::create()->all();
        return ['data' => json_encode($data)];
    }

    protected function mergeExtraData()
    {
    }

    protected function mergeExtraParam()
    {
        $extraParam = [
            'package_deliver' => app('plugins')->isEnabled('package-deliver'),
            'team_dividend'   => app('plugins')->isEnabled('team-dividend'),
            'printer'         => (app('plugins')->isEnabled('printer') || app('plugins')->isEnabled('more-printer'))
        ];

        return $extraParam;
    }

    public function orderPrinter()
    {
        try {
            $order_id = request()->order_id;
            $order = Order::find($order_id);
            if (!$order) {
                throw new \Exception('订单未找到');
            }
            //打印机
            if (app('plugins')->isEnabled('printer')) {
                \app\common\modules\shop\ShopConfig::current()->set('printer_owner', [
                    'owner'    => 1,
                    'owner_id' => 0
                ]);
                $print_type = 2;
                $code = '商城支付打印';
                if ($order->status == 0) {
                    $print_type = 1;
                    $code = '商城下单打印';
                }
                $NewPrintingService = new NewPrintingService($order, $print_type, $code);
                if ($NewPrintingService->verify()) {
                    $NewPrintingService->handle();
                    return $this->successJson('ok');
                } else {
                    return $this->errorJson('打印失败，请配置打印机！');
                }
            }

            if (app('plugins')->isEnabled('more-printer')) {
                $print_type = 2;
                if ($order->status == 0) {
                    $print_type = 1;
                }
                $order_service = new OrderPrintService($order, $print_type);
                $order_service->newPrinting();
                return $this->successJson('ok');
            }
            throw new \Exception('未开启打印机插件');
        } catch (\Exception $e) {
            return $this->errorJson($e->getMessage());
        }
    }

    public function getSynchro()
    {
        //判断是否开启了同步运单号
        $synchro = 0;
        if (app('plugins')->isEnabled('exhelper')) {
            $set = ExhelperSys::uniacid()->first();
            if ($set) {//判断是否填了快递助手信息
                $send = SendUser::uniacid()->where('isdefault', 1)->first();
                $panel = ExhelperPanel::uniacid()->where('isdefault', 1)->first();

                if ($send && $panel) {//判断是否有默认发货人和默认模板
                    $synchro = 1;//开启同步运单号
                } else {
                    $synchro = 0;
                }
            } else {
                $synchro = 0;
            }
        }
        return $this->successJson('ok', $synchro);
    }


    public function getList()
    {
        $sort = request()->search['sort'];
        $search = request()->input('search');

        if ($sort == 1) {
            $condition['order_by'][] = [$this->orderModel()->getModel()->getTable() . '.uid', 'desc'];
            $condition['order_by'][] = [$this->orderModel()->getModel()->getTable() . '.id', 'desc'];
        }


        $orderModel = $this->orderModel();
        if (app('plugins')->isEnabled('order-inventory') && \Yunshop\OrderInventory\services\SetService::pluginIsOpen(
            )) {
            //不显示存货订单
            $orderModel = \Yunshop\OrderInventory\services\InventoryService::orderListWhere($orderModel);
        }

        if (app('plugins')->isEnabled('invoice')) {
            $orderModel = $orderModel->with('orderInvoice');
            if ($search['is_invoice']) {
                $orderModel->whereHas('orderInvoice', function ($query) use ($search) {
                    return $query->where('apply', $search['is_invoice']);
                });
            }
        }

        if (app('plugins')->isEnabled('tag-balance')) {
            $orderModel = $orderModel->with([
                'tagBalancePay' => function ($query) {
                    $query->select('id','order_id','tag_id','amount','refund_type','refund_tag_first',
                        'refund_tag_second','refund_tag_third','refund_tag_four')
                        ->with(['tag:id,tag_name','refundTag:id,tag_name,level']);
                },
                'tagBalanceDeduction' => function ($query) {
                    $query->select('id','order_id','tag_id','amount','deduction_rate','payment','refund_type','refund_tag_first',
                        'refund_tag_second','refund_tag_third','refund_tag_four')
                        ->with(['tag:id,tag_name']);
                }
            ]);
        }

        if ($search['source_id']) {
            $set_goods_id = GoodsSet::where('source_id', $search['source_id'])->pluck('goods_id')->all();
            $order_ids = OrderGoods::uniacid()->whereIn('goods_id', $set_goods_id)->pluck('order_id')->unique()->all();
            $orderModel->whereIn('yz_order.id', $order_ids);
        }
        $count['total_price'] = $orderModel->sum('yz_order.price');
        $count['dispatch_price'] = $orderModel->sum('yz_order.dispatch_price');
        $build = $orderModel;

        if ($sort == 1) {
            foreach ($condition['order_by'] as $item) {
                $build->orderBy(...$item);
            }
        } else {
            $build->orderBy($this->orderModel()->getModel()->getTable() . '.id', 'desc');
        }

        $page = $build->paginate(self::PAGE_SIZE);


        $page->map(function ($order) {
            /**
             * todo 为了不在模型的 $appends 属性加动态显示
             */
            $order->fixed_button = $order->fixed_button;
            $order->top_row = $order->top_row;

            $order->part_refund = (!$order->refund_id && RefundApply::getAfterSales($order->id)->count()) ? 1 : 0;

            // 查询乐刷订单, 然后显示是否有退款不足的情况
            if (app('plugins')->isEnabled('leshua-pay') && in_array($order->pay_type_id, [85, 86, 87])) {
                $refundRecords = \Yunshop\LeshuaPay\models\RefundRecords::where('order_id', $order->id)->first();
                $order->leshua_refund_error_msg = $refundRecords->msg ?? '';
            }
        });

        $source_status = false;
        $source_is_open = \Setting::get('plugin.goods_source.is_open');
        if (app('plugins')->isEnabled('goods-source') && (is_null($source_is_open) || $source_is_open)) {
            $source_status = true;
        }
        if ($source_status) {
            $source_list = GoodsSource::uniacid()->select(['id', 'source_name'])->get();
        } else {
            $source_list = new Collection();
        }

        $count['total'] = $page->total();

        $list = $page->toArray();

        $data = [
            'list'           => $list,
            'count'          => $count,
            'is_source_open' => $source_status ? 1 : 0,
            'source_list'    => $source_list,
        ];


        return $this->successJson('list', $data);
    }


    /**
     * @return string
     * @throws \Throwable
     */
    public function index()
    {
        //$a = (new \app\backend\modules\order\services\OrderViewService())->importVue();

        //(new \app\backend\modules\order\services\OrderViewService())->topRowShow();

        return view('order.vue-list', $this->getData())->render();
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function waitPay()
    {
        return view('order.vue-list', $this->getData('waitPay'))->render();
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function waitSend()
    {
        return view('order.vue-list', $this->getData('waitSend'))->render();
    }

    /**
     * 催发货
     * @return string
     */
    public function expeditingSend()
    {
        return view('order.vue-list', $this->getData('expeditingSend'));
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function waitReceive()
    {
        return view('order.vue-list', $this->getData('waitReceive'))->render();
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function completed()
    {
        return view('order.vue-list', $this->getData('completed'))->render();
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function cancelled()
    {
        return view('order.vue-list', $this->getData('cancelled'))->render();
    }



    public function batchSend()
    {
        $order_ids = $this->orderModel()->pluck('id');

        $send_data = request()->batch_send;
        $i = 0;
        foreach ($order_ids as $order_id) {
            try {
                $param = [
                    "dispatch_type_id" => $send_data['dispatch_type_id'],
                    "express_code"     => $send_data['express_code'],
                    "express_sn"       => $send_data['express_sn'],
                    "order_id"         => $order_id,
                ];
                \app\frontend\modules\order\services\OrderService::orderSend($param);
            } catch (\Exception $e) {
                $i++;
            }
        }

        return $this->successJson('一键发货成功，失败条数' . $i . '(有退款订单不能发货)');
    }
}
