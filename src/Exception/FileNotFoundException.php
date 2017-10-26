<?php

namespace CoMech\TRP\Exception;

class FileNotFoundException extends \CoMech\TRP\Exception\LocalFileException
{
	function __construct($filename) 
	{
		parent::__construct($filename, "File not found: " . $filename);
	}
}

