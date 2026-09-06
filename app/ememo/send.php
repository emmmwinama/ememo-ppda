<?php
require 'mail.php';

// Example notification
$to      = 'yvonne.kasamba@ppda.mw';
$subject = '🔔 Alert: New Registration';
$body    = '
    <h1>Welcome, Alice!</h1>
    <p>Your account has been created successfully.</p>
';

if (sendEmailNotification($to, $subject, $body)) {
    echo "Notification sent to {$to}";
} else {
    echo "Failed to send notification.";
}
