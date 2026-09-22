<?php


namespace app\frontend\modules\project\services;


use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\Address;
use app\common\models\comment\CommentConfig;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\goods\GoodsOptionModelUrl;
use app\common\models\goods\GoodsVideo;
use app\common\models\GoodsCategory;
use app\common\models\kefu\ServiceUser;
use app\common\models\MemberLevel;
use app\frontend\models\GoodsOption;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\goods\models\Comment;
use app\frontend\modules\member\controllers\MemberFavoriteController;
use app\frontend\modules\member\controllers\MemberHistoryController;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierFollow;

class GoodsBaseService
{

    const MM = "mm";
    protected $goods;

    public function __construct($goodsId)
    {
        $option_select = 'id,goods_id,title,thumb,product_price,cost_price,market_price,stock,specs,weight,product_sn,d3model,cad_plan_model,d3ModelUrl_weld,d3MaxUrl';
        $this->goods = Goods::where('id', $goodsId)->with([
            'hasManySpecs' => function ($query) {
                return $query->select('id', 'goods_id', 'title', 'description')->with(['hasManySpecsItem' => function ($specs) {
                    return $specs->select('id', 'title', 'specid', 'thumb', 'parent_id', 'level')->where('show', 1)->orderBy('display_order', 'asc');
                }])->orderBy('display_order', 'asc');
            },
            'hasManyOptions' => function ($query) use ($option_select) {
                return $query->selectRaw($option_select);
            },
        ])->first();

    }


    public function getGoodsData()
    {
        $data = [
            'three_model' => $this->getThreeModel(),
            'product_series' => $this->getProductSeries($this->goods),
            'brand_info' => $this->getBrandInfo($this->goods),
            'get_goods' => $this->enrichGoodsModel(),
            'lovely_goods_list' => $this->lovely(),
            // 'comment_data'=>$this->setGoodsComment(),
        ];
        return $data;
    }


    public function setGoodsComment()
    {
        app('db')->cacheSelect = true;
        $pageSize = 10;

        $is_show_good_reputation_text = 0;//默认好评已隐藏字样：0-不显示，1-显示
        if (Comment::isShowGoodReputationText($this->goods->id)) {
            $is_show_good_reputation_text = 1;
        }

        $list = Comment::getCommentsByGoods($this->goods->id, false)->paginate($pageSize);
        if ($list->isEmpty()) {
            return ['is_show_good_reputation_text' => $is_show_good_reputation_text];
        }
        foreach ($list as &$item) {
            //追评ID
            if ($item->content == '' && $item->additional_comment_id != 0) {
                $item->content = $item->append->content;
            }

            $item->nick_name = substrCut($item->nick_name);
            $item->reply_count = $item->hasManyReply()->count('id');
            $item->head_img_url = $item->head_img_url ? replace_yunshop(yz_tomedia($item->head_img_url)) : yz_tomedia(\Setting::get('shop.shop.logo'));
            if (!$item->uid && $item->level_set) {
                //后台手动设置等级
                $level = MemberLevel::uniacid()->find($item->level_set);
                $item->level_name = $level->level_name ?? Setting::get('shop.member.level_name') ?? "普通会员";
            } else {
                $item->level_name = $item->hasOneMember->yzMember->level->level_name ?? Setting::get('shop.member.level_name') ?? "普通会员";
            }
        }
        //对评论图片进行处理，反序列化并组装完整图片url
        $list = $list->toArray();

        $list['is_show_good_reputation_text'] = $is_show_good_reputation_text;
        if (CommentConfig::getSetConfig('is_default_good_reputation_show') == 1) {
            $list['is_show_good_reputation_text'] = 0;//默认好评开关开启的情况下不显示该字样
        }


        foreach ($list['data'] as &$item) {
            $item['images'] = unserialize($item['images']);
            foreach ($item['images'] as &$image) {
                $image = yz_tomedia($image);
            }
            $item['append']['images'] = unserialize($item['append']['images']);
            foreach ($item['append']['images'] as &$image) {
                $image = yz_tomedia($image);
            }
            foreach ($item['has_many_reply'] as &$comment) {
                $comment['images'] = unserialize($comment['images']);
                foreach ($comment['images'] as &$image) {
                    $image = yz_tomedia($image);
                }
            }
        }

        $list['total_summary'] = $this->getCommentTotalSummary(Comment::getAllCommentTotal($this->goods->id));

        app('db')->cacheSelect = false;
        return $list;
    }


