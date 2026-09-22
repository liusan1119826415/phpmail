<?php



namespace app\backend\modules\point\controllers;


use app\backend\modules\finance\models\PointLog;
use app\common\components\BaseController;
use app\common\services\ExportService;
use app\exports\traits\ExportTrait;

class ExportController extends BaseController
{
    use ExportTrait;

    public function index()
    {
        return $this->export();
    }
}
