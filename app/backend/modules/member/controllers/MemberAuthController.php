<?php
/**
 * Created by PhpStorm.
 *
 * 
 *
 * Date: 2021-07-30
 * Time: 16:14
 */

namespace app\backend\modules\member\controllers;



use app\common\components\BaseController;

use app\common\models\project\RealNameVerification;
class MemberAuthController extends BaseController
{
    public function index()
    {

        if(request()->ajax()){
            $search = request()->search;
            $list = RealNameVerification::search($search)->with(['member'=>function($query){
                $query->select("uid","nickname","realname","mobile");
            }])->orderby('created_at', 'desc')->paginate(15);
            $list->transform(function ($item) {
                $item->id_photo_front = $item->id_photo_front?yz_tomedia($item->id_photo_front):"";
                $item->id_photo_back = $item->id_photo_back?yz_tomedia($item->id_photo_back):"";
                return $item;
            });
            return $this->successJson('ok', $list);
        }

        return view('member.memberAuth.index', []);
    }

    public function detail()
    {
        $id = request()->id;
        if(request()->ajax()){
            $detail = RealNameVerification::with(['member'=>function($query){
                $query->select("uid","nickname","realname","mobile");
            }])->find($id);
            $detail->id_photo_front = $detail->id_photo_front?yz_tomedia($detail->id_photo_front):"";
            $detail->id_photo_back = $detail->id_photo_back?yz_tomedia($detail->id_photo_back):"";
            return $this->successJson('ok', $detail);


        }
        return view('member.memberAuth.detail',['id'=>$id]);
    }




    //通过
    public function pass()
    {
        $id = request()->id;
        if (!$id) {
            return $this->errorJson('请传入正确参数');
        }
        $model = RealNameVerification::find($id);
        if (!$model) {
            return $this->errorJson('记录不存在');
        }
        $status = request()->status;
        if($status == 2){
            $model->reason = request()->reason;
        }
        $model->status = $status;
        $model->save();

        return $this->successJson('审核成功');

    }


}