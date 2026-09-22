<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

/**
 * 报备项目管理
 * Interface ReportProjectRepositoryInterface
 * @package app\frontend\modules\project\repositories
 */
interface ReportProjectRepositoryInterface
{

    public function applyFor(array $request_data):bool;



    //报备项目列表
    public function getList(array $search):array;

    //报备详情
    public function detail(int $id):array;

    //获取推荐品牌数据
    public function getRecommendBrand(int $project_id):array;

    //搜索品牌
    public function searchBrand(string $name):array;

   //修改报备
    public function edit(array $request_data):bool;

    //取消报备
    public function cancel(int $id):bool;

    public function getSearchData():array;

    //上传资料
    public function upload():array;
}