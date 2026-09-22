<?php
namespace business\common\models;

use app\common\models\BaseModel;
use business\common\services\SettingService;

class Material extends BaseModel
{
    public $table = 'yz_business_material';
    public $timestamps = false;
    public $guarded = [];
    public $appends = [];

    public static function builder($business_id = 0)
    {
        $business_id or $business_id = SettingService::getBusinessId();

        return static::uniacid()->where('business_id',$business_id);
    }
}