    /**
     * 获取评论总数概括 100+,200+,1000+.......
     * @param $total
     * @return string
     */
    final public function getCommentTotalSummary($total): string
    {
        $numLen = strlen(floor($total));//总数的位数
        if ($total <= 100) {
            $summary_total = $total;
        } elseif ($total > 100 && $total < 10000) {
            if ($numLen == 3) {
                $numMsg = '00+';
            } else {
                $numMsg = '000+';
            }
            $summary_total = substr_replace($total, $numMsg, 1, $numLen);
        } else {
            $wanLen = 5;//一万的位数
            $wanNowLen = $numLen - ($wanLen - 1);//万现在的位数

            $summary_total = substr_replace($total, str_pad(substr($total, 0, 1), $wanNowLen, '0') . '万+', 0);
        }
        return (string)$summary_total;
    }


    private function lovely()
    {
        $lovely_list = \app\frontend\modules\goods\models\Goods::select("id", "title", "thumb","is_discount","is_hot","is_stock")->where('type2',1)->with(['hasManyOptions' => function ($query) {
            $query->select("id", "goods_id", "product_price");
        }])->limit(20)->get();

        $lovely_list = $lovely_list->map(function ($item) {
            $productPrices = $item->hasManyOptions->pluck('product_price')->filter()->all();

            $min_price = !empty($productPrices) ? min($productPrices) : $item->price;
            $max_price = !empty($productPrices) ? max($productPrices) : $item->price;
            return [
                'id' => $item->id,
                'title' => $item->title,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'thumb' => yz_tomedia($item->thumb),
                "is_discount"=>$item->is_discount,
                "is_hot"=>$item->is_hot,
                "is_stock"=>$item->is_stock
            ];
        })->all();
        return $lovely_list;
    }


