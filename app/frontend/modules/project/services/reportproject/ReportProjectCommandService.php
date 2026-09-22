<?php

namespace app\frontend\modules\project\services\reportproject;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\modules\pcnotice\Template;
use app\common\services\upload\UploadService;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectReport;
use Yunshop\Supplier\supplier\services\SystemMsgService;

class ReportProjectCommandService
{
    // 报备状态
    const REPORT_STATUS_INIT = 0;     // 初始/未报备
    const REPORT_STATUS_PENDING = 1;  // 待审核

    // 取消状态
    const CANCEL_STATUS_NORMAL = 0;   // 正常
    const CANCEL_STATUS_CANCELED = 1; // 已取消

    // 错误提示
    const MSG_PROJECT_NOT_FOUND = '项目不存在';
    const MSG_APPLY_FAILED = '申请报备失败';
    const MSG_EDIT_FAILED = '修改报备失败';
    const MSG_REPORT_NOT_FOUND = '报备信息不存在';
    const MSG_EXCEPTION_FAILED = '异常失败';

    /**
     * 申请报备
     */
    public function applyFor(array $request_data): bool
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        $model = ProjectReport::where('project_id', $request_data['project_id'])->first();
        $is_apply = false;
        if ($model) {
            if ($model->supplier_id != $request_data['supplier_id']) {
                $model->delete();
                $model = new ProjectReport;
            }
            $project->report_status = self::REPORT_STATUS_INIT;
            $project->save();
            $notific = Template::REPORT['SUBMIT_EDIT'];
            $is_apply = true;
        }
        if (!$model) {
            $model = new ProjectReport;
            $notific = Template::REPORT['SUBMIT_SUCCESS'];
        }
        $member_id = \YunShop::app()->getMemberId();
        $request_data['member_id'] = $member_id;
        $request_data['project_progress'] = $request_data['project_progress'] ? implode(",", $request_data['project_progress']) : "";
        $request_data['is_cancel'] = self::CANCEL_STATUS_NORMAL;
        $request_data['company_type'] = $request_data['company_type'] ? implode(",", $request_data['company_type']) : "";
        $request_data['project_file'] = !empty($request_data['project_file']) ? serialize($request_data['project_file']) : serialize([]);

        $model->setRawAttributes($request_data);
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                $project->report_status = self::REPORT_STATUS_PENDING;
                $project->save();
                $option['related_id'] = $project->id;
                app('notification')->send(
                    $member_id,
                    Template::AUDIT,
                    Template::REPORT_NAME,
                    $notific,
                    ['report_name' => $request_data['name']],
                    $option
                );
                if (!$is_apply) {
                    (new SystemMsgService())->createProject("reportProject", $request_data['name'], $model->supplier_id, $model->id);
                }
                return true;
            } else {
                throw new AppException(self::MSG_APPLY_FAILED);
            }
        }
    }

    /**
     * 修改报备
     */
    public function edit(array $request_data): bool
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        $ProjectReport = ProjectReport::where('id', $request_data['id'])->first();

        if ($request_data['supplier_id'] != $ProjectReport->supplier_id) {
            $ProjectReport->delete();
            $ProjectReport = new ProjectReport();
        }

        unset($request_data['id']);
        $request_data['project_progress'] = $request_data['project_progress'] ? implode($request_data['project_progress']) : "";
        $request_data['company_type'] = $request_data['company_type'] ? implode($request_data['company_type']) : "";
        $request_data['purchase_mode'] = $request_data['purchase_mode'] ? implode(",", $request_data['purchase_mode']) : "";

        $ProjectReport->setRawAttributes($request_data);
        $validator = $ProjectReport->validator($ProjectReport->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($ProjectReport->save()) {
                return true;
            } else {
                throw new AppException(self::MSG_EDIT_FAILED);
            }
        }
    }

    /**
     * 取消报备
     */
    public function cancel(int $id): bool
    {
        $projectReport = ProjectReport::where('id', $id)->first();

        if (!$projectReport) {
            throw new ShopException(self::MSG_REPORT_NOT_FOUND);
        }

        try {
            ProjectReport::where('id', $id)->update(['is_cancel' => self::CANCEL_STATUS_CANCELED]);
            $project = Project::find($projectReport->project_id);
            $project->report_status = self::REPORT_STATUS_INIT;
            $project->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_EXCEPTION_FAILED);
        }
    }

    /**
     * 上传资料
     */
    public function upload(): array
    {
        $file = request()->file('file');
        $uploadService = new UploadService();
        try {
            return $uploadService->upload($file, 'files');
        } catch (ShopException $exception) {
            throw new ShopException(config("app.debug") ? $exception->getMessage() : self::MSG_EXCEPTION_FAILED);
        }
    }
}
