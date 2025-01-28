# Synology-DDNS-IPv6
Synology DSM has still very limitted support for DynDNS including IPv6. Except for synology.me, only IPv4 is supported for all the pre-configured providers. As long as the Internet provider assigns static IPv6 addresses, this is not a problem. In Germany, however, many providers are using dynamic IPv6 prefixes for private individuals, which may change each time a new internet connection is established.

Your internet router may support DDNS including IPv6, but it will not be a solution to access your Synology over IPv6 from outside. There is a single IPv4 global address (common for the router and e.g. your Synology), but there are different global IPv6 addresses for the router and your Synology. Therefore an DynDNS update routine including IPv6 on the Synology is required to make services on the Synology via IPv6 available from outside and not only your router.

On your Synology device in the folder /usr/syno/bin/ddns/ there are scripts for some providers. If you are going to "Add" in the control panel under "External Access", "DDNS" you will find in the dropdown for "Service Providers" the entries from the file `/etc.defaults/ddns_provider.conf`.

With the following steps you can use a sub domain of your domain hosted at Strato, Ionos or ipv64.net to access your Synology including IPv6:
1) Follow the instructions to setup a sub domain for the Synology on your domain.

   - Strato    : https://www.strato.de/faq/hosting/so-einfach-richten-sie-dyndns-fuer-ihre-domains-ein/
   - Ionos     : https://www.ionos.de/hilfe/domains/ip-adresse-konfigurieren/dynamisches-dns-ddns-einrichten-bei-company-name
   - ipv64.net : https://ipv64.net/dyndns

You can now use the setup script to do steps 2 and 3 for you:
SSH into your Synology and use:   
`curl -sL https://raw.githubusercontent.com/JensHouses/synology-ddns-IPv6/main/setup.sh | sudo bash`

2) Copy the php scripts from this repository to `/usr/syno/bin/ddns/`. And make it executable (chmod 755 ...)
   - `/usr/syno/bin/ddns/strato46.php` 
   - `/usr/syno/bin/ddns/ionos46.php` 
   - `/usr/syno/bin/ddns/ipv64.php` 
3) Add to that configuration file /etc.defaults/ddns_provider.conf the lines
   
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
4) In the control panel you can now select `STRATO_4_6`, `IONOS46` or `IPV64.NET` from the dropdown, enter your host name (subdomain.ihredomain.de), user name (ihredomain.de) and password.
   When using IONOS you need to split the token into two seperate strings and put the first part in user name and the second part in password. The Password field only allows 128 characters.

DSM is executing the DDNS update normally once every 24 hours. But sometimes every few minutes and that causes "abuse " response from Strato and a critical DSM Protocoll Center entry. To avoid that, a minimum interval $ageMin_h with preset to 2.0 hours was added.

**Important:** 
- In the control panel in the column "External Address" will still only your IPv4 address occure.
- After a DSM version update the steps 2 and 3 may be needed to repeat again. Or you are setting up a scheduled task to run the install.sh as root at each re-boot instead of manual re-installation after DSM version update.

But in /tmp/ddns.log the last line with the response from Strato should be e.g.

`curl_exec result: nochg 192.XXX.X.X 2003:8106:1234:5678:abcd:ef01:2345:6789`

## Credits and References
- Thanks to PhySix66 https://community.synology.com/enu/forum/1/post/130109
- Thanks to mgutt and mweigel https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm
- Thanks to hwkr https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a
