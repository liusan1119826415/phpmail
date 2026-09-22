<?php

namespace app\frontend\modules\project\infrastructure;

use app\frontend\modules\project\repositories\DiyModelRepositoryInterface;
use app\frontend\modules\project\services\diymodel\DesignAssemblyService;
use app\frontend\modules\project\services\diymodel\DesignCrudService;
use app\frontend\modules\project\services\diymodel\DesignGoodsService;
use app\frontend\modules\project\services\diymodel\DesignQueryService;
use app\common\models\project\CloudDesign;

class DiyModelRepository extends BaseRepository implements DiyModelRepositoryInterface
{
    protected DesignQueryService $queryService;
    protected DesignCrudService $crudService;
    protected DesignGoodsService $goodsService;
    protected DesignAssemblyService $assemblyService;

    public function __construct(
        CloudDesign $model,
        DesignQueryService $queryService,
        DesignCrudService $crudService,
        DesignGoodsService $goodsService,
        DesignAssemblyService $assemblyService
    ) {
        parent::__construct($model);
        $this->queryService = $queryService;
        $this->crudService = $crudService;
        $this->goodsService = $goodsService;
        $this->assemblyService = $assemblyService;
    }

    //获取系列产品
    public function seriesGoods(int $goods_id): array
    {
        return $this->queryService->seriesGoods($goods_id);
    }

    //获取关联产品
    public function relatedProducts(int $goods_id): array
    {
        return $this->queryService->relatedProducts($goods_id);
    }

    //方案管理列表
    public function designList(array $search): array
    {
        return $this->crudService->designList($search);
    }

    //创建副本
    public function duplicateDesign(int $designId): bool
    {
        return $this->crudService->duplicateDesign($designId);
    }

    //重命名
    public function renameDesign(int $designId, string $name): bool
    {
        return $this->crudService->renameDesign($designId, $name);
    }

    //删除方案
    public function deleteDesign(int $designId): bool
    {
        return $this->crudService->deleteDesign($designId);
    }

    //方案回收站列表
    public function designRecycleList(array $search): array
    {
        return $this->crudService->designRecycleList($search);
    }

    //恢复方案
    public function restoreDesign(int $designId): bool
    {
        return $this->crudService->restoreDesign($designId);
    }

    //真实删除
    public function deleteDesignReal(int $designId): bool
    {
        return $this->crudService->deleteDesignReal($designId);
    }

    //上传dwg文件
    public function uploadDwg(): array
    {
        return $this->crudService->uploadDwg();
    }

    //保存方案
    public function saveDesign(array $data): array
    {
        return $this->crudService->saveDesign($data);
    }

    //获取方案详情
    public function getDesignDetail(int $designId): array
    {
        return $this->queryService->getDesignDetail($designId);
    }

    //获取产品品类
    public function getSearchCategory(): array
    {
        return $this->queryService->getSearchCategory();
    }

    //获取查询条件产地
    public function getSearchPlace(): array
    {
        return $this->queryService->getSearchPlace();
    }

    //获取我的收藏
    public function getMyCollect(): array
    {
        return $this->queryService->getMyCollect();
    }

    //检测是否覆盖
    public function checkOverlap(int $project_id): array
    {
        return $this->queryService->checkOverlap($project_id);
    }

    //获取推荐行业案例
    public function getRecommendIndustry(): array
    {
        return $this->queryService->getRecommendIndustry();
    }

    //获取反馈问题
    public function getIssueOptions(): array
    {
        return $this->queryService->getIssueOptions();
    }

    //提交反馈
    public function submitIssue(array $data): bool
    {
        return $this->queryService->submitIssue($data);
    }

    //查询商品状态
    public function getGoodsStatusAll(array $goodsIds): array
    {
        return $this->queryService->getGoodsStatusAll($goodsIds);
    }

    //获取选项模型数据（供 GoodsOptionDataBuilder 使用）
    public function getOptionModels($optionIds)
    {
        return $this->goodsService->getOptionModels($optionIds);
    }
}
