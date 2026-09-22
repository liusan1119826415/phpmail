<?php

namespace app\frontend\modules\project\services\goods;

use app\common\exceptions\ShopException;
use app\frontend\modules\project\infrastructure\BaseRepository;

class GoodsAnalysisService
{
    protected function getBaseRepo(): BaseRepository
    {
        return app(BaseRepository::class);
    }

    // //解析cad图纸
    // public function analysis(): array
    // {
    //     $baseRepo = $this->getBaseRepo();

    //     $dwgFile = request()->file('file');
    //     $dwgFilePath = $dwgFile->storeAs('uploads', $dwgFile->getClientOriginalName());
    //     $response = $baseRepo->curl_analysis_cad("search_block", $dwgFilePath);
    //     if ($response['data']['status_code'] == 200) {
    //         $dxf_file = $response['data']['dxf_file'];
    //         $output_img = $response['data']['output_img'];

    //         $img_url = "https://" . Request()->getHost() . "/ppt/img/{$output_img}";
    //         $dxf_url = "https://" . Request()->getHost() . "/dxf/{$dxf_file}";
    //         $data = $response['data']['data'];
    //         $goods_total = collect($data)->sum('num');
    //         $grouped = collect($data)
    //             ->groupBy('space_name') // 以 space_name 分组
    //             ->map(function ($items, $spaceName) {
    //                 return [
    //                     'space_name' => $spaceName,
    //                     'data' => $items->map(function ($item) {
    //                         return [
    //                             'goods_id' => $item['goods_id'],
    //                             'option_id' => $item['option_id'],
    //                             'num' => $item['num']
    //                         ];
    //                     })->values()
    //                 ];
    //             })
    //             ->values();

    //         return [
    //             "dxf_url" => $dxf_url,
    //             "output_img" => $img_url,
    //             "goods_total" => $goods_total,
    //             "data" => $grouped->toArray()
    //         ];
    //     } else {
    //         throw new ShopException($response['data']['message']);
    //     }
    // }


    // public function analysisV2(): array
    // {
    //     $baseRepo = $this->getBaseRepo();

    //     // 接收多个 DWG 文件
    //     $dwgFiles = request()->file('file'); // 确保前端传递字段名为 'files'

    //     $uploadedFiles = [];
    //     foreach ($dwgFiles as $dwgFile) {
    //         // 保存文件到 uploads 目录
    //         $dwgFilePath = $dwgFile->storeAs('uploads', $dwgFile->getClientOriginalName());
    //         $uploadedFiles[] = $dwgFilePath;
    //     }

    //     // 调用 curl_analysis_cad 方法，传递多个文件路径
    //     $response = $baseRepo->curl_analysis_cad_v2("search_block_v2", $uploadedFiles);

    //     if ($response['data']['status_code'] == 200) {

    //         $data = $response['data']['results'];
    //         $collection = collect($data);
    //         $totalNum = $collection->flatMap(function ($item) {
    //             return $item['data'];
    //         })->sum('num');
    //         $dxfFiles = [];
    //         $transformed = $collection->map(function ($item) {

    //             $goods_total = collect($item['data'])->sum('num');
    //             $groupedData = collect($item['data'])
    //                 ->groupBy('space_name') // 按 space_name 分组
    //                 ->map(function ($group, $spaceName) {
    //                     return [
    //                         "space_name" => $spaceName,
    //                         "data" => $group->map(function ($entry) {
    //                             return [
    //                                 "goods_id" => $entry['goods_id'],
    //                                 "option_id" => $entry['option_id'],
    //                                 "num" => $entry['num'],
    //                             ];
    //                         })->values(),
    //                     ];
    //                 })->values();
    //             $dxf_file = $item['dxf_file'];
    //             $dxfFiles[] = $dxf_file;
    //             $item['data'] = $groupedData;
    //             $item['goods_total'] = $goods_total;

    //             $dxf_url = "https://" . Request()->getHost() . "/dxf/{$dxf_file}";
    //             $img_name = basename($item['output_img']);
    //             $img_url = "https://" . Request()->getHost() . "/ppt/img/{$img_name}";
    //             $item['dxf_file'] = $dxf_url;
    //             $item['output_img'] = $img_url;
    //             return $item;
    //         });

    //         return ['totalNum' => $totalNum, 'data' => $transformed->toArray()];
    //     } else {
    //         throw new ShopException($response['data']['message']);
    //     }
    // }
}
