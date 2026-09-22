<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

/**
 * 投标项目管理
 * Interface ReportProjectRepositoryInterface
 * @package app\frontend\modules\project\repositories
 */
interface BidProjectRepositoryInterface
{

    public function applyFor(array $request_data):int;



    //报备项目列表
    public function getList(array $search):array;

    //报备详情
    public function detail(int $id):array;

    //获取推荐品牌数据
    public function getRecommendBrand(int $project_id):array;

    //搜索品牌
    public function searchBrand(string $name):array;


    //取消申请
    public function cancelApply(int $id):bool;


}