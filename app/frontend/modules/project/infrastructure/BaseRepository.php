<?php


namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\ShopException;
use app\common\models\BaseModel;
use app\common\models\MemberCart;
use app\common\services\upload\UploadService;
use app\frontend\models\GoodsOption;
use app\frontend\models\Order;
use app\frontend\models\OrderGoods;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Yunshop\Supplier\common\models\SupplierColorPlane;
use app\common\models\goods\GoodsOptionModel;
use Illuminate\Support\Facades\File;
class BaseRepository
{
    const PAGE_SIZE = 6;
    const projects_visit = [
        [
            "id" => 1,
            "name" => "展厅"
        ],
        [
            "id" => 2,
            "name" => "车间"
        ],
        [
            "id" => 3,
            "name" => "办公区"
        ],
        [
            "id" => 4,
            "name" => "其它"
        ]
    ];

    const inspection_mode = [
        [
            "id" => 1,
            "name" => "带客考察"
        ],
        [
            "id" => 2,
            "name" => "自行参观"
        ]
    ];

    const travel_mode = [
        [
            "id" => 1,
            "name" => "自驾前往"
        ],
        [
            "id" => 2,
            "name" => "需派车接送"
        ]
    ];

    const payment_method = [
        [
            "id" => 1,
            "name" => "申请人支付"
        ],
        [
            "id" => 2,
            "name" => "客人自付"
        ],
        [
            "id" => 3,
            "name" => "工厂代付"
        ]
    ];
    protected $model;

