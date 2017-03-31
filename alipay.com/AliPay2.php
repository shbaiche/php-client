<?php
/**
 * 接口调用说明
 *
 * @link https://doc.open.alipay.com/doc2/detail.htm?treeId=200&articleId=105351&docType=1
 */
class AliPay2 {

	private $options = [
		'app_id'              => '',
		'ali_public_key_path' => '',
		'private_key_path'    => '',
		'notify_url'          => ''
	];

	public function __construct($options) {
		$this->options = $options + $this->options;
	}

	/**
	 * 验证交易通知
	 *
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.j2mElF&treeId=203&articleId=105286&docType=1
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.OK1UyH&treeId=193&articleId=105301&docType=1
	 */
	public function tradeNotifyVerify($params) {
		$sign = $params['sign'];
		$sign_type = $params['sign_type'];
		unset($params['sign']);
		unset($params['sign_type']);
		return $this->verifySign($params, $sign, $sign_type);
	}

	/**
	 * 外部商户创建订单并支付--手机网站支付
	 *
	 * @link https://doc.open.alipay.com/doc2/detail.htm?treeId=203&articleId=105463&docType=1
	 */
	public function tradeWapPay($out_trade_no, $total_amount, $subject) {
		$url = 'https://openapi.alipay.com/gateway.do';
		$params = [
			'app_id'=> $this->options['app_id'],
			'method'=> 'alipay.trade.wap.pay',
			'charset'=> 'utf-8',
			'sign_type'=> 'RSA',
			'timestamp'=> date('Y-m-d H:i:s', time()),
			'version'=> '1.0',
			'notify_url'=> $this->options['notify_url'],
			'biz_content'=> json_encode([
				'out_trade_no' => $out_trade_no,
				'total_amount' => $total_amount,
				'subject' => $subject,
				'product_code' => 'QUICK_WAP_PAY'
			])
		];
		$params['sign'] = $this->makeSign($params);
		return $url.'?'.http_build_query($params);
	}

	/**
	 * 外部商户创建订单并支付--App支付
	 *
	 * @link https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.PaDy3k&treeId=193&articleId=105465&docType=1
	 */
	public function tradeAppPay($out_trade_no, $total_amount, $subject) {
		$url = 'https://openapi.alipay.com/gateway.do';
		$params = [
			'app_id'=> $this->options['app_id'],
			'method'=> 'alipay.trade.app.pay',
			'charset'=> 'utf-8',
			'sign_type'=> 'RSA',
			'timestamp'=> date('Y-m-d H:i:s', time()),
			'version'=> '1.0',
			'notify_url'=> $this->options['notify_url'],
			'biz_content'=> json_encode([
				'out_trade_no' => $out_trade_no,
				'total_amount' => $total_amount,
				'subject' => $subject,
				'product_code' => 'QUICK_MSECURITY_PAY'
			])
		];
		$params['sign'] = $this->makeSign($params);
		return http_build_query($params);
	}

	/**
	 * 统一收单交易查询
	 *
	 * @param string $out_trade_no
	 *
	 * @link https://doc.open.alipay.com/doc2/apiDetail.htm?apiId=757&docType=4
	 */
	function tradeQuery($out_trade_no) {
		return $this->exec('alipay.trade.query', [
			'out_trade_no' => $out_trade_no
		]);
	}

	/**
	 * 统一收单交易退款
	 *
	 * @param string $out_trade_no
	 * @param string $out_request_no
	 * @param number $refund_amount
	 * @param string $refund_reason
	 *
	 * @link https://doc.open.alipay.com/doc2/apiDetail.htm?apiId=759&docType=4
	 */
	function tradeRefund($out_trade_no, $out_request_no, $refund_amount, $refund_reason) {
		return $this->exec('alipay.trade.refund', [
			'out_trade_no' => $out_trade_no,
			'out_request_no' => $out_request_no,
			'refund_amount' => $refund_amount,
			'refund_reason' => $refund_reason
		]);
	}

