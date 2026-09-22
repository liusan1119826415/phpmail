<?php

namespace app\frontend\modules\project\services\doororder;

use app\common\exceptions\AppException;
use app\common\models\Order;
use app\common\models\project\ProjectReport;
use app\common\modules\pcnotice\Template;
use app\common\services\SystemMsgService;
use app\frontend\modules\order\services\OrderService as BaseOrderService;
use app\common\models\project\ProjectDoor;
use app\frontend\modules\project\models\Project;
use Yunshop\Supplier\common\models\SupplierCharge;

class DoorOrderCommandService
{
    // 订单类型
    const ORDER_TYPE_DOOR = 5;   // 上门服务费订单
    const ORDER_TYPE_BRAND = 3;  // 品牌使用费订单

    // 上门订单状态
    const DOOR_STATUS_PAID = 4;     // 已支付/待服务
    const DOOR_STATUS_PENDING = 0;  // 待支付
    const DOOR_STATUS_COMPLETED = 3; // 已完成
    const DOOR_STATUS_ALL = -1;     // 全部

    // 状态映射（前端 → 数据库）
    const STATUS_MAP = [
        1 => self::DOOR_STATUS_PAID,
        2 => self::DOOR_STATUS_PENDING,
        3 => self::DOOR_STATUS_COMPLETED,
        -1 => self::DOOR_STATUS_ALL,
    ];

    // 服务分类
    const SERVICE_DATA = [
        ['id' => 1, 'name' => '办公家具'],
        ['id' => 2, 'name' => '医养家具'],
        ['id' => 3, 'name' => '酒店公寓'],
        ['id' => 4, 'name' => '政法家具'],
        ['id' => 5, 'name' => '实验室家具'],
        ['id' => 6, 'name' => '教育家具'],
    ];

    // 服务类型
    const SERVICE_TYPE_DATA = [
        ['id' => 1, 'name' => '测量'],
        ['id' => 2, 'name' => '绘制CAD'],
        ['id' => 3, 'name' => '绘制效果图'],
        ['id' => 4, 'name' => '方案讲解'],
        ['id' => 5, 'name' => '项目负责人'],
    ];

    // 申请上门限制
    const MAX_ASSIST_NUMBER = 4;  // 最大协助人数
    const MAX_SERVICE_DAY = 5;    // 最大服务天数

    // 报备状态
    const REPORT_STATUS_APPROVED = 2; // 已审核

    // 错误提示
    const MSG_PROJECT_NOT_FOUND = '项目不存在';
    const MSG_ASSIST_NUMBER_EXCEEDED = '协助人数不得超过4人';
    const MSG_SERVICE_DAY_EXCEEDED = '服务天数不得超过5天';
    const MSG_PROJECT_NOT_REPORTED = '项目未报备';
    const MSG_REPORT_NOT_APPROVED = '项目报备状态未审核';
    const MSG_DOOR_APPLY_FAILED = '申请上门失败';
    const MSG_ORDER_NOT_FOUND = '未找到订单';
    const MSG_OPERATION_FAILED = '操作失败';
    const MSG_OPERATION_FAILED_EXTRA = '操作失败异常';
    const MSG_DOOR_ORDER_NOT_FOUND = '未找到上门订单';

    protected $request_data;

    /**
     * 申请上门
     */
    public function reqDoor(array $request_data): bool
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        if ($request_data['number'] > self::MAX_ASSIST_NUMBER) {
            throw new AppException(self::MSG_ASSIST_NUMBER_EXCEEDED);
        }

        if ($request_data['service_day'] > self::MAX_SERVICE_DAY) {
            throw new AppException(self::MSG_SERVICE_DAY_EXCEEDED);
        }

        $this->request_data = $request_data;

        // 检查项目是否报备
        $projectReport = ProjectReport::where('project_id', $request_data['project_id'])->first();
        if (!$projectReport) {
            throw new AppException(self::MSG_PROJECT_NOT_REPORTED);
        }
        if ($project->report_status != self::REPORT_STATUS_APPROVED) {
            throw new AppException(self::MSG_REPORT_NOT_APPROVED);
        }

