<?php

require_once("constants.php");

/**
 * Sends an email to the email address specified in $EMAIL above.
 *
 * @param subject The email subject.
 * @param message The body of the email.
 */
function sendEmail($subject, $message){
    global $EMAIL;
    if(!$EMAIL) die("Missing email; set EMAIL in email.php.");

    mail($EMAIL, $subject, $message);
}

?>
