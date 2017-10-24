<?

namespace CoMech\TRP\Exception;

class LoginException extends \CoMech\TRP\Exception
{
	function __construct()
	{
		parent::__construct("Impersonation login failed");
	}
}