    protected function enrichGoodsModel()
    {
        $this->goods->load([
            'hasManyOptions.hasManyOptionModels' => function ($query) {
                $query->orderBy('sort', 'asc');
            },
            'hasManyStyleRelations.belongsToStyle',
        ]);

        // ✅ 处理选项模型参数 + 图片媒体链接
        foreach ($this->goods->hasManyOptions as $option) {
            // 模型参数反序列化

            $option->model_param = $option->hasManyOptionModels->map(function ($param) {
                $param->default_color = $this->safeUnserialize($param->default_color);
                $param->select_color = $this->safeUnserialize($param->select_color);
                $param->map_param = $this->safeUnserialize($param->map_param);
                return $param;
            });
            $option->thumb_url = $this->safeUnserializeArrayV3($option->thumb_url);
            // 多媒体处理
            $option->d3model = yz_tomedia($option->d3ModelUrl_weld);
            $option->cad_plan_model = yz_tomedia($option->cad_plan_model);
            $option->d3MaxUrl = yz_tomedia($option->d3MaxUrl);
            $option->thumb = yz_tomedia($option->thumb);
        }

        // ✅ 样式与材质关系处理（合并查询）
        $styleRelations = $this->goods->hasManyStyleRelations;
        $this->goods->thumb = yz_tomedia($this->goods->thumb);
        $this->goods->goods_style = $styleRelations->where('type', 1)->map(function ($item) {
            return $item->belongsToStyle->name ?? null;
        })->filter()->values();

        $this->goods->material = $styleRelations->where('type', 2)->map(function ($item) {
            return $item->belongsToStyle->name ?? null;
        })->filter()->values();

        // ✅ 处理实拍图和多图
        $this->goods->e_catalog_pdf = yz_tomedia($this->goods->e_catalog_pdf);

        $this->goods->real_image = $this->safeUnserializeArrayV2($this->goods->real_image);

        $this->goods->main_url = $this->safeUnserializeArrayV2($this->goods->main_url);
        // $this->goods->thumb_url_img = $this->safeUnserializeArrayV2($this->goods->thumb_url);
        $this->goods->thumb_url = $this->safeUnserializeArrayV2($this->goods->thumb_url);

        // ✅ 商品分类处理
        $category_ids = GoodsCategory::where('goods_id', $this->goods->id)->pluck('category_id');
        $this->goods->category_names = \app\frontend\modules\goods\models\Category::whereIn('id', $category_ids)->pluck('name')->toArray();

        // ✅ 其他操作
        $this->joinHistory($this->goods->supp_id);
        $this->goods->is_favorite = $this->getIsFavorite();


        if ($this->goods->has_option) {
            $this->goods->min_price = $this->goods->hasManyOptions->min("product_price");
            $this->goods->max_price = $this->goods->hasManyOptions->max("product_price");
            $this->goods->stock = $this->goods->hasManyOptions->sum('stock');

        }
        // $specItemIds = $this->goods->hasManySpecs->pluck('hasManySpecsItem')->flatten()->pluck('id')->all();

        // 批量查出 specs 对应的 option_id
        /*$optionIdMap = GoodsOption::whereIn('specs', $specItemIds)
            ->where('modelType', 0)
            ->get(['id', 'specs', 'product_price'])
            ->keyBy('specs');*/


        $this->goods->hasManySpecs->transform(function ($specs) {
            // 获取当前规格下的所有 items 并处理 thumb
            $allItems = $specs->hasManySpecsItem->map(function ($item) {
                $item->thumb = yz_tomedia($item->thumb);
                return $item;
            });

            // 使用引用方式构建树形结构
            $items = $allItems->mapWithKeys(function ($item) {
                $item->children = collect(); // 初始化空children集合
                return [$item->id => $item];
            });

            $tree = collect();
            foreach ($items as $id => $item) {
                if ($item->parent_id && isset($items[$item->parent_id])) {
                    $items[$item->parent_id]->children->push($item);
                } else {
                    $tree->push($item);
                }
            }

            // 生成树形结构并关联到 specitem
            $specs->setRelation('specitem', $tree);
            unset($specs->hasManySpecsItem);

            return $specs;
        });


        $uniqid = uniqid();
        $newSpecItem = [
            'id' => $uniqid,
            'title' => '部件',
            'type' => 2,
            'goods_id' => $this->goods->id,
            'specitem' => $this->get3DModel($uniqid, $this->goods->hasManySpecs->pluck('specitem'))
        ];

        $this->goods->hasManySpecs->push($newSpecItem);
        $this->goods->maintenance_doc = $this->goods->maintenance_doc ? $this->safeUnserializeArrayV4($this->goods->maintenance_doc) : [];
        $this->goods->service_link = ServiceUser::getDistributeService($this->goods->supp_id, $this->goods->id);
        $this->goods->install_guide = $this->goods->install_guide ? $this->safeUnserializeArrayV4($this->goods->install_guide) : [];
        $this->goods->wiring_diagram = $this->safeUnserializeArrayV2($this->goods->wiring_diagram);


        //商品视频
        $goods_video = GoodsVideo::where('goods_id', $this->goods->id)->first();
        $goods_video->goods_video = $goods_video->goods_video ? yz_tomedia($goods_video->goods_video) : "";
        $goods_video->video_image = $goods_video->video_image ? yz_tomedia($goods_video->video_image) : "";
        $this->goods->goods_video = $goods_video;
        $this->goods->install_guide_url = yz_tomedia($this->goods->install_guide_url);
        $this->goods->maintenance_doc_url = yz_tomedia($this->goods->maintenance_doc_url);
        $this->goods->atlas = $this->safeJsonArray(json_decode($this->goods->atlas, true));
        return $this->goods->toArray();
    }


