<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface SpaceRepositoryInterface
{

    public function addSpace(array $request_data):array;

    //修改空间商品数量
    public function updateSpaceNum(int $id,int $num):array;

    public function getProductList(int $space_id):array;

    ##3d组装商品加入项目
    public function add3DSpace(array $data):array;

    ##删除空间商品
    public function destroy(array $ids,int $space_id):array;

    //删除空间
    public function del_space(int $id):array;

    public function createSpace(int $project_id,int $floor_id,string $space_name):int;

    public function editSpace(int $space_id,string $space_name):bool;
    //获取空间产品V2
    public function getProductListV2(int $space_id):array;

    //定制
    public function customized():bool;



}