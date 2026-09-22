<?php

namespace app\frontend\modules\project\services\factoryinspection;

use app\common\exceptions\AppException;
use app\common\modules\pcnotice\Template;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectFactoryInspection;
use Yunshop\Supplier\supplier\services\SystemMsgService;

class FactoryInspectionCommandService
{
    // 报备状态
    const REPORT_STATUS_APPROVED = 2; // 已审核

    // 考察状态
    const FACTORY_STATUS_INIT = 1;     // 初始/未申请
    const FACTORY_STATUS_APPLIED = 2;  // 已申请

    // 默认支付方式
    const DEFAULT_PAYMENT_METHOD = 1;

    // 错误提示
    const MSG_PROJECT_NOT_FOUND = '项目不存在';
    const MSG_PROJECT_NOT_REPORTED = '项目未报备';
    const MSG_APPLY_FAILED = '申请工厂考察失败';
    const MSG_INSPECTION_NOT_FOUND = '工厂考察信息不存在';
    const MSG_DELETE_FAILED = '删除失败';
    const MSG_NOT_APPLIED = '未申请工厂考察';

    /**
     * 申请工厂考察
     */
    public function applyFor(array $request_data): bool
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        if ($project->report_status != self::REPORT_STATUS_APPROVED) {
            throw new AppException(self::MSG_PROJECT_NOT_REPORTED);
        }

        $model = ProjectFactoryInspection::where('project_id', $request_data['project_id'])->first();
        $is_apply = false;
        if (!$model) {
            $notific = Template::INSPECTION['SUBMIT_SUCCESS'];
            $model = new ProjectFactoryInspection;
            $is_apply = true;
        } else {
            $notific = Template::INSPECTION['SUBMIT_EDIT'];
        }

        $request_data['member_id'] = \YunShop::app()->getMemberId();
        $request_data['payment_method'] = $request_data['payment_method'] ?: self::DEFAULT_PAYMENT_METHOD;
        $request_data['company_type'] = $request_data['company_type'] ? implode(",", $request_data['company_type']) : "";
        $request_data['projects_visit'] = $request_data['projects_visit'] ? implode(",", $request_data['projects_visit']) : "";
        $model->setRawAttributes($request_data);

        // 字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                $project->factory_status = self::FACTORY_STATUS_APPLIED;
                $project->save();

                // 发送工厂消息
                $option['related_id'] = $project->id;
                app('notification')->send(
                    $request_data['member_id'],
                    Template::AUDIT,
                    Template::FACTORY,
                    $notific,
                    ['inspection_name' => $request_data['name']],
                    $option
                );
                if (!$is_apply) {
                    (new SystemMsgService())->createProject("factoryProject", $request_data['name'], $model->supplier_id, $model->id);
                }

                return true;
            } else {
                throw new AppException(self::MSG_APPLY_FAILED);
            }
        }
    }

    /**
     * 删除考察
     */
    public function delete(int $id): bool
    {
        $factory = ProjectFactoryInspection::where('id', $id)->first();
        if (!$factory) {
            throw new AppException(self::MSG_INSPECTION_NOT_FOUND);
        }
        try {
            $factory->delete();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_DELETE_FAILED);
        }
    }

    /**
     * 取消申请
     */
    public function cancelApply(int $id): bool
    {
        $projectFactoryInspection = ProjectFactoryInspection::where('project_id', $id)->first();
        if (!$projectFactoryInspection) {
            throw new AppException(self::MSG_NOT_APPLIED);
        }
        $project = Project::where('id', $id)->first();
        $project->factory_status = self::FACTORY_STATUS_INIT;
        $project->save();
        return true;
    }
}
