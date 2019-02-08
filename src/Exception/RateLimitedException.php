<?

namespace CoMech\TRP\Exception;

class RateLimitedException extends \CoMech\TRP\Exception
{
	private $seconds = 15;

	function __construct($seconds)
	{
		parent::__construct("Login failed due to rate limiting");
		$this->seconds = $seconds;
	}

	function getSeconds()
	{
		return $this->seconds;
	}
}
