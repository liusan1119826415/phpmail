<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;


use app\common\models\kefu\ServiceUser;
use app\common\services\upload\UploadService;
use app\frontend\modules\project\repositories\PptRepositoryInterface;
use app\frontend\modules\project\models\PptTemplate;
use app\common\models\project\ProjectPdf;
use app\Jobs\HighPptJob;
use app\Jobs\DispatchesJobs;
use app\Jobs\UploadPptJob;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PptRepository extends BaseRepository implements PptRepositoryInterface
{


    public function getOrderGoods(array $search): array
    {
        $Order = Order::find($search['order_id']);
        $supper = \Yunshop\Supplier\admin\models\Supplier::where('id', $Order->supp_id)->first();

        $OrderGoods = OrderGoods::where('order_id', $search['order_id'])
            ->with(['spaces:id,name'])
            ->where('floor_id', $search['floor_id'])
            ->get()
            ->map(function ($item) use ($supper) {
                // 增加客服链接

                $item->service_link = ServiceUser::getDistributeService($supper->id,$item->goods_id);
                $item->store_name = $supper->store_name;
                $item->material_color = $this->getMaterialColor($item);
                return $item;
            })
            ->groupBy(fn($item) => $item->spaces->name ?? '未分配空间') // 按照空间名称分组
            ->map(fn($group) => $group->values()->all()) // 重新整理数据结构，去除键值索引
            ->toArray();

        return $OrderGoods;
    }

    public function getTemplate():array
    {
        return PptTemplate::getTemplateList();
    }

    public function generatePpt(array $space_ids, string $project_name, int $template_id,int $project_id): array
    {

        $memberId = \YunShop::app()->getMemberId();
        $data = app('CartContainer')->make('MemberCart')->floor()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $memberId)
            ->whereIn(app('CartContainer')->make('MemberCart')->getTable() . '.space_id', $space_ids)
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get()->toArray();

        $floors = collect($data)->groupBy('belongs_to_floor.id')->map(function ($floorItems, $floorId) {
            $belongs_to_floor = $floorItems->first()['belongs_to_floor'];
            $floorName = $belongs_to_floor['floor_name'];

            // 按空间分组（最终输出数组）
            $spaces = collect($floorItems)->groupBy('belongs_to_space.id')->map(function ($spaceItems, $spaceId) {
                $spaceName = $spaceItems->first()['belongs_to_space']['space_name'];

                // 商品数据（直接转为数组）
                $goodsData = $spaceItems->map(function ($item) {

                    $atlas = json_decode($item['goods']['atlas'],true);

                    $highUrls = [];

                    if (!empty($atlas['data']) && is_array($atlas['data'])) {
                        // 1. 先筛选出 inPpt == 1 的项
                        $filteredData = array_filter($atlas['data'], function($item) {
                            return isset($item['inPpt']) && $item['inPpt'] == 1;
                        });

                        // 2. 提取 high_url 并用 yz_tomedia 处理
                        $highUrls = array_map(function($item) {
                            return yz_tomedia($item['high_url']);
                        }, $filteredData);
                    }
                    return [
                        'goods_id' => $item['goods']['id'],
                        'goods_name' => $item['goods']['title'],
                        "pdf_url" => yz_tomedia($item['goods']['e_catalog_pdf']),
                        "pdf_page" => $item['goods']['pdf_page'] ? unserialize($item['goods']['pdf_page']) : [],
                        "pdf_img" => $item['goods']['pdf_page_img'] ? unserialize($item['goods']['pdf_page_img']) : [],
                        "pdf_high_img" => $item['goods']['pdf_high_img'] ? unserialize($item['goods']['pdf_high_img']) : [],
                        'high_urls'=>$highUrls,
                        'status' => $this->getGoodsStatus($item)
                    ];
                })->values()->toArray(); // 关键点：转为数组

                return [
                    'space_id' => $spaceId,
                    'space_name' => $spaceName,
                    'goods' => $goodsData, // 这里已经是数组
                ];
            })->values()->toArray(); // 关键点：转为数组

            $space_names = collect($spaces)->pluck('space_name')->all(); // 从数组重新转为集合提取名称

            return [
                'floor_id' => $floorId,
                'floor_name' => $floorName,
                'space' => $space_names,
                'space_goods' => $spaces, // 这里已经是数组
                'floor_img' => $belongs_to_floor['thumb'] ? yz_tomedia($belongs_to_floor['thumb']) : null,
            ];
        })->values()->toArray(); // 最外层也转为数组

        $params = [
            'project_name' => $project_name,
            "floors" => $floors // 已经是数组，无需再调用 toArray()
        ];


        //获取默认模版链接
        $PptTemplate = PptTemplate::find($template_id);
        if(!$PptTemplate){
            throw new ShopException("未找到模版");
        }
        $uniqid = uniqid();
        $template_url = yz_tomedia($PptTemplate->url);
        $request_data['template_ppt'] = $template_url;
        $request_data['params'] = $params;
        $request_data['output'] = storage_path("app/public/tmp") . "/" . $uniqid . ".pptx";
        $request_data['is_high'] = 0;
   
        $response = $this->curl_python("replace_ppt",$request_data);

        if($response['data']['status_code'] == 200){
            $output_ppt = $response['data']['output_pdf'];
            $total_slides = $response['data']['total_slides'];

             // 上传到OSS
            $oss_relative_path = $this->uploadOss($output_ppt, $uniqid . ".pptx");

            $result = ProjectPdf::create(
              [
                  "project_id"=>$project_id,
                  "member_id"=>$memberId,
                  "project_name"=>$project_name,
                  "ppt_url"=>$oss_relative_path,
                  "template_id"=>$template_id,
                  'total_slides'=>$total_slides
              ]
            );
            //生成高清ppt压缩
//            $job = new HighPptJob($result->id,$params,$template_url,Request()->getHost());
//            DispatchesJobs::dispatch($job,DispatchesJobs::LOW);
            return $result->toArray();
        }else{
            throw new ShopException($response['data']['message']);
        }

    }

    private function uploadOss($save_path, $file_name)
    {

        $uploadedFile = new UploadedFile(
            $save_path,
            $file_name,
            mime_content_type($save_path),
            null,
            true // Mark as test file to avoid further validation
        );

        $uploadService = new UploadService();
        $upload_res = $uploadService->upload($uploadedFile, "file", "files");
        unlink($save_path);
        $relative_path = $upload_res['absolute_path'];
        return $relative_path;
    }

    public function export(int $id): array
    {
        $ProjectPdf = ProjectPdf::find($id);
        if(!$ProjectPdf){
            throw new ShopException("参数错误");
        }
        if($ProjectPdf->ppt_formal_url){
            $export_url = yz_tomedia($ProjectPdf->ppt_formal_url);
        }else if($ProjectPdf->thumb_url){
            $export_url = $ProjectPdf->thumb_url;
        }else if($ProjectPdf->ppt_url){
            $export_url = $ProjectPdf->ppt_url;
        }

        return ['export_ppt'=>$export_url];
    }

    public function savePpt(int $id, string $project_name): bool
    {
        $ProjectPdf = ProjectPdf::find($id);
        if(!$ProjectPdf){
            throw new ShopException("参数错误");
        }
        $ProjectPdf->project_name = $project_name;
        $ProjectPdf->status = 1;
        $ProjectPdf->save();
        //异步上传到oss
        $job = new UploadPptJob($ProjectPdf->id);
        DispatchesJobs::dispatch($job,DispatchesJobs::LOW);
        return true;

    }

    public function delete(int $id): bool
    {
        try {
            $ProjectPdf = ProjectPdf::find($id);
            if(!$ProjectPdf){
                throw new ShopException("参数错误");
            }
            $ProjectPdf->delete();
            return true;
        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }



    }


}