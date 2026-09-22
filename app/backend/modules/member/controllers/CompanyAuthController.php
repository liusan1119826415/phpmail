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
use app\common\models\project\CompanyAuths;
class CompanyAuthController extends BaseController
{
    public function index()
    {

        if(request()->ajax()){
            $search = request()->search;
            $list = CompanyAuths::search($search)->with(['member'=>function($query){
                $query->select("uid","nickname","realname","mobile");
            }])->orderby('created_at', 'desc')->paginate(15);
            $list->transform(function ($item) {
                $item->license_image = $item->license_image?yz_tomedia($item->license_image):"";
                return $item;
            });
            return $this->successJson('ok', $list);
        }

        return view('member.companyAuth.index', []);
    }

    public function detail()
    {
        $id = request()->id;
        if(request()->ajax()){
            $detail = CompanyAuths::with(['member'=>function($query){
                $query->select("uid","nickname","realname","mobile");
            }])->find($id);
            $detail->license_image = $detail->license_image?yz_tomedia($detail->license_image):"";

            return $this->successJson('ok', $detail);


        }
        return view('member.companyAuth.detail',['id'=>$id]);
    }




    //通过
    public function pass()
    {
        $id = request()->id;
        if (!$id) {
            return $this->errorJson('请传入正确参数');
        }
        $model = CompanyAuths::find($id);
        if (!$model) {
            return $this->errorJson('记录不存在');
        }
        $status = request()->status;
        if($status == 2){
            $model->rejection_reason = request()->reason;
        }
        $model->status = $status;
        if($status == 1){
            $model->reviewed_at = time();
        }

        $model->save();

        return $this->successJson('审核成功');

    }


}