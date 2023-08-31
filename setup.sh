#!/bin/bash

CONFIG_FILE="/etc.defaults/ddns_provider.conf"
TMP_CONFIG="/tmp/ddns_tmp_config"

#Repo info
GITHUB_REPO_BASE="https://raw.githubusercontent.com/JensHouses/synology-ddns-IPv6/main"
PHP_FILES=("strato46.php" "ionos46.php" "ipv64.php")

# DDNS Providers
PROVIDERS="
[STRATO_4_6]
  modulepath=/usr/syno/bin/ddns/strato46.php
  queryurl=https://dyndns.strato.com/nic/update
[IONOS46]
  modulepath=/usr/syno/bin/ddns/ionos46.php
  queryurl=https://ipv4.api.hosting.ionos.com/dns/v1/dyndns
  website=https://ipv4.api.hosting.ionos.com
[IPV64.NET]
  modulepath=/usr/syno/bin/ddns/ipv64.php
  queryurl=https://ipv64.net/nic/update
  website=https://ipv64.net
"

# Update the configuration file
touch "$TMP_CONFIG"
echo "$PROVIDERS" | while IFS= read -r line; do
    SECTION=$(echo "$line" | grep '^\[' | sed 's/\[\|\]//g')
    if [ -n "$SECTION" ]; then
        # Remove section if it already exists in config
        sed -i "/\[$SECTION\]/,/^$/d" "$CONFIG_FILE"
    fi
    echo "$line" >> "$TMP_CONFIG"
done

# Append new providers to the config file
cat "$TMP_CONFIG" >> "$CONFIG_FILE"
rm -f "$TMP_CONFIG"

# Download and set up PHP files

for file in "${PHP_FILES[@]}"; do
    wget "$GITHUB_REPO_BASE/$file" -O "/usr/syno/bin/ddns/$file"
    chmod 755 "/usr/syno/bin/ddns/$file"
done

echo "Setup completed!"
"- Thanks to PhySix66 https://community.synology.com/enu/forum/1/post/130109"
"- Thanks to mgutt and mweigel https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm"
"- Thanks to hwkr https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a"
"- Thanks to schmidhorst https://github.com/schmidhorst for the inital Repo which i only forked and edited.

