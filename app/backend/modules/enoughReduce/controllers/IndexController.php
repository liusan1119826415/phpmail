<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2018/5/18
 * Time: 下午17:28
 */

namespace app\backend\modules\enoughReduce\controllers;

use app\common\components\BaseController;
use app\common\helpers\Url;
use app\common\models\MemberLevel;

class IndexController extends BaseController
{
    public function index()
    {
        $setting = \Setting::getByGroup('enoughReduce');
        if (!empty($setting) && !isset($setting['freeFreight']['amount_type'])) {
            $setting['freeFreight']['amount_type'] = 0;
        }

		if (empty($setting)) {
			$setting = [
				'open' => false,
				'enoughReduce' => [],
				'open_level_enough_reduce' => false,
				'freeFreight' => [
					'open' => false,
					'amount_type' => 0,
					'enough' => 0,
					'cities' => [],
					'city_ids' => [],
					'province_ids' => [],
					'postage_included_category_open' => 0
			    ],
			];
		}

        $level_enough_reduce = [];
        $levels = MemberLevel::uniacid()->select('id','level_name')->get()->toArray();
        foreach ($levels as $level) {
            $enough_reduce = [];
            foreach ($setting['level_enough_reduce'] as $item) {
                if ($item['level_id'] == $level['id']) {
                    $enough_reduce = $item['enough_reduce'];
                }
            }
            $level_enough_reduce[] = [
                'level_id' => $level['id'],
                'level_name' => $level['level_name'],
                'enough_reduce' => $enough_reduce
            ];
        }
        $setting['level_enough_reduce'] = $level_enough_reduce;

        return view('goods.enoughReduce.index', [
            'setting' => json_encode($setting),
        ])->render();
    }
}