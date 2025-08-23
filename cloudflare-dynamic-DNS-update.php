<?php
/* 
**********************************************
*  Cloudflare Dynamic DNS Update PHP script  *
**********************************************

WHAT THIS DOES
- Checks your current public IP using Cloudflare’s 1.1.1.1 trace endpoint.
- Looks up an A record in Cloudflare DNS for $record_name.
- Only updates the record if your IP changed (avoids unnecessary API calls).

RECOMMENDED EDITOR
Use a code editor that respects Unix line endings (e.g., Notepad++ on Windows).
Download: https://notepad-plus-plus.org/

TYPICAL HOST
Runs great on a small always-on Linux box (e.g., Raspberry Pi 5).
https://www.raspberrypi.com/products/raspberry-pi-5/

SETUP (Debian/Ubuntu examples)

1) Install prerequisites
   sudo apt update
   sudo apt install php php-curl curl

2) Create a Cloudflare API token
   - Cloudflare Dashboard → My Profile → API Tokens → Create Token
   - Use the “Edit zone DNS” template.
   - Scope it to only your target zone (domain) for safety.
   Required permission: Zone → DNS → Edit

3) Find your Zone ID
   - Cloudflare Dashboard → Select your domain → Overview (right side) → Zone ID
   (Optional via API, replace token below)
   curl -X GET "https://api.cloudflare.com/client/v4/zones" \
     -H "Authorization: Bearer 0000000000000000000000000000000000000000" \
     -H "Content-Type: application/json"
   # Look in the JSON for: "result":[{"id":"00000000000000000000000000000000", ...

4) Configure this script (see CONFIG section)
   - $api_token: Cloudflare API token with Zone:DNS:Edit
   - $zone_id:   Cloudflare Zone ID (NOT the domain name)
   - $record_name: host to update (e.g., host.example.com or example.com)

5) Schedule with cron (every 5 minutes example)
   Edit your crontab:
     crontab -e
   Add this line (adjust PHP and script paths):
   Note: Only copy/paste the part between the quotes, but don't include the quotes.
*/
//   "*/5 * * * * /usr/bin/php /path/to/cloudflare-dynamic-DNS-update.php -q >/dev/null 2>&1"
/*
   Notes:
   - The -q flag suppresses normal output; errors still log to the history file.
   - Ensure the user running cron can write to the log file paths set below.

OPTIONAL LOGIN DISPLAY
- $login_display_file prints “current IP + last-change time”.
  Add to ~/.bashrc (or ~/.profile):
    if [ -f /home/user/log/ip-change-display.txt ]; then
        cat /home/user/log/ip-change-display.txt
    fi

LOGGING
- $history_log_file appends a timestamp each time the IP changes (no IP values).
- Errors are appended with an “ERROR:” prefix and timestamp.

*/


// ==== CONFIG ====
$api_token    = '0000000000000000000000000000000000000000'; // Cloudflare API token (Zone:DNS:Edit)
$zone_id      = '00000000000000000000000000000000';         // Cloudflare Zone ID (not the domain name)
$record_name  = 'host.example.com';                         // Hostname/subdomain to update; use example.com for root A record

/* (Optional) Determine your Zone ID via API (replace the token)
curl -X GET "https://api.cloudflare.com/client/v4/zones" \
  -H "Authorization: Bearer 0000000000000000000000000000000000000000" \
  -H "Content-Type: application/json"
# Look for the “id” inside the “result” array.
  Note: Replace that long string of zeros with your Cloudflare API token.
*/

// Paths to your log files - change to wherever you want them
$login_display_file = '/home/user/log/ip-change-display.txt'; // For login display (current IP + last-change time)
$history_log_file   = '/home/user/log/ip-change-history.log'; // For history (timestamps when IP changed)

// ==== END OF CONFIG ====



// ==== ARGUMENTS ====
// Check if -q (quiet) flag is set
$quiet = in_array('-q', $argv);

// Helper function for output respecting quiet mode
function output($message) {
    global $quiet;
    if (!$quiet) {
        echo $message . "\n";
    }
}

// Helper function to log errors with details always
function log_error($message) {
    global $history_log_file;
    file_put_contents($history_log_file, date('Y-m-d H:i') . " ERROR: {$message}\n", FILE_APPEND | LOCK_EX);
    // Also print to screen if not quiet
    global $quiet;
    if (!$quiet) {
        echo "ERROR: {$message}\n";
    }
}

// ==== STEP 1: Get your public IP ====
// Uses Cloudflare's trace service (fast, reliable)
$ip_info = @file_get_contents('https://1.1.1.1/cdn-cgi/trace');
if ($ip_info === false || !preg_match('/ip=([0-9a-f\.:]+)/', $ip_info, $matches)) {
    log_error("Failed to get public IP");
    exit(1);
}
$public_ip = $matches[1];

// ==== STEP 2: Get current DNS record ID ====
$url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?type=A&name={$record_name}";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$api_token}", "Content-Type: application/json"],
    CURLOPT_RETURNTRANSFER => true
]);
$raw_response = curl_exec($ch);
if ($raw_response === false) {
    log_error("Curl error while fetching DNS record: " . curl_error($ch));
    curl_close($ch);
    exit(1);
}
curl_close($ch);

$response = json_decode($raw_response, true);
if (empty($response['result'][0]['id'])) {
    log_error("DNS record not found or API error:\n" . $raw_response);
    exit(1);
}

$record_id = $response['result'][0]['id'];
$current_ip = $response['result'][0]['content'];

// ==== STEP 3: Only update if the IP changed ====
if ($current_ip === $public_ip) {
    output("No update needed. IP is still {$public_ip}");
    exit(0);
}

// ==== STEP 4: Update DNS record ====
$update_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
$ch = curl_init($update_url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$api_token}", "Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        'type' => 'A',
        'name' => $record_name,
        'content' => $public_ip,
        'ttl' => 120, // 2 minutes
        'proxied' => false // true if you want Cloudflare proxy enabled
    ]),
    CURLOPT_RETURNTRANSFER => true
]);
$result_raw = curl_exec($ch);
if ($result_raw === false) {
    log_error("Curl error while updating DNS record: " . curl_error($ch));
    curl_close($ch);
    exit(1);
}
curl_close($ch);

$result = json_decode($result_raw, true);

if (!empty($result['success'])) {
    output("DNS updated to {$public_ip}");

    // Format timestamps
    $date_time_display = date('m-d H:i');      // For login display (MM-DD HH:MM)
    $date_time_log = date('Y-m-d H:i');        // For history log (YYYY-MM-DD HH:MM)

    // Write the IP and date/time line for login display (overwrite)
    file_put_contents($login_display_file, "{$date_time_display} Current IP is {$public_ip}\n");

    // Append just the date/time line to the history log (append)
    file_put_contents($history_log_file, "{$date_time_log}\n", FILE_APPEND | LOCK_EX);

    exit(0);
} else {
    log_error("Update failed:\n" . json_encode($result, JSON_PRETTY_PRINT));
    exit(1);
}
?>