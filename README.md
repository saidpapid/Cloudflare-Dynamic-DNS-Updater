# Cloudflare Dynamic DNS Update PHP script

This script updates your current dynamic IP address in your DNS records which you have hosted at Cloudflare. Don't have a domain name? Cloudflare offers wholesale priced domain names at their cost. I have all of my domain names registered with Cloudflare. .com and .net domains are like $11 a year and included is free DNS hosting with free one-click DNSSEC setup! Cloudflare even offers free DNS hosting for your domains registered at another registrar! If you want, you can also proxy your website through Cloudflare's proxy to hide your IP address. 

WHAT THIS DOES
- Checks your current public IP using Cloudflare’s 1.1.1.1 trace endpoint.
- Looks up an A record in Cloudflare DNS for $record_name.
- Only updates the record if your IP changed (avoids unnecessary API calls).

RECOMMENDED EDITOR<br>
Use a code editor that respects Unix line endings (e.g., [Notepad++](https://notepad-plus-plus.org/) on Windows).

TYPICAL HOST<br>
Runs great on a small always-on Linux box (e.g., [Raspberry Pi 5](https://www.mynetblog.com/Raspberry-Pi/)).<br>

SETUP (Debian/Ubuntu examples)

1) Install prerequisites:<br>
   `$ sudo apt update`<br>
   `$ sudo apt install php php-curl curl`

2) Create a Cloudflare API token<br>
   [Cloudflare Dashboard](https://dash.cloudflare.com/) → My Profile → API Tokens → Create Token<br>
   Use the “Edit zone DNS” template.<br>
   Scope it to only your target zone (domain) for safety.<br>
   Required permission: Zone → DNS → Edit

3) Find your Zone ID<br>
   Cloudflare Dashboard → Select your domain → Overview (right side) → Zone ID<br>
   (Optional via API, replace token below)<br>
   ```bash
     curl -X GET "https://api.cloudflare.com/client/v4/zones" \
     -H "Authorization: Bearer 0000000000000000000000000000000000000000" \
     -H "Content-Type: application/json"<br>
   ```
   Look in the JSON for: "result":[{"id":"00000000000000000000000000000000", ...

4) Configure this script (see CONFIG section)<br>
   $api_token: Cloudflare API token with Zone:DNS:Edit<br>
   $zone_id:   Cloudflare Zone ID (NOT the domain name)<br>
   $record_name: host to update (e.g., host.example.com or example.com)

5) Schedule with cron (every 5 minutes example)<br>
   Edit your crontab:<br>
     `$ crontab -e`<br>
   Add this line (adjust PHP and script paths):<br>
   
   `*/5 * * * * /usr/bin/php /path/to/cloudflare-dynamic-DNS-update.php -q >/dev/null 2>&1`

   Notes:
   * The `-q` flag suppresses normal output; errors still log to the history file.
   * Ensure the user running cron can write to the log file paths set below.

OPTIONAL LOGIN DISPLAY<br>
   $login_display_file prints “current IP + last-change time”.<br>
   Add to `~/.bashrc` (or `~/.profile`):
   ```bash
      if [ -f /home/user/log/ip-change-display.txt ]; then
          cat /home/user/log/ip-change-display.txt
      fi
   ```
LOGGING
* $history_log_file appends a timestamp each time the IP changes (no IP values).<br>
* Errors are appended with an “ERROR:” prefix and timestamp.
