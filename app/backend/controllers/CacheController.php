<?php
/****************************************************************
 * Author:  libaojia
 * Date:    2017/7/12 上午10:57
 * Email:   livsyitian@163.com
 * QQ:      995265288
 * User:
 ****************************************************************/

namespace app\backend\controllers;


use app\common\components\BaseController;

class CacheController extends BaseController
{
    protected $isPublic = true;
    public function update()
    {
        \Cache::flush();
        $response = $this->message('缓存更新成功');
        \Artisan::call('config:cache');
        \Artisan::call('view:clear');

        return $response;
    }

}