        try {
            $request_data['member_id'] = \YunShop::app()->getMemberId();
            $scene_img = request()->input('scene_img');
            $build_img = request()->input('build_img');
            $remark = request()->input('remark');
            $request_data['scene_img'] = $scene_img ? serialize($scene_img) : serialize([]);
            $service_type = [];
            if ($request_data['service_type'] && is_array($request_data['service_type'])) {
                $service_type = $request_data['service_type'];
                $request_data['service_type'] = implode(",", $request_data['service_type']);
            }
            $request_data['build_img'] = $build_img ? serialize($build_img) : serialize([]);
            $request_data['remark'] = $remark;
            $request_data['upgrade'] = request()->input('upgrade');
            $model = new ProjectDoor;
            $model->setRawAttributes($request_data);
            // 字段检测
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {
                throw new AppException($validator->messages());
            } else {
                if ($model->save()) {
                    $trade = \Setting::get('shop.trade');
                    $door_fee = $trade['door_fee'] ?: 0;
                    $total_fee = $door_fee * $request_data['service_day'] * $request_data['number'];

                    // 检查是否已支付品牌使用费
                    $brandOrder = Order::where('project_id', $request_data['project_id'])
                        ->where('order_type', self::ORDER_TYPE_BRAND)
                        ->whereIn('status', [1, 2, 3])->first();
                    if ($brandOrder) {
                        $is_pay = 1;
                        $this->generateOrder($request_data['project_id'], $projectReport, $total_fee, self::ORDER_TYPE_DOOR, self::DOOR_STATUS_PAID, $model->id, $total_fee, $is_pay);
                    } else {
                        if ($request_data['upgrade'] == 1) {
                            $brandOrder = Order::where('project_id', $request_data['project_id'])
                                ->where('order_type', self::ORDER_TYPE_BRAND)
                                ->where('status', self::DOOR_STATUS_PENDING)->first();
                            if (!$brandOrder) {
                                $charges = SupplierCharge::where('supplier_id', $projectReport->supplier_id)
                                    ->select('supplier_id', 'brand_fee', 'bid_document_fee')
                                    ->first();
                                $this->generateOrder($request_data['project_id'], $projectReport, $charges->brand_fee ?: 0, self::ORDER_TYPE_BRAND, self::DOOR_STATUS_PENDING, 0, $charges->brand_fee ?: 0);
                            }
                        }

                        $this->generateOrder($request_data['project_id'], $projectReport, $total_fee ?: 0, self::ORDER_TYPE_DOOR, self::DOOR_STATUS_PAID, $model->id, $total_fee);
                    }
                    return true;
                } else {
                    \Log::debug("申请上门失败222", $model->getErrors());
                    throw new AppException(self::MSG_DOOR_APPLY_FAILED);
                }
            }
        } catch (\Exception $e) {
            \Log::debug("申请上门失败", $e->getMessage());
            throw new AppException(config('app.debug') ? $e->getMessage() : self::MSG_DOOR_APPLY_FAILED);
        }
    }

    /**
     * 验收确认
     */
    public function acceptanceCheck(int $id): bool
    {
        try {
            $order = Order::find($id);
            if (!$order) {
                throw new AppException(self::MSG_ORDER_NOT_FOUND);
            }
            $order->status = Order::WAIT_PAY;
            $order->product_time = time();
            $order->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_OPERATION_FAILED);
        }
    }

    /**
     * 添加加项金额
     */
    public function addAmount($id, $amount, $extra_enable): bool
    {
        try {
            $order = Order::find($id);
            $order->extra_enable = $extra_enable;
            $order->extra_amount = $amount;
            $order->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_OPERATION_FAILED_EXTRA);
        }
    }

    /**
     * 生成订单
     */
    protected function generateOrder($project_id, $projectReport, $price, $order_type, $status, $door_id, $goods_price, $is_pay = 0)
    {
        $time = time();
        $brandOrderData['uniacid'] = \YunShop::app()->uniacid;
        $brandOrderData['uid'] = \YunShop::app()->getMemberId();
        $brandOrderData['order_sn'] = BaseOrderService::createOrderSN();
        $brandOrderData['create_time'] = $time;
        $brandOrderData['project_id'] = $project_id;
        $brandOrderData['report_id'] = $projectReport->id;
        $brandOrderData['supp_id'] = $projectReport->supplier_id;
        $brandOrderData['created_at'] = $time;
        $brandOrderData['updated_at'] = $time;
        $brandOrderData['order_type'] = $order_type;
        $brandOrderData['goods_price'] = $goods_price;

        $brandOrderData['price'] = $price;
        $brandOrderData['door_id'] = $door_id;
        $brandOrderData['status'] = $status;
        if ($is_pay == 1) {
            $brandOrderData['discount_price'] = $goods_price;
        }
        $order_id = Order::insertGetId($brandOrderData);

        $option['related_id'] = $order_id;
        // 供应商通知
        $order = Order::find($order_id);
        (new SystemMsgService())->createOrder($order);

        app('notification')->send(
            $brandOrderData['uid'],
            Template::ORDER,
            Template::ORDER_TYPE[$order_type],
            getNoticeTitle($order_type, "SUBMIT"),
            ['order_no' => $brandOrderData['order_sn'], 'appointment_time' => $this->request_data['door_time']],
            $option
        );
    }
}
