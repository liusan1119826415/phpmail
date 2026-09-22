<?php

namespace app\backend\modules\goods\models;

use app\backend\modules\order\models\OrderExport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GoodsExport extends OrderExport
{

    public $data;//订单数据
    public $status; //sheet名称（订单状态）
    public $column; //总行数
    public $goodsNum = []; //一个订单的商品数量

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $this->column = count($this->data[0]);

        $list = [];
        $count = 1;
        foreach ($this->data as $key => $value) {
            $rowHandle = 0;
            if ($key > 0) {
                $column_array = [];
                $column = [];
                foreach ($value as $column_key => $column_value) {
                    if (is_array($column_value)) {
                        if (!$rowHandle) {
                            $this->goodsNum[] = ['num' => count($column_value)?:1, 'row' => $count + 1];
                            $count += count($column_value)?:1;
                            $rowHandle = 1;
                        }
                        foreach ($column_value as $goods_key => $goods_value) {
                            $column_array[] = array_merge($column, $goods_value);
                        }
                    } else {
                        if ($column_array) {
                            foreach ($column_array as $k => $v) {
                                $column_array[$k][] = $column_value;
                            }

                        } else {
                            $column[] = $column_value;
                        }
                    }
                }
                if (!$rowHandle) {
                    $count++;
                }
                if ($column_array) {
                    $list = array_merge($list, $column_array);
                } else {
                    $list = array_merge($list, [$column]);
                }
            } else {
                $list[] = $value;
            }
        }
//        dd($this->goodsNum);
        return $list;

    }

    public function styles(Worksheet $sheet)
    {
        $a=array_intersect($this->data[0],['商品规格标题','库存','现价','原价','成本价','商品编码','商品条码']);

        $needCells = $this->generateColumn(key($a));
        $index = array_search('供应商名称',$this->data[0]);
        if($index!==false){
            array_push($needCells,$this->numberToLetter($index));
        }
        $cells = $this->generateColumn(count($this->data[0]));
        foreach ($cells as $item) {
            if (in_array($item, $needCells)) {
                foreach ($this->goodsNum as $key => $value) {
                    $start = $value['row'];
                    $end = $start + $value['num'] - 1;
                    if ($value['num'] > 1) {
                        $sheet->mergeCells($item . $start . ':' . $item . $end); //合并单元格
                    }
                }
            }
        }
    }

    //数字转换成execl中的列编号
    private function numberToLetter($num){
        $y = ($num / 26);
        if ($y >= 1) {
            $y = intval($y);
            $cell = chr($y + 64).chr($num - $y * 26 + 65);
        } else {
            $cell = chr($num + 65);
        }
        return $cell;
    }


    //获取对应个数表格编号
    private function generateColumn($column){
        $cell=[];
        for($i=0;$i< $column;$i++) {
            $y = ($i / 26);
            if ($y >= 1) {
                $y = intval($y);
                $cell[] = chr($y + 64).chr($i - $y * 26 + 65);
            } else {
                $cell[] = chr($i + 65);
            }
        }
        return $cell;
    }
}