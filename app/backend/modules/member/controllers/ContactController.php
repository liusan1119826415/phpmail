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
use app\backend\modules\member\services\ContactService;
use app\common\models\Member;
use app\common\models\member\SendMessageReport;
use Illuminate\Http\Request;
use app\common\models\project\CustomerContact;

class ContactController extends BaseController
{
    private ContactService $contactService;


    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
    }

    public function index()
    {


        if(request()->ajax()){

            $query = CustomerContact::select("id","member_id","name","content","phone","status","ip","created_at")->with(['member'=>function($query){
                $query->select("uid","nickname","mobile","avatar");
            }])->orderBy('created_at', 'desc');
            $lists = $query->paginate(20);
            return $this->successJson('ok',$lists);
        }

        return view('member.contact.index');
    }

   





}