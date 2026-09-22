<?php

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/14
 * Time: 下午3:16
 */
namespace app\backend\modules\order\observers;

use app\common\events\order\AfterOrderModelChangeEvent;
use app\common\observers\BaseObserver;
use Illuminate\Database\Eloquent\Model;

class OrderObserver extends BaseObserver
{

    public function created(Model $model)
    {
        event(new AfterOrderModelChangeEvent($model, 'created'));
    }

    public function saving(Model $model)
    {

    }
    public function saved(Model $model)
    {
        $this->pluginObserver('observer.order',$model,'saved', 1);
        event(new AfterOrderModelChangeEvent($model,'saved'));
    }

    public function updating(Model $model)
    {
        (new \app\common\services\operation\OrderLog($model, 'update'));
    }

    public function updated(Model $model)
    {
        event(new AfterOrderModelChangeEvent($model,'updated'));
    }
}