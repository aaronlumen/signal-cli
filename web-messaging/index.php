<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone for timestamps
date_default_timezone_set('America/Los_Angeles');

// Email notification configuration
// Now multiple emails can be separated by semicolons - below we use an alert alias and  a tmobile phone to alert
$alert_email = 'alerts@your-domain.com;5095551212@tmomail.net';

// Allowed passcodes to access the site
$allowed_passcodes = ['0001', '1212'];

// Paths for message files and Signal-CLI configuration
$message_file = '/var/www/your-domain.com/signal.txt'; // Ensure this path is correct and writable
$signalCliPath = '/opt/signal-cli/bin/signal-cli '; // Path to signal-cli
$default_recipient_number = '+12065551212'; // Default recipient number
$signal_message_status = ''; // To store the status of Signal message sending
$signalNumber = '+17075551212';
/**
 * Function to retrieve a visitor’s IP address in a slightly more robust way.
 */
function getVisitorIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    // In case multiple IPs are in HTTP_X_FORWARDED_FOR, take the first
    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }

    return $ip;
}

/**
 * Function to send email notifications to multiple recipients.
 * - Splits a semicolon-separated list into an array
 * - Joins them with commas for RFC-compliant To headers
 */
function send_email_notification($toString, $subject, $message) {
    // Split semicolon-separated addresses
    $recipients = explode(';', $toString);
    // Convert array to comma-separated string for the "To" header
    $to = implode(', ', $recipients);

    $headers = "From: safety@your-domain.com\r\n";
    // Optionally, add more headers, e.g. Reply-To

    mail($to, $subject, $message, $headers);
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['authenticated']);
    session_destroy();
    header('Location: /index.php');
    exit;
}

// Handle passcode submission
if (isset($_POST['passcode'])) {
    $entered_code = trim($_POST['passcode']);
    if (in_array($entered_code, $allowed_passcodes)) {
        $_SESSION['authenticated'] = true;
        // Send email notification for login and include the IP address the visitor arrived from for extra phunctionality
        $visitorIP = getVisitorIP();
        $subject = "\"New Login to Safety Center\"";
        $message = "A visitor logged into the Family Safety Center at " . date('Y-m-d H:i:s') . ".\nIP Address: " . $visitorIP;
        send_email_notification($alert_email, $subject, $message);
        
        header('Location: /index.php'); // Reload to avoid form resubmission
        exit;
    } else {
        $error = "Incorrect passcode. Please try again.";
    }
}



// Handle posting a new local message including requiring the 3 characters "123" at the end of the message or 
// it will not post to the website.
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message) && substr($message, -3) === '123') {
        $cleaned_message = rtrim($message, '123');
        $timestamp = date('Y-m-d H:i:s');
        $entry = "$timestamp|$cleaned_message\n";
        file_put_contents($message_file, $entry, FILE_APPEND | LOCK_EX);

        // Send email notification for new message
        $subject = "New Message Posted to Safety Center";
        $email_message = "A new message was posted at $timestamp:\n\n $cleaned_message";
        send_email_notification($alert_email, $subject, $email_message);
    }
}

// Handle sending a Signal message
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_POST['send_signal'])) {
    $recipient_number = escapeshellarg($_POST['recipient_number'] ?? $default_recipient_number);
    $signal_message = escapeshellarg($_POST['signal_message'] ?? '');
    
    // Example signal-cli command - there are two seperate versions of the command. 
    // The extra "exec(...)" line looks duplicated in your snippet. If you intentionally want to send two identical messages, that's okay. If not, remove one.
    $signal_command = "$signalCliPath -u +17075551212 send -m 'hello - $signal_message' $recipient_number 2>&1";
    $command = "sudo $signalCliPath -u $signalNumber send -m $signal_message $recipient_number 2>&1";

    // First attempt
    exec($signal_command, $output, $return_var);
    // Second attempt (if you want to send twice)
    exec($command, $output2, $return_var2);

    if ($return_var === 0 && $return_var2 === 0) {
        $signal_message_status = "Message sent successfully to $recipient_number.";
    } else {
        $signal_message_status = "Failed to send the message. Please check your setup.";
    }
}

// Load messages for display
$messages = [];
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && file_exists($message_file)) {
    $lines = array_reverse(file($message_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    foreach ($lines as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $messages[] = [
                'timestamp' => $parts[0],
                'message'   => $parts[1],
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Family Center</title>
    <style>
        body {
            background-color: #000;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .message-entry {
            margin-bottom: 10px;
            padding: 10px;
            background: #222;
            border-radius: 5px;
        }
        .form-section {
            margin-bottom: 20px;
        }
        .error {
            color: #f00;
        }
        .logout-button {
            text-align: right;
        }
        .status {
            color: #0f0;
            margin-top: 10px;
        }
        label {
            display: inline-block;
            margin-bottom: 6px;
        }
        textarea, input {
            width: 100%;
            max-width: 400px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<h1>Family Center</h1>

<?php if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true): ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="passcode">Enter Passcode:</label><br>
        <input type="password" name="passcode" id="passcode" required><br>
        <button type="submit">Login</button>
    </form>
<?php else: ?>
    <div class="logout-button">
        <form method="get">
            <button type="submit" name="logout">Logout</button>
        </form>
    </div>

    <div class="form-section">
        <form method="post">
            <label for="message">Post a Local Message (end with '123'):</label><br>
            <textarea name="message" id="message" rows="4" required></textarea><br>
            <button type="submit">Post Message</button>
        </form>
    </div>

    <div class="form-section">
        <h2>Send a Signal Message:</h2>
        <form method="post">
            <label for="recipient_number">Recipient Phone Number:</label><br>
            <input type="text" name="recipient_number" id="recipient_number"
                   value="<?php echo htmlspecialchars($default_recipient_number); ?>" required><br>

            <label for="signal_message">Your Message:</label><br>
            <textarea name="signal_message" id="signal_message" rows="4" required></textarea><br>

            <button type="submit" name="send_signal">Send Signal Message</button>
        </form>
        <?php if (!empty($signal_message_status)): ?>
            <div class="status"><?php echo htmlspecialchars($signal_message_status); ?></div>
        <?php endif; ?>
    </div>

    <h2>Messages Posted:</h2>
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message-entry">
                <strong><?php echo htmlspecialchars($msg['timestamp']); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No messages posted yet.</p>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>


