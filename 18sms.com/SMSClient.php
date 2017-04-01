<?php
class SMSClient {

	/**
	 * 通过示远短信平台发送短信
	 *
	 * @param string $mobile  手机号
	 * @param string $msg     短信内容(控制在70个字符内,使用URL方式编码为UTF-8格式)
	 * @param string $account 帐号
	 * @param string $pswd    密码
	 *
	 * @return string
	 * @link http://www.18sms.com/Document
	 */
	public static function send($mobile, $msg, $account, $pswd, $needStatus = false) {
        $url = "http://send.18sms.com/msg/HttpBatchSendSM?".http_build_query([
			'mobile'=>$mobile,
			'msg'=>$msg,
            'account'=>$account,
            'pswd'=>$pswd,
            'needstatus'=>$needStatus
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
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
