<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;


use app\frontend\modules\cart\services\GroupManager;
use app\frontend\modules\coupon\models\Goods;
use app\frontend\modules\member\services\MemberCartService;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\FloorSpace;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\repositories\SpaceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use app\frontend\modules\project\services\AssemblyGoodsService;
class SpaceRepository extends BaseRepository implements SpaceRepositoryInterface
{


    /**
     * 3d项目加入空间
     * @return |array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */

    public function add3DSpace(array $data): array
    {

        $projectInfo = Project::where('id',$data['project_id'])->where('order_status',1)->first();
        if($projectInfo){
           throw new AppException("项目订单状态已经锁定");
        }
        //验证项目楼层空间
        $project = Project::where('member_id', \YunShop::app()->getMemberId())->where('id', $data['project_id'])->where('activate', 1)->first();
        if (!$project) {
            throw new ShopException('当前项目未激活或不存在！');
        }

        $floors = Floors::where('project_id', $data['project_id'])->where('id', $data['floor_id'])->first();
        if (!$floors) {
            throw new ShopException('未找到项目楼层！');
        }

        $space = FloorSpace::where('project_id', $data['project_id'])->where('floor_id', $data['floor_id'])->where('id', $data['space_id'])->first();
        if (!$space) {
            throw new ShopException('未找到项目空间！');
        }

        $service = new AssemblyGoodsService;

        //编写验证逻辑
        $is_edit = false;
        $hasGoodsModel = null;
        if($data['cart_id']){
             $res = $this->check3DSpace($data);
             $is_edit = $res['is_edit'];
             $hasGoodsModel = $res['memberCart'];
        }
        $result = $service->assembleDwg($data,$is_edit);

        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],  //项目id
            'space_id' => $data['space_id'],  //空间id
            'floor_id' => $data['floor_id'],  //楼层id
            'type'=>2,
            'goods_id' => $result['goods_id'],
            'total' => $data['total'],
            'option_id' => $result['option_id'] ?: 0,
            'components'=>$data['components'],
            'components_hash'=>md5($data['components']),
            'jsonData'=>$data['jsonData'],
            'modelData'=>$data['modelData']
        );

        $cartModel = app('OrderManager')->make('MemberCart', $data);
        $cart_id = $hasGoodsModel->id;
        //todo 商品权限最低购买数量处理
        $min_buy_limit = 0;

        if ($hasGoodsModel) {
            $num = intval($data['total']) ?: 0;
            $hasGoodsModel->total = max($hasGoodsModel->total + $num, $min_buy_limit);
            $hasGoodsModel->components = $data['components'];
            $hasGoodsModel->jsonData = $data['jsonData'];
            $hasGoodsModel->modelData = $data['modelData'];
            $hasGoodsModel->validate();

            if ($hasGoodsModel->update()) {
                $this->updateCart($data['space_id']);
                $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$data['project_id']);
                return ['cart_id' => $cart_id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),'project_statistics'=>$data];
            }
            throw new ShopException('数据更新失败，请重试！');
        }
        $cartModel->validate();

        $validator = $cartModel->validator($cartModel->getAttributes());

        if ($validator->fails()) {
            throw new ShopException("数据验证失败，加入空间失败！！！");
        } else {
            if ($cartModel->save()) {
                event(new \app\common\events\cart\AddCartEvent($cartModel));
                $this->updateCart($data['space_id']);
                $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$data['project_id']);
                return [
                    'cart_id' => $cartModel->id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics'=>$data
                ];
            } else {
                throw new ShopException("写入出错，加入空间失败！！！");
            }
        }
        throw new ShopException("接收数据出错，加入空间失败!");
    }


   private function check3DSpace($data)
   {
       $memberCart = app('OrderManager')->make('MemberCart')->where('id',$data['cart_id'])->first();

       $goods = Goods::find($memberCart->goods_id);
       if($goods->productType == 5){
            if(serialize($data['optionData']) == $goods->old_option){
                return ['is_edit'=>0,'memberCart'=>$memberCart];
            }else{
                return ['is_edit'=>1,'memberCart'=>$memberCart];
            }
       }else{
           $jsonData = json_decode($memberCart->jsonData,true);
           $jsonData1 = json_decode($data['jsonData'],true);
           $blocknames = array_column($jsonData, 'blockname');
           $blockname_two = array_column($jsonData1, 'blockname');

           if($blocknames === $blockname_two){
               //没有变更
               return ['is_edit'=>0,'memberCart'=>$memberCart];
           }else{
               return ['is_edit'=>1,'memberCart'=>$memberCart];
           }
       }

   }
    /**
     * 普通商品加入空间
     * @return |array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */

    public function addSpace(array $data): array
    {
        $projectInfo = Project::where('id',$data['project_id'])->where('order_status',1)->first();
        if($projectInfo){
            throw new AppException("项目订单状态已经锁定");
        }
        $goods = Goods::find($data['goods_id']);
        if(!$goods || $goods->status == 0){
            throw new AppException("商品已下架或者已删除");
        }
        $data['components'] = json_encode($data['components'], JSON_UNESCAPED_UNICODE);
        $data = array(
            'member_id' => \YunShop::app()->getMemberId(),
            'uniacid' => \YunShop::app()->uniacid,
            'project_id' => $data['project_id'],  //项目id
            'space_id' => $data['space_id'],  //空间id
            'floor_id' => $data['floor_id'],  //楼层id
            'goods_id' => $data['goods_id'],
            'total' => $data['total'],
            'option_id' => $data['option_id'] ?: 0,
            'components'=>$data['components'],
            'components_hash'=>md5($data['components'])
        );
        //验证项目楼层空间
        $project = Project::where('member_id', \YunShop::app()->getMemberId())->where('id', $data['project_id'])->where('activate', 1)->first();
        if (!$project) {
            throw new ShopException('当前项目未激活或不存在！');
        }

        $floors = Floors::where('project_id', $data['project_id'])->where('id', $data['floor_id'])->first();
        if (!$floors) {
            throw new ShopException('未找到项目楼层！');
        }

        $space = FloorSpace::where('project_id', $data['project_id'])->where('floor_id', $data['floor_id'])->where('id', $data['space_id'])->first();
        if (!$space) {
            throw new ShopException('未找到项目空间！');
        }

        $cartModel = app('OrderManager')->make('MemberCart', $data);
        $hasGoodsModel = app('OrderManager')->make('MemberCart')->hasGoodsToMemberCart($data);
        $cart_id = $hasGoodsModel['id'];
        //todo 商品权限最低购买数量处理
        $min_buy_limit = 0;
        if ($hasGoodsModel) {
            $num = intval($data['total']) ?: 1;
            $hasGoodsModel->total = max($hasGoodsModel->total + $num, $min_buy_limit);

            $hasGoodsModel->validate();

            if ($hasGoodsModel->update()) {
                $this->updateCart($data['space_id']);
                $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$data['project_id']);
                return ['cart_id' => $cart_id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),'project_statistics'=>$data];
            }
            throw new ShopException('数据更新失败，请重试！');
        }
        $cartModel->validate();

        $validator = $cartModel->validator($cartModel->getAttributes());
        event(new \app\common\events\cart\AddCartEvent($cartModel->getAttributes()));
        if ($validator->fails()) {
            throw new ShopException("数据验证失败，加入空间失败！！！");
        } else {
            if ($cartModel->save()) {
                event(new \app\common\events\cart\AddCartEvent($cartModel));
                $this->updateCart($data['space_id']);
                $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$data['project_id']);
                return [
                    'cart_id' => $cartModel->id,
                    'cart_num' => \app\frontend\models\MemberCart::getCartNum(\YunShop::app()->getMemberId()),
                    'project_statistics'=>$data
                ];
            } else {
                throw new ShopException("写入出错，加入空间失败！！！");
            }
        }
        throw new ShopException("接收数据出错，加入空间失败!");
    }

    public function updateSpaceNum(int $cartId, int $num): array
    {

        if (is_null($cartId)) {
            $cartId = $this->getMemberCarId();
        }

        if ($cartId && $num) {

            $cartModel = app('OrderManager')->make('MemberCart')->find($cartId);
            if ($cartModel) {
                $projectInfo = Project::where('id',$cartModel->project_id)->where('order_status',1)->first();
                if($projectInfo){
                    throw new AppException("项目订单状态已经锁定");
                }

                $goods = Goods::find($cartModel->goods_id);
                if(!$goods || $goods->status == 0){
                    throw new AppException("商品已下架或者已删除");
                }
                //todo 商品权限最低购买数量处理
                $min_buy_limit = 0;
                $goodsPrivilege = $cartModel->goods->hasOnePrivilege;
                //商品有购物权限并且设置了起购数量
                if (isset($goodsPrivilege) && $goodsPrivilege->min_buy_limit) {
                    //有设置按规格控制购买权限
                    if ($cartModel->isOption() && $goodsPrivilege->option_id_array) {
                        //并且该规格再限制里面
                        if (in_array($cartModel->option_id, $goodsPrivilege->option_id_array)) {
                            $min_buy_limit = $goodsPrivilege->min_buy_limit;
                        }
                    } else {
                        $min_buy_limit = $goodsPrivilege->min_buy_limit;
                    }
                }

                $cartModel->total = $num;

                if ($cartModel->total < 1 || $cartModel->total < $min_buy_limit) {
                    $result = MemberCartService::clearCartByIds([$cartModel->id]);
                    if ($result) {
                        $this->updateCart($cartModel->space_id);
                        $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$cartModel->project_id);
                        return ['project_statistics'=>$data];
                    }
                }
                $cartModel->validate();
                if ($cartModel->update()) {
                    $this->updateCart($cartModel->space_id);
                    $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$cartModel->project_id);
                    return ['project_statistics'=>$data];
                }
            }
        }

        throw new ShopException('未获取到数据，请重试！');
    }

    public function getProductList(int $space_id): array
    {
        $member_id =  \YunShop::app()->getMemberId();

        $cartList = app('CartContainer')->make('MemberCart')->carts()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $member_id)
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.space_id', $space_id)
            ->with(["hasManyAddress"=>function($query) use ($member_id){
                return $query->where("uid",$member_id)->where("isdefault",1);
            }])
            ->with(["hasManyMemberAddress"=> function($query) use ($member_id){
                return $query->where("uid",$member_id)->where("isdefault",1);
            }])

            ->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get();


        $manager = new GroupManager();
        $manager->init($cartList);
        $cartLists = $manager->cartList();
        return  $cartLists;
    }
    //获取空间商品V2版本
    public function getProductListV2(int $space_id):array
    {
        $member_id =  \YunShop::app()->getMemberId();
        $cacheKey = "member_cart_list_{$member_id}_{$space_id}";
        $data = Cache::get($cacheKey);
        if($data){
            return $data;
        }

        $data = $this->processProduct($member_id,$space_id);
        Cache::put($cacheKey, $data, 24*60+rand(10, 99));
        return $data;

    }






    private function getMemberCarId()
    {
        $cartId = null;
        $memberId = \YunShop::app()->getMemberId();
        $goods_id = request()->input('goods_id');

        if (!is_null($memberId) && !is_null($goods_id)) {
            $cartList = app('OrderManager')->make('MemberCart')->carts()->where('member_id', $memberId)
                ->orderBy('created_at', 'desc')
                ->get();

            if (!$cartList->isEmpty()) {
                collect($cartList)->map(function ($item, $key) use ($goods_id, &$cartId) {

                    if ($item->goods_id == $goods_id) {
                        $cartId = $item->id;
                    }
                });
            }
        }

        return $cartId;
    }


    public function destroy(array $ids,int $space_id): array
    {
        $space = FloorSpace::find($space_id);
        if (empty($ids)) {
            $ids = $this->getMemberCarId();
        }
        $result = MemberCartService::clearCartByIds($ids);
        $this->updateCart($space_id);
        $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$space->project_id);
        if ($result) {
            return ['project_statistics'=>$data];
        }
        throw new AppException('写入出错，移除空间失败！');
    }

    public function del_space(int $id): array
    {
        try {
            $space = FloorSpace::find($id);
            if(!$space){
                throw new AppException('找不到空间');
            }
            $space->delete();
            app('OrderManager')->make('MemberCart')->where('space_id',$space->id)->delete();
            $member_id = \YunShop::app()->getMemberId();
            $cacheKey = "member_cart_list_{$member_id}_{$id}";
            Cache::forget($cacheKey);  // 删除旧缓存
            $data = $this->updateActivateCache(\YunShop::app()->getMemberId(),$space->project_id);
            return ['project_statistics'=>$data];

        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }

    }


    public function createSpace(int $project_id, int $floor_id, string $space_name): int
    {
        $project = Project::getProject($project_id);
        if (!$project) {
            throw new ShopException("项目不存在");
        }

        $floor = Floors::getFloor($floor_id, $project_id);
        if (!$floor) {
            throw new ShopException("楼层不存在");
        }
        if (!$space_name) {
            throw new ShopException("请输入空间名称");
        }

        $FloorSpace = new FloorSpace();
        $space_data['project_id'] = $project->id;
        $space_data['floor_id'] = $floor->id;
        $space_data['name'] = $space_name;
        $FloorSpace->fill($space_data);
        $validator = $FloorSpace->validator();
        if ($validator->fails()) {
            throw new ShopException($validator->messages());
        }
        if ($FloorSpace->save()) {
            $this->updateActivateCache(\YunShop::app()->getMemberId(),$project->id);
            return $FloorSpace->id;
        } else {
            throw new ShopException("创建空间失败");
        }

    }

    public function editSpace(int $space_id, string $space_name): bool
    {
        try{

            $space_model = FloorSpace::where('id',$space_id)->first();
            $space_model->name = $space_name;
            $space_model->save();
            $this->updateActivateCache(\YunShop::app()->getMemberId(),$space_model->project_id);

            return true;
        }catch (\Exception $e){
            throw new ShopException("修改空间失败");
        }

    }

    //是否定制
    public function customized():bool
    {
        try {
            $memberId = \YunShop::app()->getMemberId();
            $id = request()->input('id');
            $customized_remark = request()->input('customized_remark');
            $is_customized = request()->input('is_customized');
            $member_cart = app('OrderManager')->make('MemberCart')->where('id',$id)->first();
            if(!$member_cart){
                throw new AppException("空间数据不存在");
            }
            $member_cart->is_customized = $is_customized;
            $member_cart->customized_remark = $customized_remark;
            $member_cart->save();
            $cacheKey = "member_cart_list_{$memberId}_{$member_cart->space_id}";
            Cache::forget($cacheKey);
            return true;

        }catch (\Exception $e){
            throw new AppException("定制失败");
        }
    }




}