<?php

/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/12/10
 * Time: 14:04
 */

namespace app\common\models\goods;

use app\common\models\BaseModel;

use app\common\models\Category;

class GoodsSpecCategory extends BaseModel
{
    protected $table = 'yz_goods_spec_category';

    public $timestamps = true;

    protected $fillable = [
        "spec_item_id",
        "category_id",
        "category_type",
        "parent_category_id"
    ];

    protected $casts = [
        'category_type' => 'integer',
    ];

    /**
     * 关联分类模型
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * 获取二级分类名称
     * 
     * @param int $spec_item_id 规格项ID
     * @return array|string 返回二级分类名称
     */
    // public static function getCategoryNames($spec_item_id)
    // {
    //     // 只查询二级分类（category_type = 2）
    //     $records = self::where('spec_item_id', $spec_item_id)
    //         ->where('category_type', 2) // 只获取二级分类
    //         ->with('category')
    //         ->get();
        
    //     if ($records->isEmpty()) {
    //         return [];
    //     }
        
    //     // 提取分类名称
    //     $categoryNames = [];
    //     foreach ($records as $record) {
    //         if ($record->category) {
    //             $categoryNames[] = $record->category->name;
    //         }
    //     }
        
    //     // 去重并返回
    //     return array_unique($categoryNames);
    // }


    /**
     * 获取二级分类名称（只返回第一个）
     * 
     * @param int $spec_item_id 规格项ID
     * @return string|null 返回二级分类名称，如果没有则返回null
     */
    public static function getCategoryNames($spec_item_id)
    {
        // 只查询二级分类（category_type = 2），获取第一个
        $record = self::where('spec_item_id', $spec_item_id)
            ->where('category_type', 2) // 只获取二级分类
            ->with('category')
            ->first(); // 使用 first() 代替 get()

        // 如果没有记录或没有关联的分类，返回null
        if (!$record || !$record->category) {
            return null;
        }

        // 直接返回分类名称
        return $record->category->name;
    }

    /**
     * 获取二级分类名称字符串
     * 
     * @param int $spec_item_id 规格项ID
     * @param string $separator 分隔符，默认逗号
     * @return string 返回分类名称字符串
     */
    public function getCategoryNamesString($spec_item_id, $separator = ',')
    {
        $categoryNames = $this->getCategoryNames($spec_item_id);

        if (empty($categoryNames)) {
            return '';
        }

        return implode($separator, $categoryNames);
    }

    /**
     * 获取二级分类的详细信息（包括ID和名称）
     * 
     * @param int $spec_item_id 规格项ID
     * @return array 返回包含分类ID和名称的数组
     */
    public function getCategoryDetails($spec_item_id)
    {
        // 只查询二级分类（category_type = 2）
        $records = self::where('spec_item_id', $spec_item_id)
            ->where('category_type', 2)
            ->with('category')
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        $categoryDetails = [];
        foreach ($records as $record) {
            if ($record->category) {
                $categoryDetails[] = [
                    'id' => $record->category_id,
                    'name' => $record->category->name,
                    'parent_category_id' => $record->parent_category_id,
                ];
            }
        }

        return $categoryDetails;
    }
}
