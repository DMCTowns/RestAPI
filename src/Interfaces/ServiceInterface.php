<?php
/**
 * Interface and Abstract class to run Restful Web Service
 */

namespace DMCTowns\RestAPI\Interfaces;

interface ServiceInterface{

	/**
	 * Runs webservice from raw request data
	 */
	public function handleRawRequest();

	/**
	 * Handles request
	 * @param string $url
	 * @param string $method
	 * @param array $arguments
	 * @param string $accept
	 */
	public function handleRequest($url, $method, $arguments, $accept);

	/**
	 * Method to return schools
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performGet($url, $arguments, $accept);
	/**
	 * Method to handle Head Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performHead($url, $arguments, $accept);

	/**
	 * Method to handle Post Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performPost($url, $arguments, $accept);

	/**
	 * Method to handle Put Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performPut($url, $arguments, $accept) ;

	/**
	 * Method to handle Patch Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performPatch($url, $arguments, $accept) ;

	/**
	 * Method to handle Delete Requests
	 * @param string $url
	 * @param array $arguments
	 * @param string $accept
	 */
	public function performDelete($url, $arguments, $accept);
}