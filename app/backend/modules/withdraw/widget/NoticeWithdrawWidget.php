<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/10/8
 * Time: 18:13
 */

namespace app\backend\modules\withdraw\widget;


use app\common\models\notice\MessageTemp;

class NoticeWithdrawWidget extends BaseWithdrawSetWidget
{
    /**
     * 返回设置参数
     * @return mixed|array
     */
    public function getData()
    {
        $data = $this->defaultHandle($this->currentConfig());
//        dd(\app\common\models\notice\MessageTemp::getIsDefaultById(['income_withdraw']));
        return $data;
    }


    /**
     * 保存设置数据处理
     * @return mixed|array
     */
    public function fillAttributes()
    {
        $data = request()->input('notice', []);
        return $data;
    }

    public function defaultData()
    {
        $data = $this->currentConfig();
        $def_temp_id = MessageTemp::uniacid()->where('is_default', 1)->value('id');
        $news_options = MessageTemp::uniacid()
            ->select('id', 'title')
            ->where('is_default', 0)
            ->get()
            ->map(function ($value) {
                return ['label' => $value['title'], 'value' => (string)$value['id']];
            })
            ->prepend(['label' => '默认消息模板', 'value' => (string)$def_temp_id]);
        return [
            'common' => [
                'enabled' => [
                    'income_withdraw_pay_title' => \YunShop::notice()->getNotSend('withdraw.income_withdraw_pay_title'),
                    'income_withdraw_check_title' => \YunShop::notice()->getNotSend('withdraw.income_withdraw_check_title'),
                    'income_withdraw_title' => \YunShop::notice()->getNotSend('withdraw.income_withdraw_title'),
                    'income_withdraw_arrival_title' => \YunShop::notice()->getNotSend('withdraw.income_withdraw_arrival_title'),
                ],
                'news_options' => $news_options,
                'def' => [
                    'income_withdraw' => $this->getIsDefaulteExists($data['income_withdraw']),
                    'income_withdraw_check' => $this->getIsDefaulteExists($data['income_withdraw_check']),
                    'income_withdraw_pay' => $this->getIsDefaulteExists($data['income_withdraw_pay']),
                    'income_withdraw_arrival' => $this->getIsDefaulteExists($data['income_withdraw_arrival']),
                    'member_withdraw' => $this->getIsDefaulteExists($data['member_withdraw']),
                ],
                'url' => [
                    'open' => yzWebUrl('setting.default-notice.index'),
                    'cancel' => yzWebUrl('setting.default-notice.cancel'),
                    'search_member' => yzWebFullUrl("member.member.get-search-member-json"),
                ]
            ]
        ];
    }

    /**
     * 返回默认模板
     * @param $tempId
     * @return int
     */
    protected function getIsDefaulteExists($tempId): int
    {
        return MessageTemp::whereId($tempId)->where('is_default', 1)->exists() ? 1 : 0;
    }

    /**
     * 设置保存键
     * @return mixed|string
     */
    public function getWidgetKey()
    {
        return 'notice';
    }

    public function componentName()
    {
        return 'income_withdrawal_notice';
    }

    public function pagePath()
    {
        return $this->getPath('resources/views/withdraw/assets/js/components/');
    }
}
