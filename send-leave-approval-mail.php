<?php
// ═══════════════════════════════════════════════════════
//  BharatGPS HR — Leave Approval Email Notifier
//  Sends to employee when admin approves their leave
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { echo json_encode(['ok'=>false,'error'=>'POST only']); exit; }

define('GMAIL_USER',         'info@bharatgps.com');
define('GMAIL_APP_PASSWORD', 'rxeumqjrhyrzeeye');
define('GMAIL_FROM_NAME',    'BharatGPS HR Team');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$empId     = htmlspecialchars($data['empId']     ?? '');
$empName   = htmlspecialchars($data['empName']   ?? '');
$empEmail  = htmlspecialchars($data['empEmail']  ?? '');
$leaveType = htmlspecialchars($data['leaveType'] ?? 'Leave');
$fromDate  = htmlspecialchars($data['fromDate']  ?? '');
$toDate    = htmlspecialchars($data['toDate']    ?? $fromDate);
$adminNote = htmlspecialchars($data['adminNote'] ?? '');

if (!$empEmail) { echo json_encode(['ok'=>false,'error'=>'No employee email']); exit; }

// Calculate duration
$days = '';
if ($fromDate && $toDate) {
    $d1   = new DateTime($fromDate);
    $d2   = new DateTime($toDate);
    $diff = $d1->diff($d2)->days + 1;
    $days = $diff . ($diff === 1 ? ' day' : ' days');
}

$toDateLine  = ($toDate && $toDate !== $fromDate)
    ? "<div class='row'><div class='lbl'>To Date</div><div class='val'>{$toDate}" . ($days ? " <span style='color:#8b93b0;font-weight:400;'>({$days})</span>" : "") . "</div></div>"
    : '';
$noteLine    = $adminNote
    ? "<div class='row'><div class='lbl'>Admin Note</div><div class='val' style='color:#1c3faa;font-style:italic;'>{$adminNote}</div></div>"
    : '';
$toDatePlain = ($toDate && $toDate !== $fromDate) ? "To Date      : {$toDate}" . ($days ? " ({$days})" : "") . "\n" : '';
$notePlain   = $adminNote ? "Admin Note   : {$adminNote}\n" : '';

$recipients = [$empEmail];
$subject    = "Leave Approved — {$leaveType} | BharatGPS";

