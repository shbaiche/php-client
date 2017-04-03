<?php
class SMSClient {

	private $config = [
		'account'=>'',
		'pswd'=>'',
		'proxy_host'=>null,
		'proxy_port'=>0
	];

	/**
	 *
	 * string $account    帐号
	 * string $pswd       密码
	 * string $proxy_host 代理主机(option)
	 * int    $proxy_port 代理端口(option)
	 *
	 */
	public function __construct($params) {
		$this->config = $params + $this->config;
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
            'account'=>$this->config['account'],
            'pswd'=>$this->config['pswd'],
            'needstatus'=>$needStatus
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if(!empty($this->config['proxy_host'])) {
			curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host']);
			curl_setopt($ch, CURLOPT_PROXYPORT, $this->config['proxy_port']);
		}
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

}
