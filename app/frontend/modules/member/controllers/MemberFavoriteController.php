<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/6
 * Time: 上午9:26
 */

namespace app\frontend\modules\member\controllers;

use app\common\components\ApiController;
use app\common\components\BaseController;
use app\common\exceptions\ShopException;
use app\framework\Http\Request;
use app\frontend\modules\goods\services\GoodsService;
use app\frontend\modules\member\models\MemberFavorite;

class MemberFavoriteController extends ApiController
{
    public function index()
    {
        $memberId = \YunShop::app()->getMemberId();
        $name = request()->input('name');
        $favoriteList = MemberFavorite::getFavoriteList($memberId,$name);


        return $this->successJson('成功', $favoriteList);
    }

    public function isFavorite(Request $request, $integrated = null)
    {
        $memberId = \YunShop::app()->getMemberId();
        $goodsId = \YunShop::request()->goods_id;
        if (!$goodsId) {
            $goodsId = \YunShop::request()->id;
        }
        if ($goodsId) {
            if (MemberFavorite::getFavoriteByGoodsId($goodsId, $memberId)) {
                $data = array(
                    'status' => 1,
                    'message' => '商品已收藏'
                );
            } else {
                $data = array(
                    'status' => 0,
                    'message' => '商品未收藏'
                );
            }
            if (is_null($integrated)) {
                return $this->successJson('接口访问成功', $data);
            } else {
                return show_json(1, $data);
            }
        }

        if (is_null($integrated)) {
            return $this->errorJson('未获取到商品ID');
        } else {
            return show_json(0, '未获取到商品ID');
        }
    }

    public function store()
    {
        if (\YunShop::request()->goods_id) {
            $memberId = \YunShop::app()->getMemberId();
            if (MemberFavorite::getFavoriteByGoodsId(\YunShop::request()->goods_id, $memberId)) {
                return $this->errorJson('商品已收藏，不需要重复添加！');
            }
            $requestFaveorit = array(
                'member_id' => $memberId,
                //'member_id' => \YunShop::app()->getMemberId(),
                'goods_id' => \YunShop::request()->goods_id,
                'uniacid' => \YunShop::app()->uniacid
            );

            $favoriteModel = new MemberFavorite();

            $favoriteModel->setRawAttributes($requestFaveorit);
            $favoriteModel->uniacid = \YunShop::app()->uniacid;
            $validator = $favoriteModel->validator($favoriteModel->getAttributes());
            if ($validator->fails()) {
                return $this->errorJson($validator->messages());
            }
            if ($favoriteModel->save()) {
                return $this->successJson('添加收藏成功');
            }
            return $this->errorJson("数据写入出错，请重试！");
        }
        return $this->errorJson("未获取到商品ID");
    }


    public function destroy()
    {
        $has_all = request('has_all', false);
        $favoriteModel = MemberFavorite::uniacid()->where('member_id', \YunShop::app()->getMemberId());
        try {
            if ($has_all) {
                $favoriteModel->delete();
            } else {
                $this->validate(['goods_id' => 'required']);
                $goods_id = (array)request('goods_id', []);
                $favoriteModel->whereIn('goods_id', $goods_id)->delete();
            }
        } catch (ShopException $e) {
            return $this->errorJson($e->getMessage());
        }
        return $this->successJson("移除收藏成功");

    }
}
