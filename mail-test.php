<?php
// ── FILL THESE IN ──────────────────────────────────────
$user = 'info@bharatgps.com';
$pass = 'rxeumqjrhyrzeeye'; // no spaces
$to   = 'admin@bharatgps.com';
// ──────────────────────────────────────────────────────

header('Content-Type: text/plain');

echo "=== BharatGPS Gmail SMTP Test ===\n\n";

// Try port 465 (SSL)
echo "Connecting ssl://smtp.gmail.com:465 ...\n";
$s = @fsockopen('ssl://smtp.gmail.com', 465, $en, $es, 15);
if (!$s) { echo "FAILED: $es\n"; exit; }
echo "Connected!\n";

function r($s){ $o=''; while(!feof($s)){$l=fgets($s,512);$o.=$l;if(isset($l[3])&&$l[3]===' ')break;} echo "  < ".trim($o)."\n"; return $o; }
function w($s,$c){ echo "  > $c\n"; fwrite($s,$c."\r\n"); }

r($s);
w($s,'EHLO bharatgps.com');       r($s);
w($s,'AUTH LOGIN');                r($s);
w($s,base64_encode($user));       $rr=r($s);
w($s,base64_encode($pass));       $auth=r($s);

if (strpos($auth,'235') === false) {
    echo "\nAUTH FAILED — check App Password\n";
    fclose($s); exit;
}
echo "\nAUTH OK!\n";

w($s,"MAIL FROM:<$user>");        r($s);
w($s,"RCPT TO:<$to>");            r($s);
w($s,'DATA');                     r($s);

$subj = '=?UTF-8?B?'.base64_encode('BharatGPS HR - Test').'?=';
$msg  = "From: BharatGPS HR <$user>\r\n";
$msg .= "To: $to\r\n";
$msg .= "Subject: $subj\r\n";
$msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$msg .= "Test email from BharatGPS HR System.\nTime: ".date('d M Y H:i:s')."\r\n.";
w($s,$msg);                       $sent=r($s);

if (strpos($sent,'250') !== false)
    echo "\n✅ EMAIL SENT to $to — check inbox!\n";
else
    echo "\nSEND FAILED\n";

w($s,'QUIT'); fclose($s);
?>
