<?php

namespace app\common\traits;

use app\common\models\GoodsOption;
use app\common\models\kefu\ServiceUser;
use app\common\models\goods\GoodsSpecCategory;
use app\frontend\modules\project\infrastructure\BaseRepository;

trait GoodsOptionTrait
{

    protected function getBaseRepo(): BaseRepository
    {
        return app(BaseRepository::class);
    }

    // ========== BaseRepository 桥接方法（供 trait 内部 $this-> 调用） ==========

    public function getMirrorThumb($item)
    {
        return $this->getBaseRepo()->getMirrorThumb($item);
    }

    public function safeThumbData($item)
    {
        return $this->getBaseRepo()->safeThumbData($item);
    }

    public function getGoodsStatus($data)
    {
        return $this->getBaseRepo()->getGoodsStatus($data);
    }

    public function getMaterialColor($item)
    {
        return $this->getBaseRepo()->getMaterialColor($item);
    }

    /**
     * 获取标题的最后部分
     */
    protected function getLastPart($title, $delimiter = '+')
    {
        if (strpos($title, $delimiter) !== false) {
            $parts = explode($delimiter, $title);
            return end($parts);
        }
        return $title;
    }

    /**
     * 处理商品选项数据
     * 
     * @param array $item 商品数据项
     * @param mixed $Project 项目对象
     * @param array $orderGoods 订单商品数据
     * @param string $format 输出格式类型: 'cart' 或 'standard'
     * @return array 处理后的商品数据
     */
    protected function processGoodsOptionData($item, $Project, $orderGoods, $format = 'standard')
    {
        /*// 处理type=2时的旧商品ID
        $oldGoodsId = 0;
        if ($item['type'] == 2 && !empty($item['goods']['old_option'])) {
            $oldOptionData = unserialize($item['goods']['old_option']);
            if (is_array($oldOptionData) && !empty($oldOptionData[0])) {
                $oldGoodsId = is_numeric($oldOptionData[0])
                    ? $oldOptionData[0]
                    : ($oldOptionData[0]['goods_id'] ?? 0);
            }
        }

        // 处理productType=5时的旧选项数据
        $oldOptionData = [];
        if ($item['goods']['productType'] == 5 && !empty($item['goods']['old_option'])) {
            $oldOptionData = $this->processOldOptionData($item['goods']['old_option']);
        }*/


        // 处理type=2时的旧商品ID
        $oldGoodsId = 0;
        // 处理productType=5时的旧选项数据
        $oldOptionData = [];
        if (!empty($item['goods']['old_option'])) {
            $oldOptionData = unserialize($item['goods']['old_option']);
            if (is_array($oldOptionData) && !empty($oldOptionData[0])) {
                $oldGoodsId = is_numeric($oldOptionData[0])
                    ? $oldOptionData[0]
                    : ($oldOptionData[0]['goods_id'] ?? 0);
            }
            $oldOptionData = $this->processOldOptionData($item['goods']['old_option']);
        }

        // 构建商品键
        $goodsKey = $item['goods']['id'] . '_' . $item['goods_option']['id'] . '_' . $item['space_id'];

        // 根据格式返回不同的数据结构
        if ($format === 'cart') {
            return $this->formatCartData($item, $Project, $orderGoods, $goodsKey, $oldGoodsId, $oldOptionData);
        } else {
            return $this->formatStandardData($item, $Project, $orderGoods, $goodsKey, $oldGoodsId, $oldOptionData);
        }
    }


     /**
     * 获取规格标题（返回数组格式，包含尺寸和镜像标识）
     * 
     * @param array $item 商品数据数组
     * @return array 处理后的规格数据 [尺寸字符串, 镜像标识]
     */
    public function getSpecTitle($item)
    {
        // 检查是否有商品规格数据
        if (!isset($item['goods_option']) || empty($item['goods_option'])) {
            return ['', ''];
        }

        $goodsOption = $item['goods_option'];
        $specTitle = '';
        $mirrorTitle = '';

        // 构建基础尺寸信息（去掉小数点）
        $dimensions = [];

        if (isset($goodsOption['length']) && !empty($goodsOption['length'])) {
            // 去掉小数点，例如：100.5 -> 100
            $length = $this->removeDecimal($goodsOption['length']);
            $dimensions[] = $length . 'W';
        }

        if (isset($goodsOption['width']) && !empty($goodsOption['width'])) {
            // 去掉小数点
            $width = $this->removeDecimal($goodsOption['width']);
            $dimensions[] = $width . 'D';
        }

        if (isset($goodsOption['height']) && !empty($goodsOption['height'])) {
            // 去掉小数点
            $height = $this->removeDecimal($goodsOption['height']);
            $dimensions[] = $height . 'H';
        }

        // 如果有尺寸信息，拼接基础规格标题（使用不带空格的连接符）
        if (!empty($dimensions)) {
            // 使用 '×' 连接，注意是乘号字符，两边都不加空格
            $specTitle = implode('×', $dimensions);
        }

        // 检查是否需要添加镜像标识
        $isMirrored = isset($item['is_mirrored']) && $item['is_mirrored'] == 1;
        $mirrorEnable = isset($goodsOption['mirror_enable']) ? $goodsOption['mirror_enable'] : 0;
        $mirrorTitle = '';
        if ($isMirrored && $mirrorEnable > 0) {
            // 添加镜像方向标识
            switch ($mirrorEnable) {
                case 1:
                    $mirrorTitle = '（右）';
                    break;
                case 2:
                    $mirrorTitle = '（左）';
                    break;
            }
        }
        if (!$mirrorTitle) {
            switch ($mirrorEnable) {
                case 1:
                    $mirrorTitle = '（左）';
                    break;
                case 2:
                    $mirrorTitle = '（右）';
                    break;
            }
        }
        if ($mirrorTitle) {
            return [$specTitle, $mirrorTitle];
        } else {
            return [$specTitle];
        }
    }


