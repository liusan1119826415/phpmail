<?php

namespace app\frontend\modules\project\services\related;
/**
 * 负责单独处理“关联规格”的数据（常用于父模型构建等场景）
 */
class RelatedGoodsOptionHandler
{
    private $queryService;
    private $builder;
    private $relatedOptionIds;  // 直接传入关联的 option id 数组

    public function __construct(array $relatedOptionIds, GoodsOptionQueryService $queryService, GoodsOptionDataBuilder $builder)
    {
        $this->relatedOptionIds = $relatedOptionIds;
        $this->queryService = $queryService;
        $this->builder = $builder;
    }

    public function fetch(int $contextGoodsId = 0): array
    {
        if (empty($this->relatedOptionIds)) {
            return [];
        }
        $options = $this->queryService->getOptionsByIds($this->relatedOptionIds);
        // 构建时标记为关联（添加 is_related = true）
        return $this->builder->build($options, $contextGoodsId, $this->relatedOptionIds, true);
    }
}