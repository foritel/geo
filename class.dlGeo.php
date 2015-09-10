<?php
/**
 * 
 * dlGeo class
 *
 * Developer: Pigalov Denis
 * E-mail: denis.p@direcltine.su
 * Copyright: (c) 2014
 * 
 * @return geolocation info about user ip (http://ya.ru/ as example)
 * @return array, format:
 * 	'ip' => string (12) "93.158.134.3"
 * 	'country' => string (2) "RU"
 * 	'city' => string UTF-8 (6) "Москва"
 * 	'region' => string UTF-8 (6) "Москва"
 * 	'district' => string UTF-8 (29) "Центральный федеральный округ" // only from ipgeobase.ru
 * 	'lat' => string (9) "55.755787"
 * 	'lng' => string (9) "37.617634"
 */
class dlGeo
{
	// user vars
	private $allow_formats = array("json","xml");
	private $out_format = "json";
	private $fallback_encoding = "UTF-8";

	private $curl_wait_time = 2;

	// system vars
	private $site_encoding = false;
	private $return_json = false;
	private $has_curl = false;
	private $storage = false;
	private $servers = false;
	private $ip = false;

/**
 * main method
 * @param string $debug_ip allow get info about different ip
 */
	public static function GetInfo( $debug_ip = false )
	{
		$inst = new dlGeo();
		return $inst->_getInfo( $debug_ip );
	}

	public function __construct() {

		session_start(); // !important

		// set vars
		$this->site_encoding = defined("SITE_CHARSET") ? SITE_CHARSET : $this->fallback_encoding;
		$this->storage = &$_SESSION["GEOIP"];
		$this->servers = array(
			"ipgeobase_ru" => array(
				"xml" => "http://ipgeobase.ru:7020/geo/?ip=[ip]",
				"json" => "http://ipgeobase.ru:7020/geo/?ip=[ip]&json=1"
			),
			"geoip_elib_ru" => array(
				"xml" => "http://geoip.elib.ru/cgi-bin/getdata.pl?ip=[ip]&lt=1&lg=1&tn=1&cn=1&rg=1",
				"json" => "http://geoip.elib.ru/cgi-bin/getdata.pl?ip=[ip]&lt=1&lg=1&tn=1&cn=1&rg=1&fmt=json"
			),
			"geoplugin_net" => array(
				"xml" => "http://geoplugin.net/xml.gp?ip=[ip]",
				"json" => "http://geoplugin.net/json.gp?ip=[ip]"
			),
			"api_sypexgeo_net" => array(
				"xml" => "http://api.sypexgeo.net/xml/[ip]",
				"json" => "http://api.sypexgeo.net/json/[ip]"
			),
			// 5000/day
			"ru_smart_ip_net" => array(
				"xml" => "http://ru.smart-ip.net/geoip-xml/[ip]/a?lang=ru",
				"json" => "http://ru.smart-ip.net/geoip-json/[ip]/a?lang=ru"
			)
		);

		// test curl
		if ( function_exists("curl_init") ) {
			$this->has_curl = true;
		}

		// test json
		if ( function_exists("json_decode") && $this->out_format == "json" ) {
			$this->return_json = true;
		}
	}

/**
 * return info from session or get new info
 * @param  boolean $debug_ip
 * @return array
 */
	private function _getInfo( $debug_ip = false ) {

		if( $debug_ip ) {
			$this->storage = false;
			$this->ip = $debug_ip;
		}
		else {
			$this->ip = $this->GetIP();
		}

		// fill session info
		if( !is_array($this->storage) ) {

			foreach ($this->servers as $key => $arServer) {
				$action = "GetDataFrom_{$key}";
				if( $this->$action() == true ) {
					$this->storage['source'] = $key;
					break;
				}
			}

		}

		// return data
		return $this->storage;
	}

