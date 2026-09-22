<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/5/15 上午10:00
 * Email: livsyitian@163.com
 */

namespace app\backend\modules\export\models;


use app\backend\modules\export\observes\ExportRecordObserve;
use app\common\models\BaseModel;
use app\common\models\user\User;
use app\common\scopes\UniacidScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportRecord extends BaseModel
{
    use SoftDeletes;

    protected $table = 'yz_export_record';

    protected $guarded = [''];

    public $appends = ['status_name', 'export_type_name', 'user_name'];

    public $hidden =  ['user_id', 'export_type', 'hasOneUser'];

    protected $casts = [
        'request' => 'json',
        'columns' => 'json'
    ];

    private $status_list = [
        0 => '导出中',
        1 => '导出完成',
        2 => '导出失败',
    ];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope('uniacid',function (Builder $builder) {
            $builder->uniacid();
        });
        static::observe(ExportRecordObserve::class);
    }

    public function hasOneUser()
    {
        return $this->hasOne(User::class, 'uid', 'user_id')->select(['uid', 'username']);
    }

    public function getStatusNameAttribute()
    {
        return $this->status_list[$this->status];
    }

    public function getExportTypeNameAttribute()
    {
        return app('Export')->getTypes()[$this->export_type];
    }

    public function getUserNameAttribute()
    {
        return $this->hasOneUser->username;
    }

    public static function getList($search)
    {
        return (new self())->when($search['export_type'], function ($query) use ($search) {
            $query->where('export_type', $search['export_type']);
        })->when($search['user_id'], function ($query) use ($search) {
            $user_ids = User::where('username', 'like', '%' . $search['user_id'] . '%')->pluck('uid');
            $query->whereIn('user_id', $user_ids);
        })->when(($search['export_status'] || $search['export_status'] === 0), function ($query) use ($search) {
            $query->where('status', $search['export_status']);
        })->when($search['search_time'], function ($query) use ($search) {
            $query->whereBetween('created_at', $search['search_time']);
        });

    }

}
