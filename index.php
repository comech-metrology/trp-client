<?php

require_once 'vendor/autoload.php';

try {
	/* First, instantiate the API client. Use your own certificate here. */
	$tr = new CoMech\TRP\API("../tests/test.pem");

	/* You can enable debugging with the setDebug() method */
	/*$tr->setDebug();*/

	/* If using impersonation auth, you must first log in with a username and password */
	$tr->loginUser("test.user", "testpassword");

	/* If the login succeeds, you'll receive a session ID. store this, or pass it along to the user's session for further requests.
	 */
	print "Logged in, session ID: " . $tr->getSessionID() . "\n";

	/* Note: In later requests you could use the setSessionID() method with this token rather than logging in again. */

	/* Fetch a user as an example */
	print "Dump users:\n";
	$result = $tr->desc('users.id')->getUsers();
	print_r($result);

	$result = $tr->getRandomPassword();
	print "Random password: $result\n";

	print "Get session:\n";
	print_r($tr->getSession());

	/* Log out to clear up the token */
	print "Logging out\n";
	$tr->logoutUser();
}
catch (CoMech\TRP\Exception $exception) {
	print "Caught ". get_class($exception)  .": " . $exception->getMessage() . "\n";
}

?>
