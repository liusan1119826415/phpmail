<?php
// 修改后的getData方法
public function getData()
{
    $specs = [];
    $option_new_data = [];

    if (!is_null($this->goods)) {
        $goodsSpecs = GoodsSpec::select('id', 'title', 'goods_id')->where('goods_id', $this->goods->id)
            ->with(['hasManySpecsItem' => function ($item) {
                return $item->select('id', 'specid', 'title', 'show', 'parent_id', 'level')
                    ->orderBy('display_order', 'asc');
            }])->orderBy('display_order', 'asc')->get();

        // 先获取所有选项并按specs分组
        $allOptions = GoodsOption::where('goods_id', $this->goods->id)->get();
        $optionsGrouped = $allOptions->groupBy('specs')->toArray();

        if (!$goodsSpecs->isEmpty()) {
            foreach ($goodsSpecs as $spec) {
                $temporary = $spec->attributesToArray();

                // 构造树形结构
                $items = collect($spec->hasManySpecsItem->toArray())->mapWithKeys(function ($item) {
                    $item['children'] = [];
                    return [$item['id'] => $item];
                })->toArray();

                $tree = [];
                foreach ($items as $id => &$node) {
                    if (!empty($node['parent_id']) && isset($items[$node['parent_id']])) {
                        $items[$node['parent_id']]['children'][] = &$node;
                    } else {
                        $tree[] = &$node;
                    }
                }
                unset($node);
                $temporary['spec_item'] = $tree;

                $specs[] = $temporary;
            }

            // 处理所有选项数据
            $option_ids = $allOptions->pluck('id')->toArray();

            // 获取模型参数
            $modelParams = GoodsOptionModel::whereIn('option_id', $option_ids)->orderBy('sort','asc')
                ->get()
                ->groupBy('option_id')
                ->map(function ($items) {
                    return $items->unique(function ($item) {
                        return $item->name . '-' . $item->option_id;
                    });
                });

            $modelParams->transform(function ($items) {
                return $items->map(function ($item) {
                    // 处理颜色参数
                    $item->default_color = !empty($item->default_color) ? unserialize($item->default_color) : [];
                    if (is_array($item->default_color)) {
                        $this->processColorParams($item->default_color);
                    }

                    $item->select_color = !empty($item->select_color) ? unserialize($item->select_color) : [];
                    if (is_array($item->select_color)) {
                        foreach ($item->select_color as &$color) {
                            $this->processColorParams($color);
                        }
                    }

                    $item->map_param = !empty($item->map_param) ? unserialize($item->map_param) : [];
                    $item->meshs_name = !empty($item->meshs_name) ? unserialize($item->meshs_name) : [];
                    return $item;
                });
            });

            // 获取模型URL
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

            // 构建option_new_data - 按specs分组处理
            foreach ($optionsGrouped as $specsString => $options) {
                foreach ($options as $value) {
                    // 处理媒体文件路径
                    if ($value['thumb']) {
                        $value['thumb'] = yz_tomedia($value['thumb']);
                    }
                    if ($value['package_option']) {
                        $value['package_option'] = unserialize($value['package_option']);
                    }
                    $value['thumb_url'] = unserialize($value['thumb_url']);

                    // 处理3D模型URL
                    $modelUrls = $goodsOptionModelUrl->get($value['id'], collect());
                    $value['d3model_url'] = $value['d3ModelUrl_weld'] ? yz_tomedia($value['d3ModelUrl_weld']) : $modelUrls;
                    $value['d3ModelUrl_weld'] = $value['d3ModelUrl_weld'] ? yz_tomedia($value['d3ModelUrl_weld']) : $modelUrls;
                    $value['d3ModelUrl_ori'] = yz_tomedia($value['d3ModelUrl_ori']);

                    // 获取模型参数
                    $model_data = $modelParams->get($value['id'], collect());
                    $value['model_param'] = $model_data->toArray();

                    // 构建modelTypes
                    $modelTypes = [
                        [
                            "modelType" => $value['modelType'] ?? 0,
                            "title" => $value['title'] ?? '',
                            "option" => [
                                "id" => $value['id'] ?? '',
                                "title" => $value['title'] ?? '',
                                "combination" => $value['title'] ?? '',
                                "specs" => $value['specs'] ?? '',
                                "product_price" => $value['product_price'] ?? '',
                                "singleType" => null,
                                "product_model" => $value['product_model'] ?? '',
                                "volume" => $value['volume'] ?? '',
                                "structure" => $value['structure'] ?? '',
                                "length" => $value['length'] ?? '',
                                "width" => $value['width'] ?? '',
                                "height" => $value['height'] ?? '',
                                "package_number" => $value['package_number'] ?? 1,
                                "d3model" => $value['d3model'] ?? '',
                                "d3modelName" => $value['d3modelName'] ?? '',
                                "d3model_url" => $value['d3model_url'] ?? '',
                                "cad_plan_model" => $value['cad_plan_model'] ?? '',
                                "cad_plan_modelName" => $value['cad_plan_modelName'] ?? '',
                                "thumb" => $value['thumb'] ?? '',
                                "thumbName" => $value['thumbName'] ?? '',
                                "model_param" => $value['model_param'] ?? []
                            ]
                        ]
                    ];

                    $option_new_data[] = [
                        "activeTab" => "0",
                        "spec_item_id" => $specsString,
                        "modelTypes" => $modelTypes
                    ];
                }
            }
        }
    }

    return [
        'has_option' => is_null($this->goods) ? 0 : $this->goods->has_option,
        'specs' => $specs,
        'option' => $option_new_data
    ];
}

// 修改后的getOptionNewData方法
private function getOptionNewData($spec_item_id = null)
{
    $query = GoodsOption::where('goods_id', $this->goods->id);

    if ($spec_item_id) {
        $query->where('specs', $spec_item_id);
    }

    return $query->orderBy('display_order', 'asc')->get()->toArray();
}