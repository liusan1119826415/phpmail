<?php

namespace app\exports\generator;

class FromGenerator implements \Maatwebsite\Excel\Concerns\FromGenerator
{

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function generator():\Generator
    {
        \Log::debug('导出任务守护进程内存--数据处理',[memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024]);
        return $this->data;
    }
}
