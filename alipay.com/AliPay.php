<?php
/**
 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.3srKIc&treeId=193&articleId=105333&docType=1
 */
class AliPay {

	private $gateway = 'https://mapi.alipay.com/gateway.do?';
	private $input_charset = 'utf-8';

	private $partner = '';
	private $seller_email = '';
	private $seller_name = '';
	private $key = '';
	private $cacert = '';
	private $transport = '';
	private $ali_public_key_path = '';
	private $private_key_path = '';
	private $notify_url = '';

	public function __construct($config) {
		$this->partner             = $config['partner']; //合作身份者id，以2088开头的16位纯数字
		$this->seller_email        = $config['seller_email']; //收款支付宝账号，一般情况下收款账号就是签约账号
		$this->seller_name         = $config['seller_name'];
		$this->key                 = $config['key']; //安全检验码，以数字和字母组成的32位字符
		$this->cacert              = $config['cacert']; //ca证书路径地址
		$this->transport           = $config['transport']; //访问模式,http或https
		$this->ali_public_key_path = $config['ali_public_key_path'];
		$this->private_key_path    = $config['private_key_path'];
		$this->notify_url          = $config['notify_url']; //异步通知地址
	}

	/**
     * 是否是支付宝发出的合法消息
	 *
     * @return 验证结果
     */
	public function verifyResult($values){
		if(empty($values)) {//判断POST来的数组是否为空
			return false;
		}
		return $this->verifyNotifySign($values, $values["sign"], $values['sign_type'])
			&& $this->verifyNotifyId($values["notify_id"]);
	}

	/**
	 * 创建手机网站支付跳转地址
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.U4DnXc&treeId=60&articleId=104790&docType=1
	 *
	 * @param string $out_trade_no 商户订单系统中唯一订单号
	 * @param number $total_fee 支付金额（元）
	 * @param string $subject 订单描述
	 * @param string $return_url 支付后返回地址
	 *
	 * @return string redirect_url
	 */
	public function createWapDirectPayUrl($out_trade_no, $total_fee, $subject, $return_url = "") {
		$str = $this->buildRequestParaToString([
			"service"        => "alipay.wap.create.direct.pay.by.user",
			"partner"        => $this->partner,
			"seller_id"      => $this->partner,
			"payment_type"	 => "1",
			"notify_url"	 => $this->notify_url,
			"return_url"	 => $return_url,
			"out_trade_no"	 => $out_trade_no,
			"subject"	     => $subject,
			"total_fee"	     => $total_fee,
			"_input_charset" => $this->input_charset
		], 'MD5');
		return $this->gateway.$str;
	}

	/**
	 * 创建移动支付请求参数
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.e8ONaV&treeId=59&articleId=103663&docType=1
	 *
	 * @param string $out_trade_no 商户订单系统中唯一订单号
	 * @param number $total_fee 支付金额（元）
	 * @param string $subject 订单描述
	 * @param string $notify_url 异步通知地址
	 *
	 * @return string
	 */
	public function createMobilePayPrameters($out_trade_no, $total_fee, $subject) {
		return $this->buildRequestParaToString([
			"service"           => "mobile.securitypay.pay",
			"partner"           => $this->partner,
			"seller_id"         => $this->seller_email,
			"payment_type"	    => 1,
			"out_trade_no"	    => $out_trade_no,
			"total_fee"	        => $total_fee,
			"subject"	        => $subject,
			"body"	            => $subject,
			"notify_url"	    => $this->notify_url,
			"_input_charset"	=> $this->input_charset
		], 'RSA');
	}