     /**
     * 移除数字的小数部分
     * 
     * @param mixed $number 数字（可能是字符串、整数或浮点数）
     * @return string 去掉小数点的整数部分
     */
    private function removeDecimal($number)
    {
        // 如果为空，返回空字符串
        if ($number === null || $number === '' || $number === false) {
            return '0';
        }

        // 转换为字符串
        $str = (string)$number;

        // 去除首尾空格
        $str = trim($str);

        // 查找小数点位置
        $dotPos = strpos($str, '.');

        if ($dotPos !== false) {
            // 如果有小数点，取小数点之前的部分
            $str = substr($str, 0, $dotPos);
        }

        // 如果是负数，特殊处理
        if ($str === '-') {
            return '0';
        }

        // 如果为空，返回0
        if (empty($str)) {
            return '0';
        }

        return $str;
    }

    /**
     * 处理旧的选项数据
     */
    protected function processOldOptionData($oldOption)
    {
        $oldOptionData = [];
        $oldOption = unserialize($oldOption);

        if (is_array($oldOption)) {
            $optionIds = collect($oldOption)->pluck('option_id')->all();
            $options = GoodsOption::whereIn('id', $optionIds)
                ->with(['goods' => function ($q) {
                    $q->select("id", "sku");
                }])
                ->get(['id', "goods_id", 'length', 'width', 'height', 'title', 'structure', 'product_price', 'mirror_enable','spec_item_id'])
                ->keyBy('id');

            foreach ($oldOption as $opt) {
                $optId = $opt['option_id'];
                if (isset($options[$optId])) {
                    $oldOptionData[] = [
                        'option_id' => $optId,
                        'num' => $opt['num'],
                        'spec_title' => $this->getSpecTitle([
                            'goods_option' => [
                                'mirror_enable' => $options[$optId]->mirror_enable,
                                'length' => $options[$optId]->length,
                                'width' => $options[$optId]->width,
                                'height' => $options[$optId]->height
                            ],
                            'is_mirrored' => $opt['is_mirrored'],

                        ]),
                        'length' => $options[$optId]->length,
                        'width' => $options[$optId]->width,
                        'height' => $options[$optId]->height,
                        'title' => $this->getLastPart($options[$optId]->title, '+'),
                        'product_price' => $options[$optId]->product_price,
                        'total_price' => $options[$optId]->product_price * $opt['num'],
                        'structure' => $options[$optId]->structure,
                        'sku' => $options[$optId]->goods->sku ?? null,
                        'category_name' => GoodsSpecCategory::getCategoryNames($options[$optId]->spec_item_id)
                    ];
                }
            }
        }

        return $oldOptionData;
    }

