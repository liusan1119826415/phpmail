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
use app\backend\modules\member\services\InvitationService;
use app\common\models\member\InvitationCode;
use Illuminate\Http\Request;
class InvitationController extends BaseController
{
    private InvitationService $invitationService;


    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    public function index()
    {

        if(request()->ajax()){
            $search = request()->search;
            $query = InvitationCode::query();
            if($search['code']){
                $query->where('code','like','%'.$search['code'].'%');
            }
            if(isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)){
                $query->where('status',$search['status']);
            }

            if(isset($search['is_expired']) && ($search['is_expired'] !== '' || $search['is_expired'] === 0)){

                if($search['is_expired'] == 1){
                    $query->where('expires_at', '<=', time());
                }elseif($search['is_expired'] == 0){
                    $query->where('expires_at', '>', time());
                }
            }

            $codes = $query->paginate(20);

            $codes->transform(function ($item){
               // $item->url = InvitationService::getUrl("register",$item->code);
                return $item;
            });
            return $this->successJson('ok',$codes);
        }

        return view('member.invitation.index');
    }

    public function create(Request $request)
    {
        if(request()->ajax()){
            /*$request->validate([
                'expires_days' => 'required|integer|min:1',
                'max_uses'=> 'required|integer|min:1',
            ]);*/



            $code = $this->invitationService->generateCode(
                $request->expires_days,
                $request->max_uses,
                $request->is_limit
            );

            return $this->successJson('ok');
        }

        return view('member.invitation.create');
    }


    public function status(Request $request)
    {
        $id = request()->id;
        $code = InvitationCode::find($id);
        $code->status = $code->status==1?0:1;
        $code->save();
        return $this->successJson('ok');
    }



    public function destroy()
    {
        $id = request()->id;
        $code = InvitationCode::findOrFail($id);

        $code->delete();

        return $this->successJson('ok');
    }

    public function shareQrCode(Request $request)
    {
        $id = request()->id;
        $codeinfo = InvitationCode::findOrFail($id);

        $url = $this->invitationService->getMiniInviteCode($codeinfo);

        return $this->successJson('ok',['mini_qrcode'=>$url]);

    }


}