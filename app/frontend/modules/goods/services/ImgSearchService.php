<?php


namespace app\frontend\modules\goods\services;


use Illuminate\Support\Facades\DB;

class ImgSearchService
{

    public function getToday()
    {
        $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        return [$beginToday, $endToday];
    }


    public function batchUpdateDistances($distanceData)
    {
        if (empty($distanceData)) {
            return;
        }

        // 构建插入数据的 SQL 片段
        $values = collect($distanceData)->map(function ($item) {
            $goods_id = intval($item['goods_id']);
            $distance = number_format($item['distance'], 3, '.', '');
            return "($goods_id, $distance)";
        })->implode(', ');

        DB::beginTransaction();

        try {
            // 创建临时表
            DB::statement("CREATE TEMPORARY TABLE temp_goods_distance (goods_id INT, distance FLOAT)");

            // 插入数据
            DB::statement("INSERT INTO temp_goods_distance (goods_id, distance) VALUES $values");

            // 执行更新
            DB::statement("
            UPDATE ims_yz_goods g
            JOIN temp_goods_distance tgd ON g.id = tgd.goods_id
            SET g.distance = tgd.distance
        ");

            // 删除临时表
            DB::statement("DROP TEMPORARY TABLE temp_goods_distance");

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e; // 或者记录日志：Log::error($e->getMessage());
        }
    }

    public function uploadAndSearch($filePath)
    {
        $host = Request()->getHost();
        if (strpos($host, 'test') !== false) {
            $is_type = 2;
        } else {
            $is_type = 1;
        }
        $api_url = 'https://search2.abangmi.com/myapp/index/search/upload_search';

        $image_url = $filePath;

        $ch = curl_init($api_url);


        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $data = json_encode(array(
            'url' => $image_url,
            'type' => $is_type
        ));

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL 错误: ' . curl_error($ch);
        }
        $response_data = json_decode($response, true);

        curl_close($ch);
        return $response_data;
    }

    protected function getGoodsModel()
    {
        $goods_model = \app\common\modules\shop\ShopConfig::current()->get('goods.models.commodity_classification');
        return new $goods_model;
    }

    // 用于处理使用 goods_ids 进行分页的函数
    public function paginateGoods($goods_ids, $page)
    {
        $goods_model = $this->getGoodsModel();
        $ims = DB::getTablePrefix();
        $requestSearch['search_goods_ids'] = $goods_ids;

        // 不要随意添加或修改字段，以免某些搜索条件导致错误
        $goods_select = "{$ims}yz_goods.has_option,{$ims}yz_goods.stock,{$ims}yz_goods.id,{$ims}yz_goods.thumb,{$ims}yz_goods.id as goods_id,";
        $goods_select .= "plugin_id,real_sales+virtual_sales total_sales,market_price,price,min_price,max_price,cost_price,title";

        $option_select = 'goods_id,product_price,market_price,stock,cost_price';
        $build = $goods_model->SearchList($requestSearch)
            ->selectRaw($goods_select)
            ->with(['hasManyOptions' => function ($query) use ($option_select) {
                $query->selectRaw($option_select);
            }])
            ->where("yz_goods.status", 1);

        if (app('plugins')->isEnabled('good-style')) {
            $build = $build->with(['goodStyle' => function ($query) {
                $query->selectRaw('goods_id,current_logo,current_name');
            }]);
        }

        // 使用给定的 page 参数进行分页
        $list = $build->orderBy('distance', 'desc')->paginate(20, ['*'], 'page', $page);

        $list = $list->toArray();
        if ($list['data']) {
            foreach ($list['data'] as $key => $v) {
                $list['data'][$key]['thumb'] = yz_tomedia($v['thumb']);
            }
        }

        $list['goods_ids'] = $goods_ids;
        return $list;
    }
}