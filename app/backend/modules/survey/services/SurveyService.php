<?php

namespace app\backend\modules\survey\services;

use app\backend\modules\dispatch\models\DispatchType;
use app\backend\modules\finance\models\BalanceRechargeRecords;
use app\backend\modules\member\models\MemberUnique;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\Category;
use app\common\models\Comment;
use app\common\models\CouponLog;
use app\common\models\finance\Balance;
use app\common\models\finance\BalanceRecharge;
use app\common\models\finance\PointLog;
use app\common\models\Goods;
use app\common\models\GoodsCategory;
use app\common\models\Income;
use app\common\models\McMappingFans;
use app\common\models\Member;
use app\common\models\MemberCart;
use app\common\models\MemberCoupon;
use app\common\models\MemberMiniAppModel;
use app\common\models\MemberShopInfo;
use app\common\models\MemberWechatModel;
use app\common\models\Order;
use app\common\models\order\OrderCoupon;
use app\common\models\OrderGoods;
use app\common\models\Withdraw;
use app\common\payment\PaymentManager;
use app\common\services\finance\PointService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yunshop\StoreCashier\common\models\Store;

class SurveyService
{
    /**
     * 总驾驶舱
     * @return array
     */
    public function mainCockpit(): array
    {
        //今日营业额
        $todayTurnover = $this->turnoverModel()
            ->whereBetween('created_at',$this->getDay(time()))
            ->sum('price');

        //总营业额
        $totalTurnover = $this->turnoverModel()->sum('price');

        $total_turnover = [
            'name' => "营业额（元）",
            'value' => $totalTurnover,
        ];
        if ($totalTurnover > 1000000) {
            $total_turnover = [
                'name' => "营业额（万元）",
                'value' => bcdiv($totalTurnover,10000,2),
            ];
        }

        return [
            'today_turnover' => $todayTurnover,
            'total_turnover' => $total_turnover,
            'goods_analysis' => $this->goodsAnalysis(),
            'turnover_to_area' => $this->turnoverToArea(),
            'store_to_area'    => $this->storeToArea(),
        ];
    }

    /**
     * 订单视角
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function orderView(): array
    {
        $orderStatistic = Order::uniacid()
            ->select(DB::raw("SUM(IF(status=0,1,0)) as wait_pay,SUM(IF(status=1,1,0)) as wait_send,COUNT(DISTINCT(uid)) as buyer_count"))
            ->first();

        $totalOrderStatistic = $this->turnoverModel()
            ->select(DB::raw("COUNT(id) as order_count,SUM(price) as turnover"))
            ->whereBetween('pay_time',[1,time()])
            ->first();

        $todayOrderCount = $this->turnoverModel()
            ->whereBetween('created_at',$this->getDay(time()))
            ->whereBetween('pay_time',[1,time()])
            ->count();

        return [
            'total_turnover' => $totalOrderStatistic->turnover ? : 0,     //总营业额
            'today_order_count' => $todayOrderCount ? : 0,                //今日订单总数
            'order_count'    => $totalOrderStatistic->order_count ? : 0,  //订单总数
            'wait_pay'       => $orderStatistic->wait_pay ? : 0,          //待付款
            'wait_send'      => $orderStatistic->wait_send ? : 0,         //待发货
            'buyer_count'    => $orderStatistic->buyer_count ? : 0,       //下单人数
            'order_count_to_area' => $this->orderCountToArea(),
            'buyer_level_proportion' => $this->buyerLevelProportion(),
            'order_plugin_proportion' => $this->orderPluginProportion(),
            'pay_type_analysis'   => $this->payTypeAnalysis(),
        ];
    }

    /**
     * 会员视角
     * @return array
     * @throws AppException
     */
    public function memberView(): array
    {
        $newMemberTime = $this->timeSlot(time(),'-30');//新会员的判断时间

        //交易额统计
        $totalTrade = $this->turnoverModel()
            ->whereBetween('pay_time',[1,time()])
            ->sum('price');
        $newMemberTrade = $this->turnoverModel()
            ->whereIn('uid',function ($where) use ($newMemberTime) {
                $where->select('uid')
                    ->from('mc_members')
                    ->where('uniacid',\YunShop::app()->uniacid)
                    ->whereBetween('created_at',$newMemberTime);
            })
            ->whereBetween('pay_time',[1,time()])
            ->sum('price');
        $oldMemberTrade = bcsub($totalTrade,$newMemberTrade,2);

        //充值金额统计
        $totalRecharge = BalanceRecharge::uniacid()->sum('money');
        $newMemberRecharge = BalanceRecharge::uniacid()
            ->whereIn('member_id',function ($where) use ($newMemberTime) {
                $where->select('uid')
                    ->from('mc_members')
                    ->where('uniacid',\YunShop::app()->uniacid)
                    ->whereBetween('created_at',$newMemberTime);
            })->sum('money');
        $oldMemberRecharge = bcsub($totalRecharge,$newMemberRecharge,2);

        //交易订单量统计
        $totalOrder = $this->turnoverModel()->whereBetween('pay_time',[1,time()])->count();
        $newMemberOrder = $this->turnoverModel()
            ->whereIn('uid',function ($where) use ($newMemberTime) {
                $where->select('uid')
                    ->from('mc_members')
                    ->where('uniacid',\YunShop::app()->uniacid)
                    ->whereBetween('created_at',$newMemberTime);
            })->count();
        $oldMemberOrder = bcsub($totalOrder,$newMemberOrder);

        $data = MemberShopInfo::uniacid()
            ->select(DB::raw('COUNT(member_id) as member_count,level_id'))
            ->with(['level:id,level_name'])
            ->groupBy('level_id')
            ->orderBy('member_count','desc')
            ->get()->toArray();
        $shop_set = Setting::get('shop.member');
        $memberLevel = [];
        $memberLevelOther = [];
        $color = ['#BEE8CC','#78DCBA','#2CC08D','#4D8BFC','#86B0FE','#BBD3FF','#825EE8','#DDDFE2'];
        foreach ($data as $k=>$item) {
            if ($k > 7) {//显示前八个
                $other['level_name'] = "其他";
                $other['member_count'] = bcadd(($other['member_count']?:0),$item['member_count']);
                $other['color'] = $color[7];
            } else {
                $level_name = $item['level'] ? $item['level']['level_name'] : '未知等级(已删除)';
                if ($item['level_id'] == 0) {
                    $level_name = $shop_set['level_name'] ?: '普通会员';
                }
                $memberLevel[] = [
                    'level_name' => $level_name,
                    'member_count' => $item['member_count'],
                    'color' => $color[$k]
                ];
            }
        }
        if ($memberLevelOther) {
            $memberLevel[] = $memberLevelOther;
        }

        $genderStatistic = Member::uniacid()
            ->select(DB::raw('COUNT(uid) as member_count,gender'))
            ->groupBy('gender')
            ->get();
        $male = $genderStatistic->where('gender',1)->first();
        $woman = $genderStatistic->where('gender',2)->first();
        $other = $genderStatistic->where('gender',0)->first();
        $gender = [
            [
                'name' => '男性',
                'value' => $male ? $male->member_count : 0,
                'color' => '#2CC08D',
            ],
            [
                'name' => '女性',
                'value' => $woman ? $woman->member_count : 0,
                'color' => '#4D8BFC',
            ],
            [
                'name' => '未知',
                'value' => $other ? $other->member_count : 0,
                'color' => '#DDDFE2',
            ]
        ];

        $member_structure = [];
        foreach ($this->memberType() as $key => $value) {
            switch ($key) {
                case 1://微信公众号会员
                    $count = McMappingFans::uniacid()->count();
                    break;
                case 2://微信小程序会员
                    $count = MemberMiniAppModel::uniacid()->count();
                    break;
                case 3://APP会员
                    $count = MemberWechatModel::uniacid()->count();
                    break;
                case 4://微信开放平台会员
                    $count = MemberUnique::uniacid()->count();
                    break;
                case 5://手机绑定会员
                    $count = Member::uniacid()->where('mobile','>',0)->count();
                    break;
                default :
                    $count = 0;
            }
            $member_structure[] = ['member_type' => $key,'name' => $value,'value' => $count?:0];
        }

        return [
            'total_trade' => $totalTrade ? : 0,
            'new_member_trade' => $newMemberTrade ? : 0,
            'old_member_trade' => $oldMemberTrade ? : 0,
            'total_order' => $totalOrder ? : 0,
            'new_member_order' => $newMemberOrder ? : 0,
            'old_member_order' => $oldMemberOrder ? : 0,
            'total_recharge' => $totalRecharge ? : 0,
            'new_member_recharge' => $newMemberRecharge ? : 0,
            'old_member_recharge' => $oldMemberRecharge ? : 0,
            'member_level' => $memberLevel,
            'gender' => $gender,
            'member_structure' => $member_structure,
            'age_turnover' => $this->ageTurnover(),
            'turnover_to_area' => $this->turnoverToArea(),
        ];
    }

