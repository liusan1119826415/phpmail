<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/14
 * Time: 15:28
 */

namespace app\backend\modules\discount\controllers;


use app\backend\modules\discount\models\CategoryDiscount;
use app\backend\modules\goods\models\Category;
use app\backend\modules\goods\models\Category as CategoryModel;
use app\backend\modules\goods\models\Discount;
use app\backend\modules\member\models\MemberLevel;
use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\models\GoodsCategory;
use app\common\models\Sale;
use Illuminate\Support\Facades\DB;
use app\backend\modules\goods\services\CreateGoodsService;
use app\backend\modules\goods\models\Dispatch;
use app\backend\modules\discount\models\CategoryBalance;
use app\backend\modules\goods\models\GoodsDispatch;
use Illuminate\Support\Facades\Redis;

class BatchBalanceController extends BaseController
{

    public function index(){
        return view('discount.balance')->render();
    }

    public function getSet(){
        $category = CategoryBalance::uniacid()->get()->toArray();
        foreach ($category as $k => $item) {
            $category[$k]['category_ids'] = CategoryModel::select('id', 'name')->whereIn('id', explode(',', $item['category_ids']))->get()->toArray();
        }
        return $this->successJson('success',$category);
    }

    public function balanceSet()
    {
        return view('discount.balance-set', [
            'firstCate'=>Category::uniacid()->where('level', 1)->where('plugin_id',0)->orderBy('id', 'asc')->get(),
            'url' => json_encode(yzWebFullUrl('discount.batch-balance.balance-save')),
        ])->render();
    }

    public function updateBalance()
    {
        $id=request()->id;
        $form_data=request()->form_data;

        if (!$id) {
            throw new ShopException('参数错误!');
        }
        if  ($form_data) {
            if(isset($form_data['category_ids'][0]['id'])){
                $form_data['category_ids']=array_column($form_data['category_ids'],'id');
            }
            $categorys = $form_data['category_ids'];
            foreach ($categorys as $v){
                $categorys_r[] = $v;
            }

            $data = [
                'uniacid' => \YunShop::app()->uniacid,
                'category_ids' => implode(',',$form_data['category_ids']),
                'balance_deduct' => $form_data['balance_deduct'],
                'balance_deduct_type' => $form_data['balance_deduct_type'],
                'max_balance_deduct' => $form_data['max_balance_deduct'],
                'min_balance_deduct' => $form_data['min_balance_deduct'],
            ];
            if(!(CategoryBalance::find($id)->update($data))){
                return $this->errorJson("修改失败");
            }
            $item_id = [];

            foreach( $categorys_r as  $categoryID){
                $item_id = array_unique(array_merge($item_id, $this->updateGoodsDispatch($categoryID)));
            }
            $item_id = array_values($item_id);
            $time = 'batch_balance' . \YunShop::app()->uniacid . time();
            Redis::setex($time, 86400, json_encode($item_id));
            return $this->successJson('ok', [
                'item_id' => $time,
                'save_data' => $data,
                'count_goods' => count($item_id),
            ]);

        }
        $categoryBalance = CategoryBalance::find($id);
        $classify = CategoryBalance::classify($id);
        $categoryBalance['category_ids'] = CategoryModel::select('id', 'name')
            ->whereIn('id', explode(',', $categoryBalance['category_ids']))
            ->get()->toArray();
        return view('discount.balance-set', [
            'classify'=>json_encode($classify),
            'firstCate'=>Category::uniacid()->where('level', 1)->where('plugin_id',0)->orderBy('id', 'asc')->get(),
            'categoryBalance' => json_encode($categoryBalance),
            'url' => json_encode(yzWebFullUrl('discount.batch-balance.update-balance',['id' => $id])),
        ])->render();
    }

    public function balanceSave()
    {
       $form_data = request()->form_data;
        if ($form_data) {
            $categorys = $form_data['category_ids'];
            foreach ($categorys as $v) {
                $categorys_r[] = $v;
            }
            $data = [
                'uniacid' => \YunShop::app()->uniacid,
                'category_ids' => implode(',',$form_data['category_ids']),
                'balance_deduct' => $form_data['balance_deduct'],
                'balance_deduct_type' => $form_data['balance_deduct_type'],
                'max_balance_deduct' => $form_data['max_balance_deduct'],
                'min_balance_deduct' => $form_data['min_balance_deduct'],
            ];
            $model = new CategoryBalance();
            $model->fill($data);
            if ($model->save()) {
                $item_id = [];
                foreach($categorys_r as $categoryID){
                    $item_id = array_unique(array_merge($item_id, $this->updateGoodsDispatch($categoryID)));
                }
                $item_id = array_values($item_id);
                $time = 'batch_balance' . \YunShop::app()->uniacid . time();
                Redis::setex($time, 86400, json_encode($item_id));
                return $this->successJson('ok', [
                    'item_id' => $time,
                    'save_data' => $data,
                    'count_goods' => count($item_id),
                ]);
            }
        }

        return view('discount.balance-set', [
            'url' => json_encode(yzWebFullUrl('discount.batch-balance.index')),
        ])->render();
    }

