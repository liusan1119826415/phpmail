<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface DiyModelRepositoryInterface
{

    public function seriesGoods(int $goods_id):array;

    public function relatedProducts(int $goods_id):array;


    //方案管理列表
    public function designList(array $search):array;

    //创建副本
    public function duplicateDesign(int $designId):bool;

    //重命名
    public function renameDesign(int $designId,string $name):bool;

    //删除方案
    public function deleteDesign(int $designId):bool;

    //方案回收站列表
    public function designRecycleList(array $search):array;

    //恢复方案
    public function restoreDesign(int $designId):bool;

    //删除回收站真实删除
    public function deleteDesignReal(int $designId):bool;

    //上传dwg或dxf文件
    public function uploadDwg():array;

    //保存方案
    public function saveDesign(array $data):array;

    //获取方案详情
    public function getDesignDetail(int $designId):array;

    //获取产品品类
    public function getSearchCategory():array;

    //获取查询条件产地
    public function getSearchPlace():array;

    //获取我的收藏
    public function getMyCollect():array;

    //检测项目是否加入了别的商品
    public function checkOverlap(int $projectId):array;

    //获取推荐行业案例
    public function getRecommendIndustry():array;

    //获取反馈问题
    public function getIssueOptions():array;

    //提交反馈
    public function submitIssue(array $data):bool;

    //查询商品状态
    public function getGoodsStatusAll(array $goodsIds):array;






}