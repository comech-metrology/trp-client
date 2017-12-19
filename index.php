<?php

require_once 'vendor/autoload.php';

try {
	/* First, instantiate the API client. Use your own certificate here. */
	$tr = new CoMech\TRP\API("../tests/test.pem");

	/* If using impersonation auth, you must first log in with a username and password */
	$tr->loginUser("test.user", "testpassword", "127.0.0.1", "API-Test-3.0");

	/* If the login succeeds, you'll receive a session ID. store this, or pass it along to the user's session for further requests.
	 */
	print "Logged in, session ID: " . $tr->getSessionID() . "\n";

	/* Note: In later requests you could use the setSessionID() method with this token rather than logging in again. */

	/* Fetch a user as an example */
	print "Dump users:\n";
	//$result = $tr->getPageRange($tr->desc('assets.asset_no')->getAssets([]), 1, 5);
	$result = $tr->getAssetById(17472);
	print_r($result);

	print "Done\n";

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