    public function goodsSave()
    {
        $res_data = request()->all();
        $item_id = json_decode(Redis::get($res_data['item_id']), true);

        $current = $res_data['current'];
        try {

            $sale = Sale::where('goods_id', $item_id[$current])->first();
            if (empty($sale)) {
                $sale = new Sale(['goods_id' => $item_id[$current]]);
            }
            $sale->balance_deduct = $res_data['save_data']['balance_deduct'];
            $sale->balance_deduct_type = $res_data['save_data']['balance_deduct_type'];
            $sale->max_balance_deduct = $res_data['save_data']['max_balance_deduct'];
            $sale->min_balance_deduct = $res_data['save_data']['min_balance_deduct'];
            $sale->save();
            $current++;
            return $this->successJson('ok', [
                'current' => $current
            ]);
        } catch (\Exception $e) {
            return $this->successJson($e->getMessage(), [
                'current' => $current
            ]);
        }
    }
    public function goodsSaveQuick()
    {
        $res_data = request()->all();
        $item_id = json_decode(Redis::get($res_data['item_id']), true);

        foreach ($item_id as $item) {
            $sale = Sale::where('goods_id', $item)->first();
            if (empty($sale)) {
                $sale = new Sale(['goods_id' => $item]);
            }
            $sale->balance_deduct = $res_data['save_data']['balance_deduct'];
            $sale->balance_deduct_type = $res_data['save_data']['balance_deduct_type'];
            $sale->max_balance_deduct = $res_data['save_data']['max_balance_deduct'];
            $sale->min_balance_deduct = $res_data['save_data']['min_balance_deduct'];
            $sale->save();
        }
        return $this->successJson('ok');
    }

    public function updateGoodsDispatch($categoryID){
        $item_id =[];
        //2级联动
       if (Setting::get('shop.category')['cat_level'] >= 2){
           $goods_ids = GoodsCategory::select('goods_id')
               ->whereHas('goods', function ($query) {
                   $query->whereIn('plugin_id', [0, 44, 92, 120]); //44 为聚合供应链商品
               })
               ->whereRaw('FIND_IN_SET(?,category_ids)', $categoryID)
               ->get()
               ->toArray();

           $goods_id = GoodsCategory::select('goods_id')
               ->whereHas('goods', function ($query) {
                   $query->whereIn('plugin_id', [0, 44, 92, 120]);
               })
               ->where('category_id', $categoryID)
               ->get()
               ->toArray();

           $arr = array_merge($goods_ids, $goods_id);
       }else {
           $arr = GoodsCategory::select('goods_id')
               ->whereHas('goods', function ($query) {
                   $query->whereIn('plugin_id', [0, 44, 92, 120]);

               })
               ->where('category_id', $categoryID)
               ->get()
               ->toArray();

       }

        foreach ($arr as $goods_id) {
            $item_id[] = $goods_id['goods_id'];
        }

        return $item_id;
    }


    public function selectCategory()
    {
        $kwd = \YunShop::request()->keyword;
        if ($kwd) {
            $category = CategoryModel::getMallCategorysByName($kwd);
            return $this->successJson('ok', $category);
        }
    }

    public function deleteSet()
    {
        if (CategoryBalance::find(request()->id)->delete()) {
            return $this->successJson('ok');
        };
    }

    public function getChild()
    {
        $level = \YunShop::request()->level;
        $ids = \YunShop::request()->cate;
        $ids = explode(',', $ids);
        $returnArray = CategoryModel::uniacid()
            ->getQuery()
            ->select(['id', 'name', 'enabled', 'parent_id'])
            ->where('plugin_id', 0)
            ->where('deleted_at', null);
        switch ($level) {
            case 2:
                $returnArray = $returnArray->where('level', 2);
                break;
            case 3:
                $returnArray = $returnArray->where('level', 3);
                break;
        }
        $returnArray = $returnArray->whereIn('parent_id', $ids)
            ->orderBy('parent_id', 'asc')->get();
        return $this->successJson('ok', $returnArray);
    }

    public function getAllCate(){
        return $this->successJson('success',(new CategoryModel())->getAllCategory());
    }

}