    /**
     * BaseRepository 构造函数，注入模型
     * @param Model $model
     */
    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * 在事务中执行操作
     * @param \Closure $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback)
    {
        return DB::transaction($callback);
    }

    /**
     * 保存数据并进行验证
     * @param array $data
     * @return BaseModel
     * @throws ShopException
     */
    public function save(array $data): BaseModel
    {
        $this->model->fill($data);
        $validator = $this->model->validator();

        if ($validator->fails()) {
            throw new ShopException($validator->messages());
        }

        $this->model->save();

        return $this->model;
    }

    /**
     * 创建一个新记录
     *
     * @param array $data
     * @return BaseModel
     */
    public function create(array $data): BaseModel
    {
        return $this->model->create($data);
    }

    /**
     * 更新记录
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $model = $this->model->find($id);
        if (!$model) {
            throw new ShopException("Record not found");
        }
        return $model->update($data);
    }

    /**
     * 删除记录
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $model = $this->model->find($id);
        if (!$model) {
            throw new ShopException("Record not found");
        }
        return $model->delete();
    }

    /**
     * 查询单个记录
     *
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?BaseModel
    {
        return $this->model->find($id);
    }

    /**
     * 批量查询
     *
     * @param array $conditions
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findBy(array $conditions)
    {
        return $this->model->where($conditions)->get();
    }


    protected function updateActivateCache($member_id, $project_id)
    {
        $project = Project::getProject($project_id);

        // 如果项目未激活，直接返回
        if ($project->activate != 1) {
            return;
        }

        // 删除旧缓存
        Redis::del("user:{$member_id}:active_project_1");

        // 获取激活项目的数据，优化查询
        $activate_project = Project::select("id", "name", "activate", "status","order_status")
            ->where("member_id", $member_id)
            ->where("id", $project_id)
            ->where('activate', 1)
            ->with([
                "floors" => function ($query) {
                    $query->select("id", "project_id", "name", "activate")->orderBy('sort', 'asc')->orderBy('id', 'desc')
                        ->with([
                            'spaces' => function ($query) {
                                $query->select("id", "floor_id", "name", "activate")
                                    ->withSum('spaceCart', 'total')->orderBy('sort', 'asc')/*->orderBy('id', 'desc')*/;
                            }
                        ]);
                }
            ])
            ->first()
            ->toArray();

        if($activate_project['order_status'] == 1){
            $activate_project['order_id'] = Order::where('project_id',$activate_project['id'])->where('status','!=',-1)->value("id");
        }

        // 获取所有的 goods_id 和价格
        $optionIds = MemberCart::whereIn('floor_id', array_column($activate_project['floors'], 'id'))
            ->pluck('option_id')
            ->unique()
            ->toArray();

        // 获取商品单价格
       /* $goodsPrices = GoodsOption::whereIn('id', $optionIds)
            ->pluck('market_price', 'id')
            ->toArray();*/
        //获取商品销售价格
        if($project->order_id){
            $orderIds = \app\common\models\Order::where('parent_id',$project->order_id)->pluck('id')->toArray();
            $goodsSalePrices = OrderGoods::whereIn('order_id', $orderIds)
                ->pluck('goods_option_price', 'goods_option_id')
                ->toArray();
        }else{
            $goodsSalePrices = GoodsOption::whereIn('id', $optionIds)
                ->pluck('product_price', 'id')
                ->toArray();
        }


        // 初始化项目总金额
        $project_total_price = 0;
        //初始化项目销售总金额
        $project_total_sale_price = 0;
        // 遍历项目中的每个楼层
        foreach ($activate_project['floors'] as $key => $item) {
            // 初始化当前楼层的总金额
            $floor_total_price = 0;
            //初始化楼层销售总金额
            $floor_total_sale_price = 0;
            // 遍历当前楼层中的每个空间
            foreach ($item['spaces'] as $k => $space) {
                $space_goods = MemberCart::where('space_id', $space['id'])
                    ->get(['option_id', 'total'])  // 获取商品的 option_id 和数量
                    ->toArray();

                // 计算当前空间的总金额
                $total_price = 0;

                // 计算当前空间的销售总金额
                $total_sale_price = 0;

                foreach ($space_goods as $goods) {
                    $option_id = $goods['option_id'];
                    $quantity = $goods['total'];  // 获取商品数量

                    // 使用提前获取的商品价格，并乘以数量
                    $total_price += isset($goodsSalePrices[$option_id]) ? $goodsSalePrices[$option_id] * $quantity : 0;

                    // 使用提前获取的商品销售价格，并乘以数量
                    $total_sale_price += isset($goodsSalePrices[$option_id]) ? $goodsSalePrices[$option_id] * $quantity : 0;
                }

                // 加上空间中商品数量的影响
//                $total_price *= $space['space_cart_sum_total'];
//
//                $total_sale_price *= $space['space_cart_sum_total'];
                // 保存到当前空间的总金额
                $activate_project['floors'][$key]['spaces'][$k]['total_price'] = $this->truncate_to_two_decimals($total_price);

                // 累加到当前楼层的总金额
                $floor_total_price += $total_price;

                $floor_total_sale_price +=$total_sale_price;

            }


            $activate_project['floors'][$key]['total_price'] = $floor_total_price;


            $project_total_price += $floor_total_price;
            $project_total_sale_price+=$floor_total_sale_price;
        }


        $activate_project['project_total_price'] = $project_total_price;
        //更新项目总金额到我的项目里面
        //Project::where('id',$project_id)->update(['price'=>$project_total_sale_price]);
        // 将更新后的数据保存到 Redis
        $baseCacheTime = 24 * 60 * 60; // 基础缓存时间：一天
        $randomFactor = rand(10, 99);
        $cacheTime = (int)($baseCacheTime * $randomFactor); // 计算缓存时间
        Redis::setex("user:{$member_id}:active_project_1", $cacheTime, json_encode($activate_project));
        return $activate_project;
    }


    protected function truncate_to_two_decimals($number)
    {
        // 将数字转为字符串并找到小数点的位置
        $numberStr = (string)$number;
        $pointPos = strpos($numberStr, '.');

        // 如果没有小数点，则直接返回数字并添加".00"
        if ($pointPos === false) {
            return $numberStr . '.00';
        }

        // 提取小数点后的部分
        $decimalPart = substr($numberStr, $pointPos + 1);

        // 如果小数点后只有一位或没有数字，则补零
        if (strlen($decimalPart) < 2) {
            return $numberStr . '0';
        }

        // 截取小数点后的前两位
        return substr($numberStr, 0, $pointPos + 3);
    }


    protected function updateCart(int $space_id)
    {
        $member_id = \YunShop::app()->getMemberId();
        $data = $this->processProduct($member_id, $space_id);
        $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
        Cache::forget($cacheKey);  // 删除旧缓存
        Cache::put($cacheKey, $data, 24 * 60 + rand(10, 99));  // 写入新缓存
        return $data;

    }


    protected function removeCacheCart(int $space_id)
    {
        $member_id = \YunShop::app()->getMemberId();
        $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
        Cache::forget($cacheKey);  // 删除旧缓存


    }






    protected function processProduct($member_id, $space_id)
    {
        $cartList = app('CartContainer')->make('MemberCart')->carts()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $member_id)
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.space_id', $space_id)
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() .'.sort','asc')
            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get();

        $project_id = FloorSpace::where('id',$space_id)->value('project_id');
        $Project = Project::find($project_id);
        $orderGoods = null;
        if($Project->order_id){

            $orderGoods = OrderGoods::select("goods_id", "goods_option_id","space_id", "price", "goods_option_price")

                ->where('order_main_id', $Project->order_id)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [ $item->goods_id . '_' . $item->goods_option_id . '_'. $item->space_id => $item ];
                });
        }
        $newList = [];
        if ($cartList) {
            $cartList = $cartList->toArray();

            foreach ($cartList as $key => $item) {

                if($item['type'] == 2){
                    $old_goods_option_data = $item['goods']['old_option']?unserialize($item['goods']['old_option']):[];
                    $old_goods_id = is_numeric($old_goods_option_data[0])?$old_goods_option_data[0]:$old_goods_option_data[0]['goods_id'];

                }

                $oldOptionData = [];
                if ($item['goods']['productType'] == 5 && !empty($item['goods']['old_option'])) {
                    // 反序列化 old_option
                    $old_option = unserialize($item['goods']['old_option']);

                    if (is_array($old_option)) {
                        // 查询 old_option 对应的 goods_option 尺寸信息
                        $optionIds = collect($old_option)->pluck('option_id')->all();
                        $options = GoodsOption::whereIn('id', $optionIds)->with(['goods'])
                            ->get(['id', 'length', 'width', 'height', 'title','structure','product_price'])
                            ->keyBy('id');

                        // 组装 old_option 数据，包含数量和尺寸
                        foreach ($old_option as $opt) {
                            $optId = $opt['option_id'];
                            $oldOptionData[] = [
                                'option_id' => $optId,
                                'num'       => $opt['num'],
                                'length'    => isset($options[$optId]) ? $options[$optId]->length : null,
                                'width'     => isset($options[$optId]) ? $options[$optId]->width : null,
                                'height'    => isset($options[$optId]) ? $options[$optId]->height : null,
                                'title'     => isset($options[$optId]) ? $options[$optId]->title : null,
                                'product_price' => isset($options[$optId]) ? $options[$optId]->product_price : 0,
                                'total_price' => isset($options[$optId]) ? $options[$optId]->product_price*$opt['num'] : 0,
                                'structure' =>isset($options[$optId]) ? $options[$optId]->structure : null,
                                'sku' =>isset($options[$optId]) ? $options[$optId]->goods->sku : null,
                            ];
                        }
                    }
                }

                $goods_key = $item['goods']['id'] . '_' . $item['goods_option']['id'] . '_' . $item['space_id'];
                $newList[$key]['cart_id'] = $item['id'];
                $newList[$key]['goods_id'] = $item['goods_id'];
                $newList[$key]['total'] = $item['total'];
                $newList[$key]['thumb_data'] = $this->safeUnserializeArrayV2($item['goods_option']['thumb_url']);
                $newList[$key]['is_customized'] = $item['is_customized'];
                $newList[$key]['customized_remark'] = $item['customized_remark'];
                $newList[$key]['space_id'] = $item['space_id'];
                $newList[$key]['option_id'] = $item['goods_option']['id'];
                $newList[$key]['thumb'] = yz_tomedia($item['goods_option']['thumb']);
                $newList[$key]['product_price'] =$Project->order_id ? $orderGoods[$goods_key]->goods_option_price:$item['goods_option']['product_price'];
                $newList[$key]['unit_price'] = $item['goods_option']['market_price']; //单价
                $newList[$key]['total_price'] = $Project->order_id ? $orderGoods[$goods_key]->price:$item['total'] * $item['goods_option']['product_price'];
                $newList[$key]['old_goods_id'] = $item['type'] ==2?$old_goods_id:0;
                $newList[$key]['stock'] = $item['goods']['stock'];
                $newList[$key]['title'] = $item['goods']['title'];
                $newList[$key]['option_title'] = $item['goods_option']['title'];
                $newList[$key]['status'] = $this->getGoodsStatus($item);
                $newList[$key]['sku'] = $item['goods']['sku'];
                $newList[$key]['type'] = $item['type'];
                $newList[$key]['length'] = $item['goods_option']['length'];
                $newList[$key]['width'] = $item['goods_option']['width'];
                $newList[$key]['height'] = $item['goods_option']['height'];
                $newList[$key]['option_children']= $oldOptionData;
                $newList[$key]['cad_plan_model'] = yz_tomedia($item['goods_option']['cad_plan_model']);
                $newList[$key]['product_model'] = $item['goods_option']['product_model'];
                $newList[$key]['material_color'] = $this->getMaterialColor($item);
                $newList[$key]['components'] = $item['type'] ==1?!empty($item['components'])?json_decode($item['components'],true):[]:"";
                $newList[$key]['structure'] = $item['goods_option']['structure'];
                $newList[$key]['jsonData'] = !empty($item['jsonData'])?json_decode($item['jsonData'],true):[];
                $newList[$key]['modelData'] = !empty($item['modelData'])?json_decode($item['modelData'],true):[];

            }
        }
        return $newList;
    }


    private function safeUnserializeArrayV2($value)
    {
        if (empty($value)) {
            return [];
        }

        // 尝试反序列化
        $unserialized = @unserialize($value);
        if ($unserialized === false) {
            return [];
        }

        // 如果不是数组，返回空数组
        if (!is_array($unserialized)) {
            return [];
        }


        // 处理多维数组

            if (isset($unserialized['high_url'])) {
                $unserialized['high_url'] = (!empty($unserialized['high_url']) && $unserialized['high_url'] != 'null') ? yz_tomedia($unserialized['high_url']) : "";
            }
            if (isset($unserialized['thumb'])) {
                $unserialized['thumb'] = $unserialized['thumb'] ? yz_tomedia($unserialized['thumb']) : "";
            }
            if (isset($unserialized['main_thumb'])) {
                $unserialized['main_thumb'] = $unserialized['main_thumb'] ? yz_tomedia($unserialized['main_thumb']) : "";
            }


        return $unserialized;
    }

    /**
     * 判断是否是一维数组
     */
    private function isOneDimensionalArray(array $array): bool
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                return false;
            }
        }
        return true;
    }


    //获取拼接diy的模型数据


    protected function getMaterialColor($item)
    {

         if($item['components']){
              if($item['type'] == 1){
                  $data = json_decode($item['components'],true);

                  $colorIds = array_column($data, 'color_id');
                  $componentIds = array_column($data, 'component_id');


                  $colors = SupplierColorPlane::whereIn('id', $colorIds)->pluck('name', 'id');

                  $components = GoodsOptionModel::whereIn('id', $componentIds)->pluck('name', 'id');

                  $result = [];

                  foreach ($data as $it) {
                      $color = $colors[$it['color_id']] ?? '未知颜色';
                      $component = $components[$it['component_id']] ?? '未知部件';

                      $result[] = "{$color}/{$component}";
                  }
                  $finalResult = implode('|', $result);
              }else{
                  $finalResult = $item['components'];
              }
         }else{
             $finalResult = "";
         }
         return $finalResult;
    }


    protected function curl_python($api_method, $params)
    {
        $apiUrl = "http://127.0.0.1:8000/" . $api_method;
        $response = Http::post($apiUrl, $params);

        // 检查响应状态
        if ($response->successful()) {
            return ["status" => 1, "data" => $response->json()];
        } else {
            return ["status" => 0];
        }
    }

    protected function curl_analysis_cad($api_method, $dwgFilePath)
    {
        $apiUrl = "http://127.0.0.1:8000/" . $api_method;

        // 获取文件的原始名称
        $originalFileName = basename($dwgFilePath); // 从路径中获取文件名

        // 使用 Http::attach 正确传递文件名
        $response = Http::attach(
            'dwg_file',
            file_get_contents(storage_path('app/' . $dwgFilePath)),
            $originalFileName // 设置文件名
        )->post($apiUrl);

        // 检查响应状态
        if ($response->successful()) {
            return ["status" => 1, "data" => $response->json()];
        } else {
            return ["status" => 0];
        }
    }



    protected function curl_analysis_cad_v2($api_method, $dwgFiles)
    {
        $apiUrl = "http://127.0.0.1:8000/" . $api_method;

        $request = Http::asMultipart();

        // 附加多个文件
        foreach ($dwgFiles as $dwgFilePath) {
            $originalFileName = basename($dwgFilePath); // 获取文件名
            $request->attach(
                'dwg_files', // 注意字段名是数组
                file_get_contents(storage_path('app/' . $dwgFilePath)),
                $originalFileName
            );
        }

        // 发送 POST 请求
        $response = $request->post($apiUrl);


        // 检查响应状态
        if ($response->successful()) {
            return ["status" => 1, "data" => $response->json()];
        } else {
            return ["status" => 0, "error" => $response->body()];
        }
    }




    protected function getGoodsStatus($data)
    {
        if($data['goods']['deleted_at']){
            return -1;
        }
        return $data['goods']['status'];
    }



    protected static function uploadOssThumb($input)
    {
        $uniqid = uniqid();
        // If $input is base64, decode and save as an image file
        $save_path = storage_path("app/public/tmp") . "/" . $uniqid . ".png";
        self::decodeAndSaveBase64($input, $save_path);


        $uploadedFile = new UploadedFile(
            $save_path,
            $uniqid . ".png",
            "image/png",
            null,
            false // Mark as test file to avoid further validation
        );

        $uploadService = new UploadService();

        $upload_res = $uploadService->upload($uploadedFile, "image");


        unlink($save_path);
        $image_url = $upload_res['relative_path'];
        return $image_url;
    }


    protected static function decodeAndSaveBase64($base64Str, $savePath)
    {
        // 检查是否包含 'data:image/...' 前缀
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $matches)) {
            // 如果包含，去掉前缀部分并解码

            $base64Str = substr($base64Str, strpos($base64Str, ',') + 1);
        }
        // 解码 Base64 数据
        $fileContents = base64_decode($base64Str);


        // 保存到指定路径
        File::put($savePath, $fileContents);

    }

    protected function formatNumber($number) {
        // 确保输入是数字
        if (!is_numeric($number)) {
            return $number;
        }

        // 将数字转换为浮点数
        $floatValue = (float)$number;

        // 检查小数部分是否为0
        if (fmod($floatValue, 1) == 0) {
            // 小数部分为0，返回整数形式
            return (int)$floatValue;
        } else {
            // 小数部分不为0，返回原数字
            return $number;
        }
    }

}