<?php

namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\GoodsExport;
use app\common\services\ExportService;

class GoodsExportService extends ExportService
{
    public function __construct($builder, $export_page = 1,$pageSize=500)
    {
        $this->page_size=$pageSize;
        parent::__construct($builder,$export_page,$pageSize);

    }

    public function getExportBuilder()
    {
        return new GoodsExport($this->export_data);
    }
}