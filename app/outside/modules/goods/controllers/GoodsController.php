<?php
/**
 * Created by PhpStorm.
 * Date: 2023/4/14
 * Time: 10:40
 */

namespace app\outside\modules\goods\controllers;


use app\common\models\Goods;
use app\outside\controllers\OutsideController;

class GoodsController extends OutsideController
{
    public function list()
    {


        $search = request()->only(['in_stock','status','goods_title','min_price','max_price','plugin']);

        $list = \app\outside\modes\Goods::getList($search)->orderBy('created_at', 'desc')->paginate(15);

        $list->map(function ($goods) {
//            $goods->setHidden(['status_name']);
            $goods->thumb = yz_tomedia($goods->thumb);
        });

        $page = $list->toArray();

        $array = array_only($page,['total','current_page','data','per_page']);

        return $this->successJson('list', $array);
    }


    public function index()
    {
        $model = Goods::uniacid()->select('yz_goods.id', 'title', 'thumb', 'sku', 'goods_sn', 'price', 'stock', 'yz_goods.created_at', 'status');

        $created_at = request()->input('created_at');
        //创建时间
        if ($created_at && is_array($created_at)) {
            $model->whereBetween('yz_goods.created_at', $created_at);
        }

        //商品名称
        if ($title = trim(request()->input('title'))) {
            $model->whereTitle($title);
        }

        //商品ID
        if ($goods_id = intval(request()->input('goods_id'))) {
            $model->where('yz_goods.id', $goods_id);
        }
        if (request()->input('filtering_name')) {
            $value = request()->input('filtering_name');
            $model->join('yz_goods_filtering', 'yz_goods_filtering.goods_id','yz_goods.id')
                ->join('yz_search_filtering', function ($join) use ($value) {
                    $join->on('yz_goods_filtering.filtering_id', 'yz_search_filtering.id')
                        ->where('yz_search_filtering.name', 'like', '%' . $value . '%');
                });
        }

        //获取上架商品
        $list = $model->where('yz_goods.status', 1)
            ->orderBy('yz_goods.created_at', 'desc')
            ->paginate(15);

        $list->map(function ($goods) {
            $goods->setHidden(['status_name']);
            $goods->thumb = yz_tomedia($goods->thumb);
        });



        return $this->successJson('list', $list);
    }
}
