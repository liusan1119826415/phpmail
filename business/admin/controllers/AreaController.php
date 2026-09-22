<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022-05-27
 * Time: 11:17
 */

namespace business\admin\controllers;


use app\common\models\Area;
use app\common\models\Street;
use business\common\controllers\components\BaseController;
use Illuminate\Database\Eloquent\Collection;

class AreaController extends BaseController
{
    public function openStreet()
    {
        $is_street = \Setting::get("shop.trade")['is_street']?1:0;
        return $this->successJson('street', ['is_street' => $is_street]);
    }

    public function index()
    {
        if ($this->isStreet(request('parent_id'))) {
            $areas = Street::where('parentid',request('parent_id'))->get();
        } else {
            $areas = Area::where('parentid',request('parent_id'))->get();
        }
        return $this->successJson('成功', $this->formatAreas($areas));
    }

    public function init()
    {
        list($provinceId,$cityId,$districtId,$street_id) = explode(',',request('area_ids'));
        $province = Area::where('parentid',0)->get();
        $cities = Area::where('parentid',$provinceId)->get();
        $districts = Area::where('parentid',$cityId)->get();
        $streets = Street::where('parentid',$districtId)->get();
        $province = $this->formatAreas($province);
        $province->where('id',$provinceId)->first()->children = $cities;
        $cities->where('id',$cityId)->first()->children = $districts;
        $districts->where('id',$districtId)->first()->children = $streets;
        return $this->successJson('成功', $province);
    }

    protected function isStreet($areaId)
    {
        if ($areaId == 0) {
            return false;
        }
        $area = Area::find(request('parent_id'));
        if (!$area) {
            return false;
        }
        return $area->level == 3;
    }

    protected function formatAreas(Collection $areas)
    {
        if ($areas->isEmpty()) {
            return $areas;
        }
        // 不是子节点
        if (!$areas->first()->isLeaf()) {
            $areas->each(function (Area $area) {
                $area['children'] = [];
            });
        }
        return $areas;
    }
}