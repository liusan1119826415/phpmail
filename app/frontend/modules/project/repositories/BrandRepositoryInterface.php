<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface BrandRepositoryInterface
{

    public function conditions():array;

    public function search(array $filters):array;

    //关注品牌厂家
    public function follow(int $supplier_id,int $follow_type):bool;

    //查询是否关注
    public function isFollow(int $supplier_id):array;

    public function detail(int $supplier_id):array;

    public function getBrandGoods(int $supplier_id):array;


    public function getBrandOther(int $supplier_id,int $other_type):array;





}