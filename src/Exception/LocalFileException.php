<?

namespace CoMech\TRP\Exception;

class LocalFileException extends \CoMech\TRP\Exception
{
	private $filename = '';

	function __construct($filename, $message = '')
	{
		$this->filename = $filename;
		parent::__construct($message);
	}

	function getFilename()
	{
		return $this->filename;
	}
}