    /**
     * 商品视角
     * @return array
     */
    public function goodsView()
    {
        $goodsStatistic = Goods::uniacid()
            ->select(DB::raw('SUM(IF(status=1,1,0)) AS goods_grounding,COUNT(id) AS goods_count'))
            ->first();
        $goods_count = $goodsStatistic->goods_count ? : 0;         //商品总数
        $goods_grounding = $goodsStatistic->goods_grounding ? : 0; //上架商品
        $goods_off_grounding = bcsub($goods_count,$goods_grounding);       //下架商品

        $categoryStatistic = GoodsCategory::select(DB::raw('category_id,COUNT(goods_id) AS goods_count'))
            ->whereIn('goods_id',function ($where) {
                $where->select('id')
                    ->from('yz_goods')
                    ->where('uniacid',\YunShop::app()->uniacid)
                    ->whereNull('deleted_at');
            })
            ->groupBy('category_id')
            ->orderBy('goods_count','desc')
            ->get();
        $goodsCategory = [];
        $other = [];
        $color = ['#BEE8CC','#78DCBA','#2CC08D','#4D8BFC,','#86B0FE','#BBD3FF','#825EE8','#B59CFC','#D7CAF9','#FF8E5B','#DDDFE2'];
        foreach ($categoryStatistic as $k=>$v) {
            if ($k > 9) {
                $other['category_name'] = '其他';
                $other['goods_count'] = bcadd(($other['goods_count']?:0),$v->goods_count);
                $other['color'] = $color[10];
            } else {
                $category = Category::uniacid()->select('name')->find($v->category_id);
                $goodsCategory[] = [
                    'category_name' => $category ? $category->name : '未知分类(已删除)',
                    'goods_count' => $v->goods_count,
                    'color' => $color[$k],
                ];
            }
        }
        if ($other) {
            $goodsCategory[] = $other;
        }

        $commentData = [
            1 => [
                'level' => "1星",
                'comment_count' => 0
            ],
            2 => [
                'level' => "2星",
                'comment_count' => 0
            ],
            3 => [
                'level' => "3星",
                'comment_count' => 0
            ],
            4 => [
                'level' => "4星",
                'comment_count' => 0
            ],
            5 => [
                'level' => "5星",
                'comment_count' => 0
            ]
        ];
        $comment = Comment::uniacid()
            ->select(DB::raw('level,COUNT(*) AS comment_count'))
            ->where('reply_id',0)
            ->groupBy('level')
            ->get()->toArray();
        foreach ($comment as $v) {
            if (isset($commentData[$v['level']])) {
                $commentData[$v['level']]['comment_count'] = $v['comment_count'];
            }
        }
        $commentData = array_values($commentData);
        return [
            'goods_count' => $goods_count,
            'goods_grounding' => $goods_grounding,
            'goods_off_grounding' => $goods_off_grounding,
            'goods_category' => $goodsCategory,
            'comment' => $commentData,
        ];
    }

