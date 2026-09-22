<?php

namespace app\exports;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\helpers\Cache;
use app\common\models\Address;
use app\common\models\Goods;
use app\common\models\MemberLevel;
use app\common\models\SearchFiltering;
use app\common\models\Street;
use EasyWeChat\Factory;
use Ixudra\Curl\Facades\Curl;
use Yunshop\EnergyMachine\models\MachineRole;

abstract class PluginExportService
{

    const USABLE = ['shopOrder', 'shopOrderOld', 'shopOrderTeamDividend'];
    const CATEGORY_NAME = '';

    abstract function getColumn(): array;

    abstract function getValue($item): array;

    abstract function categoryName(): string;

    public function query($query)
    {
        return $query;
    }

    public function usable($type): bool
    {
        if (in_array($type, self::USABLE)) {
            return true;
        }
        return false;
    }

}