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
use app\common\models\Member;
use business\common\services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yunshop\GroupDevelopUser\common\models\GroupMemberLog;

class StaffTicketCode extends BaseModel
{

    public $table = 'yz_business_staff_ticket_code';
    public $timestamps = true;
    protected $guarded = [];
    protected $appends = [];
    protected $casts = [];


    use SoftDeletes;

    public function scopeBusiness(Builder $query, $business_id = 0)
    {
        $business_id = $business_id ?: SettingService::getBusinessId();
        return $query->where('business_id', $business_id);
    }

    public function scopeTicketUrl(Builder $query, $search)
    {
        return $query->where('ticket_url', $search);
    }

}