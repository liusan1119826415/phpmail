<?php
/**
 * Created by PhpStorm.
 * User: liuyifan
 * Date: 2019/3/13
 * Time: 17:30
 */

namespace app\platform\modules\user\models;


use app\common\models\BaseModel;

class YzUserProfile extends BaseModel
{
    public $table = 'yz_users_profile';
    public $timestamps = true;
    protected $guarded = [''];

    public function atributeNames()
    {
        return [
            'mobile' => '手机号'
        ];
    }

    public function rules()
    {
       return [
           'mobile' => 'required|regex:/^1[34578]\d{9}$/|unique:yz_users_profile'
       ];
    }

    /**
     * 同步手机号
     * @param $uid
     * @param $mobile
     * @return self | mixed
     */
    public static function syncMobile($uid = 0, $mobile = 0)
    {
        if (!$uid || !$mobile) {
            return;
        }

        $data = static::updateOrCreate(
            ['uid' => $uid],
            [
                'uid' => $uid,
                'mobile' => $mobile
            ]
        );
        return $data;
    }

}
