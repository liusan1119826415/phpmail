<?php
/**
 * Author:
 * Date: 2017/10/24
 * Time: 下午9:48
 */

namespace app\backend\modules\member\models;


use app\common\models\BaseModel;

class MemberPluginsHomePage extends BaseModel
{
    protected $table = 'yz_member_plugins_min_app_home_page';

    protected $guarded = [''];
    public $appends = [''];

    public static function getPageModel($member_id, $plugin_name)
    {
        $model = self::uniacid()->where('member_id', $member_id)->where('plugin_name', $plugin_name)->first();
        if (!$model) {
            $model = new self();
        }
        return $model;
    }

}