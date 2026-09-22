<?php


namespace app\frontend\modules\project\models;

use app\common\models\goods\PptTemplate as BaseTemplate;
class PptTemplate extends BaseTemplate
{

    public static function getTemplateList()
    {
        $list = self::select("id", "name","thumb", "url")->where('status',1)->get();
        $transformedList = $list->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'thumb' => yz_tomedia($item->thumb),
                'url' => yz_tomedia($item->url),
            ];
        })->all();
        return $transformedList;

    }

}