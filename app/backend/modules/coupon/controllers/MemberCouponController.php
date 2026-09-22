<?php


namespace app\backend\modules\coupon\controllers;

use app\backend\modules\coupon\models\CouponLog;
use app\common\components\BaseController;
use app\common\models\coupon\CouponUseLog;
use app\common\models\coupon\ShoppingShareCouponLog;
use app\backend\modules\coupon\models\MemberCoupon;

class MemberCouponController extends BaseController
{
    public function index()
    {
        $member_id = request('member_id', 0);
        if (request()->type) {
            return view('coupon.member-unused', [
                'member_id' => $member_id,
            ])->render();
        }
        return view('coupon.member',[
            'member_id' => $member_id,
        ])->render();
    }

    public function getData()
    {
        $search = request()->search;
        $list = MemberCoupon::search($search)
            ->select(['yz_member_coupon.uid','used','get_time'])
            ->with(['member' => function($query){
                return $query->select(['uid','avatar', 'nickname','mobile','realname']);
            }])
            ->groupBy('yz_member_coupon.uid')
            ->orderBy('uid', 'desc')
            ->paginate(15);

        $uids = $list->pluck('uid')->all();
        if ($uids) {
            $unused_total = MemberCoupon::uniacid()
                ->selectRaw('uid,coupon_id,get_time,COUNT(*) AS unused_total')
                ->whereIn('uid',$uids)
                ->where(['used'=>0,'is_expired'=>0,'is_member_deleted'=>0])
                ->groupBy('uid')
                ->get()
                ->toArray();
            $unused_total = array_column($unused_total,null,'uid');
            $get_total = CouponLog::uniacid()
                ->selectRaw('member_id,COUNT(*) AS get_total')
                ->whereIn('member_id',$uids)
                ->where('getfrom', 0)
                ->groupBy('member_id')
                ->get()
                ->toArray();
            $get_total = array_column($get_total,null,'member_id');
            $get_from_total = CouponLog::uniacid()
                ->selectRaw('member_id,COUNT(*) AS get_from_total')
                ->whereIn('member_id',$uids)
                ->where('getfrom', 1)
                ->groupBy('member_id')
                ->get()
                ->toArray();
            $get_from_total = array_column($get_from_total,null,'member_id');
            $receive_total = ShoppingShareCouponLog::uniacid()
                ->selectRaw('receive_uid,COUNT(*) AS receive_total')
                ->whereIn('receive_uid',$uids)
                ->groupBy('receive_uid')
                ->get()
                ->toArray();
            $receive_total = array_column($receive_total,null,'receive_uid');
            $used_total = CouponUseLog::uniacid()
                ->selectRaw('member_id,COUNT(*) AS used_total')
                ->whereIn('member_id',$uids)
                ->groupBy('member_id')
                ->get()
                ->toArray();
            $used_total = array_column($used_total,null,'member_id');

            $list->map(function ($item) use ($unused_total,$get_total,$get_from_total,$receive_total,$used_total) {//这种比上面的更快，还能进一步优化

                $item['unused_total'] = $unused_total[$item->uid]['unused_total'] ? : 0;;
                $item['get_total'] = $get_total[$item->uid]['get_total'] ? : 0;;
                $item['get_from_total'] = $get_from_total[$item->uid]['get_from_total'] ? : 0;;
                $item['receive_total'] = $receive_total[$item->uid]['receive_total'] ? : 0;;
                $item['used_total'] = $used_total[$item->uid]['used_total'] ? : 0;
                return $item;
            });
        }

        $data = [
            'list' => $list,
        ];
        return $this->successJson('ok', $data);
    }

    public function deleteCoupon()
    {
        $coupon_id = (int)request()->id;
        $uid = (int)request()->uid;
        if (!$coupon_id || !$uid) {
            return $this->errorJson('请传入正确参数');
        }
        $member_coupon = MemberCoupon::uniacid()->where(['coupon_id'=>$coupon_id,'uid'=>$uid])->get();
        if ($member_coupon->isEmpty()) {
            return $this->errorJson('优惠券不存在');
        }
        MemberCoupon::uniacid()->where(['coupon_id'=>$coupon_id,'uid'=>$uid])->delete();
        foreach ($member_coupon as $item) {
            $this->createUseLog($item, $coupon_id);
        }
        return $this->successJson('删除成功');
    }

    private function createUseLog($member_coupon, $coupon_id)
    {
        CouponUseLog::create([
            'uniacid' => \YunShop::app()->uniacid,
            'member_id' => $member_coupon->uid,
            'detail' => '后台作废会员(ID为' . $member_coupon->uid . ')一张(ID为' . $coupon_id . '的优惠券)',
            'coupon_id' => $member_coupon->belongsToCommonCoupon->id,
            'member_coupon_id' => $member_coupon->id,
            'type' => CouponUseLog::TYPE_BACKEND_DEL
        ]);
    }

    public function getUnused()
    {
        $search = request()->search;
        $list = MemberCoupon::search($search)
            ->selectRaw('id,uid,used,get_time,coupon_id,COUNT(id) as unused_total')
            ->with(['belongsToCoupon' => function($query){
                return $query->select(['id','name','display_order']);
            }])
            ->where(['used'=>0,'is_expired'=>0,'is_member_deleted'=>0])
            ->groupBy('coupon_id')
            ->orderBy('coupon_id', 'desc')
            ->paginate(15);
        $data = [
            'list' => $list,
        ];
        return $this->successJson('ok', $data);
    }
}
