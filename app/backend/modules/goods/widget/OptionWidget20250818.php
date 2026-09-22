<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/14
 * Time: 17:38
 */

namespace app\backend\modules\goods\widget;


//商品规格
use app\backend\modules\goods\models\GoodsOption;
use app\backend\modules\goods\models\GoodsSpec;
use app\common\models\goods\GoodsOptionModel;
use app\common\models\goods\GoodsOptionModelUrl;

class OptionWidget extends BaseGoodsWidget
{
    public $group = 'base';

    public $widget_key = 'option';

    public $code = 'option';

    public function pluginFileName()
    {
        return 'goods';
    }

    public function getData()
    {
        $specs = [];
        $option = [];
        if (!is_null($this->goods)) {
            $goodsSpecs = GoodsSpec::select('id', 'title', 'goods_id')->where('goods_id', $this->goods->id)
                ->with(['hasManySpecsItem' => function ($item) {
                    return $item->select('id', 'specid', 'title', 'show')->orderBy('display_order', 'asc');
                }])->orderBy('display_order', 'asc')->get();
            $spec_title_key = [];
            $spec_item_title_arr = [];
            $option_new_data = [];
            if (!$goodsSpecs->isEmpty()) {
                foreach ($goodsSpecs as $spec) {
                    $temporary = $spec->attributesToArray();

                    $temporary['spec_item'] = $spec->hasManySpecsItem->toArray();

                    foreach ($spec->hasManySpecsItem->toArray() as $kk => $item) {
                        $option = $this->getOptionNewData($item['id']);
                        $option_ids = collect($option)->pluck('id')->toArray();
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
                                $item->default_color = !empty($item->default_color) ? unserialize($item->default_color) : [];
                                if (is_array($item->default_color)) {
                                    if (isset($item->default_color['opacity'])) {
                                        $item->default_color['opacity'] = (float) $item->default_color['opacity'];
                                    }
                                    if (isset($item->default_color['metalness'])) {
                                        $item->default_color['metalness'] = (float) $item->default_color['metalness'];
                                    }
                                    if (isset($item->default_color['roughness'])) {
                                        $item->default_color['roughness'] = (float) $item->default_color['roughness'];
                                    }
                                }

                                // 解析推荐颜色 (二维数组)
                                $item->select_color = !empty($item->select_color) ? unserialize($item->select_color) : [];
                                if (is_array($item->select_color)) {
                                    foreach ($item->select_color as &$color) {
                                        if (isset($color['opacity'])) {
                                            $color['opacity'] = (float) $color['opacity'];
                                        }
                                        if (isset($color['metalness'])) {
                                            $color['metalness'] = (float) $color['metalness'];
                                        }
                                        if (isset($color['roughness'])) {
                                            $color['roughness'] = (float) $color['roughness'];
                                        }
                                    }
                                }
                                $item->map_param = !empty($item->map_param) ? unserialize($item->map_param) : [];
                                $item->meshs_name = !empty($item->meshs_name) ? unserialize($item->meshs_name) : [];
                                return $item;
                            });
                        });

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
                        $modelTypes = [];
                        foreach ($option as $k => $value) {
                            if ($value['thumb']) {
                                $value['thumb'] = yz_tomedia($value['thumb']);
                            }
                            if ($value['package_option']) {
                                $value['package_option'] = unserialize($value['package_option']);
                            }
                            $value['thumb_url'] = unserialize($value['thumb_url']);
                            $modelUrls = $goodsOptionModelUrl->get($value['id'], collect());
                            $value['d3model_url'] = $value['d3ModelUrl_weld']?yz_tomedia($value['d3ModelUrl_weld']):$modelUrls;
                            $value['d3ModelUrl_weld'] = $value['d3ModelUrl_weld']?yz_tomedia($value['d3ModelUrl_weld']):$modelUrls;
                            $value['d3ModelUrl_ori'] = yz_tomedia($value['d3ModelUrl_ori']);
                            //$value['model_param'] = unserialize($value['model_param']);
                            //模型参数更改V2
                            $model_data = $modelParams->get($value['id'], collect());

                            $value['model_param'] = $model_data->toArray();
                            $value[$spec->title] = $item['title'];
                            $name = $this->getName($value['modelType']);
                            $modelTypes[] = [
                                "modelType"=>$value['modelType'],
                                'title'=>$item['title']."(".$name.")",
                                "option"=>$value
                            ];
                        }

                        $option_new_data[] = [
                            "spec_item_id" => $item['id'],
                           // "activeTab" => 0,
                            "modelTypes" => $modelTypes
                        ];

                    }

                    $specs[] = $temporary;
                }
            }

        }

        return [
            'has_option' => is_null($this->goods) ? 0 : $this->goods->has_option,
            'specs' => $specs,
            'option' => $option_new_data
        ];
    }


    /**
     * 处理序列化的模型URL数据
     * - 如果能反序列化，则处理其中的model_url字段
     * - 如果不能反序列化，直接返回原数据
     *
     * @param mixed $d3ModelUrl_weld 可能是序列化字符串或普通数据
     * @return mixed 处理后的数据
     */
    private function chunk_serialized($d3ModelUrl_weld)
    {
        // 如果不是字符串，直接返回
        if (!is_string($d3ModelUrl_weld)) {
            return $d3ModelUrl_weld;
        }

        // 尝试反序列化
        $data = @unserialize($d3ModelUrl_weld);

        // 反序列化失败，直接返回原数据
        if ($data === false) {
            return yz_tomedia($d3ModelUrl_weld);
        }

        // 成功反序列化且是数组，处理每个元素的model_url
        if (is_array($data)) {
            foreach ($data as &$value) {
                if (isset($value['model_url'])) {
                    $value['model_url'] = yz_tomedia($value['model_url']);
                }
            }
            unset($value); // 销毁引用
        }

        return $data;
    }





    private function getName($modelType)
    {
        if($this->goods->productType == 1 && $modelType == 0){
            return "独立位";
        }elseif($this->goods->productType == 2 && $modelType == 0){
            return "独立位";
        }elseif($this->goods->productType == 2 && $modelType == 2){
            return "延伸位";
        }elseif($this->goods->productType == 3 && $modelType == 0){
            return "独立位";
        }elseif($this->goods->productType == 3 && $modelType == 1){
            return "首位";
        }elseif($this->goods->productType == 3 && $modelType == 2){
            return "延伸位";
        }elseif($this->goods->productType == 3 && $modelType == 3){
            return "尾位";
        }elseif($this->goods->productType == 4 && $modelType == 0){
            return "十字型";
        }elseif($this->goods->productType == 4 && $modelType == 1){
            return "T字型";
        }elseif($this->goods->productType == 4 && $modelType == 2){
            return "L型";
        }elseif($this->goods->productType == 4 && $modelType == 3){
            return "T字型-1";
        }elseif($this->goods->productType == 4 && $modelType == 4){
            return "L型-1";
        }

    }


    private function getOptionNewData($spec_item_id)
    {
        $option = GoodsOption::where('goods_id', $this->goods->id)->where('specs', $spec_item_id)->orderBy('display_order', 'asc')->get()->toArray();

        return $option;


    }


    public function pagePath()
    {
        return $this->getPath('resources/views/goods/assets/js/components/');
    }

    public function getLangData()
    {
        return [
            'option' => __('goods/option'),
        ];
    }
}
