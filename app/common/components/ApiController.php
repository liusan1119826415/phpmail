<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/28
 * Time: 上午10:49
 */

namespace app\common\components;

use app\common\exceptions\ShopException;
use app\common\exceptions\UniAccountNotFoundException;
use app\common\helpers\Client;
use app\common\helpers\Url;
use app\common\middleware\AuthenticateFrontend;
use app\common\middleware\BasicInformation;
use app\common\models\Member;
use app\common\models\UniAccount;
use app\common\modules\shop\models\Shop;
use app\frontend\modules\member\services\factory\MemberFactory;
use Illuminate\Support\Facades\Redis;

class ApiController extends BaseController
{

    protected $publicController = [];
    protected $publicAction = [];
    protected $ignoreAction = [];

    public function __construct()
	{
		parent::__construct();
		$this->middleware([AuthenticateFrontend::class]);
	}

	/**
     * @throws ShopException
     * @throws UniAccountNotFoundException
     */
    public function preAction()
    {
        parent::preAction();
    }

	public function getPublicController(): array
	{
		return $this->publicController;
	}

	public function getPublicAction(): array
	{
		return $this->publicAction;
	}

	public function getIgnoreAction(): array
	{
		return $this->ignoreAction;
	}

    protected function PreventDuplicateSubmission($request)
    {

        // 构建唯一标识
        $uniqueId = $request->ip() . '|' . $request->path() . '|' . hash('sha256', json_encode($request->all()));

        // Redis 键名称
        $redisKey = "request_id:{$uniqueId}";

        // 检查 Redis 中是否存在该键，存在则拒绝请求，防止重复提交
        if (Redis::exists($redisKey)) {
            return $this->errorJson('请勿重复提交');
        }

        // 设置请求 ID 键，有效期设置为 3 秒（根据需求调整）
        Redis::setex($redisKey, 3, true);

    }
}