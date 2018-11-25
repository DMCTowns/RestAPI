<?php
/**
 * Abstract class to run Restful Web Service
 */
namespace DMCTowns\RestAPI;

abstract class Service implements Interfaces\ServiceInterface{

	/**
	 * @var string $_supportedMethods
	 */
	protected $_supportedMethods = array('GET', 'HEAD', 'POST', 'PATCH', 'DELETE', 'OPTIONS');

	/**
	 * @var array $_supportedFormats
	 */
	protected $_supportedFormats = array('text/xml','application/json', 'text/javascript');

	/**
	 * Access Control Origin
	 * @var string $_accessControlOrigin
	 */
	protected $_accessControlOrigin = '*';

	/**
	 * Access Control Headers
	 * @var array
	 */
	protected $_accessControlHeaders = array('Content-Type', 'Accept');

	/**
	 * Store for HMAC secrets
	 * @var array
	 */
	protected $_hmacSecrets = array();

	/**
	 * HMAC algorithm
	 */
	protected $_hmacAlgorithm = 'sha1';

	/**
	 * Errors
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * Log handler
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logHandler;

	/**
	 * Default format returned by service
	 * @var string
	 */
	protected $_defaultFormat = 'text/json';

	/**
	 * Sets access control headers
	 * @param array $headers
	 */
	public function setAccessControlHeaders($headers){
		$this->_accessControlHeaders = $headers;
	}

	public function setAccessControlOrigin($origin){
		$this->_accessControlOrigin = $origin;
	}

