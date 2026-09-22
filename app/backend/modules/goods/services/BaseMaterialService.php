<?php


namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\Style;
use app\common\exceptions\ShopException;
use app\common\services\Session;
use Yunshop\Supplier\common\models\SupplierColorCategory;
use Yunshop\Supplier\common\models\SupplierColorPlane;
class BaseMaterialService
{


    public function getList($search)
    {
        $query = SupplierColorPlane::where('supplier_id',0)->with(['belongsToCategory'=>function($query){
            $query->select("id","name");
        },'belongsToUv'=>function($query){
            $query->select("id","name","metalness","roughness","opacity");
        }]);
        if($search['name']){
            $query->where('name','like','%'.$search['name'].'%');
        }

        $list = $query->orderBy('id','desc')->paginate(15);
        return $list;

    }

    public function add($requestBrand):bool
    {
        if ($requestBrand) {

            //将数据赋值到model
            $supplier_color_category = SupplierColorCategory::where('supplier_id',0)->first();

            $thumb_data = $requestBrand['thumb_data'];
            unset($requestBrand['thumb_data']);
            if ($thumb_data) {
                $insert_data = [];
                $uniacid = \YunShop::app()->uniacid;
                $supplier_id = 0;

                foreach ($thumb_data as $item) {
                    $insert_data[] = [
                        'uniacid' => $uniacid,
                        'supplier_id' => $supplier_id,
                        'name' => preg_replace('/\.(jpg|jpeg|png|gif|bmp|webp|svg|ico)$/i', '', $item['name']),
                        'thumb' => $item['thumb'],
                        "uv_id"=>$item['uv_id'],
                        'cate_id'=>$supplier_color_category->id,
                        'cate_ids'=>$supplier_color_category->id,
                        'created_at'=>time()
                    ];
                }

                // 批量插入
                SupplierColorPlane::insert($insert_data);
                return true;
            }

        }
    }





    public function delete($id):bool
    {
        try {
            $style = SupplierColorPlane::find($id);
            if(!$style) {
                throw new ShopException('无此数据');
            }
            $style->delete();
        }catch (\Exception $e){
            throw new ShopException('删除失败');
        }
        return true;
    }
}