	private function GetDataFrom_ipgeobase_ru() {
		$url = $this->return_json ? $this->servers["ipgeobase_ru"]["json"] : $this->servers["ipgeobase_ru"]["xml"];
		if( $data = $this->GetData( $url ) ) {
			if( $this->return_json ) {
				// json
				if( $this->site_encoding != "WINDOWS-1251" ) {
					$data = iconv("WINDOWS-1251", $this->site_encoding, $data);
				}
				$jsonData = json_decode($data, true);
				$jsonData = $jsonData[ $this->ip ];
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $jsonData["country"],
					"city" => $jsonData["city"],
					"region" => $jsonData["region"],
					"district" => $jsonData["district"],
					"lat" => $jsonData["lat"],
					"lng" => $jsonData["lng"]
				);
			}
			else {
				// xml
				$xmlData = new SimpleXMLElement($data);
				$xmlData = $xmlData->ip;
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $xmlData->country->__toString(),
					"city" => $xmlData->city->__toString(),
					"region" => $xmlData->region->__toString(),
					"district" => $xmlData->district->__toString(),
					"lat" => $xmlData->lat->__toString(),
					"lng" => $xmlData->lng->__toString()
				);
			}
			return true;
		}
		else {
			return false;
		}
	}

	private function GetDataFrom_geoip_elib_ru() {
		$url = $this->return_json ? $this->servers["geoip_elib_ru"]["json"] : $this->servers["geoip_elib_ru"]["xml"];
		if( $data = $this->GetData( $url ) ) {
			if( $this->return_json ) {
				// json
				$jsonData = json_decode($data, true);
				$jsonData = $jsonData[ $this->ip ];
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $jsonData["Country"],
					"city" => $jsonData["Town"],
					"region" => $jsonData["Region"],
					"district" => "",
					"lat" => $jsonData["Lat"],
					"lng" => $jsonData["Lon"]
				);
			}
			else {
				// xml
				$xmlData = new SimpleXMLElement($data);
				$xmlData = $xmlData->GeoAddr;
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $xmlData->Country->__toString(),
					"city" => $xmlData->Town->__toString(),
					"region" => $xmlData->Region->__toString(),
					"district" => "",
					"lat" => $xmlData->Lat->__toString(),
					"lng" => $xmlData->Lon->__toString()
				);
			}
			return true;
		}
		else {
			return false;
		}
	}

	private function GetDataFrom_geoplugin_net() {
		$url = $this->return_json ? $this->servers["geoplugin_net"]["json"] : $this->servers["geoplugin_net"]["xml"];
		if( $data = $this->GetData( $url ) ) {
			if( $this->return_json ) {
				// json
				if( $this->site_encoding != "WINDOWS-1251" ) {
					$data = iconv("WINDOWS-1251", $this->site_encoding, $data);
				}
				$jsonData = json_decode($data, true);
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $jsonData["geoplugin_countryName"],
					"city" => $jsonData["geoplugin_city"],
					"region" => $jsonData["geoplugin_regionName"],
					"district" => "",
					"lat" => $jsonData["geoplugin_latitude"],
					"lng" => $jsonData["geoplugin_longitude"]
				);
			}
			else {
				// xml
				$xmlData = new SimpleXMLElement($data);
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $xmlData->geoplugin_countryName->__toString(),
					"city" => $xmlData->geoplugin_city->__toString(),
					"region" => $xmlData->geoplugin_regionName->__toString(),
					"district" => "",
					"lat" => $xmlData->geoplugin_latitude->__toString(),
					"lng" => $xmlData->geoplugin_longitude->__toString()
				);
			}
			return true;
		}
		else {
			return false;
		}
	}

	private function GetDataFrom_api_sypexgeo_net() {
		$url = $this->return_json ? $this->servers["api_sypexgeo_net"]["json"] : $this->servers["api_sypexgeo_net"]["xml"];
		if( $data = $this->GetData( $url ) ) {
			if( $this->return_json ) {
				// json
				$jsonData = json_decode($data, true);
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $jsonData["country"]["name_ru"],
					"city" => $jsonData["city"]["name_ru"],
					"region" => $jsonData["region"]["name_ru"],
					"district" => "",
					"lat" => $jsonData["city"]["lat"],
					"lng" => $jsonData["city"]["lon"]
				);
			}
			else {
				// xml
				$xmlData = new SimpleXMLElement($data);
				$xmlData = $xmlData->ip;
				$this->storage = array(
					"ip" => $this->ip,
					"country" => $xmlData->country->name_ru->__toString(),
					"city" => $xmlData->city->name_ru->__toString(),
					"region" => $xmlData->region->name_ru->__toString(),
					"district" => "",
					"lat" => $xmlData->city->lat->__toString(),
					"lng" => $xmlData->city->lon->__toString()
				);
			}
			return true;
		}
		else {
			return false;
		}
	}

	private function GetDataFrom_ru_smart_ip_net() {
		$url = $this->return_json ? $this->servers["ru_smart_ip_net"]["json"] : $this->servers["ru_smart_ip_net"]["xml"];
		if( $data = $this->GetData( $url ) ) {
			return false; // not work (16.07.2014)
		}
		else {
			return false;
		}
	}

/**
 * choice way to get data (curl or file_get_contents)
 * @param string $url
 */
	private function GetData( $url ) {

		$url = str_replace("[ip]", $this->ip, $url);

		if( $this->has_curl ) {
			$data = $this->GetCurlData( $url );
			$data = $data ? $data : $this->GetFileData( $url );
		}
		else {
			$data = $this->GetFileData( $url );
		}
		return $data;
	}

/**
 * get data by curl
 * @param string $url
 */
	private function GetCurlData( $url ) {
		if( $url != "" ) {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_wait_time);
			// curl_setopt($ch, CURLOPT_HEADER, TRUE);
			curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$response = curl_exec($ch);

			$error = curl_errno($ch);
			$errorText = curl_error($ch);
			curl_close($ch);

			if ($error) {
				return false;
			}
			return $response;
		}
	}

/**
 * get data by file_get_contents
 * @param string $url
 */
	private function GetFileData( $url ) {
		if( $url != "") {
			$response = file_get_contents( $url );
			return $response != "" ? $response : false;
		}
	}

/**
 * return real user ip
 */
	private function GetIP() {

		$ip = $_SERVER["REMOTE_ADDR"];

		if ( $_SERVER["HTTP_X_REAL_IP"] != '' && filter_var($_SERVER["HTTP_X_REAL_IP"], FILTER_VALIDATE_IP) ) {
			$ip = $_SERVER["HTTP_X_REAL_IP"];
		}

		if ( $_SERVER["HTTP_CLIENT_IP"] != '' && filter_var($_SERVER["HTTP_CLIENT_IP"], FILTER_VALIDATE_IP) ) {
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		}

		if( $_SERVER["HTTP_X_FORWARDED_FOR"] != '' ) {
			$explode = explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"]);
			$as_ip = array_pop($explode);
			if( filter_var($as_ip, FILTER_VALIDATE_IP) ) {
				$ip = $as_ip;
			}
		}

		return $ip;
	}
}
?>