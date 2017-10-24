<?php

namespace CoMech\TRP;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/** TrackRecordPro API Client class v1.0.
 *
 * Supports API v3.0+
 * (C) CoMech Metrology Ltd 2017,
 * First revision: Craig Edwards
 */
class API implements LoggerAwareInterface
{
	/** Current certificate filename used for requests */
	private $certificate = "";

	/** Active user session object, for impersonation mode */
	private $user_session = "";

	/** Last response from an API call */
	private $response = null;

	/** Base address for API endpoints */
	private $base = 'https://api.trackrecordpro.co.uk/';

	/** Version number to use */
	private $version = '3.0';

	/** Session information returned with the last request */
	private $sessiondata = null;

	/** PSR\Log\LoggerInterface instance */
	private $logger = null;

	/** Mappings of class method names to HTTP method verbs and parameter lists for __call() */
	private $mappings = [
		'get'		=>	['method'=>'GET',	'filters'=>0,		'post'=>null],
		'update'	=>	['method'=>'POST',	'filters'=>0,		'post'=>1],
		'create'	=>	['method'=>'PUT',	'filters'=>null,	'post'=>0],
		'delete'	=>	['method'=>'DELETE',	'filters'=>0,		'post'=>null],
	];

	/** Constructor accepts a private key in PEM format.
	 * @param $PrivateKey The PEM encoded private key to use for authentication. This should contain both the private and public key parts.
	 * @param $logger A logger implemeneting Psr\Log\LoggerInterface to receive debug messages
	 */
	function __construct($PrivateKey, LoggerInterface $logger = null)
	{
		$this->setCertificate($PrivateKey);
		$this->base = $this->base . $this->version . '/';
		if ($logger) {
			$this->setLogger($logger);
		}
	}

	/** Returns the current user's session ID if using login impersonation */
	function getSession()
	{
		return $this->sessiondata;
	}

	/** Sets the current certificate to a specific file.
	 * This function will validate the given certificate and throw an exception if it is not valid.
	 * @param $PrivateKey The X509 PEM file to use as the certificate.
	 */
	function setCertificate($PrivateKey)
	{
		if (!file_exists($PrivateKey)) {
			throw new Exception\FileNotFoundException($PrivateKey);
		}

		$certhandle = openssl_x509_read(file_get_contents($PrivateKey));
		if ($certhandle === false) {
			throw new Exception\CertificateFormatException($PrivateKey);
		}

		$this->certificate = $PrivateKey;
	}

	/** Enable or disable logging to a Monolog\Logger instance
	 * @param $log A valid instance of Psr\Log\LoggerInterface
	 */
	function setLogger(LoggerInterface $log)
	{
		$this->logger = $log;
	}

	/** Logs a message to a logger if there is a logger set
	 * @param message to log to the debug log of the logger
	 */
	function logIf($message)
	{
		if ($this->logger) {
			$this->logger->debug($message);
		}
	}

	/** Use an existing session ID for impersonation, if you had previously called LoginUser() */
	function setSessionID($session)
	{
		$this->user_session = $session;
	}

	/** Log in a user using impersonation mode auth
	 * @param $username The username of the user to log in
	 * @param $password The password of the user to log in
	 * @return True if the user was logged in successfully, false if otherwise.
	 */
	function loginUser($username, $password)
	{
		try {
			$response = $this->MakeRequest("login", [], ["username"=>$username, "password"=>$password], 'POST');
			$this->user_session = $response->user_token;
		}
		catch (Exception\JSONResponseException $e) {
			throw new Exception\LoginException();
		}
	}

	/** Log out a user previously logged in via impersonation */
	function logoutUser()
	{
		if (!empty($this->user_session)) {
			return ($this->MakeRequest("logout") ? true : false);
		}
		return false;
	}

	function getRandomPassword()
	{
		return $this->makeRequest("randompassword")[0]->randompassword;
	}

	/** Get the current user's session ID after successful LoginUser call */
	function getSessionID()
	{
		return $this->user_session;
	}

