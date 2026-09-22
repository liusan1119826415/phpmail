<?php
/**
 * Author:
 * Date: 2017/10/24
 * Time: 下午9:48
 */

namespace app\backend\modules\member\models;


use app\common\models\BaseModel;

class MemberRecord extends BaseModel
{
    protected $table = 'yz_member_record';

    protected $guarded = [''];
    public $appends = ['status_value'];

    public static function getRecord($uid)
    {
        return self::uniacid()
            ->where('uid', $uid)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getStatusValueAttribute()
    {
        if ($this->status == 0) {
            return '修改中';
        } elseif ($this->status == 2) {
            return '修改失败';
        }
        return '修改成功';
    }

    public function scopeSearch($query, $search = [])
    {
        if ($search['mid']) {
            $query->where('uid', $search['mid']);
        }
        if ($search['bf_mid']) {
            $query->where('parent_id', $search['bf_mid']);
        }
        if ($search['af_mid']) {
            $query->where('after_parent_id', $search['af_mid']);
        }
        if (is_numeric($search['times']['end']) && is_numeric($search['times']['start'])) {
            $query->whereBetween('created_at', $search['times']);
        }

    }
}