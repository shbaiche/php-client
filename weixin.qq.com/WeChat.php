<?php
class WeChat {

	private $appid = '';
	private $appsecret = '';
	private $notify_token = '';
	private $notify_aeskey = '';

	public function __construct($config) {
		$this->appid         = $config['appid'];
		$this->appsecret     = $config['appsecret'];
		$this->notify_token  = $config['notify_token'];
		$this->notify_aeskey = $config['notify_aeskey'];
	}

	/**
	 * 构造获取code的url连接
	 * 通过跳转获取用户的openid，跳转流程如下：
	 * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器
	 * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140842&token=&lang=zh_CN
	 *
	 * @param string $redirectUrl 微信服务器回跳的url，需要url编码
	 * @param string $scope snsapi_base或snsapi_userinfo
	 *
	 * @return 返回构造好的url
	 */
	public function createOauthUrlForCode($redirectUrl, $scope = "snsapi_userinfo", $state = "STATE") {
		$values["appid"] = $this->appid;
		$values["redirect_uri"] = $redirectUrl;
		$values["response_type"] = "code";
		$values["scope"] = $scope;
		$values["state"] = $state;
		$bizStr = http_build_query($values)."#wechat_redirect";
		return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizStr;
	}

	/**
	 * 通过code从公众号平台获取openid和access_token
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140842&token=&lang=zh_CN
 	 *
 	 * @param string $code
	 *
	 * @return 网页授权接口微信服务器返回的数据
	 * 返回样例如下
 	 * {
 	 *  "access_token":"ACCESS_TOKEN",
 	 *  "expires_in":7200,
 	 *  "refresh_token":"REFRESH_TOKEN",
 	 *  "openid":"OPENID",
 	 *  "scope":"SCOPE",
 	 *  "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
 	 * }
 	 * 其中access_token可用于获取共享收货地址
 	 * openid是微信支付jsapi支付接口必须的参数
 	 */
	public function getOAuthInfo($code) {
		// 构造获取openid和access_toke的url地址
		$values["appid"] = $this->appid;
		$values["secret"] = $this->appsecret;
		$values["code"] = $code;
		$values["grant_type"] = "authorization_code";
		$bizStr = http_build_query($values);
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizStr;
		$res = $this->curlGet($url);
		return json_decode($res, true);
	}

	/**
	 * 拉取用户信息(需scope为 snsapi_userinfo)
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140842&token=&lang=zh_CN
	 *
	 * @param string $access_token
	 * @param string $openid
	 *
	 * @return 网页授权接口微信服务器返回的数据
	 */
	public function getUserInfo($access_token, $openid, $lang = 'zh_CN') {
		$urlObj["access_token"] = $access_token;
		$urlObj["openid"] = $openid;
		$urlObj["lang"] = $lang;
		$bizString = http_build_query($urlObj);
		$url = "https://api.weixin.qq.com/sns/userinfo?".$bizString;
		$res = $this->curlGet($url);
		return json_decode($res, true);
	}

	/**
	 * 获取access_token
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140183&token=&lang=zh_CN
	 */
	private function refreshAccessToken() {
		$url = "https://api.weixin.qq.com/cgi-bin/token?".http_build_query([
			'appid' => $this->appid,
			'secret' => $this->appsecret,
			'grant_type' => 'client_credential'
		]);
		$res = $this->curlGet($url);
		return json_decode($res, true);
	}

	protected function getAccessToken() {
		$key = "wx_access_token_".$this->appid;
		$val = AppCache::get($key);
		if(empty($val)) {
			$res = $this->refreshAccessToken();
			$val = $res['access_token'];
			AppCache::put($key, $res['access_token'], $res['expires_in'] + time() - 60);
		}
		return $val;
	}