	/**
	 * 批量退款(有密)
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.plCK7B&treeId=60&articleId=104744&docType=1
	 *
	 * @param array[array] [[$trade_no, $refund_fee, $reason],...]
	 * @param string $notify_url
	 *
	 * @return string redirect_url
	 */
	public function createBatchRefundUrl($list, $notify_url = '') {
		$batch_num = 0;
		$detail_data = '';
		foreach($list as $trade) {
			if(!empty($detail_data)) {
				$detail_data .= '#';
			}
			$detail_data .= $trade[0].'^'.$trade[1].'^'.$trade[2];
			$batch_num ++;
		}
		$str = $this->buildRequestParaToString([
			'service'        => 'refund_fastpay_by_platform_pwd',
			'partner'        => $this->partner,
			'seller_email'   => $this->seller_email,
			'refund_date'    => date('Y-m-d H:i:s', time()),
			'batch_no'       => date('Ymd', time()).uniqid(),
			'batch_num'      => $batch_num,
			'detail_data'    => $detail_data,
			"notify_url"	 => $notify_url,
			"_input_charset" => 'utf-8'
		], 'MD5');
		return $this->gateway.$str;
	}

	/**
	 * 批量付款(有密)
	 *
	 * @param array[array] [[$trans_no, $target_id, $target_name, $trans_fee, $trans_reason],...]
	 * @param string $notify_url
	 *
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.OmbJe8&treeId=64&articleId=104804&docType=1
	 */
	 public function createBatchTransUrl($list, $notify_url = '') {
 		$batch_num = 0; //付款总笔数
		$batch_fee = 0; //付款总金额
 		$detail_data = '';
 		foreach($list as $trade) {
 			if(!empty($detail_data)) {
 				$detail_data .= '|';
 			}
 			$detail_data .= $trade[0].'^'.$trade[1].'^'.$trade[2].'^'.$trade[3].'^'.$trade[4];
 			$batch_num ++;
			$batch_fee = $batch_fee + (float) $trade[3];
 		}
 		$str = $this->buildRequestParaToString([
 			'service'        => 'batch_trans_notify',
 			'partner'        => $this->partner,
			'email'          => $this->seller_email,
 			'account_name'   => $this->seller_name,
 			'pay_date'       => date('Ymd', time()),
 			'batch_no'       => date('Ymd', time()).uniqid(),
 			'batch_num'      => $batch_num,
			'batch_fee'      => $batch_fee,
 			'detail_data'    => $detail_data,
 			"notify_url"	 => $notify_url,
 			"_input_charset" => 'utf-8'
 		], 'MD5');
 		return $this->gateway.$str;
 	}

	/**
	 * 单笔交易查询
	 *
	 * @param string $out_trade_no
	 *
	 * $result['is_success'] == 'T' && $result['response']['trade']['trade_status'] == "TRADE_SUCCESS"
	 */
	public function singleTradeQuery($out_trade_no) {
	    $res = $this->buildRequestHttp([
	        "service" => "single_trade_query",
	        "partner" => $this->partner,
	        "out_trade_no" => $out_trade_no,
	        "_input_charset" => $this->input_charset
	    ], 'RSA');
	    return json_decode(json_encode(simplexml_load_string($res)), TRUE);
	}

