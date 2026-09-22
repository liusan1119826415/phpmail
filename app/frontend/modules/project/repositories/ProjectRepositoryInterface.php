<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface ProjectRepositoryInterface
{

    public function createProject(array $request_data):int;

    public function editProject(array $request_data):bool;

    public function getFloors(int $project_id):array;



    //激活配置项目
    public function switchActiveProject(int $project_id,int $floor_id=null,int $space_id =null):bool;

    //获取配置项目
    public function getActiveProject():array;
    //获取我的项目
    public function getMyProject():array;

    //退出方案
    public function logout(int $project_id):bool;

    //创建楼层
    public function createFloor(int $project_id,string $floor_name):array;

    //修改排序
   // public function updateSort(array $data):bool;

    //整体改价
    public function updatePrice(int $project_id,float $ratio):array;

    //项目列表
    public function getList(array $search):array;

    //获取省市
    public function getProvinceCity(int $id):array;

    //项目加入回收站
    public function delete(int $id):bool;

    //更改项目状态
    public function updateStatus(int $id,int $status):bool;

    public function copyProject(int $id):bool;

    //项目详情
    public function detail(int $id):array;

    //平面图删除
    public function planDelete(int $id):bool;

    //还原回收站
    public function restore(int $id):bool;

    public function realDelete(int $id):bool;

    //保存平面图
    public function savePlan():bool;

    public function getProjectData(int $space_id):array;

    public function updateFloorCad(array $data):bool;






}