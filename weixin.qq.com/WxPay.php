<?php
class WxPay {

	private $appid = '';
	private $appsecret = '';
	private $mchid = '';
	private $key = '';
	private $sslcert = '';
	private $sslkey = '';
	private $cacert = '';
	private $notify_url = '';

	public function __construct($config) {
		$this->appid      = $config['appid'];
		$this->appsecret  = $config['appsecret'];
		$this->mchid      = $config['mchid'];
		$this->key        = $config['key'];
		$this->sslcert    = $config['sslcert'];
		$this->sslkey     = $config['sslkey'];
		$this->cacert     = $config['cacert'];
		$this->notify_url = $config['notify_url'];
	}

	/**
	 * 验证签名
	 */
	public function verifyResult($values) {
		if(empty($values)) {
			return false;
		}
		$sign = $values['sign'];
		if($this->verifySign($values, $sign)) {
			return $values;
		}
		return false;
	}

	/**
	 * 构造jsapi支付的参数
	 *
	 * @link https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
	 */
	public function buildJsApiParameters($out_trade_no, $total_fee, $body, $openid) {
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'notify_url'=> $this->notify_url,
			'out_trade_no'=> $out_trade_no,
			'total_fee'=> $total_fee,
			'body'=> $body,
			'openid'=> $openid,
			'trade_type'=> "JSAPI"
		]);
		$unifiedOrder = $this->fromXml($res);
		$time = time();
		$values['appId'] = $unifiedOrder["appid"];
		$values['nonceStr'] = $unifiedOrder["nonce_str"];
		$values['package'] = "prepay_id=".$unifiedOrder['prepay_id'];
		$values['timeStamp'] = "$time";
		$values['signType'] = 'MD5';
		ksort($values);
		$values['paySign'] = $this->makeSign($values);//签名
		return $values;
	}

	/**
	 * 构造APP支付的参数
	 *
	 * @link https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
	 */
	public function buildAppParameters($out_trade_no, $total_fee, $body) {
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'notify_url'=> $this->notify_url,
			'out_trade_no'=> $out_trade_no,
			'total_fee'=> $total_fee,
			'body'=> $body,
			'trade_type'=> "APP"
		]);
		$unifiedOrder = $this->fromXml($res);
		$values = [
			"appid" => $unifiedOrder["appid"],
			"partnerid" => $unifiedOrder["mch_id"],
			"prepayid" => $unifiedOrder["prepay_id"],
			"noncestr" => $unifiedOrder["nonce_str"],
			"package" => "Sign=WXPay",
			"timestamp" => time()
		];
		ksort($values);
		$values["sign"] = $this->makeSign($values);
		return $values;
	}

	/**
	 * 查询订单，out_trade_no、transaction_id至少填一个
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_2
	 */
	public function orderQuery($mch_trade_no) {
		$url = "https://api.mch.weixin.qq.com/pay/orderquery";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'out_trade_no'=> $mch_trade_no
		]);
		return $this->fromXml($res);
	}

	/**
	 * 关闭订单
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_3
	 */
	public function orderClose($mch_trade_no) {
 		$url = "https://api.mch.weixin.qq.com/pay/closeorder";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'out_trade_no'=> $mch_trade_no
		]);
		return $this->fromXml($res);
 	}

	/**
	 * 申请退款，out_trade_no、transaction_id至少填一个且
	 * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_4
	 */
	public function refund($out_trade_no, $out_refund_no, $total_fee, $refund_fee) {
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'out_trade_no'=> $out_trade_no,
			'out_refund_no'=> $out_refund_no,
			'total_fee'=> $total_fee,
			'refund_fee'=> $refund_fee,
			'op_user_id'=> $this->mchid
		], true);
		return $this->fromXml($res);
	}

	/**
	 * 查询退款
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_5
	 */
	public function refundQuery($out_refund_no) {
 		$url = "https://api.mch.weixin.qq.com/pay/refundquery";
 		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'out_refund_no'=> $out_refund_no
		]);
		return $this->fromXml($res);
 	}

	/**
	 * 下载对账单
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_6
	 */
	public function downloadBill($bill_date, $bill_type) {
 		$url = "https://api.mch.weixin.qq.com/pay/downloadbill";
 		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
			'bill_date'=> $bill_date,
			'bill_type'=> $bill_type
		]);
		return $this->fromXml($res);
 	}

	/**
	 * 企业付款
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_2
	 */
	public function transfer($partner_trade_no, $amount, $desc, $openid) {
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
		$res = $this->submit($url, [
			'mch_appid'=> $this->appid,
			'mchid'=> $this->mchid,
	        'partner_trade_no'=> $partner_trade_no,
	        'amount'=> $amount,
	        'desc'=> $desc,
			'openid'=> $openid,
	        'check_name'=> 'NO_CHECK'
		], true);
		return $this->fromXml($res);
	}

	/**
	 * 企业付款查询
	 *
	 * https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_3
	 */
	public function transferQuery($partner_trade_no) {
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo";
		$res = $this->submit($url, [
			'appid'=> $this->appid,
			'mch_id'=> $this->mchid,
	        'partner_trade_no'=> $partner_trade_no
		], true);
		return $this->fromXml($res);
	}

	public function submit($url, $options, $useCert = false) {
		$inputObj['spbill_create_ip'] = '1.1.1.1';//终端ip $_SERVER['REMOTE_ADDR']
		$inputObj['nonce_str'] = $this->makeNonceStr();//随机字符串

		$inputObj = $options + $inputObj;
		ksort($inputObj);

		$inputObj['sign'] = $this->makeSign($inputObj);//签名
		$xml = $this->toXml($inputObj);

		return $this->curlPost($url, $xml, $useCert);
	}

	// 将array转为xml
	public function toXml($values) {
    	$xml = "<xml>";
    	foreach ($values as $key=>$val) {
    		if (is_numeric($val)) {
    			$xml.="<".$key.">".$val."</".$key.">";
    		} else {
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml;
	}

    // 将xml转为array
	public function fromXml($xml) {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
	}

	// array参数格式化成url参数
	public function toUrlParams($values) {
		$buff = "";
		foreach ($values as $k => $v) {
			if($k != "sign" && $v != "" && !is_array($v)) {
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}

	// 生成签名
	public function makeSign($values) {
		//签名步骤一：按字典序排序参数
		ksort($values);
		$string = $this->toUrlParams($values);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$this->key;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}

	// 验证签名
	public function verifySign($values, $sign) {
		//签名步骤一：按字典序排序参数
		ksort($values);
		$string = $this->toUrlParams($values);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$this->key;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result == $sign;
	}

	/**
	 *
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public function makeNonceStr($length = 32) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
		}
		return $str;
	}

	/**
	 * 以post方式提交数据到对应的接口url
	 */
	private function curlPost($url, $data, $useCert = false) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		if($useCert) {
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $this->sslcert);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $this->sslkey);
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CAINFO, $this->cacert);

		$res = curl_exec($ch);
		if($res) {
			curl_close($ch);
			return $res;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			throw new Exception("curl error: $error");
		}
	}

}
