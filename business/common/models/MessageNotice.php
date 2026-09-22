<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/8/3
 * Time: 16:59
 */

namespace business\common\models;


use app\common\models\Member;
use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use business\common\notice\BusinessNoticeFactory;
use business\common\services\SettingService;

/**
 * Class MessageNotice
 * @method static self businessId($business_id = null)
 * @package business\common\models
 */
class MessageNotice extends BaseModel
{
    use SoftDeletes;

    public $table = 'yz_business_message_notice';

    protected $guarded = [];

    protected $hidden = ['param','html','deleted_at','updated_at'];
    protected $appends = ['message_body', 'send_staff'];

    protected $casts = [
        'param' => 'json',
    ];



    public static function getList($search = [])
    {
        $model = self::uniacid();

        $model->businessId();

        if (isset($search['status']) && is_numeric($search['status'])) {
            $model->where('status', $search['status']);
        }

        if (isset($search['handle_status']) && is_numeric($search['handle_status'])) {
            $model->where('handle_status', $search['handle_status']);
        }


        if (isset($search['plugin']) && $search['plugin'] != '') {
            $model->where('plugin', $search['plugin']);
        }

        if (isset($search['notice_type']) && $search['notice_type'] != '') {
            $model->where('notice_type', $search['notice_type']);
        }


        if (isset($search['recipient_id'])) {
            $model->where('recipient_id', $search['recipient_id']);
        }

        if (isset($search['operate_id'])) {
            $model->where('operate_id', $search['operate_id']);
        }


        if ($search['end_time'] && $search['start_time']) {
            $range = [strtotime($search['start_time']), strtotime($search['end_time'])];
        } else {
            //默认只查询一年以内的通知
            $range = [strtotime("-1 year") , time() + 10];
        }
        $model->whereBetween('created_at', $range);


//        $model->with(['hasOneRecipient']);


        $model->orderBy('id', 'desc');

        return $model;
    }


    public static function getByPlugin($plugin)
    {
        return self::uniacid()->businessId()->where('plugin', $plugin)->get();
    }


    public function getSendStaff()
    {
       return $this->getProductModule()->sendStaff();
    }

    public function getSendStaffAttribute()
    {
        return $this->getSendStaff();
    }

    public function getMessageBodyAttribute()
    {
        return $this->getProductModule()->showBody();
    }


    protected $pluginProductModule;

    public function getProductModule()
    {
        if (!isset($this->pluginProductModule)) {
            $this->pluginProductModule = BusinessNoticeFactory::selectInstance($this);
        }

        return $this->pluginProductModule;
    }


    /**
     * @param Builder $query
     * @param null $business_id
     * @return Builder
     */
    public function scopeBusinessId(Builder $query, $business_id = null)
    {
        is_null($business_id) && $business_id =  SettingService::getBusinessId();
        $query = $query->where('business_id',$business_id);
        return $query;
    }


    //消息发送人
    public function hasOneCreator()
    {
        return $this->hasOne(Staff::class,'id','operate_id');
    }

    //消息通知人
    public function hasOneRecipient()
    {
        return $this->hasOne(Staff::class, 'id', 'recipient_id');
    }

    public function hasOneBusiness()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }
}