	/**
	 * 统一收单交易退款查询
	 *
	 * @param string $out_trade_no
	 * @param string $out_request_no
	 *
	 * @link https://doc.open.alipay.com/doc2/apiDetail.htm?docType=4&apiId=1049
	 */
	function tradeRefundQuery($out_trade_no, $out_request_no) {
		return $this->exec('alipay.trade.fastpay.refund.query', [
			'out_trade_no' => $out_trade_no,
			'out_request_no' => $out_request_no
		]);
	}

	protected function exec($method, $biz_content) {
		$url = 'https://openapi.alipay.com/gateway.do';
		$params = [
			'app_id'=> $this->options['app_id'],
			'method'=> $method,
			'charset'=> 'utf-8',
			'sign_type'=> 'RSA',
			'timestamp'=> date('Y-m-d H:i:s', time()),
			'version'=> '1.0',
			'biz_content'=> json_encode($biz_content)
		];
		$params['sign'] = $this->makeSign($params);
		return $this->curl($url, $params);
	}

	private function makeStringToBeSigned($params) {
		ksort($params);
		$stringToBeSigned = "";
		$i = 0;
		foreach ($params as $k => $v) {
			if (!empty($v) && "@" != substr($v, 0, 1)) {
				// 转换成目标字符集
				//$v = $this->characet($v, $this->postCharset);
				if ($i == 0) {
					$stringToBeSigned .= "$k" . "=" . "$v";
				} else {
					$stringToBeSigned .= "&" . "$k" . "=" . "$v";
				}
				$i++;
			}
		}
		unset ($k, $v);
		return $stringToBeSigned;
	}

	function makeSign($params, $signType = "RSA", $priKey = null) {
		$stringToBeSigned = $this->makeStringToBeSigned($params);
		if(empty($priKey)) {
			$priKey = file_get_contents($this->options['private_key_path']);
		}
		$res = openssl_get_privatekey($priKey);
		if ("RSA2" == $signType) {
			openssl_sign($stringToBeSigned, $sign, $res, OPENSSL_ALGO_SHA256);
		} else {
			openssl_sign($stringToBeSigned, $sign, $res);
		}
		openssl_free_key($res);
		return base64_encode($sign);
	}

	function verifySign($params, $sign, $signType = "RSA", $pubKey = null) {
		$stringToBeSigned = $this->makeStringToBeSigned($params);
		if(empty($pubKey)) {
			$pubKey = file_get_contents($this->options['ali_public_key_path']);
		}
		$res = openssl_get_publickey($pubKey);
		if ("RSA2" == $signType) {
			$result = (bool)openssl_verify($stringToBeSigned, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
		} else {
			$result = (bool)openssl_verify($stringToBeSigned, base64_decode($sign), $res);
		}
		openssl_free_key($res);
		return $result;
	}

	protected function getMillisecond() {
		list($s1, $s2) = explode(' ', microtime());
		return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
	}

	protected function curl($url, $postFields = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$postBodyString = "";
		$encodeArray = Array();
		$postMultipart = false; //文件上传用multipart/form-data，否则用www-form-urlencoded

		if (is_array($postFields) && 0 < count($postFields)) {
			foreach ($postFields as $k => $v) {
				if ("@" != substr($v, 0, 1)) { //判断是不是文件上传
					$postBodyString .= "$k=" . urlencode($v) . "&";
					$encodeArray[$k] = $v;
				} else {
					$postMultipart = true;
					$encodeArray[$k] = new \CURLFile(substr($v, 1));
				}
			}
			unset ($k, $v);
			curl_setopt($ch, CURLOPT_POST, true);
			if ($postMultipart) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeArray);
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
			}
		}

		$postCharset = "UTF-8";
		if ($postMultipart) {
			$headers = array('content-type: multipart/form-data;charset=' . $postCharset . ';boundary=' . $this->getMillisecond());
		} else {
			$headers = array('content-type: application/x-www-form-urlencoded;charset=' . $postCharset);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$reponse = curl_exec($ch);
		if (curl_errno($ch)) {
			throw new Exception(curl_error($ch), 0);
		} else {
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (200 !== $httpStatusCode) {
				throw new Exception($reponse, $httpStatusCode);
			}
		}

		curl_close($ch);
		return $reponse;
	}

}
