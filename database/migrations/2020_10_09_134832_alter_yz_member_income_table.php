<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use app\common\services\income\IncomeService;
class AlterYzMemberIncomeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('yz_member_income')) {
            Schema::table('yz_member_income', function (Blueprint $table) {
                if (!Schema::hasColumn('yz_member_income', 'dividend_code')) {
                    $table->integer('dividend_code')->after('member_id')->nullable()->comment('分红插件');
                }
                if (!Schema::hasColumn('yz_member_income', 'order_sn')) {
                    $table->string('order_sn')->nullable()->default('')->comment('订单号');
                }
                if (Schema::hasColumn('yz_member_income', 'incometable_type')) {
                    $table->string('incometable_type')->nullable()->default('')->comment('收入表类型')->change();
                }
            });
            $classList = [
                IncomeService::COMMISSION_ORDER => 'Yunshop\Commission\models\CommissionOrder',
                IncomeService::TEAM_DIVIDEND => 'Yunshop\TeamDividend\models\TeamDividendModel',
                IncomeService::AGENT_DIVIDEND => 'Yunshop\AgentDividend\common\models\AgentDividendModel',
                IncomeService::APPOINTMENT_ORDER => 'Yunshop\Appointment\common\models\AppointmentIncome',
                IncomeService::AREA_DIVIDEND => 'Yunshop\AreaDividend\models\AreaDividend',
                IncomeService::MEMBER_BONUS_RECORDS => 'Yunshop\Asset\Common\Models\MemberBonusRecordsModel',
                IncomeService::SELL_RECORDS => 'Yunshop\Asset\Common\Models\Digitization\SellRecordsModel',
                IncomeService::AUCTION_BID_REWARD => 'Yunshop\Auction\models\AuctionBidReward',
                IncomeService::AUCTION_PREPAYMENT => 'auction_prepayment',
                IncomeService::CHANNEL_AWARD => 'Yunshop\Channel\model\ChannelAward',
                IncomeService::CLOCK_REWARD_LOG => 'Yunshop\ClockIn\models\ClockRewardLogModel',
                IncomeService::COMMISSION_ACTIVITY_REWARD => 'Yunshop\CommissionActivity\common\models\CommissionActivityReward',
                IncomeService::COMMISSION_MANAGE_LOG => 'Yunshop\CommissionManage\common\models\CommissionManageLogModel',
                IncomeService::CONSUME_RETURN => 'Yunshop\ConsumeReturn\common\models\Log',
                IncomeService::DELIVERY_STATION_DIVIDEND => 'Yunshop\DeliveryStation\models\DeliveryStationDividend',
                IncomeService::DISTRIBUTOR_MANAGE => 'Yunshop\DistributorManage\models\DistributorManage',
                IncomeService::DIY_QUEUE_LOG => 'Yunshop\DiyQueue\common\models\DiyQueueLog',
                IncomeService::TEAM_PERFORMANCE_STATISTICS_LOG => 'Yunshop\EliteAward\models\TeamPerformanceStatisticsLogModel',
                IncomeService::ENERGY_CABIN => 'Yunshop\EnergyCabin\models\Dividend',
                IncomeService::FIXED_REWARD_LOG => 'Yunshop\FixedReward\models\FixedRewardLog',
                IncomeService::FROZE_WITH_DRAW => 'Yunshop\Froze\Common\Models\FrozeWithdraw',
                IncomeService::FULL_RETURN => 'Yunshop\FullReturn\common\models\Log',
                IncomeService::FULL_REWARD => 'Yunshop\FullReward\common\models\Log',
                IncomeService::GLOBAL_DIVIDEND => 'Yunshop\GlobalDividend\models\GlobalDividendModel',
                IncomeService::HOTEL_CASHIER_ORDER => 'Yunshop\Hotel\common\models\CashierOrder',
                IncomeService::HOTEL_ORDER => 'Yunshop\Hotel\common\models\HotelOrder',
                IncomeService::NOMINATE_BONUS => 'Yunshop\Nominate\models\NominateBonus',
                IncomeService::INTEGRAL_WITHDRAW => 'Yunshop\Integral\Common\Models\IntegralWithdrawModel',
                IncomeService::INTERESTS_DIVIDEND => 'Yunshop\InterestsDividend\models\InterestsDividendModel',
                IncomeService::CONSUMPTION_RECORDS => 'Yunshop\IntervalConsumption\Common\models\ConsumptionRecords',
                IncomeService::LEVEL_RETURN => 'Yunshop\LevelReturn\models\LevelReturnModel',
                IncomeService::LOVE_WITHDRAW_RECORDS => 'Yunshop\Love\Common\Models\LoveWithdrawRecords',
                IncomeService::LOVE_RETURN_LOG => 'Yunshop\Love\Common\Models\LoveReturnLogModel',
                IncomeService::LOVE_TEAM_AWARD => 'Yunshop\LoveTeam\model\LoveTeamAward',
                IncomeService::MANAGE_AWARD_RECORDS => 'Yunshop\ManageAward\Common\Models\AwardRecordsModel',
                IncomeService::MANAGEMENT_DIVIDEND => 'Yunshop\ManagementDividend\models\ManagementDividend',
                IncomeService::MANUAL_LOG => 'Yunshop\ManualBonus\models\ManualLog',
                IncomeService::MEMBER_RETURN_LOG => 'Yunshop\MemberReturn\common\models\Log',
                IncomeService::MERCHANT_BONUS_LOG => 'Yunshop\Merchant\common\models\MerchantBonusLog',
                IncomeService::MICRO_SHOP_BONUS_LOG => 'Yunshop\Micro\common\models\MicroShopBonusLog',
                IncomeService::MICRO_COMMUNITIES_STICK_REWARD => 'Yunshop\MicroCommunities\models\MicroCommunitiesStickReward',
                IncomeService::MEMBER_REFERRAL_AWARD => 'Yunshop\Mryt\common\models\MemberReferralAward',
                IncomeService::MEMBER_TEAM_AWARD => 'Yunshop\Mryt\common\models\MemberTeamAward',
                IncomeService::ORDER_PARENTING_AWARD => 'Yunshop\Mryt\common\models\OrderParentingAward',
                IncomeService::ORDER_TEAM_AWARD => 'Yunshop\Mryt\common\models\OrderTeamAward',
                IncomeService::TIER_AWARD => 'Yunshop\Mryt\common\models\TierAward',
                IncomeService::NET_CAR_DIVIDEND => 'Yunshop\NetCar\models\NetCarDividend',
                IncomeService::TEAM_PRIZE => 'Yunshop\Nominate\models\TeamPrize',
                IncomeService::ORDINARY_DIVIDEND => 'Yunshop\OrdinaryDividend\models\RewardModel',
                IncomeService::OZY_AWARD_RECORD => 'Yunshop\Ozy\models\AwardRecordModel',
                IncomeService::PACKAGE_DELIVER_BONUS => 'Yunshop\PackageDeliver\model\DeliverBonus',
                IncomeService::PARTNER_REWARD_LOG => 'Yunshop\PartnerReward\common\models\PartnerRewardLogModel',
                IncomeService::PENDING_ORDER_DIVIDEND => 'Yunshop\PendingOrder\models\PendingOrderDividend',
                IncomeService::PERFORMANCE_BONUS => 'Yunshop\Performance\common\model\PerformanceBonus',
                IncomeService::PERIOD_RETURN_LOG => 'Yunshop\PeriodReturn\model\PeriodLog',
                IncomeService::POINT_ACTIVITY_AWARD_LOG => 'Yunshop\PointActivity\Common\Models\PointActivityAwardLog',
                IncomeService::RED_PACKET_RECEIVE_LOGS => 'Yunshop\RedPacket\models\ReceiveLogsModel',
                IncomeService::RED_PACKET_BONUS => 'Yunshop\RedPacket\models\BonusReceiveLogsModel',
                IncomeService::REVENUE_AWARD_BONUS => 'Yunshop\RevenueAward\model\IncomeBonusLogModel',
                IncomeService::ROOM_BONUS_LOG => 'Yunshop\Room\models\BonusLog',
                IncomeService::SALES_COMMISSION => 'Yunshop\SalesCommission\models\SalesCommission',
                IncomeService::SCORING_DIVIDEND => 'Yunshop\ScoringDividend\models\ScoringDividendModel',
                IncomeService::SCORING_REWARD => 'Yunshop\ScoringDividend\models\ScoringRewardModel',
                IncomeService::SERVICE_STATION_DIVIDEND => 'Yunshop\ServiceStation\models\ServiceStationDividend',
                IncomeService::SHARE_CHAIN_AWARD_LOG => 'Yunshop\ShareChain\common\model\ShareChainAwardLog',
                IncomeService::SHAREHOLDER_DIVIDEND => 'Yunshop\ShareholderDividend\models\ShareholderDividendModel',
                IncomeService::RETURN_SINGLE_LOG => 'Yunshop\SingleReturn\models\ReturnSingleLog',
                IncomeService::STORE_CASHIER_ORDER => 'Yunshop\StoreCashier\common\models\CashierOrder',
                IncomeService::STORE_CASHIER_STORE_ORDER => 'Yunshop\StoreCashier\common\models\StoreOrder',
                IncomeService::STORE_CASHIER_BOSS_ORDER => 'Yunshop\StoreCashier\common\models\BossOrder',
                IncomeService::TEAM_MANAGE_BONUS => 'Yunshop\TeamManage\common\model\Bonus',
                IncomeService::TEAM_MANAGEMENT_LOG => 'Yunshop\TeamManagement\models\TeamManagementLogModel',
                IncomeService::TEAM_RETURN_LOG => 'Yunshop\TeamReturn\models\TeamReturnLog',
                IncomeService::TEAM_REWARDS_ORDER => 'Yunshop\TeamRewards\common\models\TeamRewardsOrderModel',
                IncomeService::TEAM_MEMBER_TASKS => 'Yunshop\TeamRewards\common\models\TeamMemberTasksModel',
                IncomeService::TEAM_SALES_BONUS => 'Yunshop\TeamSales\common\models\TeamSalesModel',
                IncomeService::LECTURER_REWARD_LOG => 'Yunshop\VideoDemand\models\LecturerRewardLogModel',
                IncomeService::VIDEO_SHARE_BONUS => 'Yunshop\VideoShare\common\model\Bonus',
                IncomeService::WEIGHTED_DIVIDEND => 'Yunshop\WeightedDividend\models\RewardModel',
                IncomeService::AUCTION_INCOME => 'Yunshop\Auction\models\AuctionOrderModel',
                IncomeService::AUCTION_ENDORSEMENT => 'Yunshop\Auction\models\AuctionEndorsement',
                IncomeService::TEAM_SZTT => 'Yunshop\TeamSztt\models\TeamSzttModel',
                IncomeService::TEAM_SIDEWAYS_WITHDRAW => 'Yunshop\TeamSideways\model\SidewaysWithdrawLog',
                IncomeService::COLLAGE_BONUS => 'Yunshop\Collage\models\BonusModel',
                IncomeService::COLLAGE_AREA_DIVIDEND => 'Yunshop\Collage\models\AreaDividendModel',
                IncomeService::CONSUME_RED_PACKET => 'Yunshop\ConsumeRedPacket\Common\Models\PondReceiveModel',
                IncomeService::SNATCH_REWARD => 'Yunshop\SnatchRegiment\models\SnatchReward',
                IncomeService::REGIONAL_REWARD => 'Yunshop\RegionalReward\Common\models\RecordModel',
                IncomeService::CLOUD_WAREHOUSE => 'Yunshop\CloudWarehouse\models\CloudWarehouseDividend',
                IncomeService::STORE_SHAREHOLDER => 'Yunshop\StoreShareholder\model\ShareholderBonusInfo',
                IncomeService::ASSEMBLE => 'Yunshop\Assemble\Common\Models\OrderBonusModel',
                IncomeService::ASSEMBLE_WAGES => 'Yunshop\Assemble\Common\Models\OrderWagesModel',
                IncomeService::PERIOD_RETURN => 'Yunshop\PeriodReturn\model\PeriodLog',
                IncomeService::TEAM_FJYX => 'Yunshop\TeamFjyx\models\TeamFjyxModel',
                IncomeService::RECOMMENDER => 'Yunshop\Recommender\models\RewardModel',
                IncomeService::SUPERIOR_REWARD => 'Yunshop\SuperiorReward\models\OrderBuyModel',
                IncomeService::SELL_AWARD => 'Yunshop\SellAward\model\AwardLog',
                IncomeService::EQUITY_REWARD => 'Yunshop\EquityReward\models\EquityReward',
                IncomeService::STORE_CARD_INCOME => 'Yunshop\StoreCard\Common\Models\CardIncomeModel',
                IncomeService::CIRCLE_VIDEO_BOUNS => 'Yunshop\Circle\common\model\CircleVideoBonus',
                IncomeService::CIRCLE_INVITATION_REWARD => 'Yunshop\Circle\common\model\CircleReward',
                IncomeService::CIRCLE_ADD => 'Yunshop\Circle\common\model\CirclePayLog',
                IncomeService::CONSUME_REWARD => 'Yunshop\ConsumeReward\models\RewardLog',
                IncomeService::AGENCY_REWARD => 'Yunshop\Agency\models\AgencyModel',
                IncomeService::RANKING_AWARD => 'Yunshop\CommissionRanking\models\CommissionOrder',
                IncomeService::RESERVE_FUND => 'Yunshop\ReserveFund\models\ReserveFundBonusModel',
                IncomeService::ROOM_CODE => 'Yunshop\Room\models\CodeUsed',
                IncomeService::SCHOOL_COMPANY => 'Yunshop\SchoolCompany\models\IncomeModel',
                IncomeService::LIVE_INSTALL => 'Yunshop\LiveInstall\models\WorkerReward',
                IncomeService::INVEST_PEOPLE => 'Yunshop\InvestPeople\models\Dividend',
                IncomeService::NEW_RETAIL_REWARD => 'Yunshop\NewRetail\models\RewardModel',
                IncomeService::NEW_RETAIL_RIGHT_REWARD => 'Yunshop\NewRetail\models\StockRightReward',
                IncomeService::STORE_BUSINESS_ALLIANCE_RECOMMEND => 'Yunshop\StoreBusinessAlliance\models\RecommendAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_BUSINESS => 'Yunshop\StoreBusinessAlliance\models\BusinessAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_SERVICE => 'Yunshop\StoreBusinessAlliance\models\ServiceAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_OPERATION => 'Yunshop\StoreBusinessAlliance\models\OperationAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_PRICE => 'Yunshop\StoreBusinessAlliance\models\PriceDifferenceAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_STORE => 'Yunshop\StoreBusinessAlliance\models\StoreAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_SUPPORT => 'Yunshop\StoreBusinessAlliance\models\SupportAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_TASK => 'Yunshop\StoreBusinessAlliance\models\TaskAward',
                IncomeService::STORE_BUSINESS_ALLIANCE_TEAM => 'Yunshop\StoreBusinessAlliance\models\TeamAward',
                IncomeService::PLUGIN_PARENT_PAYMENT_COMMISSION => 'Yunshop\ParentPayment\common\models\BehalfOrderModel',
                IncomeService::STORE_REWARDS => 'Yunshop\StoreRewards\common\models\StoreRewardsRecord',
                IncomeService::STORE_PROJECTS_ORDER => 'Yunshop\StoreProjects\common\models\ProjectsOrderService',
                IncomeService::ZHP_REWARD => 'Yunshop\ZhpGroupLottery\models\ZhpRewardLogModel',
                IncomeService::STORE_BALANCE_AWARD => 'Yunshop\StoreBalance\model\BalanceAward',
                IncomeService::COMMISSION_EXTRA_BONUS => 'Yunshop\CommissionExtra\models\CommissionExtraBonusModel',
                IncomeService::MERCHANT_MEETING_BONUS => 'Yunshop\MerchantMeeting\models\BonusModel',
                IncomeService::GRATITUDE_REWARD_BONUS => 'Yunshop\GratitudeReward\models\BonusModel',
                IncomeService::QQ_ADVERTISE_REWARD => 'Yunshop\QqAdvertise\models\QqAdvertiseRewardLogModel',
                IncomeService::ORDER_QUANTITY_BONUS => 'Yunshop\OrderQuantityBonus\common\models\BonusRecords',
                IncomeService::STAR_STORE => 'Yunshop\StarStore\models\StarStoreRewardLogModel',
                IncomeService::DEALER_TASK_REWARD => 'Yunshop\DealerTaskReward\common\models\Reward',
                IncomeService::NEW_WEIGHTED_DIVIDEND => 'Yunshop\NewWeightedDividend\common\models\DetailModel',
                IncomeService::AGENCY_SUBSIDY_REWARD => 'Yunshop\AgencySubsidy\common\models\RewardsModel',
                IncomeService::SALESMAN_DIVIDEND => 'Yunshop\SalesmanDividend\models\SalesmanDividendRewardLogModel',
                IncomeService::FIND_POINT_REWARD => 'Yunshop\FindPointReward\common\models\RewardsModel',
                IncomeService::SHARE_PARTNER_DIVIDEND => 'Yunshop\SharePartner\models\Dividend',
                IncomeService::XZHH_POOL_REWARD => 'Yunshop\XzhhBonusPool\common\models\RewardsModel',
                IncomeService::FIGHT_GROUPS_LOTTERY => 'Yunshop\FightGroupsLottery\models\FightGroupsLotteryRewardModel',
                IncomeService::DISTRIBUTION_INCOME_REWARD => 'Yunshop\DistributionIncome\common\models\RewardsModel',
                IncomeService::LOVE_SPEED_POOL_PLUS => 'Yunshop\LoveSpeedPool\model\PlusRecord',
                IncomeService::PUBLIC_FUND_DIVIDEND => 'Yunshop\PublicFund\common\models\FundAmount',
                IncomeService::CASH_BACK_REWARD => 'Yunshop\CashBack\common\Models\RewardModel',
                IncomeService::ZHP_BONUS_POOL => 'Yunshop\ZhpBonusPool\models\ZhpBonusPoolDividendLogsModel',
                IncomeService::AREA_MERCHANT_BONUS => 'Yunshop\AreaMerchant\common\models\BonusModel',
                IncomeService::FK_DISTRIBUTION => 'Yunshop\FkDistribution\common\models\RewardsModel',
                IncomeService::DISTRIBUTION_APPRECIATION => 'Yunshop\DistributionAppreciation\models\RewardsModel',
                IncomeService::LAWYER_DIVIDEND => 'Yunshop\LawyerPlatform\common\models\LawyerDividend',
                IncomeService::LAWYER_FIRM_DIVIDEND => 'Yunshop\LawyerPlatform\common\models\LawyerFirmDividend',
                IncomeService::SUBSCRIPTION_BONUS => 'Yunshop\Subscription\common\models\BuyModel',
                IncomeService::AGENT_EARNINGS => 'Yunshop\AgentEarnings\models\AgentEarningsOrderRewardRelevanceLogsModel',
                IncomeService::JYK_ORDER => 'Yunshop\JykFindPoint\models\RewardsModel',
                IncomeService::JYK_OTHERS => 'Yunshop\JykFindPoint\models\OthersModel',
                IncomeService::NEWCOMER_FISSION => 'Yunshop\NewcomerFission\models\RewardsModel',
                IncomeService::LOVE_QUEUE => 'Yunshop\LoveQueue\common\models\QueueDetailModel',
                IncomeService::COMMISSION_POINT => 'Yunshop\CommissionPoint\models\CpointCommission',
                IncomeService::COUPON_STORE_INCOME => 'Yunshop\CouponStore\models\Reward',
                IncomeService::SIGN_BUY_MANAGE_AWARD => 'Yunshop\SignBuy\models\SignReward',
                IncomeService::REDPACK_TOOL_INCOME => 'Yunshop\RedpackTool\model\RedpackManage',
                IncomeService::PERFORMANCE_BONUS_NEW => 'Yunshop\PerformanceNew\common\model\PerformanceBonus',
                IncomeService::STOCK_SERVICE_INCOME => 'Yunshop\StockService\models\IncomeModel',
                IncomeService::TASK_PACKAGE_PLATFORM_FLOW_INCOME => "Yunshop\TaskPackage\models\TaskPackagePlatformFlowBonus",
                IncomeService::LINK_MOVE_AWARD                   => "Yunshop\LinkMove\models\LinkMoveReward",
                IncomeService::STATIC_POINT_DIVIDEND             => "Yunshop\StaticPointDividend\models\StaticPointDividendRewardModel",
                IncomeService::WEEKLY_REWARDS                    => "Yunshop\WeeklyRewards\models\RewardsModel",
                IncomeService::CONSIGNMENT                       => "Yunshop\Consignment\common\models\SettlementRecordModel",
                IncomeService::COFFEE_MACHINE_REWARD             => "Yunshop\CoffeeMachine\models\Commission",
                IncomeService::PERFORMANCE_PEER                  => "Yunshop\PerformancePeer\models\Log",
                IncomeService::DISTRIBUTOR_TEAM                  => "Yunshop\DistributorTeam\models\RewardsModel",
                IncomeService::BE_WITHIN_CALL                    => "Yunshop\BeWithinCall\models\Dividend",
                IncomeService::ENERGY_REWARDS                    => "Yunshop\EnergyPool\models\RewardsDetailModel",
                IncomeService::WISE_YUAN_TRADE_YUAN              => "Yunshop\WiseYuanTrade\models\YuanPointRecord",
                IncomeService::REGION_EXTERNAL_REWARD            => "Yunshop\RegionExternalReward\common\models\BaseBonusRecordModel",
                IncomeService::LINK_MOVE_AVERAGE_AWARD => 'Yunshop\linkMoveAverageDividend\models\AverageDividend',
                IncomeService::RANKING_DIVIDEND_REWARD           => 'Yunshop\RankingDividend\models\RewardsModel',
                IncomeService::WISE_YUAN_TRADE_INCOME            => 'Yunshop\WiseYuanTrade\models\OtherAssetsLog',
                IncomeService::TXHL_BONUS_POOL_REWARD            => 'Yunshop\TxBonusPool\models\RewardsModel',
                IncomeService::COMMISSION_PERFORMANCE_REDPACK    => 'Yunshop\CommissionPerformance\models\Redpack',
                IncomeService::COMMISSION_PERFORMANCE            => 'Yunshop\CommissionPerformance\models\DaySettle',
                IncomeService::BALANCE_WITHDRAW_COMMISSION       => 'Yunshop\BalanceWithdrawCommission\models\CommissionLog',
                IncomeService::CONTRIBUTION_REWARD               => 'Yunshop\Contribution\models\RewardsModel',
            ];
            foreach ($classList as $code =>$class) {
                \Illuminate\Support\Facades\DB::table('yz_member_income')->where('incometable_type',$class)->update(['dividend_code'=>$code]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
