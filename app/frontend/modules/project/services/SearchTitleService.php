<?php


namespace app\frontend\modules\project\services;

use app\common\models\Category;
use Yunshop\Supplier\common\models\Supplier;

class SearchTitleService
{


    public function search_fuzzy($title, $search_type)
    {
        if (empty($title)) {
            return [];
        }

        switch ($search_type) {
            case 1:
                // 分类搜索
                $data = $this->searchCategoriesOnly($title);
                break;
            case 2:
                // 商品/规格搜索
                $data = $this->searchGoodsAndOptionsOnly($title);
                break;
            case 3:
                // 品牌搜索
                $data = $this->searchBrandsOnly($title);
                break;
            default:

                $data = [];
                break;
        }

        return $data;
    }


    /**
     * 单独搜索商品和规格（使用 DB left join 查询）
     * 同时搜索商品标题和规格标题，返回拼接后的完整标题（商品+规格）
     */
    protected function searchGoodsAndOptionsOnly($query, $limit = 20)
    {
        // if (strlen($query) < 2) {
        //     return [];
        // }

        $db = \DB::connection();

        $searchTerm = str_replace(' ', '', $query);

        $results = $db->table('yz_goods as g')
            ->leftJoin('yz_goods_option as go', 'g.id', '=', 'go.goods_id') // 关联所有规格，不再限制 is_default
            ->where(function ($queryBuilder) use ($searchTerm) {
                // 忽略空格进行匹配：将商品标题和规格标题中的空格去掉后再 LIKE
                $queryBuilder->where(\DB::raw("REPLACE(ims_g.title, ' ', '')"), 'like', "%{$searchTerm}%")
                    ->orWhere(\DB::raw("REPLACE(ims_go.title, ' ', '')"), 'like', "%{$searchTerm}%");       
              
            })
            ->where('g.status', 1)
            ->whereNull('g.deleted_at')
            ->where('go.is_default', 1)
            ->select([
                'g.id as goods_id',
                'go.id as option_id',
                $db->raw("CONCAT(IFNULL(ims_g.title, ''), IFNULL(SUBSTRING_INDEX(ims_go.title, '+', 1), '')) as text"),
                $db->raw("CASE 
                WHEN ims_go.id IS NOT NULL THEN 'option'
                ELSE 'goods'
            END as type")
            ])
            ->orderBy('text', 'asc') // 按拼接文本排序
            ->limit($limit)
            ->get()
            ->unique('text') // 按拼接后的完整标题去重
            ->map(function ($item) {
                // 根据类型返回对应的 ID
                if ($item->type == 'option') {
                    $item->id = $item->option_id;
                } else {
                    $item->id = $item->goods_id;
                }
                $item->display = $item->text;
                unset($item->goods_id, $item->option_id);
                return $item;
            });

        return array_values($results->toArray());
    }


    /**
     * 单独搜索分类（按display_order ASC排序）
     */
    protected function searchCategoriesOnly($query, $limit = 20)
    {
        // if (strlen($query) < 2) {
        //     return [];
        // }

        return Category::where('name', 'like', "%{$query}%")
            ->orderBy('display_order', 'asc')
            ->select([
                'id',
                'name as text',
                'parent_id'
            ])
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->display = $item->text;
                return $item;
            });
    }


    /**
     * 单独搜索品牌（按display_order ASC排序）
     */
    protected function searchBrandsOnly($query, $limit = 20)
    {
        // if (strlen($query) < 2) {
        //     return [];
        // }

        return Supplier::where('store_name', 'like', "%{$query}%")
            ->where('enable', 1)
            ->orderBy('display_order', 'asc')
            ->select([
                'id',
                'store_name as text',
            ])
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->display = $item->text;
                return $item;
            });
    }
}
