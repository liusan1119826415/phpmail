<?php

namespace app\common\services\point;


use app\common\events\member\MemberBindMobile;
use app\common\facades\Setting;
use \app\common\models\point\BindMailAward as BindMailAwardModel;

class BindMailAward
{
    /**
     * @param MemberBindMobile $event
     */
    public function award($event)
    {
        $memberModel = $event->getMemberModel();
        if ($this->awardIsRun()) {
            $this->awardMember($memberModel->uid);
        }
    }

    private function awardMember($memberId)
    {
        if (!BindMailAwardModel::isAwarded($memberId)) {
            BindMailAwardModel::awardMember($memberId, $this->awardPoint(), $this->awardUpPoint());
        }
    }

    /**
     * 是否运行开启绑定邮箱奖励积分
     *
     * @return bool
     */
    private function awardIsRun()
    {
        return $this->awardState() && $this->awardPoint() > 0;
    }

    /**
     * 绑定邮箱奖励积分值
     *
     * @return bool
     */
    private function awardPoint()
    {
        return Setting::get('point.set.bind_mail_award_point');
    }

    /**
     * 绑定邮箱奖励上级积分值
     *
     * @return bool
     */
    private function awardUpPoint()
    {
        return Setting::get('point.set.bind_mail_award_up_point');
    }

    /**
     * 绑定邮箱奖励状态
     *
     * @return bool
     */
    private function awardState()
    {
        return !!Setting::get('point.set.bind_mail_award');
    }
}
