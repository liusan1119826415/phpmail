<?php
namespace app\frontend\modules\project\services\related;


/**
 * 负责获取"当前商品的主规格"数据（可包含关联规格）
 */
class CurrentGoodsOptionHandler
{
    private $queryService;
    private $builder;
    private $goodsId;

    public function __construct(int $goodsId, GoodsOptionQueryService $queryService, GoodsOptionDataBuilder $builder)
    {
        $this->goodsId = $goodsId;
        $this->queryService = $queryService;
        $this->builder = $builder;
    }

    /**
     * 获取最终数据（主规格 + 合并的关联规格）
     */
    public function fetch(): array
    {
        // 1. 获取主选项
        $mainOptions = $this->queryService->getMainOptionsByGoodsId($this->goodsId);

        // 2. 获取关联选项 ID
        $relatedIds = $this->queryService->getRelatedOptionIds($this->goodsId);
        $relatedOptions = collect();
        if (!empty($relatedIds)) {
            $relatedOptions = $this->queryService->getOptionsByIds($relatedIds);
            // 标记关联来源
            foreach ($relatedOptions as $opt) {
                $opt->is_related = true;
            }
        }

        // 3. 合并去重
        $allOptions = $mainOptions;
        $existingIds = $mainOptions->pluck('id')->all();
        foreach ($relatedOptions as $ro) {
            if (!in_array($ro->id, $existingIds)) {
                $allOptions->push($ro);
            }
        }

        // 4. 构建输出
        return $this->builder->build($allOptions, $this->goodsId, $relatedIds, false);
    }
}
