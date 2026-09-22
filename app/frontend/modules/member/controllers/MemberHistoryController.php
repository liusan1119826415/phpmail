<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/23
 * Time: 上午10:26
 */

namespace app\frontend\modules\member\controllers;

use app\common\components\ApiController;
use app\common\components\BaseController;
use app\common\events\member\MemberGoodsHistoryEvent;
use app\common\exceptions\ShopException;
use app\framework\Http\Request;
use app\frontend\modules\member\models\MemberFavorite;
use app\frontend\modules\member\models\MemberHistory;
use app\common\helpers\PaginationHelper;


class MemberHistoryController extends ApiController
{
    public function index()
    {
        $memberId = \YunShop::app()->getMemberId();
        if (!versionCompare('1.1.143') || !miniVersionCompare('1.1.143')) {
            $history_list = MemberHistory::getoldMemberHistoryList($memberId);
            $data['history_list'] = $history_list;
        }else{
            $data = MemberHistory::getMemberHistoryList($memberId);
        }
        return $this->successJson('获取列表成功', $data);
    }

    public function store(Request $request, $integrated = null,$supplier_id)
    {
        //#24511 任务需求添加标识is_records_history存在且为false的时候不更新我的足迹
        if (isset(\YunShop::request()->is_records_history) && \YunShop::request()->is_records_history == 0) {
            if (is_null($integrated)) {
                return $this->successJson('跳过足迹更新');
            } else {
                return show_json(1, '跳过足迹更新');
            }
        }

        $memberId = \YunShop::app()->getMemberId();
        if (\YunShop::request()->id) {
            $goodsId = \YunShop::request()->id;
        } else {
            $goodsId = \YunShop::request()->goods_id;
        }

        $owner_id = intval(request()->owner_id);
        if (!$goodsId) {
            if (is_null($integrated)) {
                return $this->errorJson('未获取到商品ID，添加失败！');
            } else {
                return show_json(0, '未获取到商品ID，添加失败！');
            }
        }

        if (\YunShop::request()->mark && \YunShop::request()->mark_id) {
            event(new MemberGoodsHistoryEvent($goodsId, \YunShop::request()->mark, \YunShop::request()->mark_id));
        }
        $res = MemberHistory::getHistoryByGoodsId($memberId, $goodsId);

        $historyModel = $res ?: new MemberHistory();

        $historyModel->goods_id = $goodsId;
        $historyModel->supplier_id = $supplier_id;
        $historyModel->member_id = $memberId;
        $historyModel->uniacid = \YunShop::app()->uniacid;
        $historyModel->owner_id = $owner_id;
        $historyModel->updated_at = time();
        // 👇 加入这段判断逻辑
        if ($res) {
            $historyModel->count = intval($historyModel->count) + 1; // 自增 count
        } else {
            $historyModel->count = 1; // 首次访问，初始化 count 为 1
        }
        if ($historyModel->save()) {
            if (is_null($integrated)) {
                return $this->successJson('更新足迹成功');
            } else {
                return show_json(1, '更新足迹成功');
            }
        }
    }

    public function destroy()
    {
        //真就全部删除
        $has_all = request('has_all', false);
        $historyModel = MemberHistory::uniacid()->where('member_id', \YunShop::app()->getMemberId());
        try {
            if ($has_all) {
                $historyModel->delete();
            } else {
                $this->validate(['id' => 'required']);
                $id = (array)request('id', []);
                $historyModel->whereIn('id', $id)->delete();
            }
        } catch (ShopException $e) {
            return $this->errorJson($e->getMessage());
        }
        return $this->successJson('移除成功');
    }

}
