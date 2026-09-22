<?php

namespace app\frontend\modules\project\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\models\project\ProjectBid;
use app\frontend\modules\project\services\BidProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Yunshop\Supplier\common\models\SupplierBidFiles;

class BidProjectController extends ApiController
{


    public $transactionActions = ["applyFor"];

    /**
     *创建我的项目
     */

    private BidProjectService $bidProjectService;

    public function __construct(BidProjectService $bidProjectService)
    {
        $this->bidProjectService = $bidProjectService;
        parent::__construct();
    }


    public function cancelApply(Request $request)
    {
        $this->validate(
            [
                'id' => 'required|integer|min:0',
            ]
        );
        $id = $request->input("id");
        $this->bidProjectService->cancelApply($id);
        return $this->successJson('ok');
    }


    //申请投标

    public function applyFor(Request $request)
    {
        $validat = [
            'project_id' => 'required|integer|min:0',
            'supplier_id' => 'required|integer|min:0',
            'name' => 'required|string',
            'province_id' => 'required|integer|min:0',
            'city_id' => 'required|integer|min:0',
            'district_id' => 'required|integer|min:0',
            'address_detail' => 'required|string',
            'project_area' => 'required',
            'project_file' => 'required|array',
            'bid_type' => 'required|integer|min:0',
            'need_bid_document' => 'required|integer|min:0',
            'need_bid_bond' => 'required|integer|min:0',
            'company_name' => 'required|string',
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
            'company_address' => 'required|string',
            'company_type' => 'required|array',
            'consignee' => 'required_if:need_bid_document,1|nullable|string',
            'consignee_mobile' => 'required_if:need_bid_document,1|nullable|string',
            'consignee_address_detail' => 'required_if:need_bid_document,1|nullable|string',
            'consignee_province_id' => 'required_if:need_bid_document,1|nullable|integer',
            'consignee_city_id' => 'required_if:need_bid_document,1|nullable|integer',
            'consignee_district_id' => 'required_if:need_bid_document,1|nullable|integer',
            // 只有当 bid_type 为 1 时，authorized_person_name 才是必填
            'authorized_person_name' => 'required_if:bid_type,1|nullable|string',
            'authorized_person_phone' => 'required_if:bid_type,1|nullable|string',
            'authorized_person_idcard' => 'required_if:bid_type,1|nullable|string',
            'account_name' => 'required_if:need_bid_bond,1|nullable|string',
            'bank_branch' => 'required_if:need_bid_bond,1|nullable|string',
            'bank_name' => 'required_if:need_bid_bond,1|nullable|string',
            'amount' => 'required_if:need_bid_bond,1|nullable|string',
            'contact_bank_mobile' => 'required_if:need_bid_bond,1|nullable|string',
        ];

        $this->validate($validat);
        $validated = $request->validate($validat);

        $this->PreventDuplicateSubmission($request);
        $id = $this->bidProjectService->applyFor($validated);
        return $this->successJson('ok', ['id' => $id]);
    }


    //获取项目列表
    public function getList(Request $request)
    {
        $search = $request->input("search", []);
        $data = $this->bidProjectService->getList($search);
        return $this->successJson('ok', $data);
    }

    //报备详情
    public function detail(Request $request)
    {
        $validat = [
            'id' => 'required|integer|min:0',
        ];
        $this->validate($validat);
        $id = $request->input("id");
        $data = $this->bidProjectService->detail($id);
        return $this->successJson('ok', $data);

    }

    //获取报备推荐品牌数据
    public function getRecommendBrand(Request $request)
    {
        $this->validate(
            [
                'project_id' => 'required|integer|min:0',
            ]
        );
        $project_id = $request->input("project_id");
        $data = $this->bidProjectService->getRecommendBrand($project_id);
        return $this->successJson('ok', $data);
    }

    //搜索品牌
    public function searchBrand(Request $request)
    {
        $this->validate(
            [
                'name' => 'required|string',
            ]
        );
        $name = $request->input("name");
        $data = $this->bidProjectService->searchBrand($name);
        return $this->successJson('ok', $data);
    }


    public function getBidInformation(Request $request)
    {
        $project_id = $request->input('project_id');
        $projectBid = ProjectBid::where('project_id', $project_id)->first();
        if (!$projectBid) {
            throw new AppException('没有投标项目');
        }
        $SupplierBidFiles = SupplierBidFiles::where('supplier_id', $projectBid->supplier_id)->first();
        $data = ["bid_files" => $projectBid->bid_files ? unserialize($projectBid->bid_files) : [],
            'default_bid_files' => $SupplierBidFiles ? unserialize($SupplierBidFiles->file_path) : []];
        return $this->successJson('ok', $data);


    }


}