    /**
     * 财务视角
     * @return array
     */
    public function financeView()
    {
        $incomeStatistic = Income::uniacid()
            ->select(DB::raw("SUM(amount) as income_total,SUM(IF(status=1,amount,0)) as withdraw_income,
                SUM(IF(pay_status=2,amount,0)) as pay_income,SUM(IF(pay_status=3,amount,0)) as reject_income,
                SUM(IF(pay_status=-1,amount,0)) as invalid_income,SUM(IF(status=1 and pay_status=0,amount,0)) as no_check_income"))
            ->first();

        $incomeTotal = $incomeStatistic->income_total ? : 0;        //累计收入
        $withdrawIncome = $incomeStatistic->withdraw_income ? : 0;  //已提现收入
        $unWithdrawIncome = bcsub($incomeTotal,$withdrawIncome,2);    //未提现收入
        $noCheckIncome = $incomeStatistic->no_check_income ? : 0;   //未审核提现收入
        $payIncome = $incomeStatistic->pay_income ? : 0;            //已打款收入
        $unPayIncome = bcsub($withdrawIncome,$payIncome,2);    //已提现未打款
        $invalidIncome = $incomeStatistic->invalid_income ? : 0;    //无效提现收入
        $rejectIncome = $incomeStatistic->reject_income ? : 0;      //已驳回收入

        $withdrawStatistic = Withdraw::uniacid()
            ->select(DB::raw("SUM(IF(status=0, servicetax, actual_servicetax)) as servicetax_total,
                SUM(IF(status=0, poundage, actual_poundage)) as poundage_total"))
            ->first();

        return [
            'income_total' => $incomeTotal,
            'withdraw_income' => $withdrawIncome,
            'un_withdraw_income' => $unWithdrawIncome,
            'no_check_income' => $noCheckIncome,
            'pay_income' => $payIncome,
            'un_pay_income' => $unPayIncome,
            'reject_income' => $rejectIncome,
            'invalid_income' => $invalidIncome,
            'servicetax_total' => $withdrawStatistic->servicetax_total ?: 0,
            'poundage_total' => $withdrawStatistic->poundage_total ?: 0,
        ];
    }

    /**
     * 营业额数据分析
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function turnoverAnalysis(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);
        $order = $this->turnoverModel()
            ->select('price','created_at')
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $total = round($order->whereBetween('created_at',[$search['start'],$search['end']])->sum('price'),2);
        $x_axis = [];
        $series = [];
        while ($start <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($start);
                    $x = date('m/d',$start);
                    $start = Carbon::createFromTimeStamp($start)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($start);
                    $x = date('Y/W',$start);
                    $start = Carbon::createFromTimeStamp($start)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($start);
                    $x = date('Y/m',$start);
                    $start = Carbon::createFromTimeStamp($start)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $series[] = round($order->whereBetween('created_at',$timeArr)->sum('price'),2) ? : 0;
        }

        return [
            'total' => $total,
            'chart_data' => [
                'x_axis' => $x_axis,
                'series' => $series,
            ],
        ];
    }

    /**
     * 订单分析
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function orderAnalysis(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $order = Order::uniacid()
            ->select('id','uid','status','created_at','refund_id')
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $total = $order->count();
        $waitPayTotal = $order->where('status',0)->whereBetween('created_at',[$search['start'],$search['end']])->count();
        $waitSendTotal = $order->where('status',1)->whereBetween('created_at',[$search['start'],$search['end']])->count();
        $sendTotal = $order->whereBetween('created_at',[$search['start'],$search['end']])->where('status','>=',2)->count();
        $completeTotal = $order->whereBetween('created_at',[$search['start'],$search['end']])->where('status','>=',3)->count();
        $orderPeople = $order->whereBetween('created_at',[$search['start'],$search['end']])->groupBy('uid')->count();
        $refundOrder = $order->whereBetween('created_at',[$search['start'],$search['end']])->where('refund_id','>',0)->count();

        $x_axis = [];
        $waitPaySeries = [];
        $waitSendSeries = [];
        $sendSeries = [];
        $completeSeries = [];
        $orderCountSeries = [];
        $orderPeopleSeries = [];
        $refundOrderSeries = [];

        while ($start <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($start);
                    $x = date('m/d',$start);
                    $start = Carbon::createFromTimeStamp($start)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($start);
                    $x = date('Y/W',$start);
                    $start = Carbon::createFromTimeStamp($start)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($start);
                    $x = date('Y/m',$start);
                    $start = Carbon::createFromTimeStamp($start)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $orderCountSeries[] = $order->whereBetween('created_at',$timeArr)->count() ? : 0;
            $orderPeopleSeries[] = $order->whereBetween('created_at',$timeArr)->groupBy('uid')->count() ? : 0;
            $waitPaySeries[] = $order->where('status',0)->whereBetween('created_at',$timeArr)->count() ? : 0;
            $waitSendSeries[] = $order->where('status',1)->whereBetween('created_at',$timeArr)->count() ? : 0;
            $sendSeries[] = $order->where('status','>=',2)->whereBetween('created_at',$timeArr)->count() ? : 0;
            $completeSeries[] = $order->where('status','>=',3)->whereBetween('created_at',$timeArr)->count() ? : 0;
            $refundOrderSeries[] = $order->where('refund_id','>',0)->whereBetween('created_at',$timeArr)->count() ? : 0;
        }

        return [
            'total' => $total,                   //订单总数
            'wait_pay_total' => $waitPayTotal,   //待付款
            'wait_send_total' => $waitSendTotal, //待发货
            'send_total' => $sendTotal,          //已发货
            'complete_total' => $completeTotal,  //已完成
            'order_people' => $orderPeople,      //下单人数
            'refund_order' => $refundOrder,      //售后订单
            'chart_data' => [
                "order_count" => [
                    'name' => "订单件数",
                    'x_axis' => $x_axis,
                    'series' => $orderCountSeries,
                ],
                "order_people" => [
                    'name' => "下单人数",
                    'x_axis' => $x_axis,
                    'series' => $orderPeopleSeries,
                ],
                "order_wait_pay" => [
                    'name' => "待付款",
                    'x_axis' => $x_axis,
                    'series' => $waitPaySeries,
                    'color'  => '#FF8E5B',
                ],
                "order_wait_send" => [
                    'name' => "待发货",
                    'x_axis' => $x_axis,
                    'series' => $waitSendSeries,
                    'color'  => '#4D8BFC',
                ],
                "order_send" => [
                    'name' => "已发货",
                    'x_axis' => $x_axis,
                    'series' => $sendSeries,
                ],
                "order_complete" => [
                    'name' => "已完成",
                    'x_axis' => $x_axis,
                    'series' => $completeSeries,
                    'color'  => '#2CC08D',
                ],
                'refund_order' => [
                    'name' => "售后",
                    'x_axis' => $x_axis,
                    'series' => $refundOrderSeries,
                    'color'  => '#825EE8',
                ],
            ],
        ];
    }

    /**
     * 会员访问分析
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function memberAccessAnalysis(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        //会员总数
        $memberCount = Member::uniacid()->whereBetween('createtime',[$search['start'],$search['end']])->count();
        $browseCount = 0;//浏览量
        $visitorCount = 0;//访客量
        if (app('plugins')->isEnabled('browse-footprint')) {
            $browseData = \Yunshop\BrowseFootprint\models\BrowseFootprintModel::uniacid()
                ->select(DB::raw('day,COUNT(id) as count,COUNT(DISTINCT(cookie)) as cookie_count'));

            if ($search['port_type']) {
                $browseData->where('port_type',$search['port_type']);
            }

            $browseData = $browseData->whereBetween('day',[date('Ymd',$start),date('Ymd',$end)])
                ->groupBy('day')
                ->get();

            $browseCount = $browseData->whereBetween('day',[date('Ymd',$search['start']),date('Ymd',$search['end'])])->sum("count");
            $visitorCount = $browseData->whereBetween('day',[date('Ymd',$search['start']),date('Ymd',$search['end'])])->sum("cookie_count");
        } else {
            $browseData = collect([]);
        }

        $x_axis = [];
        $browseSeries = [];//浏览量Y轴
        $visitorSeries = [];//访客量Y轴
        while ($start <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($start);
                    $x = date('m/d',$start);
                    $start = Carbon::createFromTimeStamp($start)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($start);
                    $x = date('Y/W',$start);
                    $start = Carbon::createFromTimeStamp($start)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($start);
                    $x = date('Y/m',$start);
                    $start = Carbon::createFromTimeStamp($start)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $browseSeries[] = $browseData->whereBetween('day',[date('Ymd',$timeArr[0]),date('Ymd',$timeArr[1])])->sum("count") ? : 0;
            $visitorSeries[] = $browseData->whereBetween('day',[date('Ymd',$timeArr[0]),date('Ymd',$timeArr[1])])->sum("cookie_count") ? : 0;
        }

        return [
            'member_count' => $memberCount,
            'browse_count' => $browseCount,
            'visitor_count' => $visitorCount,
            'chart_data' => [
                "browse" => [
                    'name' => "浏览量",
                    'x_axis' => $x_axis,
                    'series' => $browseSeries,
                ],
                "visitor" => [
                    'name' => "访客量",
                    'x_axis' => $x_axis,
                    'series' => $visitorSeries,
                ],
            ],
        ];
    }

    /**
     * 会员访问数据
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function memberAccessData(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $browseCount = 0;//浏览量
        $visitorCount = 0;//访客量
        $todayNewBrowse = 0;  //今日新增浏览量
        $todayNewVisitor = 0; //今日新增访客数
        $yesterdayNewVBrowse = 0;//昨日新增浏览量
        $yesterdayNewVisitor = 0;//昨日新增访客数

        if (app('plugins')->isEnabled('browse-footprint')) {
            $browseData = \Yunshop\BrowseFootprint\models\BrowseFootprintModel::uniacid()
                ->select(DB::raw('day,COUNT(id) as count,COUNT(DISTINCT(cookie)) as cookie_count'));
            if ($search['port_type']) {
                $browseData->where('port_type',$search['port_type']);
            }
            $browseData = $browseData->whereBetween('day',[date('Ymd',$start),date('Ymd',$end)])
                ->groupBy('day')
                ->get();

            $browseCount = $browseData->whereBetween('day',[date('Ymd',$search['start']),date('Ymd',$search['end'])])->sum("count");
            $visitorCount = $browseData->whereBetween('day',[date('Ymd',$search['start']),date('Ymd',$search['end'])])->sum("cookie_count");

            $yesterdayTime = $this->timeSlot($search['start'],-1,$search['time_type']);
            $beforeYesterdayTime = $this->timeSlot($search['start'],-2,$search['time_type']);
            switch ($search['time_type']) {
                case 'day':
                    $todayTime = $this->getDay($search['start']);
                    $yesterdayTime = $this->getDay($yesterdayTime[0]);
                    $beforeYesterdayTime = $this->getDay($beforeYesterdayTime[0]);
                    break;
                case 'week':
                    $todayTime = $this->getWeek($search['start']);
                    $yesterdayTime = $this->getWeek($yesterdayTime[0]);
                    $beforeYesterdayTime = $this->getWeek($beforeYesterdayTime[0]);
                    break;
                case 'month':
                    $todayTime = $this->getMonth($search['start']);
                    $yesterdayTime = $this->getMonth($yesterdayTime[0]);
                    $beforeYesterdayTime = $this->getMonth($beforeYesterdayTime[0]);
                    break;
                default:
                    throw new AppException("时间类型错误");
            }

            $todayBrowseCount = $browseData->whereBetween('day',$todayTime)->sum("count") ? : 0;
            $yesterdayBrowseCount = $browseData->whereBetween('day',$yesterdayTime)->sum("count") ? : 0;
            $beforeYesterdayBrowseCount = $browseData->whereBetween('day',$beforeYesterdayTime)->sum("count") ? : 0;

            $todayVisitorCount = $browseData->whereBetween('day',$todayTime)->sum("cookie_count") ? : 0;
            $yesterdayVisitorCount = $browseData->whereBetween('day',$yesterdayTime)->sum("cookie_count") ? : 0;
            $beforeYesterdayVisitorCount = $browseData->whereBetween('day',$beforeYesterdayTime)->sum("cookie_count") ? : 0;

            $todayNewBrowse = $yesterdayBrowseCount - $todayBrowseCount;
            $todayNewVisitor = $yesterdayVisitorCount - $todayVisitorCount;
            $yesterdayNewVBrowse = $beforeYesterdayBrowseCount - $yesterdayBrowseCount;
            $yesterdayNewVisitor = $beforeYesterdayVisitorCount - $yesterdayVisitorCount;
        } else {
            $browseData = collect([]);
        }

        $x_axis = [];
        $browseSeries = [];
        $visitorSeries = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $browseSeries[] = $browseData->whereBetween('day',[date('Ymd',$timeArr[0]),date('Ymd',$timeArr[1])])->sum("count") ? : 0;
            $visitorSeries[] = $browseData->whereBetween('day',[date('Ymd',$timeArr[0]),date('Ymd',$timeArr[1])])->sum("cookie_count") ? : 0;
        }
        return [
            'browse_count' => $browseCount,
            'visitor_count' => $visitorCount,
            'todayNewBrowse' => $todayNewBrowse,
            'todayNewVisitor' => $todayNewVisitor,
            'yesterdayNewVBrowse' => $yesterdayNewVBrowse,
            'yesterdayNewVisitor' => $yesterdayNewVisitor,
            'chart' => [
                "browse" => [
                    'name' => "浏览量",
                    'x_axis' => $x_axis,
                    'series' => $browseSeries,
                    'color'  => '#4D8BFC',
                ],
                "visitor" => [
                    'name' => "访客量",
                    'x_axis' => $x_axis,
                    'series' => $visitorSeries,
                    'color'  => '#13C267'
                ]
            ]
        ];
    }

    /**
     * 商品分析
     * @return array
     */
    public function goodsAnalysis(): array
    {
        $goodsStatistic = Goods::uniacid()
            ->select(DB::raw('COUNT(id) as total,SUM(IF(status=1,1,0)) as grounding'))
            ->first();

        return [
            'goods_total' => $goodsStatistic->total ? : 0,
            'goods_grounding' => $goodsStatistic->grounding ? : 0,
            'goods_off_grounding' => ($goodsStatistic->total ? : 0) - ($goodsStatistic->grounding ? : 0),
        ];
    }

    /**
     * 分地区营业额统计
     * @return array
     */
    public function turnoverToArea(): array
    {
        $statistic = $this->turnoverModel()
            ->select(DB::raw('areaname,SUM(price) as turnover'))
            ->join('yz_order_address', function ($join) {
                $join->on('yz_order.id', 'yz_order_address.order_id');
            })
            ->leftJoin('yz_address', function ($join) {
                $join->on('yz_order_address.province_id', 'yz_address.id');
            })
            ->whereBetween('yz_order.pay_time',[1,time()])
            ->groupBy('yz_order_address.province_id')
            ->get()->toArray();
        $data = [];
        foreach ($statistic as $item) {
            $data[] = [
                'areaname' => $item['areaname'],
                'turnover' => $item['turnover'] ? : 0,
            ];
        }
        return [
            'max' => collect($data)->max('turnover') ? : 0,
            'data' => $data
        ];
    }

    /**
     * 分地区门店数量统计
     * @return array
     */
    public function storeToArea(): array
    {
        if (!app('plugins')->isEnabled('store-cashier')) {
            return [];
        }
        $statistic = Store::uniacid()
            ->select(DB::raw('areaname,COUNT(*) as store_count'))
            ->leftJoin('yz_address', function ($join) {
                $join->on('yz_store.province_id', 'yz_address.id');
            })
            ->groupBy('yz_store.province_id')
            ->get()->toArray();
        $data = [];
        foreach ($statistic as $item) {
            $data[] = [
                'areaname' => $item['areaname'],
                'store_count' => $item['store_count'] ? : 0,
            ];
        }
        return [
            'max' => collect($data)->max('store_count') ? : 0,
            'data' => $data
        ];
    }

    /**
     * 分地区订单数统计
     * @return array
     */
    public function orderCountToArea(): array
    {
        $statistic = $this->turnoverModel()
            ->select(DB::raw('areaname,COUNT(*) as order_count'))
            ->join('yz_order_address', function ($join) {
                $join->on('yz_order.id', 'yz_order_address.order_id');
            })
            ->leftJoin('yz_address', function ($join) {
                $join->on('yz_order_address.province_id', 'yz_address.id');
            })
            ->whereBetween('yz_order.pay_time',[1,time()])
            ->groupBy('yz_order_address.province_id')
            ->get()->toArray();
        $data = [];
        foreach ($statistic as $item) {
            $data[] = [
                'areaname' => $item['areaname'],
                'order_count' => $item['order_count'] ? : 0,
            ];
        }
        return [
            'max' => collect($data)->max('order_count') ? : 0,
            'data' => $data
        ];
    }

    /**
     * 营业额模型
     * @return \app\common\models\BaseModel
     */
    private function turnoverModel()
    {
        return Order::uniacid()->whereIn('status',[-1,1,2,3]);
    }

    /**
     * 下单会员占比
     * @return array
     */
    public function buyerLevelProportion(): array
    {
        $data = MemberShopInfo::uniacid()
            ->select(DB::raw('COUNT(member_id) as member_count,level_id'))
            ->with(['level:id,level_name'])
            ->whereIn('member_id',function ($where) {
                $where->select('uid')
                    ->from('yz_order')
                    ->where('uniacid',\Yunshop::app()->uniacid);
            })
            ->groupBy('level_id')
            ->orderBy('member_count','desc')
            ->get()->toArray();
        $shop_set = Setting::get('shop.member');
        $returnData = [];
        $other = [];
        $color = ['#BEE8CC','#78DCBA','#2CC08D','#4D8BFC','#86B0FE','#BBD3FF','#825EE8','#B59CFC','#D7CAF9','#FF8E5B','#DDDFE2'];
        foreach ($data as $k=>$item) {
            if ($k > 9) {//显示前十个
                $other['level_name'] = "其他";
                $other['member_count'] = bcadd(($other['member_count']?:0),$item['member_count']);
                $other['color'] = $color[10];
            } else {
                $level_name = $item['level'] ? $item['level']['level_name'] : '未知等级(已删除)';
                if ($item['level_id'] == 0) {
                    $level_name = $shop_set['level_name'] ?: '普通会员';
                }
                $returnData[] = [
                    'level_name' => $level_name,
                    'member_count' => $item['member_count'],
                    'color' => $color[$k]
                ];
            }
        }
        if ($other) {
            $returnData[] = $other;
        }
        return $returnData;
    }

    /**
     * 订单分类占比
     * @return array
     */
    public function orderPluginProportion(): array
    {
        $list = Order::uniacid()
            ->select(DB::raw('COUNT(id) as order_count,plugin_id'))
            ->where('status',3)
            ->groupBy('plugin_id')
            ->orderBy('order_count','desc')
            ->get()->toArray();

        $plugin = $this->orderPlugin();
        $data = [];
        $other = [];
        $color = ['#BEE8CC','#DDDFE2','#D7CAF9','#B59CFC','#825EE8','#BBD3FF','#86B0FE','#4D8BFC','#2CC08D','#FF8E5B','#78DCBA'];
        foreach ($list as $k=>$item) {
            if ($k > 9) {
                $other['name'] = "其他";
                $other['order_count'] = bcadd(($other['order_count']?:0),$item['order_count']);
                $other['color'] = $color[10];
            } else {
                $data[] = [
                    'name' => $plugin[$item['plugin_id']]?:'未知',
                    'order_count' => $item['order_count'],
                    'color' => $color[$k]
                ];
            }
        }
        if ($other) {
            $data[] = $other;
        }
        return $data;
    }

    /**
     * 支付方式分析
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function payTypeAnalysis(): array
    {
        $payTypes = [];
        foreach (app(PaymentManager::class)->all()->toArray() as $gateway) {
            $payment = app()->make($gateway);
            $payTypes[] = [
                'id' => $payment->getId(),
                'name' => $payment->getName(),
            ];
        }
        $payTypes = collect($payTypes);

        $defaultPayTypes = app(PaymentManager::class)->getPayType();

        $list = Order::uniacid()
            ->select(DB::raw('COUNT(id) as order_count,pay_type_id'))
            ->whereBetween('pay_time',[1,time()])
            ->groupBy('pay_type_id')
            ->orderBy('order_count','desc')
            ->get()->toArray();
        $data = [];
        $other = [];
        $color = ['#BEE8CC','#78DCBA','#2CC08D','#4D8BFC','#86B0FE','#BBD3FF','#4856AE','#825EE8','#B59CFC','#D7CAF9','#DDDFE2'];
        foreach ($list as $k=>$item) {
            if ($k > 9) {
                $other['name'] = "其他";
                $other['order_count'] = bcadd(($other['order_count']?:0),$item['order_count']);
                $other['color'] = $color[10];
            } else {
                $payType = $payTypes->where('id',$item['pay_type_id'])->first();
                if (!$payType) {
                    $payType = $defaultPayTypes->where('id',$item['pay_type_id'])->first();
                }
                $data[] = [
                    'name' => $payType['name']?:'未知支付',
                    'order_count' => $item['order_count'],
                    'color' => $color[$k],
                ];
            }
        }
        if ($other) {
            $data[] = $other;
        }
        return $data;
    }

    /**
     * 订单排名
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function orderRanking(array $search = []): array
    {
        $statistic = $this->turnoverModel()
            ->select(DB::raw('areaname,COUNT(*) as order_count,SUM(price) as turnover'))
            ->join('yz_order_address', function ($join) {
                $join->on('yz_order.id', 'yz_order_address.order_id');
            })
            ->leftJoin('yz_address', function ($join) {
                $join->on('yz_order_address.province_id', 'yz_address.id');
            })
            ->whereBetween('pay_time',[1,time()]);

        $search = $this->handleSearch($search);

        switch ($search['time_type']) {
            case 'day':
                $statistic->whereBetween('yz_order.created_at',$this->getDay($search['start']));
                break;
            case 'week':
                $statistic->whereBetween('yz_order.created_at',$this->getWeek($search['start']));
                break;
            case 'month':
                $statistic->whereBetween('yz_order.created_at',$this->getMonth($search['start']));
                break;
            default:
                throw new AppException('搜索时间类型错误');
        }

        $statistic = $statistic->groupBy('yz_order_address.province_id')
            ->limit(10)
            ->get()->toArray();
        $data = [];
        foreach ($statistic as $item) {
            $data[] = [
                'areaname' => $item['areaname'],
                'order_count' => $item['order_count'] ? : 0,
                'turnover'    => $item['turnover'] ? : 0,
            ];
        }
        return $data;
    }

    /**
     * 配送方式
     * @param array $search
     * @return array[]
     * @throws AppException
     */
    public function deliveryMethod(array $search = []): array
    {
        $code = ['dispatch','package_deliver','city_delivery','shop_pos','package_delivery'];
        $deliveryTypes = DispatchType::whereIn('code', $code)->get();
        $deliveryTypeIds = $deliveryTypes->pluck('id')->all();

        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $order = Order::uniacid()
            ->select('id','created_at','dispatch_type_id')
            ->whereIn('dispatch_type_id',($deliveryTypeIds?:[-1]))
            ->whereBetween('created_at',[$start,$end])
            ->get();
        $x_axis = [];
        $chart = [];
        $total = [];
        foreach ($deliveryTypeIds as $k=>$deliveryTypeId) {
            $nextStart = $start;
            $series = [];
            while ($nextStart <= $end) {
                switch ($search['time_type']) {
                    case 'day':
                        $timeArr = $this->getDay($nextStart);
                        $x = date('m/d',$nextStart);
                        $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                        break;
                    case 'week':
                        $timeArr = $this->getWeek($nextStart);
                        $x = date('Y/W',$nextStart);
                        $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                        break;
                    case 'month':
                        $timeArr = $this->getMonth($nextStart);
                        $x = date('Y/m',$nextStart);
                        $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                        break;
                    default:
                        throw new AppException("时间类型错误");
                }
                if ($k == 0) {
                    $x_axis[] = $x;
                }
                $series[] = $order->where('dispatch_type_id',$deliveryTypeId)
                    ->whereBetween('created_at',$timeArr)
                    ->count() ? : 0;
            }
            $delivery = $deliveryTypes->where('id',$deliveryTypeId)->first();
            $color = [
                'dispatch' => '#FF8E5B',
                'package_deliver' => '#FF5B6C',
                'city_delivery' => '#825EE8',
                'shop_pos' => '#4D8BFC',
                'package_delivery' => '#2CC08D',
            ];
            $total[] = [
                'name' => $delivery['name'],
                'order_count' => $order->where('dispatch_type_id',$deliveryTypeId)->whereBetween('created_at',[$search['start'],$search['end']])->count() ? : 0,
            ];
            $chart[] = [
                'name' => $delivery['name'],
                'x_axis' => $x_axis,
                'series' => $series,
                'color' => $color[$delivery['code']] ? : '#FF8E5B',
            ];
        }
        return [
            'total' => $total,
            'chart' => $chart,
        ];
    }

    /**
     * 会员数据分析
     * @param array $search
     * @return array[]
     * @throws AppException
     */
    public function memberDataAnalysis(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $list = Member::uniacid()
            ->select('uid','createtime')
            ->whereBetween('createtime',[$start,$end])
            ->get();

        $totalCount = $list->count(); //会员总数
        $newCount = $list->whereBetween('createtime',[$search['start'],$search['end']])->count(); //搜索日期会员新增总数

        $x_axis = [];
        $series = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $series[] = $list->whereBetween('createtime',$timeArr)->count() ? : 0;
        }
        return [
//            'statistic' => [
//                'totalCount' => [
//                    'name' => '会员总数（人）',
//                    'value' => $totalCount,
//                ],
//                'todayCount' => [
//                    'name' => '新增会员数（人）',
//                    'value' => $newCount,
//                ],
//            ],
            'total_count' => $totalCount,
            'today_count' => $newCount,
            'chart' => [
                'x_axis' => $x_axis,
                'series' => $series,
            ],
        ];
    }

    /**
     * 商品销售排行
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function goodsSaleRanking(array $search = []): array
    {
        $list = OrderGoods::uniacid()
            ->select(DB::raw('goods_id,SUM(total) AS goods_total'))
            ->with('hasOneGoods:id,title');

        if ($search['start']) {
            $search['start'] = Carbon::createFromTimeStamp($search['start'])->startOfDay()->timestamp;
            $search['end'] = Carbon::createFromTimeStamp($search['start'])->endOfDay()->timestamp;
            $list = $list->whereBetween('created_at',[$search['start'],$search['end']]);
        }

        $list = $list->groupBy('goods_id')
            ->orderBy('goods_total','desc')
            ->limit(10)
            ->get()
            ->toArray();

        $data = [];
        foreach ($list as $k=>$item) {
            $data[] = [
                'key' => $k+1,
                'title' => $item['has_one_goods']['title'] ? : "(商品已删除)",
                'value' => $item['goods_total'],
            ];
        }
        return $data;
    }

    /**
     * 交易趋势
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function tradeTrend(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $payList = Order::uniacid()
            ->select('goods_total','created_at')
            ->whereBetween('pay_time',[1,time()])
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $cartList = MemberCart::withTrashed()
            ->uniacid()
            ->select(DB::raw('total,created_at AS created_time'))
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $order_goods_count = Order::uniacid()->whereBetween('created_at',[$search['start'],$search['end']])->sum('goods_total') ? : 0;//商品下单数
        $pay_goods_count = $payList->whereBetween('created_at',[$search['start'],$search['end']])->sum('goods_total') ? : 0; //商品成交数

        $x_axis = [];
        $cartSeries = [];
        $payGoodsSeries = [];
        $saleGoodsSeries = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $cartSeries[] = $cartList->whereBetween('created_time',$timeArr)->sum('total') ? : 0;
            $payGoodsSeries[] = $payList->whereBetween('created_at',$timeArr)->sum('goods_total') ? : 0;
            $saleGoods = OrderGoods::uniacid()
                ->select(DB::raw('COUNT(DISTINCT(goods_id)) AS goods_count'))
                ->whereIn('order_id',function ($query) use ($timeArr) {
                    $query->select('id')
                        ->from('yz_order')
                        ->where('uniacid', \YunShop::app()->uniacid)
                        ->whereBetween('pay_time',[1,time()])
                        ->whereBetween('created_at',[1,$timeArr[1]]);
                })
                ->whereBetween('created_at',[1,$timeArr[1]])
                ->first();
            $saleGoodsSeries[] = $saleGoods->goods_count ? : 0;
        }
        return [
            'order_goods_count' => $order_goods_count,
            'pay_goods_count' => $pay_goods_count,
            'chart' => [
                "cart_goods" => [
                    'name' => "商品加购件数",
                    'x_axis' => $x_axis,
                    'series' => $cartSeries,
                    'color' => '#825EE8',
                ],
                "pay_goods" => [
                    'name' => "商品成交件数",
                    'x_axis' => $x_axis,
                    'series' => $payGoodsSeries,
                    'color' => '#4D8BFC',
                ],
                "sale_goods" => [
                    'name' => "动销商品数",
                    'x_axis' => $x_axis,
                    'series' => $saleGoodsSeries,
                    'color' => '#2CC08D',
                ],
            ]
        ];
    }

    /**
     * 会员数据统计（收入）
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function memberIncomeStatistic(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $incomeStatistic = Income::uniacid()
            ->select(DB::raw('amount,status,pay_status,created_at AS created_time'))
            ->whereBetween('created_at',[$start,$end])
            ->get();


        $withdrawStatistic = Withdraw::uniacid()
            ->select(DB::raw('servicetax,poundage,actual_servicetax,actual_poundage,status,created_at AS created_time'))
            ->whereBetween('created_at',[$start,$end])
            ->get();


        $total = [
            [
                "name" => "总收入",
                "tips" => "统计筛选时间内的，所有收入明细总和",
                "value" => round($incomeStatistic->whereBetween('created_time',[$search['start'],$search['end']])->sum('amount'),2) ? : 0,
            ],
            [
                "name" => "已提现金额",
                "tips" => "统计筛选时间内的，所有已提现的收入记录总和",
                "value" => round($incomeStatistic->where('status',1)->whereBetween('created_time',[$search['start'],$search['end']])->sum('amount'),2) ? : 0,
            ],
            [
                "name" => "劳务税总和",
                "tips" => "统计筛选时间内的，所有已提现的劳务税总和",
                "value" => bcadd(($withdrawStatistic->where('status',0)->whereBetween('created_time',[$search['start'],$search['end']])->sum('servicetax') ? : 0),($withdrawStatistic->where('status',"!=",0)->whereBetween('created_at',[$search['start'],$search['end']])->sum('actual_servicetax') ? : 0),2),
            ],
            [
                "name" => "手续费金额",
                "tips" => "统计筛选时间内的，所有已提现的手续费总和",
                "value" => bcadd(($withdrawStatistic->where('status',0)->whereBetween('created_time',[$search['start'],$search['end']])->sum('oundage') ? : 0),($withdrawStatistic->where('status',"!=",0)->whereBetween('created_at',[$search['start'],$search['end']])->sum('ctual_poundage') ? : 0),2),
            ],
            [
                "name" => "收入笔数",
                "tips" => "统计筛选时间内的，收入笔数",
                "value" => $incomeStatistic->count() ? : 0,
            ],
        ];

        $x_axis = [];
        $chart = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $chart['amount_total']['series'][] = round($incomeStatistic->whereBetween('created_time',$timeArr)->sum('amount'),2) ? : 0;
            $chart['withdraw_income']['series'][] = round($incomeStatistic->whereBetween('created_time',$timeArr)->where('status',1)->sum('amount'),2) ? : 0;
            $chart['servicetax_total']['series'][] = bcadd(($withdrawStatistic->whereBetween('created_time',$timeArr)->where('status',0)->sum('servicetax') ? : 0),($withdrawStatistic->whereBetween('created_at',$timeArr)->where('status',"!=",0)->sum('actual_servicetax') ? : 0),2);
            $chart['poundage_total']['series'][] = bcadd(($withdrawStatistic->whereBetween('created_time',$timeArr)->where('status',0)->sum('poundage') ? : 0),($withdrawStatistic->whereBetween('created_at',$timeArr)->where('status',"!=",0)->sum('actual_poundage') ? : 0),2);
            $chart['income_count']['series'][] = $incomeStatistic->whereBetween('created_time',$timeArr)->count() ? : 0;
        }
        $chart['amount_total']['x_axis'] = $x_axis;
        $chart['amount_total']['name'] = "总收入";
        $chart['amount_total']['color'] = "#2CC08D";
        $chart['withdraw_income']['x_axis'] = $x_axis;
        $chart['withdraw_income']['name'] = "已提现金额";
        $chart['withdraw_income']['color'] = "#4D8BFC";
        $chart['servicetax_total']['x_axis'] = $x_axis;
        $chart['servicetax_total']['name'] = "劳务税金额";
        $chart['servicetax_total']['color'] = "#BEE8CC";
        $chart['poundage_total']['x_axis'] = $x_axis;
        $chart['poundage_total']['name'] = "手续费金额";
        $chart['poundage_total']['color'] = "#BBD3FF";
        $chart['income_count']['x_axis'] = $x_axis;
        $chart['income_count']['name'] = "收入笔数";
        $chart['income_count']['color'] = "#825EE8";

        return [
            'statistic' => $total,
            'chart' => $chart
        ];
    }

    /**
     * 余额数据统计
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function balanceStatistic(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);

        $totalBalanceStatistic = Balance::select(DB::raw('change_money,created_at AS created_time'))
            ->whereBetween('created_at',[0,$end])
            ->get();

        $balanceStatistic = Balance::select(DB::raw('change_money,created_at AS created_time,service_type'))
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $total = [
            [
                "name" => "可使用余额",
                "tips" => "统计筛选时间内，累计剩余余额总数",
                "value" => round($totalBalanceStatistic->whereBetween('created_time',[0,$search['end']])->sum('change_money'),2) ? : 0,
            ],
            [
                "name" => "已使用余额",
                "tips" => "统计筛选时间内，累计已使用余额总数",
                "value" => round($balanceStatistic->where('service_type',2)->whereBetween('created_time',[$search['start'],$search['end']])->sum('change_money'),2) ? : 0,
            ],
            [
                "name" => "已提现余额",
                "tips" => "统计筛选时间内，累计已提现余额总数",
                "value" => round($balanceStatistic->where('service_type',6)->whereBetween('created_time',[$search['start'],$search['end']])->sum('change_money'),2) ? : 0,
            ],
            [
                "name" => "收入余额",
                "tips" => "统计筛选时间内，累计收入提现到余额总数",
                "value" => round($balanceStatistic->where('service_type',7)->whereBetween('created_time',[$search['start'],$search['end']])->sum('change_money'),2) ? : 0,
            ],
        ];

        foreach ($total as &$item) {
            if ($item['value'] < 0) {
                $item['value'] = bcmul($item['value'],-1,-2);
            }
        }
        unset($item);

        $x_axis = [];
        $chart = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $chart['use_balance']['series'][] = $totalBalanceStatistic->whereBetween('created_time',[0,$timeArr[1]])->sum('change_money') ? : 0;
            $chart['used_balance']['series'][] = $balanceStatistic->whereBetween('created_time',$timeArr)->where('service_type',2)->sum('change_money') ? : 0;
            $chart['withdraw_balance']['series'][] = $balanceStatistic->whereBetween('created_time',$timeArr)->where('service_type',6)->sum('change_money') ? : 0;
            $chart['income_balance']['series'][] = $balanceStatistic->whereBetween('created_time',$timeArr)->where('service_type',7)->sum('change_money') ? : 0;
        }

        foreach ($chart as $key=>$item) {
            foreach ($item['series'] as $k=>$v) {
                if ($v < 0) {
                    $chart[$key]['series'][$k] = bcmul($v,-1,-2);
                }
            }
        }

        $chart['use_balance']['x_axis'] = $x_axis;
        $chart['use_balance']['name'] = "可使用余额";
        $chart['use_balance']['color'] = "#2CC08D";
        $chart['used_balance']['x_axis'] = $x_axis;
        $chart['used_balance']['name'] = "已使用余额";
        $chart['used_balance']['color'] = "#4D8BFC";
        $chart['withdraw_balance']['x_axis'] = $x_axis;
        $chart['withdraw_balance']['name'] = "已提现余额";
        $chart['withdraw_balance']['color'] = "#825EE8";
        $chart['income_balance']['x_axis'] = $x_axis;
        $chart['income_balance']['name'] = "收入余额";
        $chart['income_balance']['color'] = "#FF8E5B";

        $rechargeList = BalanceRecharge::select(DB::raw('COUNT(*) AS recharge_count,type'))
            ->groupBy('type')
            ->orderBy('recharge_count','desc')
            ->get();
        $data = [];
        $other = [];
        $recordModel = new BalanceRechargeRecords();
        $color = ['#BEE8CC','#DDDFE2','#D7CAF9','#B59CFC','#825EE8','#BBD3FF','#86B0FE','#4D8BFC','#2CC08D','#FF8E5B','#78DCBA'];
        foreach ($rechargeList as $k=>$item) {
            if ($k > 9) {
                $other['name'] = "其他";
                $other['recharge_count'] = bcadd(($other['recharge_count']?:0),$item['recharge_count']);
                $other['color'] = $color[10];
            } else {
                $data[] = [
                    'name' => $recordModel->getTypeNameComment($item['type']),
                    'recharge_count' => $item['recharge_count'],
                    'color' => $color[$k]
                ];
            }
        }
        if ($other) {
            $data[] = $other;
        }

        return [
            'statistic' => $total,
            'chart' => $chart,
            'recharge_data' => $data,
        ];
    }

    /**
     * 积分数据统计
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function pointStatistic(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);
        $totalPointStatistic = PointLog::uniacid()
            ->select(DB::raw('point,created_at AS created_time'))
            ->whereBetween('created_at',[0,$end])
            ->get();

        $pointStatistic = PointLog::uniacid()
            ->select(DB::raw('point,created_at AS created_time,point_income_type,point_mode'))
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $total = [
            [
                "name" => "可使用积分",
                "tips" => "统计筛选时间内，商城积分总数",
                "value" => round($totalPointStatistic->whereBetween('created_time',[0,$search['end']])->sum('point'),2) ? : 0,
            ],
            [
                "name" => "已消耗积分",
                "tips" => "统计筛选时间内，已消耗积分总数",
                "value" => round($pointStatistic->where('point_mode',6)->whereBetween('created_time',[$search['start'],$search['end']])->sum('point'),2) ? : 0,
            ],
            [
                "name" => "已赠送积分",
                "tips" => "统计筛选时间内，已赠送积分总数",
                "value" => round($pointStatistic->where('point_income_type',1)->where('point_mode','!=',5)->whereBetween('created_time',[$search['start'],$search['end']])->sum('point'),2) ? : 0,
            ],
            [
                "name" => "充值积分",
                "tips" => "统计筛选时间内，充值积分总数",
                "value" => round($pointStatistic->where('point_mode',5)->whereBetween('created_time',[$search['start'],$search['end']])->sum('point'),2) ? : 0,
            ],
        ];

        foreach ($total as &$item) {
            if ($item['value'] < 0) {
                $item['value'] = bcmul($item['value'],-1,-2);
            }
        }
        unset($item);

        $x_axis = [];
        $chart = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $chart['use_point']['series'][] = $totalPointStatistic->whereBetween('created_time',[0,$timeArr[1]])->sum('point') ? : 0;
            $chart['used_point']['series'][] = $pointStatistic->whereBetween('created_time',$timeArr)->where('point_mode',6)->sum('point') ? : 0;
            $chart['give_point']['series'][] = $pointStatistic->whereBetween('created_time',$timeArr)->where('point_income_type',1)->where('point_mode','!=',5)->sum('point');
            $chart['recharge_point']['series'][] = $pointStatistic->whereBetween('created_time',$timeArr)->where('point_mode',5)->sum('point') ? : 0;
        }

        foreach ($chart as $key=>$item) {
            foreach ($item['series'] as $k=>$v) {
                if ($v < 0) {
                    $chart[$key]['series'][$k] = bcmul($v,-1,-2);
                }
            }
        }

        $chart['use_point']['x_axis'] = $x_axis;
        $chart['use_point']['name'] = "可使用积分";
        $chart['used_point']['x_axis'] = $x_axis;
        $chart['used_point']['name'] = "已消耗积分";
        $chart['give_point']['x_axis'] = $x_axis;
        $chart['give_point']['name'] = "已赠送积分";
        $chart['recharge_point']['x_axis'] = $x_axis;
        $chart['recharge_point']['name'] = "充值积分";

        $data = [];
        $other = [];
        $pointList = PointLog::uniacid()
            ->select(DB::raw('SUM(point) AS point_total,point_mode'))
            ->where('point_income_type',1)
            ->groupBy('point_mode')
            ->orderBy('point_total','desc')
            ->get();
        foreach ($pointList as $k=>$item) {
            if ($k > 2) {
                $other['name'] = "其他";
                $other['point_total'] = bcadd(($other['point_total']?:0),$item['point_total'],2);
            } else {
                $data[] = [
                    'name' => PointService::getAllSourceName()[$item['point_mode']],
                    'point_total' => $item['point_total'],
                ];
            }
        }
        if ($other) {
            $data[] = $other;
        }

        return [
            'statistic' => $total,
            'chart' => $chart,
            'point_data' => $data,
        ];
    }

    /**
     * 优惠券数据统计
     * @param array $search
     * @return array
     * @throws AppException
     */
    public function couponStatistic(array $search = []): array
    {
        $search = $this->handleSearch($search);
        list($start,$end) = $this->timeSlot($search['start'],-6,$search['time_type']);
        $couponStatistic = MemberCoupon::uniacid()
            ->select(DB::raw('get_time AS created_time,used,is_expired,get_type'))
            ->whereBetween('get_time',[$start,$end])
            ->get();

        $orderCouponStatistic = OrderCoupon::select(DB::raw('created_at AS created_time,amount'))
            ->whereIn('order_id',function ($query) use ($start,$end) {
                $query->select('id')
                    ->from('yz_order')
                    ->where('uniacid',\Yunshop::app()->uniacid)
                    ->whereBetween('created_at',[$start,$end]);
            })
            ->whereBetween('created_at',[$start,$end])
            ->get();

        $total = [
            [
                "name" => "已赠送优惠券",
                "tips" => "统计筛选时间内，已赠送优惠券总数",
                "value" => $couponStatistic->whereBetween('created_time',[$search['start'],$search['end']])->count() ? : 0,
            ],
            [
                "name" => "已领取优惠券",
                "tips" => "统计筛选时间内，领券中心领取优惠券总数",
                "value" => $couponStatistic->where('get_type',1)->whereBetween('created_time',[$search['start'],$search['end']])->count() ? : 0,
            ],
            [
                "name" => "已使用优惠券",
                "tips" => "统计筛选时间内，已使用优惠券总数",
                "value" => $couponStatistic->where('used',1)->whereBetween('created_time',[$search['start'],$search['end']])->count() ? : 0,
            ],
            [
                "name" => "已过期优惠券",
                "tips" => "统计筛选时间内，已过期优惠券总数",
                "value" => $couponStatistic->where('is_expired',1)->where('used',0)->whereBetween('created_time',[$search['start'],$search['end']])->count() ? : 0,
            ],
            [
                "name" => "抵扣金额",
                "tips" => "统计筛选时间内，使用优惠券抵扣总额",
                "value" => round($orderCouponStatistic->whereBetween('created_time',[$search['start'],$search['end']])->sum('amount'),2) ? : 0,
            ],
        ];

        $x_axis = [];
        $chart = [];
        $nextStart = $start;
        while ($nextStart <= $end) {
            switch ($search['time_type']) {
                case 'day':
                    $timeArr = $this->getDay($nextStart);
                    $x = date('m/d',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addDays(1)->timestamp;
                    break;
                case 'week':
                    $timeArr = $this->getWeek($nextStart);
                    $x = date('Y/W',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addWeeks(1)->timestamp;
                    break;
                case 'month':
                    $timeArr = $this->getMonth($nextStart);
                    $x = date('Y/m',$nextStart);
                    $nextStart = Carbon::createFromTimeStamp($nextStart)->addMonths(1)->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
            $x_axis[] = $x;
            $chart['given_coupon']['series'][] = $couponStatistic->whereBetween('created_time',$timeArr)->count();
            $chart['receive_coupon']['series'][] = $couponStatistic->whereBetween('created_time',$timeArr)->where('get_type',1)->count();
            $chart['used_coupon']['series'][] = $couponStatistic->whereBetween('created_time',$timeArr)->where('used',1)->count();
            $chart['expired_coupon']['series'][] = $couponStatistic->where('is_expired',1)->where('used',0)->whereBetween('created_time',$timeArr)->count();
            $chart['deduction_price']['series'][] = $orderCouponStatistic->whereBetween('created_time',$timeArr)->sum('amount');
        }
        $chart['given_coupon']['x_axis'] = $x_axis;
        $chart['given_coupon']['name'] = "已赠送优惠券";
        $chart['receive_coupon']['x_axis'] = $x_axis;
        $chart['receive_coupon']['name'] = "已领取优惠券";
        $chart['used_coupon']['x_axis'] = $x_axis;
        $chart['used_coupon']['name'] = "已使用优惠券";
        $chart['expired_coupon']['x_axis'] = $x_axis;
        $chart['expired_coupon']['name'] = "已过期优惠券";
        $chart['deduction_price']['x_axis'] = $x_axis;
        $chart['deduction_price']['name'] = "抵扣金额";

        $data = [];
        $other = [];
        $logModel = new CouponLog();
        $couponLog = CouponLog::uniacid()
            ->select(DB::raw('COUNT(*) AS get_count,getfrom'))
            ->groupBy('getfrom')
            ->orderBy('get_count','desc')
            ->get();
        foreach ($couponLog as $k=>$item) {
            if ($k > 2) {
                $other['name'] = "其他";
                $other['get_count'] = bcadd(($other['get_count']?:0),$item['get_count']);
            } else {
                $data[] = [
                    'name' => $logModel->getTypeNames()[$item['getfrom']],
                    'get_count' => $item['get_count'],
                ];
            }
        }
        if ($other) {
            $data[] = $other;
        }

        return [
            'statistic' => $total,
            'chart' => $chart,
            'coupon_data' => $data,
        ];
    }

    /**
     * 年龄分别与消费金额
     * @return array[]
     */
    public function ageTurnover(): array
    {
        $nowYear = date('Y');//当前年份
        $arr = [
            [
                'title' => "18岁以下",
                'timeArr' => [($nowYear-17),$nowYear]
            ],
            [
                'title' => "18岁-26岁",
                'timeArr' => [($nowYear-26),($nowYear-18)]
            ],
            [
                'title' => "27岁-35岁",
                'timeArr' => [($nowYear-35),($nowYear-27)]
            ],
            [
                'title' => "36岁-45岁",
                'timeArr' => [($nowYear-45),($nowYear-36)]
            ],
            [
                'title' => "46岁以上",
                'timeArr' => [1,($nowYear-46)]
            ],
        ];
        $x_axis = [];
        $series = [];
        foreach ($arr as $item) {
            $x_axis[] = $item['title'];
            $series[] = $this->turnoverModel()
                ->whereBetween('pay_time',[1,time()])
                ->whereIn('uid',function ($query) use ($item) {
                    $query->select('uid')
                        ->from('mc_members')
                        ->where('uniacid',\YunShop::app()->uniacid)
                        ->whereBetween('birthyear',$item['timeArr']);
                })
                ->sum('price');
        }
        return [
            'x_axis' => $x_axis,
            'series' => $series
        ];
    }

    private function handleSearch($search)
    {
        if (!$search['time_type']) {
            $search['time_type'] = 'day';
            list($search['start'],$search['end']) = $this->getDay($search['start'] ? : time());
        } else {
            if (!$search['start']) {
                $search['start'] = time();
            }
            switch ($search['time_type']) {
                case 'day':
                    $search['start'] = Carbon::createFromTimeStamp($search['start'])->startOfDay()->timestamp;
                    $search['end'] = Carbon::createFromTimeStamp($search['start'])->endOfDay()->timestamp;
                    break;
                case 'week':
                    $search['start'] = Carbon::createFromTimeStamp($search['start'])->startOfWeek()->timestamp;
                    $search['end'] = Carbon::createFromTimeStamp($search['start'])->endOfWeek()->timestamp;
                    break;
                case 'month':
                    $search['start'] = Carbon::createFromTimeStamp($search['start'])->startOfMonth()->timestamp;
                    $search['end'] = Carbon::createFromTimeStamp($search['start'])->endOfMonth()->timestamp;
                    break;
                default:
                    throw new AppException("时间类型错误");
            }
        }
        return $search;
    }

    /**
     * 获取时间段
     * @param $time
     * @param $change
     * @param string $type
     * @return array
     * @throws AppException
     */
    private function timeSlot($time, $change, string $type = 'day'): array
    {
        $changeTime = Carbon::createFromTimeStamp($time);
        switch ($type) {
            case 'day':
                $changeTime = $changeTime->addDays($change)->timestamp;
                $res_change = self::getDay($changeTime);
                $res = self::getDay($time);
                break;
            case 'week':
                $changeTime = $changeTime->addWeeks($change)->timestamp;
                $res_change = self::getWeek($changeTime);
                $res = self::getWeek($time);
                break;
            case 'month':
                $changeTime = $changeTime->addMonths($change)->timestamp;
                $res_change = self::getMonth($changeTime);
                $res = self::getMonth($time);
                break;
            default:
                throw new AppException("时间类型错误");
        }
        if ($change >= 0) {
            $start = $res[0];
            $end = $res_change[1];
        } else {
            $start = $res_change[0];
            $end = $res[1];
        }
        return [$start,$end];
    }

    private function getDay($time): array
    {
        return [
            Carbon::createFromTimeStamp($time)->startOfDay()->timestamp,
            Carbon::createFromTimeStamp($time)->endOfDay()->timestamp
        ];
    }

    private function getWeek($time): array
    {
        return [
            Carbon::createFromTimeStamp($time)->startOfWeek()->timestamp,
            Carbon::createFromTimeStamp($time)->endOfWeek()->timestamp
        ];
    }

    private function getMonth($time): array
    {
        return [
            Carbon::createFromTimeStamp($time)->startOfMonth()->timestamp,
            Carbon::createFromTimeStamp($time)->endOfMonth()->timestamp
        ];
    }

    private function orderPlugin(): array
    {
        $plugin = [];
        //todo 应由插件设置进来、某些有自定义名称 先放后
        $data = SurveyConfig::current()->get('plugin');
        foreach ($data as $item) {
            $plugin[$item['id']] = $item['name'];
        }
        return $plugin;
    }

    private function memberType(): array
    {
        return [
            1 => '微信公众号会员',
            2 => '微信小程序会员',
            3 => 'APP会员',
            4 => '微信开放平台会员',
            5 => '手机绑定会员',
        ];
    }
}