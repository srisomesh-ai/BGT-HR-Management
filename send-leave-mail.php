<?php
// ═══════════════════════════════════════════════════════
//  BharatGPS HR — Leave Request Email Notifier
//  Uses Gmail SMTP (Google Workspace: info@bharatgps.com)
//
//  SETUP STEPS:
//  1. Generate a Gmail App Password:
//     → myaccount.google.com → Security → 2-Step Verification
//     → App passwords → Select app: Mail → Select device: Other
//     → Name it "BharatGPS HR" → Copy the 16-char password
//  2. Paste that password in GMAIL_APP_PASSWORD below
//  3. Upload this file + the /PHPMailer/ folder to public_html
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { echo json_encode(['ok'=>false,'error'=>'POST only']); exit; }

// ── Gmail credentials ───────────────────────────────────
define('GMAIL_USER',     'info@bharatgps.com');
define('GMAIL_APP_PASSWORD', 'rxeumqjrhyrzeeye'); // 16-char App Password
define('GMAIL_FROM_NAME','BharatGPS HR System');

// ── Recipients ──────────────────────────────────────────
$recipients = [
    'admin@bharatgps.com',
    'sales@bharatgps.com',
    'manager@bharatgps.com',
    'accounts@bharatgps.com',
];

// ── Parse request body ──────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$empId     = htmlspecialchars($data['empId']     ?? '');
$empName   = htmlspecialchars($data['empName']   ?? '');
$leaveType = htmlspecialchars($data['leaveType'] ?? 'Leave');
$fromDate  = htmlspecialchars($data['fromDate']  ?? '');
$toDate    = htmlspecialchars($data['toDate']    ?? $fromDate);
$reason    = htmlspecialchars($data['reason']    ?? '');
$reqAt     = date('d M Y, h:i A');

$days = '';
if ($fromDate && $toDate) {
    $d1   = new DateTime($fromDate);
    $d2   = new DateTime($toDate);
    $diff = $d1->diff($d2)->days + 1;
    $days = $diff . ($diff === 1 ? ' day' : ' days');
}

// ── Email subject ───────────────────────────────────────
$subject = "Leave Request: {$empName} ({$empId}) | {$leaveType}";

