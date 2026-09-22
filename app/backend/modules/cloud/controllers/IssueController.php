<?php

namespace app\backend\modules\cloud\controllers;



use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use Illuminate\Http\Request;
use app\backend\modules\cloud\services\IssueService;
use app\common\models\project\IssueOptions;

class IssueController extends BaseController
{
    private IssueService $issueService;


    public function __construct(IssueService $issueService)
    {
        $this->issueService = $issueService;
    }

    public function index()
    {
         $data = IssueOptions::select("id", "title","parent_id")->with(['children'=>function($query){
            $query->select("id", "title","parent_id");
             }])->where('parent_id', 0)->get();
        
        return view('cloud.issue.list',['issue_options'=>$data])->render();
    }


    public function getData(Request $request)
    {
        $search = $request->search;
        $data = $this->issueService->getList($search);
        return $this->successJson('获取成功', $data);

    }


    public function detail(Request $request)
    {

        $id = $request->id;
        if($request->ajax()){
          $data = $this->issueService->getDetail($id);
          return $this->successJson('获取成功', $data);
        }
        
          
        return view('cloud.issue.info',['id'=>$id])->render();
    }


    /**
     * 回复处理问题
     */
    public function reply(Request $request)
    {
        $id = $request->id;
        $content = $request->reply_content;
        $this->issueService->handleReply($id,$content);
        return $this->successJson('处理成功');
    }

    public function delete(Request $request)
    {
        $id = $request->id;
        $this->issueService->delete($id);
        return $this->successJson('删除成功');
    }







   


}