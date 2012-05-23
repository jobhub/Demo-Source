<?php
/**
 * Класс проверки ссылки.
 * @copyright izra.ru
 *
 */
class checker {
	private $ses = array(
		'http://go.mail.ru/search?q='
//		'http://yandex.ru/yandsearch?text=',
	);
	private $requests = array(
		'url="%s"|url="www.%s"',
		'"%s"<<(url="www.%s"|url="%s")',
	);
	private $last_se = 0;
	private $uas = array();
	private $interfaces = array();
	private $last_interface = 0;
	private $proxies = array();
	private $last_proxy = 0;
	private $request;

	var $referer = 'http://yandex.ru/';
	var $ua = 'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9a3pre) Gecko/20070330';
	var $url, $date, $ankor;


	/**
	 * Конструктор класса.
	 *
	 * @param object $cfg
	 */
	public function __construct($cfg) {
		$this->uas = $cfg->ua;
		$this->interfaces = array('') + $cfg->ips;
		$this->proxies = array('') + $cfg->proxies;
	}

	/**
	 * Создание запроса.
	 *
	 * @param string $url
	 * @param int $type
	 * @param string $ankor
	 */
	private function make_url($url, $ankor=null) {
		$url = preg_replace("/^(http\:\/\/)?(www\.)?/", "", $url);
		$url = preg_replace("/\/$/", '', $url);

		//	Добавление ПС в строку запроса.
		$request = $this->ses[$this->last_se];
		//	Добавление строки в строку запроса.
		if (is_null($ankor))
			$request .= urlencode(sprintf($this->requests[0], $url, $url));
		else
			$request .= urlencode(sprintf($this->requests[1], preg_replace("/#([^#]+)#/", '', $ankor), $url, $url));
		return $request;
	}
	
	
	public function __ckeck($url, $date, $ankor) {
		$this->url = $url;
		$this->date = $date;
		$this->ankor = $ankor;

		$this->change_ua();
		$this->change_se();
		$this->change_interface();

		$site_index = 0;
		$link_index = 0;

		switch ($this->last_se) {
			case 0:
				if (time() >= $this->date+45*24*3600) {
					$request = $this->make_url($url, $ankor);
					$link_index = $this->check_mail($request);
					if ($link_index == 1)
						$site_index = 1;
					elseif ($link_index !== false) {
						usleep(900);
						$request = $this->make_url($url);
						$site_index = $this->check_mail($request);
					}
				} else {
					$request = $this->make_url($url);
					$site_index = $this->check_mail($request);
				}
			break;
			case 1:
				$site_index = $this->check_yandex();
				if ($this->date+45*24*3600 <= time()) {
					usleep(900);
					$this->make_url($this->ankor);
					$link_index = $this->check_yandex();
				}
			break;
		}
		if ($site_index === false || $link_index === false)
			return false;
		return array($site_index, $link_index, $this->interfaces[$this->last_interface], $this->proxies[$this->last_proxy]);
	}

	private function get_html($request) {
		$ch = curl_init($request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_REFERER, $this->referer);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
		if ($this->interfaces[$this->last_interface] != '')
			curl_setopt($ch, CURLOPT_INTERFACE, $this->interfaces[$this->last_interface]);
		$return=curl_exec($ch);
		curl_close($ch);
		return $return;
	}

	/**
	 * Смена юзерагента.
	 *
	 */
	private function change_ua() {
		if (is_array($this->uas))
			$this->ua = trim($this->uas[rand(0, count($this->uas)-1)]);
	}

	/**
	 * Смена прокси-сервера
	 *
	 */
	private function change_proxy() {
		if (is_array($this->proxies) && count($this->proxies) > 0) {
			if (!isset($this->proxies[$this->last_proxy+1]))
				$this->last_proxy = 0;
			else
				$this->last_proxy++;
		}
	}

	/**
	 * Смена интерфейса
	 *
	 */
	private function change_interface() {
		if (is_array($this->interfaces) && count($this->interfaces) > 0) {
			if (!isset($this->interfaces[$this->last_interface+1]))
				$this->last_interface = 0;
			else
				$this->last_interface++;
		}
	}

	/**
	 * Смена ПС
	 *
	 */
	private function change_se() {
		if (!isset($this->ses[$this->last_se+1]))
			$this->last_se = 0;
		else
			$this->last_se++;
	}

	/**
	 * Парсинг маил.ру
	 *
	 */
	private function check_mail($request) {
		//	<!-- NOT FOUND: BEGIN -->
		//	<!-- FOUND: BEGIN -->
		$html = $this->get_html($request);
		/*echo $request."\n";
		var_dump($html);*/
		if (preg_match("/<!-- NOT FOUND: BEGIN -->/", $html)) {
			return 2;
		} elseif (preg_match("/<!-- FOUND: BEGIN -->/", $html)) {
			return 1;
		} else {
//			echo $html;
			return false;
		}
	}

	/**
	 * Парсинг Яндекс.ру
	 *
	 */
	private function check_yandex() {
		//	Вы робот
		//	Искомая комбинация слов нигде не встречается
		//	href="url"
		$html = $this->get_html();
		if (preg_match("/Вы робот/i", $html)) {
			echo $html;
			return false;
		} elseif (preg_match("/href=\"http:\/\/www\.".preg_replace("/\//", "\/", $this->url)."\"/i", $html)) {
			return 1;
		} else {
			return 2;
		}
//		echo $html.'<br />';
	}
}