// ── HTML body ───────────────────────────────────────────
$daysHtml = $days ? " <span style='color:#8b93b0;font-weight:400;font-size:12px;'>({$days})</span>" : '';
$body = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f6fa;margin:0;padding:20px;}
  .wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
  .hdr{background:linear-gradient(135deg,#0f1e6b,#1c3faa);padding:28px 32px;}
  .hdr-t{color:#fff;font-size:20px;font-weight:700;margin:0;}
  .hdr-s{color:rgba(255,255,255,0.6);font-size:13px;margin-top:4px;}
  .bd{padding:28px 32px;}
  .badge{display:inline-block;background:#eef2ff;color:#1c3faa;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:20px;}
  .row{display:flex;padding:10px 0;border-bottom:1px solid #f0f0f0;}
  .row:last-child{border-bottom:none;}
  .lbl{color:#8b93b0;font-size:12px;width:130px;flex-shrink:0;padding-top:2px;}
  .val{color:#0f1629;font-size:13.5px;font-weight:600;}
  .btn{display:inline-block;background:#1c3faa;color:#fff !important;padding:11px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin-top:20px;}
  .ft{background:#f5f6fa;padding:16px 32px;text-align:center;font-size:11px;color:#8b93b0;}
</style></head><body>
<div class='wrap'>
  <div class='hdr'>
    <div class='hdr-t'>&#127973; New Leave Request</div>
    <div class='hdr-s'>BharatGPS HR Management System</div>
  </div>
  <div class='bd'>
    <div class='badge'>&#9203; Pending Approval</div>
    <div class='row'><div class='lbl'>Employee</div><div class='val'>{$empName}</div></div>
    <div class='row'><div class='lbl'>Employee ID</div><div class='val'>{$empId}</div></div>
    <div class='row'><div class='lbl'>Leave Type</div><div class='val'>{$leaveType}</div></div>
    <div class='row'><div class='lbl'>From Date</div><div class='val'>{$fromDate}</div></div>
    <div class='row'><div class='lbl'>To Date</div><div class='val'>{$toDate}{$daysHtml}</div></div>
    <div class='row'><div class='lbl'>Reason</div><div class='val'>{$reason}</div></div>
    <div class='row'><div class='lbl'>Submitted At</div><div class='val'>{$reqAt}</div></div>
    <a href='https://floralwhite-locust-819146.hostingersite.com/index.html' class='btn'>Open Admin Panel &#8594;</a>
  </div>
  <div class='ft'>Automated notification from BharatGPS HR System &mdash; do not reply.</div>
</div></body></html>";

$plain = "New Leave Request\n\nEmployee  : {$empName} ({$empId})\nLeave Type: {$leaveType}\nFrom      : {$fromDate}\nTo        : {$toDate}" . ($days ? " ({$days})" : "") . "\nReason    : {$reason}\nSubmitted : {$reqAt}\n\nOpen admin panel: https://floralwhite-locust-819146.hostingersite.com/index.html";

// ── Send via Gmail SMTP using raw socket ────────────────
function smtp_send($host, $port, $user, $pass, $from, $fromName, $toList, $subject, $html, $plain) {
    $sock = @fsockopen('ssl://'.$host, $port, $errno, $errstr, 15);
    if (!$sock) return ['ok'=>false,'error'=>"Cannot connect to {$host}:{$port} — {$errstr}"];

    function smtp_cmd($sock, $cmd, $expect) {
        if ($cmd) fwrite($sock, $cmd."\r\n");
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        if ($expect && strpos($resp, $expect) === false)
            return ['ok'=>false,'error'=>"Expected {$expect}, got: ".trim($resp)];
        return ['ok'=>true,'resp'=>trim($resp)];
    }

    $r = smtp_cmd($sock, null,                          '220'); if(!$r['ok']) { fclose($sock); return $r; }
    $r = smtp_cmd($sock, 'EHLO bharatgps.com',          '250'); if(!$r['ok']) { fclose($sock); return $r; }
    $r = smtp_cmd($sock, 'AUTH LOGIN',                  '334'); if(!$r['ok']) { fclose($sock); return $r; }
    $r = smtp_cmd($sock, base64_encode($user),          '334'); if(!$r['ok']) { fclose($sock); return $r; }
    $r = smtp_cmd($sock, base64_encode($pass),          '235'); if(!$r['ok']) { fclose($sock); return $r; }
    $r = smtp_cmd($sock, "MAIL FROM:<{$from}>",         '250'); if(!$r['ok']) { fclose($sock); return $r; }

    foreach ($toList as $to) {
        $r = smtp_cmd($sock, "RCPT TO:<{$to}>", '250');
        if(!$r['ok']) { fclose($sock); return $r; }
    }

    $r = smtp_cmd($sock, 'DATA', '354'); if(!$r['ok']) { fclose($sock); return $r; }

    $boundary = md5(uniqid());
    $toHeader = implode(', ', $toList);
    $encSubj  = '=?UTF-8?B?'.base64_encode($subject).'?=';

    $msg  = "From: {$fromName} <{$from}>\r\n";
    $msg .= "To: {$toHeader}\r\n";
    $msg .= "Subject: {$encSubj}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $msg .= "X-Mailer: BharatGPS-HR/1.0\r\n\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $msg .= $plain."\r\n\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $html."\r\n\r\n";
    $msg .= "--{$boundary}--\r\n";
    $msg .= "\r\n.";

    $r = smtp_cmd($sock, $msg, '250'); if(!$r['ok']) { fclose($sock); return $r; }
    smtp_cmd($sock, 'QUIT', null);
    fclose($sock);
    return ['ok'=>true];
}

$result = smtp_send(
    'smtp.gmail.com', 465,
    GMAIL_USER, GMAIL_APP_PASSWORD,
    GMAIL_USER, GMAIL_FROM_NAME,
    $recipients,
    $subject, $body, $plain
);

echo json_encode(array_merge($result, ['recipients' => $recipients]));
?>
