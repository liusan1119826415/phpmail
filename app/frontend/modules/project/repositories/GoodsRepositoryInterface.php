<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface GoodsRepositoryInterface
{

    public function conditions():array;

    public function search(array $filters,array $order_field):array;
    //预览清单
    public function previewList(int $project_id):array;

    //批量改单价
    public function updateUnitPrice(array $option_ids,float $price,int $method,int $space_id):array;

    //替换
    public function updateOption(array $request_data):array;

    //解析cad
    public function analysis():array;

    public function analysisV2():array;

    //编辑产品
    public function getGoodsOption(int $goods_id):array;

    public function getGoodsInfo(int $goods_id):array;

    public function getComment();





}