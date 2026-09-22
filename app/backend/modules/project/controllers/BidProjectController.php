<?php

namespace app\backend\modules\project\controllers;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\ProjectBid;
use app\common\models\project\ProjectProgress;
use app\common\services\upload\UploadService;
use app\common\components\BaseController;

use app\backend\modules\project\services\BidProjectService;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectReport;
use Illuminate\Support\Str;
class BidProjectController extends BaseController
{
    protected $bidProjectService;

    public function __construct(BidProjectService $bidProjectService)
    {
        $this->bidProjectService = $bidProjectService;
        parent::__construct();
    }

    public function index()
    {

        if(request()->ajax()){
            if(request()->input('mtype') == "city"){
                $province_id = request()->input('province_id');
                $cityData = $this->bidProjectService->getProvinceData('city_id',$province_id);
                return $this->successJson('ok',$cityData);
            }
            $list = $this->bidProjectService->index();
            $list = $list->toArray();
            return $this->successJson('ok',$list);
        }

        $company_type = ProjectProgress::getCompanyType(2);
        //获取省级
        $province = $this->bidProjectService->getProvinceData('province_id');
        return view('project.bid.list',['company_data'=>collect($company_type),'province'=>$province])->render();
    }


    public function detail()
    {

        $id = request()->input('id');

        if(request()->ajax()){
           $data =  $this->bidProjectService->getDetail($id);
           return $this->successJson('ok',$data);
        }
        return view('project.bid.detail',['id'=>$id])->render();
    }

    public function apply()
    {
        if(request()->ajax()){
            $id = request()->input('id');
            $status = request()->input('status');
            $this->bidProjectService->apply($id,$status);
            return $this->successJson('操作成功');
        }
    }


    public function delete()
    {
        if(request()->ajax()){
            $this->brandService->delete();
            return $this->successJson('删除成功');
        }
    }

    public function downloadProjectZip()
    {
        $id = request()->input('id');

        $ProjectReport =  ProjectBid::find($id);
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

        $zipName = '项目投标文件_' . $ProjectReport->id . '.zip';
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

    //上传标书制作资料
    public function uploadBidFile()
    {
        $file_path= request()->input('file_path');

        $id = request()->input('id');
        $projectBid = ProjectBid::find($id);
        if(!$projectBid){
            throw new ShopException('未找到投标');
        }

        $projectBid->bid_files = $file_path?serialize($file_path):serialize([]);
        $projectBid->save();
        return $this->successJson('ok');
    }



}