<?php
/**
 * Consumer of standard REST API
 * @author Diccon Towns <diccon@also-festival.com>
 */
namespace DMCTowns\RestAPI;

class Consumer{

	/**
	 * URL of service
	 * @var string
	 */
	protected $_url;

	/**
	 * @var string $_auth
	 */
	protected $_auth;

	/**
	 * @var string $_authType
	 */
	protected $_authType = 'hmac';

	/**
	 * Response code
	 * @var string
	 */
	protected $_responseCode;

	/**
	 * Constructor
	 * @param string $url
	 * @param string $auth
	 */
	public function __construct($url, $auth=null, $authType='hmac'){
		$this->_url = $url;
		if($auth){
			$this->_auth = $auth;
			$this->_authType = $authType;
		}
	}

	/**
	 * Returns URL to use
	 * @return string
	 */
	protected function _getURL(){
		return $this->_url;
	}

	/**
	 * Returns headers used for HMAC authentication
	 * @param  string $method
	 * @param  string $request
	 * @param  string $data
	 * @return array
	 */
	protected function _getHMACHeaders($method, $request, $data = null){

		if($this->_authType == 'hmac' && $this->_auth){

			$date = gmdate('D, d M Y H:i:s') . ' GMT';
			$contentType = 'application/json';
			$contentMD5 = ($data) ? md5($data) : '';
			$canonicalString =  $method . ',' . $contentType. ',' . $contentMD5. ',' . $request . ',' . $date;

			$hmac = base64_encode(hash_hmac('sha1', $canonicalString, $this->_auth, true));

			$headers = array(
				'Authorization' => 'APIAuth 4:'. $hmac,
				'Content-type' => $contentType,
				'Accept' => $contentType,
				'DateTime' => $date
			);
			if($data){
				$headers['Content-MD5'] = $contentMD5;
			}

			return $headers;
		}
		return null;
	}

	/**
	 * Returns basic authorisation header
	 * @return array
	 */
	protected function _getBasicAuthHeader(){
		if($this->_authType == 'basic' && $this->_auth){
			return array('Authorization' => 'Basic ' . base64_encode($this->_auth));
		}
		return null;
	}

	/**
	 * Returns basic authorisation header
	 * @return array
	 */
	protected function _getTokenAuthHeader(){
		if($this->_authType == 'token' && $this->_auth){
			return array('Authorization' => 'Bearer ' . base64_encode($this->_auth));
		}
		return null;
	}

	/**
	 * Returns authorisation headers
	 * @param  string $method
	 * @param  string $request
	 * @param  array $data
	 * @return array
	 */
	protected function _getAuthHeaders($method, $request, $data = null){
		if($headers = $this->_getHMACHeaders($method, $request, $data)){
			return $headers;
		}
		if($header = $this->_getBasicAuthHeader()){
			return $header;
		}
		if($header = $this->_getTokenAuthHeader()){
			return $header;
		}
		return null;
	}

	/**
	 * Gets headers to use with CURL
	 * @param  array $headers Additional headers can be supplied as an array in the format 'header' => 'value'
	 * @return array
	 */
	protected function _getCurlHeaders($headers = null){

		$core = array('Content-type' => 'application/json', 'Accept' => 'application/json,text/javascript');

		if(is_array($headers) && !empty($headers)){
			$core = array_merge($core, $headers);
		}

		$header = array();
		foreach($core as $key=>$value){
			$header[] = $key . ': ' . trim($value);
		}

		return $header;
	}

	/**
	 * Returns response from curl object
	 * @param  resource $curl
	 * @return object
	 */
	protected function _getCurlResponse($curl){

		$this->_responseCode = null;

		$response = curl_exec($curl);

		if ($response === false) {
		    $info = curl_getinfo($curl);
		    curl_close($curl);
		    throw new \Exception('Error occured during curl exec on URL ' . $this->_url . '. Additional info: ' . var_export($info, true));
		}

		$this->_responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		if($json = json_decode($response)){
			$response = $json;
		}

		return $response;
	}

	/**
	 * Returns response code received from service
	 * @return string
	 */
	public function getResponseCode(){
		return $this->_responseCode;
	}

	/**
	 * Returns CURL respource
	 * @param  string $url
	 * @param  array $headers
	 * @return resource
	 */
	protected function _getCurl($url, $headers=null){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_getCurlHeaders($headers));
		return $curl;
	}

	/**
	 * Runs get request
	 * @param  string $path
	 * @param  array $data
	 * @param  array $headers
	 * @return object
	 */
	public function get($path, $params=null, $headers=[]){

		if(is_array($params)){
			$path .= '?' . http_build_query($params);
		}

		if($authHeaders = $this->_getAuthHeaders('GET', $path)){
			$headers = array_merge($headers, $authHeaders);
		}

		$url = $this->_getURL() . $path;

		return $this->_getCurlResponse($this->_getCurl($url, $headers));
	}

	/**
	 * Runs post request
	 * @param  string $path
	 * @param  string $data
	 * @param  array $params
	 * @param  array $headers
	 * @return object
	 */
	public function post($path, $data, $params=null, $headers=[]){

		if(is_array($params)){
			$path .= '?' . http_build_query($params);
		}

		$url = $this->_getURL() . $path;

		if(!is_string($data)){
			$data = json_encode($data);
		}

		if($authHeaders = $this->_getAuthHeaders('POST', $path, $data)){
			$headers = array_merge($headers, $authHeaders);
		}

		$curl = $this->_getCurl($url, $headers);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		return $this->_getCurlResponse($curl);
	}


	/**
	 * Runs put request
	 * @param  string $path
	 * @param  string $data
	 * @param  array $params
	 * @param  array $headers
	 * @return object
	 */
	public function put($path, $data, $params=null, $headers=[]){

		if(is_array($params)){
			$path .= '?' . http_build_query($params);
		}

		$url = $this->_getURL() . $path;

		if(!is_string($data)){
			$data = json_encode($data);
		}

		if($authHeaders = $this->_getAuthHeaders('PUT', $path, $data)){
			$headers = array_merge($headers, $authHeaders);
		}

		$curl = $this->_getCurl($url, $headers);

		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		return $this->_getCurlResponse($curl);
	}


	/**
	 * Runs delete request
	 * @param  string $path
	 * @param  string $data
	 * @param  array $params
	 * @param  array $headers
	 * @return object
	 */
	public function delete($path, $params=null, $headers=[]){

		if(is_array($params)){
			$path .= '?' . http_build_query($params);
		}

		if($authHeaders = $this->_getAuthHeaders('DELETE', $path)){
			$headers = array_merge($headers, $authHeaders);
		}

		$url = $this->_getURL() . $path;

		$curl = $this->_getCurl($url, $headers);

		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");

		return $this->_getCurlResponse($curl);
	}
}