<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['message' => 'Invalid email address.']);
  exit;
}

// ---- SMTP CONFIG ----
$smtpHost = 'mail.rollblockpartners.com';
$smtpPort = 465;
$smtpUser = '_mainaccount@rollblockpartners.com';
$smtpPass = '@,=5;kTU5V!~Rp,5E]';  // ⚠️ Replace this
$toEmail  = '_mainaccount@rollblockpartners.com'; // or another email where you want to receive messages
// ----------------------

$email = $data['email'];
$subject = "New Subscription from Website";
$body = "A user subscribed with this email: $email";

// Use PHP's built-in mail if the server already routes SMTP
$headers = "From: $smtpUser\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

// Option 1: many cPanel servers automatically send using local mail()
if (mail($toEmail, $subject, $body, $headers)) {
  echo json_encode(['message' => 'Thank you! Your email has been sent.']);
  exit;
}

// Option 2: direct SMTP (manual socket)
$context = stream_context_create();
$fp = stream_socket_client("ssl://$smtpHost:$smtpPort", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);

if (!$fp) {
  echo json_encode(['message' => 'Could not connect to SMTP server.']);
  exit;
}

function sendCmd($fp, $cmd, $expectedCode) {
  fwrite($fp, "$cmd\r\n");
  $resp = fgets($fp, 512);
  if (strpos($resp, (string)$expectedCode) !== 0) {
    throw new Exception("SMTP error: $resp");
  }
}

try {
  fgets($fp, 512); // read server hello
  sendCmd($fp, "EHLO ".$_SERVER['SERVER_NAME'], 250);
  sendCmd($fp, "AUTH LOGIN", 334);
  sendCmd($fp, base64_encode($smtpUser), 334);
  sendCmd($fp, base64_encode($smtpPass), 235);
  sendCmd($fp, "MAIL FROM:<$smtpUser>", 250);
  sendCmd($fp, "RCPT TO:<$toEmail>", 250);
  sendCmd($fp, "DATA", 354);
  fwrite($fp, "Subject: $subject\r\n");
  fwrite($fp, "From: $smtpUser\r\n");
  fwrite($fp, "Reply-To: $email\r\n");
  fwrite($fp, "Content-Type: text/plain; charset=utf-8\r\n\r\n");
  fwrite($fp, $body."\r\n.\r\n");
  sendCmd($fp, "QUIT", 221);
  fclose($fp);

  echo json_encode(['message' => 'Email sent successfully!']);
} catch (Exception $e) {
  echo json_encode(['message' => 'SMTP failed: ' . $e->getMessage()]);
}
?>
