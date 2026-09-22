<?php
/**
 * Created by PhpStorm.
 *
 * User: king/QQ：995265288
 * Date: 2018/5/15 上午10:00
 * Email: livsyitian@163.com
 */

namespace app\backend\modules\export\models;


use app\common\models\BaseModel;
use app\common\scopes\UniacidScope;
use Illuminate\Database\Eloquent\Builder;

class ExportTemplate extends BaseModel
{
    protected $table = 'yz_export_template';

    protected $guarded = [''];

    protected $casts = [
        'columns' => 'json'
    ];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope(function (Builder $builder) {
            $builder->uniacid();
        });
    }

}
