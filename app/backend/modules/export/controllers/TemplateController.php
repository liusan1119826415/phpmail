<?php

namespace app\backend\modules\export\controllers;

use app\backend\modules\export\models\ExportTemplate;
use app\common\components\BaseController;

class TemplateController extends BaseController
{
    private $export_type;

    public function preAction()
    {
        $this->export_type = request()->input('export_type');
    }

    public function create()
    {
        $model = new ExportTemplate();
        $model->columns = request()->input('columns');
        $model->name = request()->input('name');
        $model->uniacid = \YunShop::app()->uniacid;
        $model->export_type = $this->export_type;
        if ($model->save()) {
            return $this->successJson('成功', ['id' => $model->id]);
        }
        return $this->errorJson();
    }

    public function delete()
    {
        $id = request()->input('id');
        if (ExportTemplate::where('id', $id)->delete()) {
            return $this->successJson();
        }
        return $this->errorJson();
    }


    public function update()
    {
        $id = request()->input('id');
        $model = ExportTemplate::where('id', $id)->first();
        $model->columns = request()->input('columns');
        $model->name = request()->input('name');
        if ($model->save()) {
            return $this->successJson('成功', ['id' => $model->id]);
        }
        return $this->errorJson();
    }

    public function getList()
    {
        $list = ExportTemplate::where('export_type', $this->export_type)->orderBy('id', 'desc')->get();
        return $this->successJson('成功', $list);
    }

    public function getDetail()
    {
        $id = request()->input('id');
        $model = ExportTemplate::select(['id', 'name', 'columns'])->where('id', $id)->first();
        return $this->successJson('成功', $model);
    }

    public function getColumns()
    {
        $columns = app('Export')->getColumn($this->export_type) ?? [];
        return $this->successJson('成功', $columns);
    }


}
