<?php

namespace app\common\services;

use app\frontend\modules\update\models\authModel;
use Ixudra\Curl\Facades\Curl;

class AuthCodeService
{
	public static function domian()
	{
		return 'https://yun.yunzmall.com';
	}
	public static function getCode($set, $force_refresh = false)
	{
		if ($force_refresh) {
			$url = static::domian(). '/plugin.json/auth-code';
			Curl::to($url)->withHeader(
				"Authorization: Basic " . base64_encode("{$set['key']}:{$set['secret']}")
			)->get();
		}

		$code = authModel::orderBy('id', 'desc')->value('code');

		return $code;
	}

	public static function requestUrl($path)
	{
		$set = \Setting::get('shop.key');

		try {
			$retry_count = 0;

			do {
				$code = AuthCodeService::getCode($set, $retry_count ? true : false);
				$url = static::domian(). '/' . $path . '/' . $code;
				$content = Curl::to($url)->withHeader(
					"Authorization: Basic " . base64_encode("{$set['key']}:{$set['secret']}")
				)->asJsonResponse(true)->get();

				$retry_count++;
			} while (isset($content['errcode']) && $content['errcode'] == 40001 &&  $retry_count == 1);
		} catch (\Exception $e) {
			return null;
		}

		return $content;
	}

	public static function postUrl($path, $data)
	{
		$set = \Setting::get('shop.key');

		try {
			$retry_count = 0;

			do {
				$code = AuthCodeService::getCode($set, $retry_count ? true : false);
				$url = static::domian() . '/' . $path . '/' . $code;
				$content = Curl::to($url)->withHeader(
					"Authorization: Basic " . base64_encode("{$set['key']}:{$set['secret']}")
				)->withData($data)->asJsonResponse(true)->post();

				$retry_count++;
			} while (isset($content['errcode']) && $content['errcode'] == 40001 &&  $retry_count == 1);
		} catch (\Exception $e) {
			return null;
		}

		return $content;
	}
}