    /*private function get3DModel($uniqid, $specitems)
    {
        $newSpecItem = [];

        // 拿到所有 item2 的 id
        $specIds = collect($specitems)->flatten()->pluck('id')->all();

        // 预查询所有 option_id（按 modelType=0）
        $options = GoodsOption::whereIn('specs', $specIds)
            ->where('modelType', 0)
            ->pluck('id', 'specs'); // specs_id => option_id

        // 再一次性查所有 GoodsOptionModel
        $optionModelList = GoodsOptionModel::whereIn('option_id', $options->values())
            ->orderBy('sort', 'asc')
            ->get()
            ->groupBy('option_id'); // 分组提高访问效率

        foreach ($specitems as $itemGroup) {
            foreach ($itemGroup as $item2) {
                $specId = $item2->id;
                $optionId = $options[$specId] ?? null;
                if (!$optionId) continue;

                $models = $optionModelList[$optionId] ?? collect();

                foreach ($models as $value) {
                    $newSpecItem[$specId][] = [
                        "id" => $value->id,
                        "option_id" => $value->option_id,
                        "specid" => $uniqid,
                        'type' => 2,
                        "thumb" => "",
                        "visible" => $value->visible,
                        "changeLock" => $value->changeLock,
                        "title" => $value->name,
                        "select_color" => unserialize($value->select_color),
                        "default_color" => unserialize($value->default_color),
                        "map_param" => unserialize($value->map_param),
                    ];
                }
            }
        }

        return $newSpecItem;
    }*/


    private function get3DModel($uniqid)
    {
        $newSpecItem = [];

        // 1. 查询所有相关的 GoodsOption（modelType=0）
        $allOptions = GoodsOption::where('modelType', 0)
            ->where('goods_id', $this->goods->id)
            ->get(['id', 'specs']);

        // 2. 查询所有相关的 GoodsOptionModel，并按 option_id 分组
        $optionModelList = GoodsOptionModel::whereIn(
            'option_id',
            $allOptions->pluck('id')->unique()
        )
            ->orderBy('sort', 'asc')
            ->get()
            ->groupBy('option_id');

        // 3. 构建返回数据（按 option_id 分组）
        foreach ($optionModelList as $optionId => $models) {
            foreach ($models as $value) {
                $newSpecItem[$optionId][] = [
                    "id" => $value->id,
                    "option_id" => $value->option_id,
                    "specid" => $uniqid,
                    "type" => 2,
                    "thumb" => "",
                    "visible" => $value->visible,
                    "changeLock" => $value->changeLock,
                    "title" => $value->name,
                    "select_color" => unserialize($value->select_color),
                    "default_color" => unserialize($value->default_color),
                    "map_param" => unserialize($value->map_param),
                ];
            }
        }

        return $newSpecItem;
    }


    private function getProductSeries($goods_model)
    {
        $related_goods_ids = $goods_model->related_goods_id ? explode(",", $goods_model->related_goods_id) : [];

        if (empty($related_goods_ids)) {
            return [];
        }

        $goodsList = \app\common\models\Goods::select("id", "title", "price", "thumb","is_discount","is_hot","is_stock")
            ->with(['hasManyOptions:id,goods_id,product_price'])
            ->whereIn('id', $related_goods_ids)
            ->get();

        return $goodsList->map(function ($item) {
            $productPrices = $item->hasManyOptions->pluck('product_price')->filter()->all();

            $min_price = $productPrices ? min($productPrices) : $item->price;
            $max_price = $productPrices ? max($productPrices) : $item->price;

            return [
                'id' => $item->id,
                'title' => $item->title,
                'price' => $item->price,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'thumb' => yz_tomedia($item->thumb),
                "is_discount"=>$item->is_discount,
                "is_hot"=>$item->is_hot,
                "is_stock"=>$item->is_stock
            ];
        })->all();
    }


