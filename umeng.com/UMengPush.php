<?php
class UMengPush {

	/**
	 * 通过友盟平台推送ios通知
	 *
	 * @link http://dev.umeng.com/push/android/api-doc#2
	 */
	public static function ios($appkey, $appsecret, $device_tokens, $alert, $badge = 1, $sound = 'default', $intent = '', $production_mode = true) {
		$post_path = 'http://msg.umeng.com/api/send';
		$data = json_encode([
			'appkey'=> $appkey,
			'timestamp'=> time(),
			'type'=> count($device_tokens) > 1 ? 'listcast' : 'unicast',
			'device_tokens'=>implode(',', $device_tokens),
			'production_mode'=> $production_mode,
			'payload'=>[
				'aps'=>[
					'alert'=>$alert,
					'badge'=>$badge,
					'sound'=>$sound
				],
				'i'=>$intent
			]
		]);
		$sign = md5("POST" . $post_path . $data . $appsecret);
		$url = $post_path.'?sign=' . $sign;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if(defined('MY_PROXY_HOST') && defined('MY_PROXY_PORT')) {
			curl_setopt($ch, CURLOPT_PROXY, MY_PROXY_HOST);
			curl_setopt($ch, CURLOPT_PROXYPORT, MY_PROXY_PORT);
		}
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	/**
	 * 通过友盟平台推送android通知
	 *
	 * @link http://dev.umeng.com/push/android/api-doc#2
	 */
	public static function android($appkey, $appsecret, $device_tokens, $title, $text, $sound = '', $intent = '', $expire_time = null) {
		$post_path = 'http://msg.umeng.com/api/send';
		$data = json_encode([
			'appkey'=> $appkey,
			'timestamp'=> time(),
			'type'=> count($device_tokens) > 1 ? 'listcast' : 'unicast',
			'device_tokens'=>implode(',', $device_tokens),
			'payload'=>[
				'display_type' => 'notification',
				'body' => [
					'ticker'=> $title, //通知栏提示文字
					'title'=> $title, // 通知标题
					'text'=> $text, // 通知文字描述
					'sound'=> $sound,
					'after_open'=> 'go_custom'
				],
				'extra'=> [
					'i'=> $intent
				]
			],
			'policy'=> [
				'expire_time'=> empty($expire_time) ? date("Y-m-d H:i:s", time() + 7200) : $expire_time
			]
		]);
		$sign = md5("POST" . $post_path . $data . $appsecret);
		$url = $post_path.'?sign=' . $sign;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if(defined('MY_PROXY_HOST') && defined('MY_PROXY_PORT')) {
			curl_setopt($ch, CURLOPT_PROXY, MY_PROXY_HOST);
			curl_setopt($ch, CURLOPT_PROXYPORT, MY_PROXY_PORT);
		}
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

}
