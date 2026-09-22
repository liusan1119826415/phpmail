<?php

namespace app\backend\modules\survey\services;

class SurveyConfig
{
    /**
     * @var self
     */
    static $current;

    protected $items;

    /**
     *  constructor.
     */
    public function __construct()
    {
        self::$current = $this;
    }

    static public function current()
    {
        if (!isset(self::$current)) {
            return new static();
        }
        return self::$current;
    }

    protected function _getItems()
    {
        $result = [
            'plugin' => [
                ['id' => 0,"name" => '平台自营'],
                ['id' => 31,"name" => '门店-收银台'],
                ['id' => 32,"name" => '门店商品'],
                ['id' => 33,"name" => '酒店商品'],
                ['id' => 34,"name" => '费送站'],
                ['id' => 35,"name" => '商品套餐'],
                ['id' => 36,"name" => '酒店-收银台'],
                ['id' => 39,"name" => '门店余额充值'],
                ['id' => 40,"name" => '租赁'],
                ['id' => 44,"name" => '聚合供应链'],
                ['id' => 45,"name" => '商品挂单'],
                ['id' => 46,"name" => '活动报名'],
                ['id' => 49,"name" => '能量舱'],
                ['id' => 50,"name" => '第三方销售商品'],
                ['id' => 51,"name" => '第三方供应商品'],
                ['id' => 52,"name" => '供货平台供应商品'],
                ['id' => 53,"name" => '供货平台销售订单'],
                ['id' => 54,"name" => '拼团活动'],
                ['id' => 55,"name" => '第三方订单'],
                ['id' => 56,"name" => '云仓'],
                ['id' => 57,"name" => '应用市场'],
                ['id' => 58,"name" => '应用市场子平台'],
                ['id' => 59,"name" => '拼购'],
                ['id' => 60,"name" => '子供货平台'],
                ['id' => 61,"name" => '益生系统'],
                ['id' => 62,"name" => '益生系统历史订单'],
                ['id' => 63,"name" => '随叫随到服务需求与预约'],
                ['id' => 64,"name" => '随叫随到服务企业发布'],
                ['id' => 65,"name" => '随叫随到服务企业套餐'],
                ['id' => 66,"name" => '语音商品'],
                ['id' => 67,"name" => '拍卖'],
                ['id' => 68,"name" => '洗车设备'],
                ['id' => 69,"name" => '抢团'],
                ['id' => 70,"name" => '淘京拼CPS'],
                ['id' => 71,"name" => '淘京拼CPS卡券'],
                ['id' => 72,"name" => '话费慢充'],
                ['id' => 73,"name" => '店商联盟'],
                ['id' => 74,"name" => 'CPS子平台'],
                ['id' => 76,"name" => '星拼乐'],
                ['id' => 77,"name" => '抽奖'],
                ['id' => 88,"name" => '虚拟资产'],
                ['id' => 92,"name" => '供应商'],
                ['id' => 95,"name" => '提货卡'],
                ['id' => 96,"name" => '自提卡'],
                ['id' => 99,"name" => '0.1拼团'],
                ['id' => 101,"name" => '门店预约'],
                ['id' => 102,"name" => '门店预约虚拟订单'],
                ['id' => 103,"name" => '芸签电子合同'],
                ['id' => 105,"name" => '芸签聚合API'],
                ['id' => 106,"name" => '圈子'],
                ['id' => 107,"name" => '盲盒'],
                ['id' => 108,"name" => '定金阶梯团'],
                ['id' => 111,"name" => '电子签约'],
                ['id' => 113,"name" => '多门店项目核销'],
                ['id' => 115,"name" => '珍惠拼'],
                ['id' => 120,"name" => '供应链'],
                ['id' => 121,"name" => '话费充值Pro'],
                ['id' => 127,"name" => '圈仓'],
                ['id' => 128,"name" => '圈仓提货'],
                ['id' => 130,"name" => '分时预约'],
                ['id' => 131,"name" => '电费充值商品'],
                ['id' => 140,"name" => '卡卷资源'],
                ['id' => 143,"name" => '油卡充值'],
                ['id' => 144,"name" => '优惠券联盟'],
                ['id' => 147,"name" => '置换亿栈'],
                ['id' => 150,"name" => '扫码下单'],
                ['id' => 151,"name" => '御为民-超级拼团'],
                ['id' => 152,"name" => '充值积分下单'],
                ['id' => 154,"name" => '周边游'],
                ['id' => 155,"name" => '新盲盒'],
                ['id' => 156,"name" => '新盲盒运费'],
                ['id' => 157,"name" => '蛋糕叔叔'],
                ['id' => 158,"name" => '抖音cps'],
                ['id' => 159,"name" => '任务包复活'],
                ['id' => 160,"name" => '寄售商品'],
                ['id' => 161,"name" => '聚推联盟'],
                ['id' => 162,"name" => '课程供应链'],
                ['id' => 163,"name" => '企业微信视频课程'],
                ['id' => 164,"name" => '供应链租赁'],
                ['id' => 165,"name" => '付费文档'],
                ['id' => 166,"name" => '区域外奖励'],
                ['id' => 167,"name" => '余额冻结'],
                ['id' => 168,"name" => '藏品中心'],
                ['id' => 169,"name" => '兑换藏品'],
                ['id' => 170,"name" => '门店让利'],
                ['id' => 171,"name" => '单单有喜'],
                ['id' => 172,"name" => '经销代理'],
                ['id' => 173,"name" => '抖音券核销'],
                ['id' => 174,"name" => '培训课程'],
                ['id' => 175,"name" => '盛创供应链'],
                ['id' => 176,"name" => '配销券'],
                ['id' => 177,"name" => '美团团购'],
                ['id' => 178,"name" => 'YCC资产'],
                ['id' => 179,"name" => '测评'],
                ['id' => 180,"name" => '多平台订单转换'],
                ['id' => 181,"name" => '门店购物车聚合页'],
                ['id' => 182,"name" => '优惠券(新)秒杀活动'],
                ['id' => 183,"name" => '小鹅通对接'],
                ['id' => 184,"name" => '小鹅通同步订单'],
                ['id' => 185,"name" => '新拍卖'],
                ['id' => 186,"name" => '赛事报名'],
                ['id' => 187,"name" => '抖音团购'],
                ['id' => 188,"name" => '云店邀请码'],
                ['id' => 189,"name" => '地图标注'],
            ]
        ];
        return $result;
    }

    protected function getItems()
    {
        if (!isset($this->items)) {
            $this->items = $this->_getItems();
        }
        return $this->items;
    }

    public function getItem($key)
    {
        return array_get($this->getItems(), $key);
    }

    public function clear()
    {
        $this->items = null;
    }

    public function get($key = null)
    {
        if (empty($key)) {
            return $this->getItems();
        }
        return $this->getItem($key);
    }

    public function set($key, $value = null)
    {
        $items = $this->getItems();
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                array_set($items, $k, $v);
            }
        } else {
            array_set($items, $key, $value);
        }
        $this->items = $items;
    }

    public function push($key, $value)
    {
        $all = $this->getItems();
        $array = $this->getItem($key) ?: [];
        $array[] = $value;
        array_set($all, $key, $array);
        $this->items = $all;
    }

    public function unshift($key, $value)
    {
        $all = $this->getItems();
        $array = $this->getItem($key) ?: [];
        array_unshift($array, $value);
        array_set($all, $key, $array);
        $this->items = $all;
    }
}