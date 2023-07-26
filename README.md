# Synology-DDNS-IPv6
Synology DSM has still very limitted support for DynDNS including IPv6. Except for synology.me, only IPv4 is supported for all the pre-configured providers.

Your internet router may support DDNS including IPv6, but it will not be a solution to access your Synology over IPv6 from outside. There is a single IPv4 global address (common for the router and e.g. your Synology), but there are different global IPv6 addresses for the router and your Synology. Therefore an DynDNS update routine including IPv6 on the Synology is required to make services on the Synology via IPv6 available from outside and not only your router.

On your Synology device in the folder /usr/syno/bin/ddns/ there are scripts for some providers. If you are going to "Add" in the control panel under "External Access", "DDNS" you will find in the dropdown for "Service Providers" the entries from the file `/etc.defaults/ddns_provider.conf`.

With the following steps you can use a sub domain of your domain hosted at Strato to access your Synology including IPv6:
1) Follow the instructions under https://www.strato.de/faq/hosting/so-einfach-richten-sie-dyndns-fuer-ihre-domains-ein/ to setup a sub domain for the Synology on your domain.
2) Copy the php script from this repository to `/usr/syno/bin/ddns/strato46.php`. And make it executable (chmod 755 ...)
3) Add to that configuration file /etc.defaults/ddns_provider.conf the lines
   
       [STRATO_4_6]
       modulepath=/usr/syno/bin/ddns/strato46.php
       queryurl=https://dyndns.strato.com/nic/update
4) In the control panel you can now select STRATO_4_6 from the dropdown, enter your host name (subdomain.ihredomain.de), user name (ihredomain.de) and password.
 
**Important:** In the control panel in the column "External Address" will still only your IPv4 address occure.

But in /tmp/ddns.log the last line with the response from Strato should be e.g.

`curl_exec result: nochg 192.XXX.X.X 2003:8106:1234:5678:abcd:ef01:2345:6789`

## Credits and References
- Thanks to PhySix66 https://community.synology.com/enu/forum/1/post/130109
- Thanks to mgutt and mweigel https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm
- Thanks to hwkr https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a
