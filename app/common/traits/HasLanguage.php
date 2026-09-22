<?php


namespace app\common\traits;


trait HasLanguage
{

    public function bootHasLanguage()
    {
        $action = 'model.init.' . static::getModel()->getTable();
        app('eventy')->action($action, static::class);
    }

}
