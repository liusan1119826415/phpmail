<?php

namespace app\frontend\modules\goods\controllers;


use app\common\components\ApiController;
use app\frontend\modules\goods\services\BlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class BlockController extends ApiController
{
    protected $blockService;
    public function __construct(BlockService $blockService)
    {
        $this->blockService = $blockService;
        parent::__construct();
    }

    public function assemble()
    {
        $request = request();
        // 接收 JSON 数据
        $jsonData = $request->input('jsondata');
        $goods_id = $request->input('goods_id');


        // 验证 jsondata 是否是有效的 JSON 格式
        if (is_null($jsonData)) {
            return $this->errorJson('Invalid JSON data');
        }

        $result = $this->blockService->getCacheBlock($goods_id,$jsonData);

        if($result['status'] == 1){
            return $this->successJson('ok',$result['data']);
        }else{
            return $this->errorJson($result['data']);
        }
    }

    /**
     * 解析cad图纸
     */

    public function search_block(Request $request)
    {
        if (!$request->hasFile('dwg_file')) {
            return $this->errorJson("请上传dwg文件");
        }
        $file = $request->file('dwg_file');

        // 检查文件类型
        if ($file->getClientOriginalExtension() !== 'dwg') {
            return $this->errorJson("请上传dwg文件后缀");
        }
        $fileContent = file_get_contents($file->getRealPath());
        $response = Http::attach(
            'dwg_file', // 表单字段名
            $fileContent,
            $file->getClientOriginalName() // 文件名
        )->post('http://127.0.0.1:8000/search_block');
    }






}
