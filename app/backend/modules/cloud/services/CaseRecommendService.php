<?php


namespace app\backend\modules\cloud\services;

use app\common\exceptions\ShopException;
use Yunshop\Supplier\common\models\IndustryCase;
class CaseRecommendService
{


    public function getList($search)
    {

        $query = IndustryCase::select("id", "title", "thumb", "favorites_count", "supplier_id","is_suggested")->with(['brandCase' => function ($query) {
            $query->select("id", "store_name", "logo");
        }]);

        if ($search['title']) {
            $query->where('title','like', '%'.$search['title'].'%');
        }


        $query->orderBy('created_at', 'desc');

        $data = $query->paginate(20);
        $data->transform(function ($item) {
            $item->thumb_url = yz_tomedia($item->thumb);
            $item->logo_url = yz_tomedia($item->brandCase->logo);
            return $item;
        });
        
        return $data;
    }

    public function recommend($id, $is_recommend)
    {
        $case = IndustryCase::find($id);
        if (!$case) {
            throw new ShopException('案例不存在');
        }
        $case->is_suggested = $is_recommend;
        return $case->save();
    }

    public function getDetail($id)
    {
        $data = IndustryCase::with(['brandCase' => function ($query) {
            $query->select("id", "store_name", "logo");
        }])->find($id);

        $data->thumb_url = yz_tomedia($data->thumb);
        $data->logo_url = yz_tomedia($data->brandCase->logo);

        return $data;
    }
   
}