	/**
	 * Runs webservice from raw request data
	 */
	public function handleRawRequest() {
		$url = $this->getFullUrl($_SERVER);
		$method = $_SERVER['REQUEST_METHOD'];
		switch ($method) {
			case 'GET':
			case 'HEAD':
			case 'OPTIONS':
			case 'DELETE':
				$arguments = $_GET;
				break;
			case 'POST':
			case 'PUT':
			case 'PATCH':
				$arguments = file_get_contents('php://input');
				break;
		}
		$accept = (isset($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : 'text/xml';
		$this->handleRequest($url, $method, $arguments, $accept);
	}

	/**
	 * Gets full URL
	 * @return string
	 */
	public function getFullUrl() {
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
		$location = $_SERVER['REQUEST_URI'];
		if ($_SERVER['QUERY_STRING']) {
			$location = substr($location, 0, strrpos($location, $_SERVER['QUERY_STRING']) - 1);
		}
		return $protocol.'://'.$_SERVER['HTTP_HOST'].$location;
	}

	/**
	 * Handles request
	 * @param string $url
	 * @param string $method
	 * @param array $arguments
	 * @param string $accept
	 */
	public function handleRequest($url, $method, $arguments, $accept) {

		if(!stristr($this->_supportedMethods,$method)){
			$this->_methodNotAllowedResponse();
			exit;
		}

		switch($method) {
			case 'GET':
				$this->performGet($url, $arguments, $accept);
				break;
			case 'HEAD':
				$this->performHead($url, $arguments, $accept);
				break;
			case 'POST':
				$this->performPost($url, $arguments, $accept);
				break;
			case 'PUT':
				$this->performPut($url, $arguments, $accept);
				break;
			case 'PATCH':
				$this->performPatch($url, $arguments, $accept);
				break;
			case 'DELETE':
				$this->performDelete($url, $arguments, $accept);
				break;
			case 'OPTIONS':
				$this->performOptions($url, $arguments, $accept);
				break;
			default:
				$response = new \DMCTowns\HTTP\Response(501);
				$response->addHeader('Access-Control-Allow-Origin: ' . $this->_accessControlOrigin);
				$response->send();
				exit;
		}
	}

	/**
	 * Returns requested accept format
	 * @return string
	 */
	protected function _getAcceptFormat(){
		return (isset($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : ((isset($_SERVER['CONTENT_TYPE'])) ? $_SERVER['CONTENT_TYPE'] : $this->_defaultFormat);
	}

	/**
	 * Sends 405 Method not allowed response
	 */
	protected function _methodNotAllowedResponse() {
		$this->_sendResponse(405);
	}

	/**
	 * Sends 204 Method not allowed response
	 */
	protected function _noDataResponse() {
		$this->_sendResponse(204);
	}

	/**
	 * Method to handle Options Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performOptions($url, $arguments, $accept) {
		$response = new \DMCTowns\HTTP\Response(200);
		$response->addHeader('Allow: ' . $this->_supportedMethods);
		$response->addHeader('Access-Control-Allow-Origin: ' . $this->_accessControlOrigin);
		$response->addHeader('Access-Control-Allow-Headers: ' . implode(', ', $this->_accessControlHeaders));
		$response->send();
		exit;
	}

	/**
	 * Sends data to browser
	 * @param integer $code
	 * @param string $data
	 */
	protected function _sendResponse($code,$data=null){
		$response = new \DMCTowns\HTTP\Response($code,$data);
		$response->addHeader('Access-Control-Allow-Origin: ' . $this->_accessControlOrigin);
		$response->send();
		exit;
	}

	protected function _logError($error){
		$this->_errors[] = $error;
	}

	protected function _clearErrors(){
		$this->_errors = array();
	}

	public function getErrors(){
		return (count($this->_errors)) ? $this->_errors : null;
	}

	/** HMAC Methods */

	/**
	 * Adds HMAC secret to store
	 * @param string $secret
	 */
	public function addHMACSecret($secret){
		$this->_hmacSecrets[$secret] = $secret;
	}

	/**
	 * Sets HMAC algorithm
	 * @param string $algorithm
	 */
	public function setHMACAlgorithm($algorithm){
		$algorithm = strtolower($algorithm);
		if(in_array($algorithm, hash_algos())){
			$this->_hmacAlgorithm = $algorithm;
		}
	}

	/**
	 * Returns header
	 * @param  string $header
	 * @return string
	 */
	protected function _getHeader($header){
		if(!($headers = getallheaders())){
			return null;
		}
		$headers = array_change_key_case($headers, CASE_LOWER);
		if(isset($headers[strtolower($header)])){
			return $headers[strtolower($header)];
		}
		return null;
	}

	/**
	 * Tests request for correct HMAC submission
	 * @param  string $request
	 * @return boolean
	 */
	protected function _passHMAC($request, $data=null){

		$this->log('Request: ' . $request);
		$this->log('Data received:');
		$this->log($data);

		if(!count($this->_hmacSecrets)){
			$this->_logError('HMAC not configured on this service.');
			$this->log('Error: HMAC not configured on this service.');
			return false;
		}

		// Get Request HMAC
		if(!($headers = getallheaders())){
			$this->_logError('No headers supplied.');
			$this->log('Error: No headers supplied.');
			return false;
		}

		foreach($headers as $name=>$value){
			$headers[strtolower($name)] = $value;
		}

		if(!isset($headers['authorization'])){
			$this->_logError('No Authorization header supplied.');
			$this->log('Error: No Authorization header supplied.');
			return false;
		}

		if(!isset($headers['datetime'])){
			$this->_logError('No DateTime header supplied.');
			$this->log('Error: No DateTime header supplied.');
			return false;
		}
		$requestDate = $headers['datetime'];
		$requestDateObject = new \DateTime($requestDate);
		$now = new \DateTime();
		if($now->getTimestamp() - $requestDateObject->getTimestamp() > 300){
			$this->_logError('Request expired.');
			$this->log('Error: Request expired.');
			return false;
		}

		if(!isset($headers['content-type'])){
			$this->_logError('No Content-type header supplied.');
			$this->log('Error: No Content-type header supplied.');
			return false;
		}
		$contentType = $headers['content-type'];

		$method = $_SERVER['REQUEST_METHOD'];

		if(($method == 'GET' || $method == 'DELETE') && is_array($data) && count($data)){
			$request .= '?' . http_build_query($data);
			$data = null;
		}

		$contentMD5 = ($data) ? md5($data) : '';

		if($data){
			if(!isset($headers['content-md5'])){
				$this->_logError('No Content-MD5 header supplied.');
				$this->log('Error: No Content-MD5 header supplied.');
				return false;
			}
			if($headers['content-md5'] != $contentMD5){
				$this->_logError('Incorrect Content-MD5 header supplied.');
				$this->log('Error: Incorrect Content-MD5 header supplied. Expecting ' . $contentMD5 . ', got ' . $headers['content-md5']);
				return false;
			}
		}

		if(!preg_match('/APIAuth 4: ?(.+)$/', $headers['authorization'], $matches)){
			$this->_logError('Authorization header should be supplied in the format "APIAuth 4: [HMAC]".');
			$this->log('Error: Authorization header should be supplied in the format "APIAuth 4: [HMAC]".');
			return false;
		}
		$hmacRequest = $matches[1];

		// Get Expected
		$canonicalString =  $method . ',' . $contentType . ',' . $contentMD5. ',' . $request . ',' . $requestDate;

		foreach($this->_hmacSecrets as $secret){
			if(base64_encode(hash_hmac($this->_hmacAlgorithm, $canonicalString, $secret, true)) == $hmacRequest){
				return true;
			}
		}

		$this->_logError('HMAC authorization failed.');
		$this->log('Error: HMAC authorization failed.');
		return false;

	}

	/**
	 * Sets the log handloer
	 * @param \Psr\Log\LoggerInterface $logHandler
	 */
	public function setLogHandler(\Psr\Log\LoggerInterface $logHandler){
		$this->_logHandler = $logHandler;
	}

	/**
	 * Logs the message
	 * @param string $message
	 * @param string $level
	 * @return void
	 */
	public function log($message, $level=\Psr\Log\LogLevel::DEBUG){
		$this->_logHandler->log($level, $message);
	}
}