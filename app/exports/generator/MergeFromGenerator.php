<?php

namespace app\exports\generator;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MergeFromGenerator extends FromGenerator implements WithEvents
{
    public $data;//订单数据
    public $status; //sheet名称（订单状态）
    public $column; //总行数
    public $goodsNum = []; //一个订单的商品数量
    public $mergeRows = [];//每笔订单需要单独合并单元格的数据
    public $second = [];
    public $third = [];
    private $head = [];

    public function generator(): \Generator
    {
        $count = 0;
        \Log::debug('导出任务守护进程内存--数据处理',[memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024]);
        foreach ($this->data as $key => $order) {
            if ($key == 0) {
                $this->head = array_keys($order);
                $this->third = array_intersect($this->head, $this->third);
                $this->second = array_intersect($this->head, $this->second);
                $empty_order = array_fill_keys($this->head, '');
                yield $count++ => array_values($order);
            } else {
                //拿出有多少个一级分类
                if (!empty($this->second)) {
                    foreach ($order[array_first($this->second)] as $k => $v) {
                        if ($k == 0) {
                            $temp = $order;
                        } else {
                            $temp = $empty_order;
                        }
                        foreach ($this->second as $second) {
                            $temp[$second] = $order[$second][$k];
                        }

                        if ($order[array_first($this->third)]) {
                            $this->mergeRows[$key]['second'][] = count($order[array_first($this->third)][$k]);
                            foreach ($order[array_first($this->third)][$k] as $kk => $cate) {
                                $temp2 = $temp;
                                foreach ($this->third as $vv) {
                                    $temp2[$vv] = $order[$vv][$k][$kk];
                                }

                                yield $count++ => $temp2;
                            }
                        } else {
                            yield $count++ => $temp;
                        }
                    }
                } elseif (!empty($this->third)) {
                    foreach ($order[array_first($this->third)] as $k => $cate) {
                        foreach ($cate as $kk => $vv) {
                            if ($k == 0) {
                                $temp = $order;
                            } else {
                                $temp = $empty_order;
                            }
                            foreach ($this->third as $vv) {
                                $temp[$vv] = $temp[$vv][$k][$kk];
                            }
                            yield $count++ => $temp;
                        }

                    }
                } else {
                    yield $count++ => $order;
                }
                $this->mergeRows[$key]['first'] = $count ?: 1;
            }
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                \Log::debug('导出任务守护进程内存--样式处理',[memory_get_usage()/1024/1024,memory_get_usage(true)/1024/1024]);

                //获取对应个数表格编号
                $sheet = $event->sheet;
                $i = 0;
                $cell = [];
                foreach ($this->head as $value) {
                    $y = ($i / 26);
                    if ($y >= 1) {
                        $y = intval($y);
                        $cell[$value] = chr($y + 64) . chr($i - $y * 26 + 65);
                    } else {
                        $cell[$value] = chr($i + 65);
                    }
                    $i++;
                }
                //设置样式
                foreach ($cell as $key => $item) {
                    $start = 2;
                    foreach ($this->mergeRows as $rowConfig) {
                        if (in_array($key, $this->third)) {
                            $end = $start;
                        } elseif (in_array($key, $this->second)) {
//                    //复制样式
                            foreach ($rowConfig['second'] as $second) {
                                $end = $start + $second - 1;
                                if ($start != $end) {
                                    $sheet->duplicateStyle($sheet->getStyle('A1'), $item . $start . ':' . $item . $end);
                                    $sheet->mergeCells($item . $start . ':' . $item . $end); //合并单元格
                                }
                                $start = $end + 1;
                            }
                        } else {
                            $end = $rowConfig['first'];
                            //复制样式
                            if ($start != $end) {
                                $sheet->duplicateStyle($sheet->getStyle('A1'), $item . $start . ':' . $item . $end);
                                $sheet->mergeCells($item . $start . ':' . $item . $end); //合并单元格
                            }
                        }
                        $start = $end + 1;
                    }
                }
            }
        ];
        // TODO: Implement registerEvents() method.
    }
}
