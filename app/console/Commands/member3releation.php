<?php

namespace app\console\Commands;

use app\common\events\member\RegisterByAgent;
use app\common\models\member\MemberChildren;
use app\common\models\member\MemberParent;
use app\common\models\MemberShopInfo;
use app\frontend\modules\member\models\MemberModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class member3releation extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'member:3release {uniacid}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '导入会员3级关系';


	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$uniacid = $this->argument('uniacid');
		\YunShop::app()->uniacid = \Setting::$uniqueAccountId = $uniacid;
		$this->info('重置3关系链开始时间：'.Carbon::now()->toDateTimeLocalString());
		$this->process();
		$this->info('重置3关系链结束时间：'.Carbon::now()->toDateTimeLocalString());
	}

	private function process()
	{
		set_time_limit(-1);
		ini_set('memory_limit',-1);

		$parent_list = MemberShopInfo::pluck('parent_id','member_id')->toArray();

		DB::beginTransaction();

		try {
			foreach (array_chunk($parent_list,10000,true) as $parent) {
				$relation = '';

				foreach ($parent as $member_id => $parent_id) {
					$current_parent_id = $parent_id;
					$i = 1;

					while ($current_parent_id != 0 && $i <= 3) {
						$relation .= $current_parent_id . ',';

						$member_parent[$member_id] = [
							'member_id' => $member_id,
							'relation' => rtrim($relation, ','),
						];

						$current_parent_id = $parent_list[$current_parent_id];
						$i++;
					}

					if ($current_parent_id == 0 || $i > 3) {
						$relation = '';
						continue;
					}
				}

				foreach (array_chunk($member_parent,10000) as $value) {
					foreach ($value as $rows) {
						$this->info('更新会员：'. $rows['member_id']);
						MemberShopInfo::where('member_id',$rows['member_id'])->update(['relation'=>$rows['relation']]);
					}
				}

				unset($member_parent);
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();
			$this->info('重置关系链失败：'.$e->getMessage());
		}
	}


}