<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class Detail extends OrderOperation
{
    public function getApi()
    {
        return '';
    }

    public function getName()
    {
        return '项目详情';
    }

    public function getValue()
    {
        return 70;
    }

    public function enable()
    {
        return true;
    }
}
