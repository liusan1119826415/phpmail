<?php

namespace app\backend\modules\project\controllers;

use app\common\models\Address;
use app\common\models\project\ProjectProgress;
use app\platform\modules\application\models\CoreAttach;
use app\platform\modules\application\models\CoreAttachTags;
use app\common\components\BaseController;

use app\backend\modules\project\services\ReportProjectService;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectReport;
use Illuminate\Support\Str;
class ReportProjectController extends BaseController
{
    protected $reportProjectService;

    public function __construct(ReportProjectService $reportProjectService)
    {
        $this->reportProjectService = $reportProjectService;
        parent::__construct();
    }



    public function index()
    {

        if(request()->ajax()){
            if(request()->input('mtype') == "city"){
                $province_id = request()->input('province_id');
                $cityData = $this->reportProjectService->getProvinceData('city_id',$province_id);
                return $this->successJson('ok',$cityData);
            }
            $list = $this->reportProjectService->index();
            $list = $list->toArray();
            return $this->successJson('ok',$list);
        }
        $purchasing_models = $this->reportProjectService->getPurchasingModel();
        $company_type = ProjectProgress::getCompanyType(2);
        //获取省级
        $province = $this->reportProjectService->getProvinceData('province_id');
        return view('project.report.list',['purchasing_models'=>$purchasing_models,'company_data'=>collect($company_type),'province'=>$province])->render();
    }


    public function detail()
    {
        $id = request()->input('id');
        if(request()->ajax()){

            $data = $this->reportProjectService->getDetail($id);
            return $this->successJson('ok',$data);
        }
        return view('project.report.detail',['id'=>$id])->render();


    }

    public function apply()
    {
        if(request()->ajax()){
            $id = request()->input('id');
            $status = request()->input('status');
            $this->reportProjectService->apply($id,$status);
            return $this->successJson('操作成功');
        }
    }

    public function deleteTag()
    {


        $id = request()->id;
        $status = request()->moveOrDelete;
        if(!$id){
            return $this->errorJson('缺少参数');
        }
        $result = CoreAttachTags::uniacid()->where('id', $id)->delete();
        if ($status == 1)
        {
            CoreAttach::uniacid()->where('tag_id', $id)->update(['tag_id' => 0]);
        }
        else if ($status == 2)
        {
            CoreAttach::uniacid()->where('tag_id', $id)->delete();
        }
        if ($result)
        {
            return $this->successJson('成功');
        }
        else
        {
            return $this->errorJson('失败');
        }
    }

    public function downloadProjectZip()
    {
        $id = request()->input('id');

        $ProjectReport =  ProjectReport::find($id);
        $fileUrlData = $ProjectReport->project_file?unserialize($ProjectReport->project_file):[];

        foreach ($fileUrlData as $item){
            $fileUrls[] = yz_tomedia($item['url']);
        }


        if (empty($fileUrls)) {
            return $this->errorJson('No files found');
        }

        $tmpDir = storage_path('app/tmp_' . Str::random(8));
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $downloadedFiles = [];

        foreach ($fileUrls as $url) {
            $fileName = basename(parse_url($url, PHP_URL_PATH));
            $localPath = $tmpDir . '/' . $fileName;

            try {
                $fileContent = file_get_contents($url);
                file_put_contents($localPath, $fileContent);
                $downloadedFiles[] = $localPath;
            } catch (\Exception $e) {
                // 可选：记录日志或跳过
            }
        }

        if (empty($downloadedFiles)) {
            return $this->errorJson('Failed to download files');
        }

        $zipName = '项目报备文件_' . $ProjectReport->id . '.zip';
        $zipPath = storage_path('app/public/' . $zipName);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($downloadedFiles as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            return $this->errorJson('Failed to create ZIP');
        }

        // 清理临时文件夹
        foreach ($downloadedFiles as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }








}