    /**
     * 格式化购物车数据
     */
    protected function formatCartData($item, $Project, $orderGoods, $goodsKey, $oldGoodsId, $oldOptionData)
    {
        $data = [
            'cart_id' => $item['id'],
            'goods_id' => $item['goods_id'],
            'total' => $item['total'],
            'thumb_data' => $this->safeThumbData($item),
            'is_customized' => $item['is_customized'],
            'customized_remark' => $item['customized_remark'],
            'space_id' => $item['space_id'],
            'option_id' => $item['goods_option']['id'],
            'thumb' => $this->getMirrorThumb($item),
            'product_price' => $Project->order_id && isset($orderGoods[$goodsKey])
                ? $orderGoods[$goodsKey]->goods_option_price
                : $item['goods_option']['product_price'],
            'unit_price' => $item['goods_option']['market_price'],
            'total_price' => $Project->order_id && isset($orderGoods[$goodsKey])
                ? $orderGoods[$goodsKey]->price
                : $item['total'] * $item['goods_option']['product_price'],
            'old_goods_id' => $item['type'] == 2 ? $oldGoodsId : 0,
            'stock' => $item['goods']['stock'],
            'title' => $item['goods']['title'],
            'is_mirrored' => $item['is_mirrored'],
            'mirror_enable' => $item['goods_option']['mirror_enable'],
            'productType' => $item['goods_option']['productType'],
            'status' => $this->getGoodsStatus($item),
            'sku' => $item['goods']['sku'],
            'type' => $item['type'],
            'spec_title' => $this->getSpecTitle($item),
            'length' => $item['goods_option']['length'],
            'width' => $item['goods_option']['width'],
            'height' => $item['goods_option']['height'],
            'option_children' => $oldOptionData,
            'cad_plan_model' => $item['goods_option']['cad_plan_model'] ? yz_tomedia($item['goods_option']['cad_plan_model']) : "",
            'product_model' => $item['goods_option']['product_model'],
            'material_color' => $this->getMaterialColor($item),
            'components' => $item['type'] == 1 && !empty($item['components'])
                ? json_decode($item['components'], true)
                : [],
            'structure' => $item['goods_option']['structure'],
            'jsonData' => !empty($item['jsonData']) ? json_decode($item['jsonData'], true) : [],
            'modelData' => !empty($item['modelData']) ? json_decode($item['modelData'], true) : [],
        ];
        $goodsTitle = $item['goods']['title'] ?? '';
        $optionTitle = $item['goods_option']['title'] ?? '';
        $firstOptionPart = $optionTitle !== '' ? explode('+', $optionTitle)[0] : '';
        $oldOptionTitle = $oldOptionData[0]['title'] ?? '';

        switch ($item['goods']['productType']):
            case 2:
            case 3:
                if ($oldOptionTitle !== '') {
                    $data['option_title'] = $goodsTitle . "-" . $oldOptionTitle;
                } else {
                    $data['option_title'] = $goodsTitle . "-" . $firstOptionPart;
                }
                break;
            case 4:
                $data['option_title'] = $goodsTitle . "-" . $firstOptionPart;
                break;
            case 1:
            case 5:
                if ($oldOptionTitle !== '') {
                    $data['option_title'] = $goodsTitle;
                } else {
                    $data['option_title'] = $goodsTitle . "-" . $firstOptionPart;
                }
        endswitch;
        return $data;
    }

    /**
     * 格式化标准数据
     */
    protected function formatStandardData($item, $Project, $orderGoods, $goodsKey, $oldGoodsId, $oldOptionData)
    {
        return [
            'cart_id' => $item['id'],
            'old_goods_id' => $item['type'] == 2 ? $oldGoodsId : 0,
            'goods_id' => $item['goods']['id'],
            'option_id' => $item['goods_option']['id'],
            'title' => $item['goods']['title'],
            'total' => $item['total'],
            'is_customized' => $item['is_customized'],
            'thumb' => $this->getMirrorThumb($item),
            'product_price' => $Project->order_id && isset($orderGoods[$goodsKey])
                ? $orderGoods[$goodsKey]->goods_option_price
                : $item['goods_option']['product_price'],
            'unit_price' => $item['goods_option']['market_price'],
            'total_price' => $Project->order_id && isset($orderGoods[$goodsKey])
                ? $orderGoods[$goodsKey]->price
                : $item['total'] * $item['goods_option']['product_price'],
            'total_unit_price' => $item['total'] * $item['goods_option']['market_price'],
            'status' => $this->getGoodsStatus($item),
            'sku' => $item['goods']['sku'],
            'type' => $item['type'],
            'length' => $item['goods_option']['length'],
            'width' => $item['goods_option']['width'],
            'height' => $item['goods_option']['height'],
            'is_mirrored' => $item['is_mirrored'],
            'mirror_enable' => $item['goods_option']['mirror_enable'],
            'customized_remark' => $item['customized_remark'],
            'goods_option_title' => $item['goods']['title']."-".explode('+', $item['goods_option']['title'])[0],
            'spec_title' => $this->getSpecTitle($item),
            'productType' => $item['goods_option']['productType'],
            'product_model' => $item['goods_option']['product_model'],
            'material_color' => $this->getMaterialColor($item),
            'service_link' => ServiceUser::getDistributeService($item['goods_option']['id'], $item['goods']['id'],$item['goods_option']['mirror_enable'],$item['is_mirrored']),
            'structure' => $item['goods_option']['structure'],
            'volume' => $item['goods_option']['volume'],
            'jsonData' => !empty($item['jsonData']) ? json_decode($item['jsonData'], true) : [],
            'modelData' => !empty($item['modelData']) ? json_decode($item['modelData'], true) : [],
            'option_children' => $oldOptionData,
            'category_name' => GoodsSpecCategory::getCategoryNames($item['goods_option']['spec_item_id'])
        ];
    }

    /**
     * 批量处理商品列表
     */
    protected function processGoodsList($list, $Project, $orderGoods, $format = 'standard')
    {
        $newList = [];

        foreach ($list as $key => $item) {
            $newList[$key] = $this->processGoodsOptionData($item, $Project, $orderGoods, $format);
        }

        return $newList;
    }
}
