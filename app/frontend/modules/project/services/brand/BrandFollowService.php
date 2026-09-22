<?php

namespace app\frontend\modules\project\services\brand;

use app\common\exceptions\AppException;
use app\frontend\modules\project\models\Follow;

class BrandFollowService
{
    // 关注操作类型
    const FOLLOW_TYPE_ADD = 1;    // 关注
    const FOLLOW_TYPE_REMOVE = 2; // 取消关注

    // 错误提示
    const MSG_ALREADY_FOLLOWED = '已关注,请忽重复关注';
    const MSG_FOLLOW_FAILED = '关注失败';
    const MSG_NOT_FOLLOWED = '未关注，请关注';
    const MSG_UNFOLLOW_FAILED = '取消关注失败';

    /**
     * 关注/取消关注品牌厂家
     */
    public function follow(int $supplier_id, int $follow_type): bool
    {
        $memberId = \YunShop::app()->getMemberId();

        if ($follow_type == self::FOLLOW_TYPE_ADD) {
            if (Follow::IsFollow($memberId, $supplier_id)) {
                throw new AppException(self::MSG_ALREADY_FOLLOWED);
            }
            $create['member_id'] = $memberId;
            $create['supplier_id'] = $supplier_id;
            $res = Follow::create($create);
            if ($res) {
                return true;
            } else {
                throw new AppException(self::MSG_FOLLOW_FAILED);
            }
        } else {
            $follow = Follow::IsFollow($memberId, $supplier_id);
            if (!$follow) {
                throw new AppException(self::MSG_NOT_FOLLOWED);
            }
            if ($follow->delete()) {
                return true;
            } else {
                throw new AppException(self::MSG_UNFOLLOW_FAILED);
            }
        }
    }
}
