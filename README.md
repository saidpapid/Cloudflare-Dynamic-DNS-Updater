This description and instructions are in the script.

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

   */5 * * * * /usr/bin/php /path/to/cloudflare-dynamic-DNS-update.php -q >/dev/null 2>&1

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
