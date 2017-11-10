<?php

namespace CoMech\TRP\Exception;

class DataException extends \CoMech\TRP\Exception\JSONResponseException
{
	function __construct($message)
	{
		parent::__construct(preg_replace('/^Data Error: /', '', $message));
	}
}