	/** Set the HTTP headers for a curl request.
	 * @param $ch The existing curl channel to apply to headers to
	 */
	private function setHeaders($ch)
	{
		$headers = ['User-Agent: TRPAPI-PHP/1.0'];
		if (!empty($this->user_session)) {
			$headers[] = "Authorization: Basic " . base64_encode("session:" . $this->user_session);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}

	/** Initialise and return a new curl request with the default settings.
	 * @param $req_url The URL to request
	 * @param $method The method to use for accessing the resource
	 * @return A curl channel resource
	 */
	private function getCurl($req_url, $method)
	{
		$ch = curl_init($req_url);
		curl_setopt_array($ch, [
			CURLOPT_SSLCERT		=>	$this->certificate,
			CURLOPT_FOLLOWLOCATION	=>	1,
			CURLOPT_HEADER		=>	0,
			CURLOPT_RETURNTRANSFER	=>	1,
			CURLOPT_CUSTOMREQUEST	=>	$method,
		]);
		$this->setHeaders($ch);
		return $ch;
	}

	/** Sets up an existing curl channel to send a POST request, if $post is non-null
	 * @param $ch A valid curl resource handle
	 * @param $post Post data to send, or NULL if not sending POST data
	 * @paream $json True if the post data is to be encoded to JSON
	 */
	private function setupCurlPost($ch, $post, $json = true)
	{
		if ($post) {
			curl_setopt_array($ch, [
				CURLOPT_POST		=>	1,
				CURLOPT_POSTFIELDS	=>	$json ? json_encode($post) : $post,
			]);
		}
	}

	/** Given an array of key/value pairs with suffixes, encode them into URL parameters for the REST API.
	 * @param $filters A key/value list of filters with optional suffixes on the keys. Suffixes can be any one of =, ~, <, > and !.
	 * @return The URL parameter string.
	 */
	private function parseFilters($filters)
	{
		$req_url = '';
		foreach ($filters as $key => $value) {
			$op = '=';
			if (preg_match('/(<>!~=)$/', $key, $opmatch)) {
				$op = $opmatch[1];
				$key = preg_replace('/(<>!~=)$/', '', $key);
			}
			$req_url .= '/' . urlencode($key) . $op . urlencode($value);
		}
		return $req_url;
	}
	
	/** Decode a response from the endpoint and throw exceptions if it indicates an error.
	 * @param $plain_response The response text from the endpoint.
	 * @return An object containing the response. Throws either JSONResponseException or MalformedJSONException on error.
	 */
	private function decodeResponse($plain_response)
	{
		$json_response = json_decode($plain_response);
		$decoding_error = json_last_error();
		if ($decoding_error) {
			throw new Exception\MalformedJSONException($decoding_error, json_last_error_msg(), $plain_response);
		} else if (isset($json_response->error)) {
			throw new Exception\JSONResponseException($json_response->error);
		} else if ($json_response && isset($json_response->response->error)) {
			throw new Exception\JSONResponseException($json_response->response->error);
		}
		if (isset($json_response->request->session)) {
			$this->sessiondata = $json_response->request->session;
		} else {
			$this->sessiondata = null;
		}
		return $json_response->response;
	}

	/** Executes a curl request. 
	 * This will throw an exception if a curl error occurs.
	 * @param $ch An existing valid curl resource handle
	 * @return The response retrieved from the endpoint as plain text.
	 */
	private function execCurl($ch)
	{
		$plain_response = curl_exec($ch);
		$this->logIf("HTTP response " . $plain_response);
		if (!$plain_response) {
			throw new Exception\HTTPException(curl_error($ch));
		}
		return $plain_response;
	}

	/** Make a request to the API.
	 * @param $endpoint The endpoint name to call
	 * @param $filters A list of filters to apply to the search
	 * @param $post Post data as an object, or null if not sending any data
	 * @param $method The HTTP Method verb to use
	 */
	function makeRequest($endpoint, $filters = [], $post = null, $method = 'GET')
	{
		$this->logIf("API::makeRequest('$endpoint', '".json_encode($filters)."','".json_encode($post)."','$method')");
		$req_url = $this->base . $endpoint . $this->parseFilters($filters);

		$ch = $this->getCurl($req_url, $method);
		$this->setupCurlPost($ch, $post);

		return $this->decodeResponse($this->execCurl($ch));
	}

	/** Upload a file.
	 * @param $upload_id The upload ID to upload to, taken from the upload_endpoint field in the files endpoint.
	 * @param $filecontent The file content to upload
	 * @return Returns the response object containing the confirmation of the upload, or null.
	 */
	function uploadFile($upload_id, $filecontent)
	{
		$req_url = $this->base . 'upload/' . $upload_id;

		$ch = $this->getCurl($req_url, 'POST');
		$this->setupCurlPost($ch, $filecontent, false);

		return $this->decodeResponse($this->execCurl($ch));
	}

	/** Used by __call() to map the parameters passed to __call to those needed by makeRequest, depending upon the method name prefix.
	 * @param $index An index in the $array, or null
	 * @param $array an array of zero or more values
	 * @return If $index is null, returns an empty array, otherwise returns the value of the given $index in $array. This is used by
	 *         the API::mappings array to transpose parameters for makeRequest().
	 */
	private function indexOrNull($index, $array)
	{
		return $index === null ? [] : $array[$index];
	}

	/** The __call magic method.
	 * Routes all unknown method names through to the correct API endpoints and HTTP methods.
	 * @param $name The name of the method
	 * @param $arguments The parameter array to pass to the method
	 * @return Dynamically typed return value
	 */
	function __call($name, $arguments)
	{
		if (preg_match('/^(get|update|create|delete)(\w+)/i', $name, $matches)) {

			$filters = [];
			$postobject = null;

			$prefix = strtolower($matches[1]);
			$table = strtolower($matches[2]);
			$mapping = $this->mappings[$prefix];

			$method = $mapping['method'];


			$postobject = $this->indexOrNull($mapping['post'], $arguments);
			$filters = $this->indexOrNull($mapping['filters'], $arguments);

			return $this->MakeRequest($table, $filters, $postobject, $method);
		} else {
			throw new Exception\MethodNotFoundException();
		}
	}
}