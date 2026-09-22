<?php

namespace app\exports;

use app\backend\modules\export\models\ExportRecord;
use app\common\services\Storage;
use Illuminate\Support\Fluent;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Finder\Finder;

class Export
{
    private $config;

    public $header = [];

    public $export_type;

    private $types;

    public function __construct()
    {
        $files = [];
        //加载配置
        $configPath = realpath(app()->basePath('app/exports/config'));
        //引入
        foreach (Finder::create()->files()->name('*.php')->in($configPath) as $file) {
            $key = basename($file->getRealPath(), '.php');
            $files[$key] = require $file->getRealPath();
        }
        //扩展
        $this->config = $files;
    }

    public function getColumn($type)
    {
        return $this->config[$type]['columns'];
    }

    public function getTypes(): array
    {
        if (isset($this->types)) {
            return $this->types;
        }
        $this->types = [];
        foreach (ExportRecord::groupBy('export_type')->pluck('export_type') as $value) {
            $this->types[$value] = $this->getActionModel($value)->getName();
        }
        return $this->types;
    }

    public function execute($job)
    {
        \YunShop::app()->uniacid = \Setting::$uniqueAccountId = $job['uniacid'];
        [$columns, $header] = $this->getExportConfig($job);
        $ActionModel = $this->getActionModel($job['export_type'], $job['action']);
        $result = $ActionModel->getResult($columns, new Fluent($job['request']['export_search']), $header);
        //过滤字段
        $file_name = $job['export_type'] . '/' . md5($job['export_type'] . date('YmdHis') . mt_rand(1000, 9999)) . '.' . $ActionModel->ext;
        Excel::store($ActionModel->getFromGenerator($result), $file_name, 'export');
        $job->total = max($ActionModel->total, 0);
        $job->status = 1;
        $job->source_name = $file_name;
        $job->download_name = $ActionModel->getName() . $job['created_at'] . '.' . $ActionModel->getExt();
        $job->save();
    }

    public function getActionModel($export_type, $action = '')
    {
        if ($action) {
            return app($action);
        } else if ($this->config[$export_type]['action']) {
            $action = $this->config[$export_type]['action'];
        } else {
            $action = '\app\exports\action\\' . ucfirst($export_type);
        }
        return app($action);
    }


    public function getExportConfig($job)
    {
        $export_config = [];
        foreach ($this->config[$job['export_type']]['columns'] as $subArray) {
            $export_config = array_merge($export_config, $subArray);
        }
        $columns = array_values(array_intersect(array_keys($export_config), $job['columns']));
        $header = [];
        foreach ($columns as $value) {
            $header[$value] = $export_config[$value];
        }
        return [$columns, $header];
    }

}