    private function getBrandInfo($goods_model)
    {

        $memberId = \YunShop::app()->getMemberId();

        // 主 supplier 信息 + 商品总数（withCount）
        $supplier = Supplier::select('id', 'store_name', 'logo', 'province_id', 'city_id')
            ->withCount(['goods as goods_total' => function ($query) use ($goods_model) {
                $query->where('supp_id', $goods_model->supp_id);
            }])
            ->where('id', $goods_model->supp_id)
            ->first();


        if (!$supplier) {
            return [];
        }

        $supplier->logo = yz_tomedia($supplier->logo);

        // 一次性查省市名
        $addresses = Address::whereIn('id', [$supplier->province_id, $supplier->city_id])
            ->pluck('areaname', 'id');

        $supplier->provenance = ($addresses[$supplier->province_id] ?? '') . ($addresses[$supplier->city_id] ?? '');

        // 是否关注
        $supplier->isFollow = SupplierFollow::where('member_id', $memberId)
            ->where('supplier_id', $goods_model->supp_id)
            ->exists() ? 1 : 0;

        $supplier->service_link = ServiceUser::getDistributeService($supplier->id);

        // 推荐商品（9个）
        $recommendList = Goods::select("id", "thumb", "title")
            ->where('supp_id', $goods_model->supp_id)
            ->limit(9)
            ->get();

        $supplier->brand_recommend = $recommendList->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'thumb' => yz_tomedia($item->thumb),
            ];
        })->all();

        return $supplier;
    }


    public function getThreeModel()
    {
        $modelList = [];

        // 获取规格项和 ID
        /*        $specItems = $goods_model->hasManySpecs->pluck('hasManySpecsItem')->flatten();
                $specIds = $specItems->pluck('id')->toArray();*/

        // 批量获取选项（兼容多ID拼接的specs）
        $goodsOptions = GoodsOption::where("modelType", 0)->where('goods_id', $this->goods->id)
            ->get();

        $optionIds = $goodsOptions->pluck('id')->toArray();

        $modelParams = $this->getOptionModels($optionIds);

        $parentModels = $this->getAllParentModels();

        foreach ($goodsOptions as $option) {
            $modelType = $this->getModelType($option->modelType)['code'];
            $status = $this->getModelType($option->modelType)['status'];
            $optionId = $option->id;

            $model_data = $modelParams[$optionId] ?? collect();



            $modelList[$option->title][] = [
                "id" => $optionId,
                "url" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : "",
                "img" => yz_tomedia($option->thumb),
                "price" => $option->product_price,
                "position" => ["x" => 0, "y" => 0, "z" => 0],
                "rotate" => ["x" => 0, "y" => 0, "z" => 0],
                "blockname" => $option->block_name,
                "size" => $option->length . "*" . $option->width . "*" . $option->height . self::MM,
                "json" => json_encode([
                    'modelType' => $modelType,
                    'single' => $option->singleType,
                    'model_data' => array_values($model_data),
                ]),
                "models" => $parentModels[$option->specs],
            ];
        }

        return $modelList;
    }

    private function normalizeArrayForJson($data) {
        if (!is_array($data)) {
            return $data;
        }

        // 检查是否是关联数组（包含字符串键名）
        $hasStringKeys = false;
        $isSequential = true;
        $expectedIndex = 0;

        $keys = array_keys($data);
        foreach ($keys as $key) {
            // 如果有字符串键名，就是关联数组
            if (!is_int($key)) {
                $hasStringKeys = true;
                break;
            }

            // 检查数字键名是否连续
            if ($key !== $expectedIndex) {
                $isSequential = false;
            }
            $expectedIndex++;
        }

        $result = [];

        // 如果是纯数字键名且不连续，重置为连续键名
        if (!$hasStringKeys && !$isSequential) {
            $result = array_values($data);
        } else {
            // 对于关联数组或连续数字数组，保持原样
            $result = $data;
        }

        // 递归处理所有子数组
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->normalizeArrayForJson($value);
            }
        }

        return $result;
    }

