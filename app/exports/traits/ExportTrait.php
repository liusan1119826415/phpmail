<?php

namespace app\exports\traits;

use app\backend\modules\export\models\ExportRecord;
use app\backend\modules\export\models\ExportTemplate;

trait ExportTrait
{
    public function export($action = '')
    {
        $request = request()->post();
        $template_id = $request['template_id'];
        $export_type = $request['export_type'];
        //增加验证功能
        if (!app('Export')->getActionModel($export_type)->preValidate($request)) {
            return $this->errorJson('导出失败');
        }
        $columns = ExportTemplate::where('id', $template_id)->value('columns');
        ExportRecord::create([
            'template_id' => $template_id,
            'export_type' => $export_type,
            'status' => 0,
            'uniacid' => \YunShop::app()->uniacid,
            'columns' => $columns,
            'request' => $request,
            'user_id' => \YunShop::app()->uid,
            'action' => $action
        ]);
        return $this->successJson('导出成功');
    }
}
