<?php
class SMSClient {

	private $config = [
		'accountSid'=>'',
		'accountToken'=>''
	];

	/**
	 *
	 * string $accountSid   开发者主账户ACCOUNT SID（登陆官网在管理控制台获取）
	 * string $accountToken 账户授权令牌
	 *
	 */
	public function __construct($params) {
		$this->config = $params + $this->config;
	}

	/**
	 * 通过容联云短信平台发送模板短信
	 *
	 * @param string $to           短信接收端手机号码集合，用英文逗号分开，每批发送的手机号数量不得超过200个
	 * @param string $appId        应用Id
	 * @param string $templateId   模板Id
	 * @param array  $datas        内容数据，用于替换模板中{序号}
	 *
	 * @return string
	 * @link http://www.yuntongxun.com/doc/rest/sms/3_2_2_2.html
	 */
	public function send($to, $appId, $templateId, $datas) {
		$accountSid = $this->accountSid;
		$accountToken = $this->accountToken;
		$batch = date('YmdHis', time());
		$sig =  strtoupper(md5($accountSid . $accountToken . $batch));
		$authen = base64_encode($accountSid . ":" . $batch);
		$url = "https://app.cloopen.com:8883/2013-12-26/Accounts/{$accountSid}/SMS/TemplateSMS?sig={$sig}";
		$headers = [
			'Accept:application/json',
			'Content-Type:application/json;charset=utf-8',
			"Authorization:{$authen}"
		];
		$data = json_encode([
			'to'=>$to,
			'appId'=> $appId,
			'templateId'=> $templateId,
			'datas'=> $datas
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

}
