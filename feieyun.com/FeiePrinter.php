<?php
class FeiePrinter {

	private $sn = "";
	private $key = "";
	private $host = "";
	private $proxy = null;

	/**
	 *
	 * @param string $sn      打印机编号
	 * @param string $key     密钥
	 * @param string $host    购买机器后,联系客服获取即可
	 *
	 */
	public function __construct($sn, $key, $host, $proxy = null) {
		$this->sn = $sn;
		$this->key = $key;
		$this->host = $host;
		$this->proxy = $proxy;
	}

	/**
	 * 通过飞鹅云进行打印
	 * 58mm的机器,一行打印16个汉字,32个字母
	 * 80mm的机器,一行打印24个汉字,48个字母
	 * 标签说明：
	 * "<BR>"为换行符,
	 * "<CB></CB>"为居中放大,
	 * "<B></B>"为放大,
	 * "<C></C>"为居中,
	 * "<L></L>"为字体变高
	 * "<W></W>"为字体变宽,
	 * "<QR></QR>"为二维码
	 * "<CODE>"为条形码,后面接12个数字
	 *
	 * @param string $content 打印内容(3500字节以内)
	 * @param string $times   打印联数（同一订单，打印的次数）
	 *
	 * @return string
	 * @link http://www.feieyun.com/document.jsp
	 */
	public function print($content, $times = 1) {
		$url = 'http://'.$this->host.'/FeieServer/printOrderAction';
		$data = http_build_query([
			'sn'=>$this->sn,
			'key'=>$this->key,
			'printContent'=>$content,
			'times'=>$times
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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
