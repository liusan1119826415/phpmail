<?php


namespace app\frontend\modules\project\services;



use app\frontend\modules\project\repositories\DiyModelRepositoryInterface;
use phpDocumentor\Reflection\Types\Boolean;

class DiyModelService
{

    private DiyModelRepositoryInterface $diyModelRepositoryInterface ;

    public function __construct(DiyModelRepositoryInterface $diyModelRepositoryInterface)
    {
        $this->diyModelRepositoryInterface = $diyModelRepositoryInterface;
    }


    public function seriesGoods($goods_id):array
    {
        return $this->diyModelRepositoryInterface->seriesGoods($goods_id);
    }

    public function relatedProducts($goods_id):array
    {
        return $this->diyModelRepositoryInterface->relatedProducts($goods_id);
    }

    public function designList($search):array
    {
        return $this->diyModelRepositoryInterface->designList($search);
    }

    public function duplicateDesign($designId):bool
    {
        return $this->diyModelRepositoryInterface->duplicateDesign($designId);
    }

    public function renameDesign($designId,$name):bool
    {
        return $this->diyModelRepositoryInterface->renameDesign($designId,$name);
    }

    public function deleteDesign($designId):bool
    {
        return $this->diyModelRepositoryInterface->deleteDesign($designId);
    }

    public function designRecycleList($search):array
    {

        return $this->diyModelRepositoryInterface->designRecycleList($search);
    }

    public function restoreDesign($designId):bool
    {
        return $this->diyModelRepositoryInterface->restoreDesign($designId);
    }

    public function deleteDesignReal($designId):bool
    {
        return $this->diyModelRepositoryInterface->deleteDesignReal($designId);
    }

    public function uploadDwg():array
    {
        return $this->diyModelRepositoryInterface->uploadDwg();
    }

    public function saveDesign(array $data):array
    {
        return $this->diyModelRepositoryInterface->saveDesign($data);
    }

    public function getDesignDetail($designId):array
    {
        return $this->diyModelRepositoryInterface->getDesignDetail($designId);
    }

    public function getSearchCategory():array
    {
        return $this->diyModelRepositoryInterface->getSearchCategory();
    }

    public function getSearchPlace():array
    {
        return $this->diyModelRepositoryInterface->getSearchPlace();
    }

    public function getMyCollect():array
    {
        return $this->diyModelRepositoryInterface->getMyCollect();
    }

    public function checkOverlap($projectId):array
    {
        return $this->diyModelRepositoryInterface->checkOverlap($projectId);
    }

    public function getRecommendIndustry():array
    {
        return $this->diyModelRepositoryInterface->getRecommendIndustry();
    }

    public function getIssueOptions():array
    {
        return $this->diyModelRepositoryInterface->getIssueOptions();
    }

    public function submitIssue(array $data):bool
    {
        return $this->diyModelRepositoryInterface->submitIssue($data);
    }

    public function getGoodsStatus(array $goodsIds):array
    {
        return $this->diyModelRepositoryInterface->getGoodsStatusAll($goodsIds);
    }

}