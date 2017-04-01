<?php
class SMSClient {

	private $account = "";
	private $pswd = "";
	private $proxy = null;

	/**
	 *
	 * @param string $account 帐号
	 * @param string $pswd    密码
	 *
	 */
	public function __construct($account, $pswd, $proxy = null) {
		$this->account = $account;
		$this->pswd = $pswd;
		$this->proxy = $proxy;
	}

	/**
	 * 通过示远短信平台发送短信
	 *
	 * @param string $mobile  手机号
	 * @param string $msg     短信内容(控制在70个字符内,使用URL方式编码为UTF-8格式)
	 *
	 * @return string
	 * @link http://www.18sms.com/Document
	 */
	public function send($mobile, $msg, $needStatus = false) {
        $url = "http://send.18sms.com/msg/HttpBatchSendSM?".http_build_query([
			'mobile'=>$mobile,
			'msg'=>$msg,
            'account'=>$this->account,
            'pswd'=>$this->pswd,
            'needstatus'=>$needStatus
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if(!empty($this->proxy)) {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy['host']);
			curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
		}
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

}
