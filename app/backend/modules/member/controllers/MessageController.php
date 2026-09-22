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
use app\backend\modules\member\services\MessageService;
use app\common\models\Member;
use app\common\models\member\SendMessageReport;
use Illuminate\Http\Request;
use app\common\modules\pcnotice\Template;
use app\common\models\project\Notification;
class MessageController extends BaseController
{
    private MessageService $messageService;


    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function index()
    {


        if(request()->ajax()){

            $query = SendMessageReport::select("id","title","content","sub_type","send_num","send_success","send_fail");
     
            $lists = $query->paginate(20);
            return $this->successJson('ok',$lists);
        }

        return view('member.message.index');
    }

    public function create(Request $request)
    {
        if(request()->ajax()){
            $send_type = $request->send_type;
            if($send_type == "all"){
                $memberIds = Member::select("uid")->get()->pluck("uid")->toArray();
            }else{
                $memberIds = $request->memberIds;
            }
            
            $content = $request->content;   
            $title = $request->title;
            $sub_type = $request->sub_type;

        
            app('notification')->sendBatch($memberIds,$sub_type,$title,$content);

            return $this->successJson('ok');
        }

        return view('member.message.create');
    }

    public function getMember(Request $request)
    {
        if (request()->ajax()) {
            $member = Member::select("uid", "nickname", "mobile","avatar");
            
            if ($request->keyword) {
                $keyword = trim($request->keyword);
                
                // 1. 判断是否为纯数字（可能是ID或手机号）
                if (is_numeric($keyword)) {
                    // 2. 根据长度判断是ID还是手机号
                    if (strlen($keyword) == 11 && preg_match('/^1[3-9]\d{9}$/', $keyword)) {
                        // 11位且符合手机号格式，查询手机号
                        $member->where('mobile', $keyword);
                    } else {
                        // 其他纯数字，查询ID
                        $member->where('id', $keyword);
                    }
                } else {
                    // 3. 中英文字符串，查询昵称
                    $member->where('nickname', 'like', '%' . $keyword . '%');
                }
            }

            $data = $member->get()->toArray();
            foreach ($data as $key => $value) {
                $data[$key]['avatar'] = yz_tomedia($value['avatar']);
            }
            return $this->successJson('ok', $data); 
        }
    }

    public function getNoticeInfo(Request $request)
    { 
        if (request()->ajax()) {
            $id = $request->id;
 
            $data = Notification::select("id","member_id","is_read","created_at")->where('report_id',$id)->with(['member'=>function($query){
                $query->select("uid","nickname","mobile","avatar");
            }])->paginate(20);
            $data->transform(function($item){
                $item->member->avatar = yz_tomedia($item->member->avatar);
                return $item;
            });
            return $this->successJson('ok', $data);

        }
    }





}