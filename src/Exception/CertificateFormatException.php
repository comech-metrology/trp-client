<?php

namespace CoMech\TRP\Exception;

class CertificateFormatException extends \CoMech\TRP\Exception\LocalFileException
{
	function __construct($filename)
	{
		parent::__construct($filename, "Certificate " . $filename . " is not a valid PEM X509 certificate");
	}
}

