<?php
/**
 * Created by PhpStorm.
 *
 * 
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\admin\controllers;

use app\common\models\Address;
use business\common\controllers\components\BaseController;
use Illuminate\Support\Collection;

class AddressController extends BaseController
{
    public function getAddressList()
    {
        $separate = DIRECTORY_SEPARATOR;
        $path = "business{$separate}asset{$separate}json{$separate}";
        if (file_exists($path . 'address.json')) {
            $list = file_get_contents($path . 'address.json');

            return $this->successJson('成功', json_decode($list, true));
        }

        $list = Address::get();
        $group = $list->groupBy('level');
        $level_1 = $group['1'];
        foreach ($level_1 as $address) {
            $address['childs'] = $this->recursion($address, $group);
        }

        $list = json_encode($level_1);
        if (!file_exists($path)) {
            mkdir($path);
        }
        file_put_contents($path . 'address.json', $list);

        return $this->successJson('成功', $level_1);
    }

    private function recursion(Address $address, Collection $collection)
    {
        if (!$group = $collection[$address->level + 1]) {
            return [];
        }
        $childs = array();
        foreach ($group as $key => $item) {
            if ($address->id == $item->parentid) {
                unset($group[$key]);
                $child = $this->recursion($item, $collection);
                if ($child) {
                    $item['childs'] = $child;
                }
                $childs[] = $item;
            }
        }

        return $childs;
    }
}
