<?php

namespace app\backend\modules\export\observes;

use app\backend\modules\export\models\ExportRecord;
use Illuminate\Support\Facades\Storage;

class ExportRecordObserve
{
    public function deleted(ExportRecord $exportRecord) {
        $source_name = $exportRecord->source_name;
        Storage::disk('export')->delete($source_name);
    }
}
