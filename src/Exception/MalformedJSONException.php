<?php

namespace CoMech\TRP\Exception;

class MalformedJSONException extends \CoMech\TRP\Exception
{
	private $plaintext;
	private $jsonerror;
	private $errno;

	function __construct($errno, $jsonerror, $message)
	{
		$this->errno = $errno;
		$this->jsonerror = $jsonerror;
		$this->plaintext = $message;
		parent::__construct("JSON error #" . $errno . ": " . $jsonerror . "; Plaintext response was: " .$message);
	}

	function getErrorNumber()
	{
		return $this->errno;
	}

	function getJSONError()
	{
		return $this->jsonerror;
	}

	function getPlaintext()
	{
		return $this->plaintext;
	}
}

