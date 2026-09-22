<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\models;

use app\common\models\BaseModel;
use business\common\services\BusinessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redis;

class PlatLog extends BaseModel
{
    public $table = 'yz_business_plat_log';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = [];
    protected $casts = [];


    public function getPlatLogId($uid = 0)
    {
        $res = self::getPlatLog($uid);
        return $res->final_plat_id ?: 0;
    }

    public function getPlatLog($uid = 0)
    {

        if ($uid = $uid ?: \YunShop::app()->getMemberId()) {

            $model = self::where('uid', $uid)->first();

            if ($model && $model->final_plat_id) {
                $res = BusinessService::checkBusinessRight($model->final_plat_id, $uid);
                if (!$res['identity']) {
                    $model->final_plat_id = 0;
                    $model->save();
                }
            }

            if (!$model) {
                $model = self::updateOrCreate([
                    'uid' => $uid,
                ], [
                    'uniacid' => \YunShop::app()->uniacid,
                    'uid' => $uid,
                    'final_plat_id' => 0
                ]);
            }

        } else {
            $model = (object)[];
        }

        return $model;
    }

}