    /**
     * 获取返回时的签名验证结果
     * @param $para_temp 通知返回来的参数数组
     * @param $sign 返回的签名结果
     * @return 签名验证结果
     */
	function verifyNotifySign($para_temp, $sign, $sign_type) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($para_temp);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);

		$result = false;
		switch ($sign_type) {
			case "MD5" :
				$result = $this->md5Verify($prestr, $this->key, $sign);
				break;
			case "RSA" :
				$result = $this->rsaVerify($prestr, $this->ali_public_key_path, $sign);
				break;
			default :
				$result = false;
		}

		return $result;
	}

    /**
     * 获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
     * @param $notify_id 通知校验ID
     * @return 服务器ATN结果
     * 验证结果集：
     * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
     * true 返回正确信息
     * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
     */
	function verifyNotifyId($notify_id) {
		// HTTP形式消息验证地址
		$veryfy_url = 'http://notify.alipay.com/trade/notify_query.do?';
		$transport = $this->transport;
		if($transport == 'https') {
			// HTTPS形式消息验证地址
			$veryfy_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
		}
		$partner = $this->partner;
		$veryfy_url = $veryfy_url."partner=".$partner."&notify_id=".$notify_id;
		$responseTxt = $this->getHttpResponseGET($veryfy_url, $this->cacert);
		//验证
		//$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
		//isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
		if(preg_match("/true$/i",$responseTxt)) {
			return true;
		}
		return false;
	}

	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * return 签名结果字符串
	 */
	function buildRequestMysign($para_sort, $sign_type) {
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);

		$mysign = "";
		switch ($sign_type) {
			case "MD5" :
				$mysign = $this->md5Sign($prestr, $this->key);
				break;
			case "RSA" :
				$mysign = $this->rsaSign($prestr, $this->private_key_path);
				break;
			default :
				$mysign = "";
		}

		return $mysign;
	}

	/**
     * 生成要请求给支付宝的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组
     */
	function buildRequestPara($para_temp, $sign_type) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($para_temp);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildRequestMysign($para_sort, $sign_type);

		//签名结果与签名方式加入请求提交参数组中
		$para_sort['sign'] = $mysign;
		$para_sort['sign_type'] = $sign_type;

		return $para_sort;
	}

	/**
     * 生成要请求给支付宝的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组字符串
     */
	function buildRequestParaToString($para_temp, $sign_type) {
		//待请求参数数组
		$para = $this->buildRequestPara($para_temp, $sign_type);

		//把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
		$request_data = $this->createLinkstringUrlencode($para);

		return $request_data;
	}

	/**
     * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果
     * @param $para_temp 请求参数数组
     * @return 支付宝处理结果
     */
	function buildRequestHttp($para_temp, $sign_type) {
		$sResult = '';

		//待请求参数数组字符串
		$request_data = $this->buildRequestPara($para_temp, $sign_type);

		//远程获取数据
		$sResult = $this->getHttpResponsePOST($this->gateway, $this->cacert, $request_data, $this->input_charset);

		return $sResult;
	}

	/**
     * 用于防钓鱼，调用接口query_timestamp来获取时间戳的处理函数
	 * 注意：该功能PHP5环境及以上支持，因此必须服务器、本地电脑中装有支持DOMDocument、SSL的PHP配置环境。建议本地调试时使用PHP开发软件
     * return 时间戳字符串
	 */
	function query_timestamp() {
		$url = $this->gateway."service=query_timestamp&partner=".$this->partner."&_input_charset=".$this->input_charset;
		$encrypt_key = "";

		$doc = new DOMDocument();
		$doc->load($url);
		$itemEncrypt_key = $doc->getElementsByTagName( "encrypt_key" );
		$encrypt_key = $itemEncrypt_key->item(0)->nodeValue;

		return $encrypt_key;
	}

	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstring($para) {
		$arg  = "";
		while (list ($key, $val) = each ($para)) {
			$arg.=$key."=".$val."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);

		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

		return $arg;
	}

	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstringUrlencode($para) {
		$arg  = "";
		while (list ($key, $val) = each ($para)) {
			$arg.=$key."=".urlencode($val)."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);

		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

		return $arg;
	}

	/**
	 * 除去数组中的空值和签名参数
	 * @param $para 签名参数组
	 * return 去掉空值与签名参数后的新签名参数组
	 */
	private function paraFilter($para) {
		$para_filter = array();
		while (list ($key, $val) = each ($para)) {
			if($key == "sign" || $key == "sign_type" || $val == "")continue;
			else	$para_filter[$key] = $para[$key];
		}
		return $para_filter;
	}

	/**
	 * 对数组排序
	 * @param $para 排序前的数组
	 * return 排序后的数组
	 */
	private function argSort($para) {
		ksort($para);
		reset($para);
		return $para;
	}

	/**
	 * 远程获取数据，POST模式
	 * 注意：
	 * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
	 * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
	 * @param $url 指定URL完整路径地址
	 * @param $cacert_url 指定当前工作目录绝对路径
	 * @param $para 请求的数据
	 * @param $input_charset 编码格式。默认值：空值
	 * return 远程输出的数据
	 */
	private function getHttpResponsePOST($url, $cacert_url, $para, $input_charset = '') {
		if (trim($input_charset) != '') {
			$url = $url."_input_charset=".$input_charset;
		}
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
		curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
		curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
		curl_setopt($curl,CURLOPT_POST,true); // post传输数据
		curl_setopt($curl,CURLOPT_POSTFIELDS,$para);// post传输数据
		$responseText = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);

		return $responseText;
	}

	/**
	 * 远程获取数据，GET模式
	 * 注意：
	 * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
	 * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
	 * @param $url 指定URL完整路径地址
	 * @param $cacert_url 指定当前工作目录绝对路径
	 * return 远程输出的数据
	 */
	private function getHttpResponseGET($url,$cacert_url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
		curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
		$responseText = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);

		return $responseText;
	}

	/**
	 * 实现多种字符编码方式
	 * @param $input 需要编码的字符串
	 * @param $_output_charset 输出的编码格式
	 * @param $_input_charset 输入的编码格式
	 * return 编码后的字符串
	 */
	private function charsetEncode($input,$_output_charset ,$_input_charset) {
		$output = "";
		if(!isset($_output_charset) )$_output_charset  = $_input_charset;
		if($_input_charset == $_output_charset || $input ==null ) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")) {
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset change.");
		return $output;
	}

	/**
	 * 实现多种字符解码方式
	 * @param $input 需要解码的字符串
	 * @param $_output_charset 输出的解码格式
	 * @param $_input_charset 输入的解码格式
	 * return 解码后的字符串
	 */
	private function charsetDecode($input,$_input_charset ,$_output_charset) {
		$output = "";
		if(!isset($_input_charset) )$_input_charset  = $_input_charset ;
		if($_input_charset == $_output_charset || $input ==null ) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")) {
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset changes.");
		return $output;
	}

	/**
	 * 签名字符串
	 * @param $prestr 需要签名的字符串
	 * @param $key 私钥
	 * return 签名结果
	 */
	private function md5Sign($prestr, $key) {
		$prestr = $prestr . $key;
		return md5($prestr);
	}

	/**
	 * 验证签名
	 * @param $prestr 需要签名的字符串
	 * @param $key 私钥
	 * @param $sign 签名结果
	 * return 签名结果
	 */
	private function md5Verify($prestr, $key, $sign) {
		$prestr = $prestr . $key;
		$mysgin = md5($prestr);
		if ($key == '') return false;
		if ($mysgin == $sign) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * RSA签名
	 * @param $data 待签名数据
	 * @param $private_key_path 商户私钥文件路径
	 * return 签名结果
	 */
	private function rsaSign($data, $private_key_path) {
	    $priKey = file_get_contents($private_key_path);
	    $res = openssl_get_privatekey($priKey);
	    openssl_sign($data, $sign, $res);
	    openssl_free_key($res);
		//base64编码
	    $sign = base64_encode($sign);
	    return $sign;
	}

	/**
	 * RSA验签
	 * @param $data 待签名数据
	 * @param $ali_public_key_path 支付宝的公钥文件路径
	 * @param $sign 要校对的的签名结果
	 * return 验证结果
	 */
	private function rsaVerify($data, $ali_public_key_path, $sign)  {
		$pubKey = file_get_contents($ali_public_key_path);
	    $res = openssl_get_publickey($pubKey);
	    $result = (bool)openssl_verify($data, base64_decode($sign), $res);
	    openssl_free_key($res);
	    return $result;
	}

	/**
	 * RSA解密
	 * @param $content 需要解密的内容，密文
	 * @param $private_key_path 商户私钥文件路径
	 * return 解密后内容，明文
	 */
	private function rsaDecrypt($content, $private_key_path) {
	    $priKey = file_get_contents($private_key_path);
	    $res = openssl_get_privatekey($priKey);
		//用base64将内容还原成二进制
	    $content = base64_decode($content);
		//把需要解密的内容，按128位拆开解密
	    $result  = '';
	    for($i = 0; $i < strlen($content)/128; $i++  ) {
	        $data = substr($content, $i * 128, 128);
	        openssl_private_decrypt($data, $decrypt, $res);
	        $result .= $decrypt;
	    }
	    openssl_free_key($res);
	    return $result;
	}

}
