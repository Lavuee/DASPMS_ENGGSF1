<?php
// These two lines FORCE PHP to show errors on the screen instead of hiding them in a log file
error_reporting(E_ALL);
ini_set('display_errors', 1);

$to = "leelav.viin@gmail.com"; 
$subject = "XAMPP Test Email";
$message = "If you are reading this, XAMPP sendmail is working!";
$headers = "From: test@localhost\r\n";

echo "Attempting to send email to $to...<br><br>";

$result = mail($to, $subject, $message, $headers);

if($result) {
    echo "<strong style='color:green;'>SUCCESS!</strong> PHP found sendmail.exe and handed off the email.";
} else {
    echo "<strong style='color:red;'>FAILED!</strong> PHP could not execute sendmail.exe.<br><br>";
    echo "<strong>PHP Error Detail:</strong><br>";
    print_r(error_get_last());
}
?>