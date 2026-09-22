<?php
/**
 * Created by PhpStorm.
 * User: yunzhong
 * Date: 2021/1/12
 * Time: 17:55
 */

namespace app\common\modules\express;


use app\common\models\Brand;
use app\common\models\LogisticsSet;
use app\common\modules\express\expressCompany\SjtLogistics;
use app\common\modules\express\expressCompany\YqLogistics;
use app\common\modules\express\expressCompany\KdnLogistics;

class Logistics
{

    public function getTraces($comCode, $expressSn, $orderSn = '',$phoneLastFour = '', $is_encryption = 0, $member_name = '')
    {
        $set = LogisticsSet::uniacid()->first();//查询物流配置
        if (!$set){
            return json_encode(array('result'=>'error','resp'=>'请配置物流设置信息'));
        }
        $data = unserialize($set->data);
        //todo trim无法处理中文的半角圆角等空格只能用正则
        $expressSn = preg_replace("/(\s|\ \;|　|\xc2\xa0)/","",$expressSn);
        switch ($set->type){
            case 1:
                $result = new KdnLogistics($data);
                $result =  $result->getTraces($comCode, $expressSn, $orderSn,$phoneLastFour, $is_encryption, $member_name);
                 break;
            case 2:
                $result = new YqLogistics($data);
                $result =  $result->getTraces($comCode, $expressSn, $orderSn,$phoneLastFour);
                break;
            case 3:
                $result = (new SjtLogistics())->getTraces($comCode, $expressSn, $orderSn,$phoneLastFour);
                break;
        }

        return $result ?: [];
    }

}

