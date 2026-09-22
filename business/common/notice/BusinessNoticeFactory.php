<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/8/3
 * Time: 16:48
 */

namespace business\common\notice;


use app\common\exceptions\ApiException;
use app\common\exceptions\AppException;
use business\common\models\MessageNotice;
use business\common\models\Staff;
use business\common\services\SettingService;

class BusinessNoticeFactory
{

    protected static $loginUserStaffId;

    public static function getFormatAllType($plugin = null)
    {
        if(!$plugin){
            return [];
        }
        
        $allType = app('BusinessMsgNotice')->getProduct($plugin)->getAllType();

        $type = [];
        foreach ($allType as $k => $v) {
            $type[] = [
                'code' => $k,
                'mane' => $v,
            ];
        }

        return $type;
    }

    public static function allModule()
    {
        $allProduct = app('BusinessMsgNotice')->getAllProduct();

        $appModule = $allProduct->map(function (BusinessMessageNoticeBase $product) {
            return [
                'code' => $product->getPlugin(),
                'name' => $product->getPluginName(),
            ];
        })->all();

        return $appModule;
    }

    public static function loginUserStaffId()
    {
        if (empty(self::$loginUserStaffId)) {
            $staff = Staff::uniacid()
                ->select('id')
                ->where('business_id',SettingService::getBusinessId())
                ->where('uid',\YunShop::app()->getMemberId())
                ->first();

            self::$loginUserStaffId = $staff?$staff->id:0;

        }
        return self::$loginUserStaffId;
    }

    /**
     * @param $containerCode
     * @param $noticeType
     * @return BusinessMessageNoticeBase
     * @throws AppException
     */
    public static function createInstance($containerCode, $noticeType)
    {

        /**
         * @var BusinessMessageNoticeBase $appointProduct
         */
        $appointProduct = app('BusinessMsgNotice')->getProduct($containerCode);

        if (!$appointProduct) {
            throw new ApiException("不存在{$containerCode}这个消息通知类");
        }

//        if (self::loginUserStaffId()) {
//            throw new ApiException("员工信息不存在，无法获取发送通知人");
//        }

        /**
         * @var MessageNotice $messageNotice
         */
        $messageNotice = app('BusinessMsgNotice')->make('MessageNotice',[
            'uniacid' => \YunShop::app()->uniacid,
            'operate_id' => self::loginUserStaffId(),
            'business_id' => SettingService::getBusinessId(),
            'plugin' => $containerCode,
            'notice_type' => $noticeType,
        ]);


        $appointProduct->setMessageNotice($messageNotice);


        return $appointProduct;

    }

    /**
     * @param MessageNotice $messageNoticee
     * @return BusinessMessageNoticeBase
     */
    public static function selectInstance(MessageNotice $messageNotice)
    {
        /**
         * @var BusinessMessageNoticeBase $appointProduct
         */
        $appointProduct = app('BusinessMsgNotice')->getProduct($messageNotice->plugin);

        $appointProduct->setMessageNotice($messageNotice);

        return $appointProduct;
    }


}
