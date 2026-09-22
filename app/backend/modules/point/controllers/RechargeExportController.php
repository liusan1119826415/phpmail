<?php

namespace app\backend\modules\point\controllers;


use app\backend\modules\point\models\RechargeModel;
use app\common\components\BaseController;
use app\common\services\ExportService;
use app\exports\traits\ExportTrait;

class RechargeExportController extends BaseController
{
    use ExportTrait;

    public function index()
    {
        return $this->export();
    }
}