// 辅助方法：获取当前option对应的parentModels
    private function getParentModelsForOption($option, $parentModels)
    {
        $result = [];
        $optionSpecs = explode('_', $option->specs);

        foreach ($optionSpecs as $specId) {
            if (isset($parentModels[$specId])) {
                $result = array_merge($result, $parentModels[$specId]);
            }
        }

        return $result;
    }


    private function getAllOptionModelUrl($option_ids)
    {
        $goodsOptionModelUrl = GoodsOptionModelUrl::whereIn('option_id', $option_ids)
            ->get()
            ->groupBy('option_id')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'model_url' => yz_tomedia($item->model_url),
                        'name' => $item->name
                    ];
                });
            });
        return $goodsOptionModelUrl;
    }


    private function getIsFavorite()
    {
        return (new MemberFavoriteController())->isFavorite(request(), true)['json'];
    }

    private function joinHistory($supp_id)
    {
        (new MemberHistoryController())->store(request(), true, $supp_id);
    }


    // 批量获取模型参数并反序列化
    private function getOptionModels($optionIds)
    {
        return GoodsOptionModel::whereIn('option_id', $optionIds)->get()
            ->groupBy('option_id')
            ->map(function ($items) {
                return $items->sortBy('sort')->map(function ($item) {
                    $item->default_color = $this->safeUnserialize($item->default_color);
                    $item->select_color = $this->safeUnserialize($item->select_color);
                    $item->map_param = $this->safeUnserialize($item->map_param);
                    return $item;
                });
            })->toArray();
    }

    private function getAllParentModels()
    {
        // 一次性查出所有相关option（兼容多ID拼接）
        $allOptions = GoodsOption::where('goods_id', $this->goods->id)->get();

        $optionIds = $allOptions->pluck('id')->toArray();

        // 一次性查出所有模型
        $allOptionModels = $this->getOptionModels($optionIds);

        // 构建返回结构
        $parentModels = [];
        $goodsOptionModelUrl = $this->getAllOptionModelUrl($optionIds);


        $specModelData = [];

        foreach ($allOptions as $option) {
            $modelTypeInfo = $this->getModelType($option->modelType);
            $optionId = $option->id;
            $model_data = $allOptionModels[$optionId] ?? collect();

            $specModelData[$modelTypeInfo['name']] = [
                "id" => $optionId,
                "url" => $option->d3ModelUrl_weld ? yz_tomedia($option->d3ModelUrl_weld) : $goodsOptionModelUrl->get($optionId, collect()),
                "img" => yz_tomedia($option->thumb),
                "position" => ["x" => 0, "y" => 0, "z" => 0],
                "rotate" => ["x" => 0, "y" => 0, "z" => 0],
                "blockname" => $option->block_name,
                "price" => $option->product_price,
                "size" => $option->length . "*" . $option->width . "*" . $option->height . self::MM,
                "json" => json_encode([
                    "modelType" => $modelTypeInfo['code'],
                    "single" => $option->singleType,
                    "model_data" => array_values($model_data),
                ])
            ];
            $parentModels[$option->specs] = $specModelData;
        }


        return $parentModels;
    }



    private function safeUnserialize($value)
    {
        return !empty($value) ? @unserialize($value) : [];
    }


    private function safeUnserializeArray($value)
    {
        return !empty($value) ? array_map(fn($url) => yz_tomedia($url), @unserialize($value) ?: []) : [];
    }


    private function safeUnserializeArrayV3($value)
    {
        if (empty($value)) {
            return [];
        }
        // 尝试反序列化
        $unserialized = @unserialize($value);
        if ($unserialized === false) {
            return [];
        }
        // 如果不是数组，返回空数组
        if (!is_array($unserialized)) {
            return [];
        }
        if (isset($unserialized['thumb'])) {
            $unserialized['thumb'] = $unserialized['thumb'] ? yz_tomedia($unserialized['thumb']) : "";
        }
        if (isset($unserialized['main_thumb'])) {
            $unserialized['main_thumb'] = $unserialized['main_thumb'] ? yz_tomedia($unserialized['main_thumb']) : "";
        }

        return $unserialized;
    }

    protected function safeJsonArray($data)
    {
        if (empty($data)) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        foreach ($data['data'] as &$item) {
            if (isset($item['high_url'])) {
                $item['high_url'] = (!empty($item['high_url']) && $item['high_url'] != 'null') ? yz_tomedia($item['high_url']) : "";
            }
            if (isset($item['thumb'])) {
                $item['thumb'] = $item['thumb'] ? yz_tomedia($item['thumb']) : "";
            }
            if (isset($item['main_thumb'])) {
                $item['main_thumb'] = $item['main_thumb'] ? yz_tomedia($item['main_thumb']) : "";
            }
        }

        return $data;


    }

    private function safeUnserializeArrayV4($value)
    {
        if (empty($value)) {
            return [];
        }

        // 尝试反序列化
        $unserialized = @unserialize($value);
        if ($unserialized === false) {
            return [];
        }

        // 如果不是数组，返回空数组
        if (!is_array($unserialized)) {
            return [];
        }


        // 处理多维数组
        foreach ($unserialized['data'] as &$item) {
            if (isset($item['high_url'])) {
                $item['high_url'] = (!empty($item['high_url']) && $item['high_url'] != 'null') ? yz_tomedia($item['high_url']) : "";
            }
            if (isset($item['thumb'])) {
                $item['thumb'] = $item['thumb'] ? yz_tomedia($item['thumb']) : "";
            }
            if (isset($item['main_thumb'])) {
                $item['main_thumb'] = $item['main_thumb'] ? yz_tomedia($item['main_thumb']) : "";
            }
        }

        return $unserialized;
    }

    private function safeUnserializeArrayV2($value)
    {
        if (empty($value)) {
            return [];
        }

        // 尝试反序列化
        $unserialized = @unserialize($value);
        if ($unserialized === false) {
            return [];
        }

        // 如果不是数组，返回空数组
        if (!is_array($unserialized)) {
            return [];
        }

        // 检查是否是一维数组
        if ($this->isOneDimensionalArray($unserialized)) {
            // 一维数组直接返回
            return $unserialized;
        }

        // 处理多维数组
        foreach ($unserialized as &$item) {
            if (isset($item['high_url'])) {
                $item['high_url'] = (!empty($item['high_url']) && $item['high_url'] != 'null') ? yz_tomedia($item['high_url']) : "";
            }
            if (isset($item['thumb'])) {
                $item['thumb'] = $item['thumb'] ? yz_tomedia($item['thumb']) : "";
            }
            if (isset($item['main_thumb'])) {
                $item['main_thumb'] = $item['main_thumb'] ? yz_tomedia($item['main_thumb']) : "";
            }
        }

        return $unserialized;
    }

    /**
     * 判断是否是一维数组
     */
    private function isOneDimensionalArray(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                return false;
            }
        }
        return true;
    }


    private function getModelType($modelType)
    {
        if ($this->goods->productType == 1 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($this->goods->productType == 2 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($this->goods->productType == 2 && $modelType == 2) {
            return ['code' => 2, 'name' => '延伸位'];
        } elseif ($this->goods->productType == 3 && $modelType == 0) {
            return ['code' => 0, 'name' => '独立位'];
        } elseif ($this->goods->productType == 3 && $modelType == 1) {
            return ['code' => 1, 'name' => '首位'];
        } elseif ($this->goods->productType == 3 && $modelType == 2) {
            return ['code' => 2, 'name' => '延伸位'];
        } elseif ($this->goods->productType == 3 && $modelType == 3) {
            return ['code' => 3, 'name' => '尾位'];
        } elseif ($this->goods->productType == 4 && $modelType == 0) {
            return ['code' => 0, 'status' => 8, 'name' => '十字型'];
        } elseif ($this->goods->productType == 4 && $modelType == 1) {
            return ['code' => 1, 'status' => 8, 'name' => 'T字型'];
        } elseif ($this->goods->productType == 4 && $modelType == 2) {
            return ['code' => 2, 'status' => 8, 'name' => 'L型'];
        } elseif ($this->goods->productType == 4 && $modelType == 3) {
            return ['code' => 3, 'status' => 8, 'name' => 'T字型-1'];
        } elseif ($this->goods->productType == 4 && $modelType == 4) {
            return ['code' => 4, 'status' => 8, 'name' => 'L型-1'];
        }
    }

}