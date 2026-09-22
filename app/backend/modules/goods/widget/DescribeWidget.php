<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/14
 * Time: 10:13
 */

namespace app\backend\modules\goods\widget;


class DescribeWidget  extends BaseGoodsWidget
{
    public $group = 'base';

    public $widget_key = 'goods';

    public $code = 'content';

    public function pluginFileName()
    {
        return 'goods';
    }

    public function getData()
    {
        //商品属性默认值
        $data['lang_content'] = [];
        if (is_null($this->goods)) {
            return $data['content'] = '';
        }
        if ($this->goods->content) {
            $data['content'] =  changeUmImgPath($this->goods->content);
        }
        if ($this->goods->lang) {
            $data['lang_content'] = $this->goods->lang;
        }

        return $data;
    }


    public function pagePath()
    {
        return  $this->getPath('resources/views/goods/assets/js/components/');
    }

    public function getLangData()
    {
        return [
            'content' => __('goods/content'),
        ];
    }
}