$body = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f6fa;margin:0;padding:20px;}
  .wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
  .hdr{background:linear-gradient(135deg,#0f1e6b,#1c3faa);padding:28px 32px;}
  .hdr-t{color:#fff;font-size:20px;font-weight:700;margin:0;}
  .hdr-s{color:rgba(255,255,255,0.65);font-size:13px;margin-top:4px;}
  .bd{padding:28px 32px;}
  .hi{font-size:15px;color:#0f1629;margin-bottom:16px;}
  .badge{display:inline-block;background:#dcfdf0;color:#0d9e6a;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:700;margin-bottom:18px;}
  .row{display:flex;padding:9px 0;border-bottom:1px solid #f0f0f0;}
  .row:last-child{border-bottom:none;}
  .lbl{color:#8b93b0;font-size:12px;width:140px;flex-shrink:0;padding-top:2px;}
  .val{color:#0f1629;font-size:13px;font-weight:600;}
  .msg{margin-top:20px;padding:14px 16px;background:#f5f6fa;border-radius:8px;font-size:13px;color:#4a5272;line-height:1.7;}
  .ft{background:#f5f6fa;padding:14px 32px;text-align:center;font-size:11px;color:#8b93b0;border-top:1px solid #e2e5ef;}
</style></head><body>
<div class='wrap'>
  <div class='hdr'>
    <div class='hdr-t'>&#9989; Leave Request Approved</div>
    <div class='hdr-s'>BharatGPS HR Management System</div>
  </div>
  <div class='bd'>
    <div class='hi'>Dear <strong>{$empName}</strong>,</div>
    <div class='badge'>&#10003; Your leave has been approved</div>
    <div class='row'><div class='lbl'>Employee ID</div><div class='val'>{$empId}</div></div>
    <div class='row'><div class='lbl'>Leave Type</div><div class='val'>{$leaveType}</div></div>
    <div class='row'><div class='lbl'>From Date</div><div class='val'>{$fromDate}</div></div>
    {$toDateLine}
    {$noteLine}
    <div class='msg'>
      We understand you may have important personal matters to attend to.<br><br>
      Take the time you need &mdash; we have things covered here.<br>
      Wishing you and your family well. Please reach out if you need anything from our side.
    </div>
  </div>
  <div class='ft'>This is an automated notification from BharatGPS HR System &mdash; do not reply to this email.<br>For queries contact info@bharatgps.com</div>
</div>
</body></html>";

$plain = "Dear {$empName},\n\nYour leave request has been reviewed and approved.\n\n"
       . "Employee ID  : {$empId}\n"
       . "Leave Type   : {$leaveType}\n"
       . "From Date    : {$fromDate}\n"
       . $toDatePlain
       . $notePlain
       . "\nWe understand you may have important personal matters to attend to. "
       . "Take the time you need — we have things covered here. "
       . "Wishing you and your family well.\n\nWarm regards,\nBharatGPS HR Team";

function smtp_send($host, $port, $user, $pass, $from, $fromName, $toList, $subject, $html, $plain) {
    $sock = @fsockopen('ssl://'.$host, $port, $errno, $errstr, 15);
    if (!$sock) return ['ok'=>false,'error'=>"Cannot connect to {$host}:{$port} — {$errstr}"];
    function smtp_cmd3($sock, $cmd, $expect) {
        if ($cmd) fwrite($sock, $cmd."\r\n");
        $resp = '';
        while (!feof($sock)) { $line = fgets($sock, 512); $resp .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        if ($expect && strpos($resp, $expect) === false) return ['ok'=>false,'error'=>"Expected {$expect}, got: ".trim($resp)];
        return ['ok'=>true,'resp'=>trim($resp)];
    }
    $r = smtp_cmd3($sock, null, '220');                          if(!$r['ok']){fclose($sock);return $r;}
    $r = smtp_cmd3($sock, 'EHLO bharatgps.com', '250');          if(!$r['ok']){fclose($sock);return $r;}
    $r = smtp_cmd3($sock, 'AUTH LOGIN', '334');                   if(!$r['ok']){fclose($sock);return $r;}
    $r = smtp_cmd3($sock, base64_encode($user), '334');           if(!$r['ok']){fclose($sock);return $r;}
    $r = smtp_cmd3($sock, base64_encode($pass), '235');           if(!$r['ok']){fclose($sock);return $r;}
    $r = smtp_cmd3($sock, "MAIL FROM:<{$from}>", '250');          if(!$r['ok']){fclose($sock);return $r;}
    foreach ($toList as $to) { $r = smtp_cmd3($sock, "RCPT TO:<{$to}>", '250'); if(!$r['ok']){fclose($sock);return $r;} }
    $r = smtp_cmd3($sock, 'DATA', '354');                         if(!$r['ok']){fclose($sock);return $r;}
    $boundary = md5(uniqid());
    $encSubj  = '=?UTF-8?B?'.base64_encode($subject).'?=';
    $msg  = "From: {$fromName} <{$from}>\r\nTo: ".implode(', ',$toList)."\r\nSubject: {$encSubj}\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\nX-Mailer: BharatGPS-HR/1.0\r\n\r\n";
    $msg .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n\r\n";
    $msg .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n--{$boundary}--\r\n\r\n.";
    $r = smtp_cmd3($sock, $msg, '250'); if(!$r['ok']){fclose($sock);return $r;}
    smtp_cmd3($sock, 'QUIT', null); fclose($sock);
    return ['ok'=>true];
}

$result = smtp_send('smtp.gmail.com', 465, GMAIL_USER, GMAIL_APP_PASSWORD, GMAIL_USER, GMAIL_FROM_NAME, $recipients, $subject, $body, $plain);
echo json_encode(array_merge($result, ['recipients'=>$recipients]));
?>
