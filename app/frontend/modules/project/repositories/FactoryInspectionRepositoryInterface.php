<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

/**
 * 投标项目管理
 * Interface ReportProjectRepositoryInterface
 * @package app\frontend\modules\project\repositories
 */
interface FactoryInspectionRepositoryInterface
{

    public function applyFor(array $request_data):bool;



    //报备项目列表
    public function getList(array $search):array;

    //考察详情
    public function getApplyData(int $id):array;

    public function getDetail(int $id):array;

    public function delete(int $id):bool;

    //取消申请
    public function cancelApply(int $id):bool;


}