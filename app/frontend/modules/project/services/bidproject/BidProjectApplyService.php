<?php

namespace app\frontend\modules\project\services\bidproject;

use app\common\exceptions\AppException;
use app\common\models\Order;
use app\common\modules\pcnotice\Template;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectBid;
use app\frontend\modules\project\services\OrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\supplier\services\SystemMsgService;

class BidProjectApplyService
{
    // 投标方式
    const BID_TYPE_FACTORY = 1;       // 工厂直投
    const BID_TYPE_AUTHORIZED = 2;    // 授权投标

    // 投标方式名称
    const BID_TYPE_NAMES = [
        self::BID_TYPE_FACTORY => '工厂直投',
        self::BID_TYPE_AUTHORIZED => '授权投标',
    ];

    // 报备状态
    const REPORT_STATUS_APPROVED = 2; // 已报备

    // 投标状态
    const BID_STATUS_APPLIED = 2;     // 已申请
    const BID_STATUS_INIT = 1;        // 未申请

    // 错误提示
    const MSG_PROJECT_NOT_FOUND = '项目不存在';
    const MSG_PROJECT_NOT_REPORTED = '项目未报备';
    const MSG_SUPPLIER_NOT_FOUND = '品牌不存在';
    const MSG_BID_NOT_FOUND = '未申请投标';
    const MSG_ORDER_PAID_CANCEL = '投标订单已经支付了不能撤销';
    const MSG_APPLY_FAILED = '申请投标失败';
    const MSG_CANCEL_FAILED = '取消失败';
    const MSG_NEED_PERSON_NAME = '请填写授权人姓名';
    const MSG_NEED_PERSON_PHONE = '请填写授权人手机';
    const MSG_NEED_PERSON_IDCARD = '请上传授权人身份证';

    /**
     * 投标申请
     */
    public function applyFor(array $request_data): int
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        if ($project->report_status != self::REPORT_STATUS_APPROVED) {
            throw new AppException(self::MSG_PROJECT_NOT_REPORTED);
        }

        $supplier = Supplier::where('id', $request_data['supplier_id'])->first();
        if (!$supplier) {
            throw new AppException(self::MSG_SUPPLIER_NOT_FOUND);
        }

        $ProjectReport = ProjectBid::where('project_id', $request_data['project_id'])->first();

        $is_apply = false;
        if ($ProjectReport) {
            $bid_id = $ProjectReport->id;
            $model = $ProjectReport;
            $notific = Template::BID['SUBMIT_EDIT'];
            $is_apply = true;
        } else {
            $bid_id = 0;
            $model = new ProjectBid;
            $notific = Template::BID['SUBMIT_SUCCESS'];
        }

        if ($request_data['bid_type'] == self::BID_TYPE_FACTORY) {
            if (!$request_data['authorized_person_name']) {
                throw new AppException(self::MSG_NEED_PERSON_NAME);
            }
            if (!$request_data['authorized_person_phone']) {
                throw new AppException(self::MSG_NEED_PERSON_PHONE);
            }
            if (!$request_data['authorized_person_idcard']) {
                throw new AppException(self::MSG_NEED_PERSON_IDCARD);
            }
        }

        $request_data['project_file'] = !empty($request_data['project_file']) ? serialize($request_data['project_file']) : serialize([]);
        $request_data['company_type'] = $request_data['company_type'] ? implode($request_data['company_type']) : "";
        $request_data['member_id'] = \YunShop::app()->getMemberId();
        $request_data['amount'] = $request_data['amount'] ?: 0;
        $request_data['is_revoke'] = 0;
        $model->setRawAttributes($request_data);

        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                $project->bid_status = self::BID_STATUS_APPLIED;
                $project->save();
                if ($bid_id) {
                    $bid_id = $bid_id;
                } else {
                    $bid_id = $model->id;
                }

                $data['project_id'] = $model->project_id;
                $data['bid_id'] = $bid_id;
                $data['supplier_id'] = $model->supplier_id;
                $data['bid_type'] = $model->bid_type;
                $data['need_bid_document'] = $model->need_bid_document;
                $data['need_bid_bond'] = $model->need_bid_bond;
                $data['amount'] = $request_data['amount'];
                $orderService = new OrderService($data);
                $orderService->generateOrder();
                $option['related_id'] = $project->id;

                app('notification')->send(
                    $request_data['member_id'],
                    Template::AUDIT,
                    Template::BID_NAME,
                    $notific,
                    ['bid_name' => $request_data['name']],
                    $option
                );

                if (!$is_apply) {
                    (new SystemMsgService())->createProject("bidProject", $request_data['name'], $model->supplier_id, $bid_id);
                }

                return 1;
            } else {
                throw new AppException(self::MSG_APPLY_FAILED);
            }
        }
    }

    /**
     * 取消投标申请
     */
    public function cancelApply(int $id): bool
    {
        try {
            $projectFactoryInspection = ProjectBid::where('project_id', $id)->first();
            $res = Order::where('bid_id', $projectFactoryInspection->id)->whereIn('status', [1, 2, 3])->get();
            if ($res->isNotEmpty()) {
                throw new AppException(self::MSG_ORDER_PAID_CANCEL);
            }
            if (!$projectFactoryInspection) {
                throw new AppException(self::MSG_BID_NOT_FOUND);
            }
            ProjectBid::where('id', $projectFactoryInspection->id)->update(
                [
                    'is_revoke' => 1
                ]
            );

            $project = Project::where('id', $id)->first();
            $project->bid_status = self::BID_STATUS_INIT;
            $project->save();

            $updateData['status'] = Order::CLOSE;
            Order::where('bid_id', $projectFactoryInspection->id)->update($updateData);
            return true;
        } catch (\Exception $e) {
            throw new AppException(config('app.debug') ? $e->getMessage() : self::MSG_CANCEL_FAILED);
        }
    }
}