	private function refreshJsapiTicket() {
		$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?".http_build_query([
			'type' => 'jsapi',
			'access_token' => $this->getAccessToken()
		]);
		$res = $this->curlGet($url);
		return json_decode($res, true);
	}

	private function getJsapiTicket() {
		$key = "wx_jsapi_ticket_".$this->appid;
		$val = AppCache::get($key);
		if(empty($val)) {
			$res = $this->refreshJsapiTicket();
			$val = $res['ticket'];
			AppCache::put($key, $res['ticket'], $res['expires_in'] + time() - 60);
		}
		return $val;
	}

	private function makeNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	/**
	 * 生成jsapi签名
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141115&token=&lang=zh_CN
	 */
	public function createJsapiSignPackage($url) {
		$jsapiTicket = $this->getJsapiTicket();
		$nonceStr = $this->makeNonceStr();
		$timestamp = time();
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$rawString = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
		$signature = sha1($rawString);
		$signPackage = [
			"appId"     => $this->appid,
			"nonceStr"  => $nonceStr,
			"timestamp" => $timestamp,
			"url"       => $url,
			"signature" => $signature,
			"rawString" => $rawString
		];
		return $signPackage;
	}

	/**
	 * 发送模板消息
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1433751277&token=&lang=zh_CN
	 *
	 * @param array $data
	 */
	public function sendTemplateMsg($data) {
		$url ='https://api.weixin.qq.com/cgi-bin/message/template/send?'.http_build_query([
			'access_token'=> $this->getAccessToken()
		]);
		$res = $this->curlPost($url, json_encode($data));
		return json_decode($res, true);
	}

	/**
	 * 自定义菜单查询
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141014&token=&lang=zh_CN
	 */
	public function menuGet() {
		$url ='https://api.weixin.qq.com/cgi-bin/menu/get?'.http_build_query([
			'access_token'=> $this->getAccessToken()
		]);
		$res = $this->curlGet($url);
		return json_decode($res, true);
	}

	/**
	 * 自定义菜单创建
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141013&token=&lang=zh_CN
	 */
	public function menuSet($menu) {
		$url ='https://api.weixin.qq.com/cgi-bin/menu/create?'.http_build_query([
			'access_token'=> $this->getAccessToken()
		]);
		$res = $this->curlPost($url, json_encode($menu, JSON_UNESCAPED_UNICODE));
		return json_decode($res, true);
	}

	/**
	 * 新增临时素材
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1444738726&token=&lang=zh_CN
	 */
	public function mediaUpload($file_path, $type = 'image') {
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/upload?".http_build_query([
			'access_token'=> $this->getAccessToken(),
			'type'=> $type
		]);
		$res = $this->curlUpload($url, $file_path);
		return json_decode($res, true);
	}

	/**
	 * 获取临时素材
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1444738727&token=&lang=zh_CN
	 */
	public function mediaDownload($media_id, $save_to) {
		$url = 'http://file.api.weixin.qq.com/cgi-bin/media/get?'.http_build_query([
			'access_token'=> $this->getAccessToken(),
			'media_id'=> $media_id
		]);
		$content_type = $this->curlDownload($url, $save_to);
		return ['content_type'=> $content_type];
	}

	/**
	 * 创建带参数的二维码
	 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1443433542&token=&lang=zh_CN
	 */
	public function qrcodeCreate($scene) {
		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?".http_build_query([
			'access_token'=> $this->getAccessToken()
		]);
		$res = $this->curlPost($url, json_encode([
			"action_name"=>"QR_LIMIT_STR_SCENE",
			"action_info"=>[
				"scene"=>[
					"scene_str"=> $scene
				]
			]
		]));
		return json_decode($res, true);
	}

	protected function curlGet($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$res = curl_exec($ch);
		curl_close($ch);
		return $res;
	}

	protected function curlPost($url, $data) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$res = curl_exec($ch);
		curl_close($ch);
		return $res;
	}

	protected function curlUpload($url, $file_path) {
		$fields = array(
			'file_contents'=> new \CURLFile($file_path)
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		$res = curl_exec($ch);
		curl_close($ch);
		return $res;
	}

	protected function curlDownload($url, $file_path) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$content = curl_exec($ch);
		$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);
		file_put_contents($file_path, $content);
		return $content_type;
	}

}
