#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns/:/tmp/:/var/log/
<?php # Attention: No empty line before this!!!! The output must start with 'good' or 'nochg' in the 1st line for an success message 
# https://community.synology.com/enu/forum/1/post/130109
# https://www.computerbase.de/forum/threads/ddns-mit-ipv6.2057194/
# https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm
# https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a

# Remark: If the command "ip -6 addr" is reporting multiple IPv6 addresses, the 1st global one is used.
#  You may change the command and add the name the interface, to e.g. "ip -6 addr list ovs_eth1 ..." 
#  The interface names can be extracted from the result of the "ip -6 addr" command output
# Alternative (see below commented out version): Ask Google for the IPv6, whith which the DSM is online

# Parameters: 1=account (Strato Domain), 2=pwd (use single quotes!) 3=hostname (incl. sub domain), 4=ip (IPv4)

# if the IP addresses are sent too often to Strato, then you will get "abuse ..."
# DSM runs this normally once per day, but in some cases ervery few seconds
# ==> Workaround: If last update of the logfile is less than ageMin_h, don't send but simply return "nochg ..." to DSM
$LOG_NAME='/tmp/ddns_ipv64.log';
$ageMin_h=2.0;
if (file_exists($LOG_NAME)) {
  $age_h=(time()-filemtime($LOG_NAME))/3600;
} else {
  $age_h=9999;
}

# https://stackoverflow.com/questions/1062716/php-returning-the-last-line-in-a-file
$LOG_NAME_ESC = escapeshellarg($LOG_NAME); // for the security concious (should be everyone!)
$lastLogLine = `tail -n 1 $LOG_NAME_ESC`;
# should be eg.:
# returned result: nochg 87.175.192.182 2003:c8:72e:a000:9209:d0ff:fe05:c05f
$fLOG = fopen($LOG_NAME, 'a+'); # open_basedir option required!
$date = date('Y-m-d H:i:s');
$msg="\n$date Start $argv[0]\n";
# echo("\n\n$date Start\n"); 
if ($argc !== 5) {
  echo "Error: Bad param count $argc instead of 5!\n $argv[0]  <account> '<PW>' <host> <ipv4>\n";
  $msg .= "  Error: Bad param count $argc instead of 5!\n $argv[0] <account> '<PW>' <host> <ipv4>\n";
  fwrite($fLOG, "$msg");
  fclose($fLOG);
  exit("Error: Bad param count $argc instead of 5!");
}
$account = (string)$argv[1]; 
$pwd = (string)$argv[2];
$hostname = (string)$argv[3]; 
$ip = (string)$argv[4];  

// check that the hostname contains '.'
if (strpos($hostname, '.') === false) {
  echo "Error: Badparam hostname $hostname\n";
  $msg .= "  Error: Badparam hostname $hostname\n";
  fwrite($fLOG, "$msg");
  fclose($fLOG);
  exit("Error: Badparam hostname $hostname");
}

if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
  echo("Error Badparam: 1st IP is not IPv4 in '$ip'\n");
  $msg .= "  Error Badparam: 1st IP is not IPv4 in '$ip'\n";
  fwrite($fLOG, "$msg");
  fclose($fLOG);
  exit("Error Badparam: 1st IP is not IPv4 in '$ip'");
}

# Solution without contacting Google:
# https://forum.feste-ip.net/viewtopic.php?t=468
# IPV6=$(ip addr list eth0 | grep "global" | cut -d ' ' -f6 | cut -d/ -f1)
# https://superuser.com/questions/468727/how-to-get-the-ipv6-ip-address-in-linux
# $cmd = "ip -6 addr | grep inet6 | awk -F '[ \t]+|/' '{print $3}' | grep -v ^::1 | grep -v ^fe80  | grep -v ^fd00";
# 2024-10-22, issue #4 from hseliger:
$cmd = "ip -6 addr | grep inet6 | grep -v deprecated | awk -F '[ \t]+|/' '{print $3}' | grep -v ^::1 | grep -v ^fe80 | grep -v ^fd";
$cmd .= " 2>&1";
# $msg .= "cmd: $cmd\n";
$ipv6multi = shell_exec($cmd);
# msg .= "ipv6multi $ipv6multi"; # if more than one LAN active: multiple adresses
$lines = explode("\n", $ipv6multi);
$msg .= "  used IPv6: $lines[0]\n";
# msg .= "IP2: $lines[1]\n";
$ipv6=$lines[0];

# Alternative Solution: Get the online IPv6 of the disk station:
# $ipv6 = get_data('https://domains.google.com/checkip');

if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
  if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    # echo("$ipv6 is a valid IPv6 address\n");
  } else {
    echo("$ipv6 is not a valid global IPv6 address\n");
    $msg .= "  $ipv6 is not a valid global IPv6 address\n";
  }
} else {
  echo("$ipv6 is not a valid IPv6 address\n");
  $msg .= "  $ipv6 is not a valid IPv6 address\n";
}

# 
# https://ipv64.net/dyndns
# https://ipv64.net/update.php?key=<token>&domain=<domain>&ip=<ipaddr>&ip6=<ip6addr>&output=min

# $url = 'https://' . $account . ':' . $pwd   . '@dyndns.strato.com/nic/update?hostname=' . $hostname . '&myip=';
$url = 'https://ipv64.net/nic/update?output=min&key=' . $pwd . '&domain=' . $hostname . '&ip='; 
$unchanged=true;
$myips = $ip;
if($ipv6 != '') { # IPv4 and IPv6 available
  $myips .= '&ip6=';
  $unchanged=str_contains($lastLogLine, $ipv6);
}  
if($ipv6 != '') {
  $myips .= $ipv6;
}
$url .=  $myips;
$msg .= "  used url: $url\n";

$res="nochg $ip $ipv6\n";
# $msg .= "  last sending before $age_h h\n";
if (! str_contains($lastLogLine, $ip) ) {
  $unchanged=false;
  # $msg .= "  new IPv4 $ip, not found in '$lastLogLine'\n";
}
if ( ($age_h > $ageMin_h) || (! $unchanged ) )  {
  # Send now the actual IPs to the DDNS provider Strato:
  $req = curl_init();
  curl_setopt($req, CURLOPT_URL, $url);
  curl_setopt($req,CURLOPT_RETURNTRANSFER,1); # https://stackoverflow.com/questions/6516902/how-to-get-response-using-curl-in-php
  curl_setopt($req,CURLOPT_CONNECTTIMEOUT,25);
  # without an agent you will get "badagent 0.0.0.0"
  # https://www.linksysinfo.org/index.php?threads/ddns-custom-url-badagent-error.75520/
  $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';
  curl_setopt($req, CURLOPT_USERAGENT, $agent);
  # CURLOPT_AUTOREFERER
  #curl_setopt($req, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  #curl_setopt($req, CURLOPT_USERPWD, "$account:$pwd");

  # $res = 'nochg 9.99.999.999 2003:99:99:99:99:99:99:99'; # my be used for debugging
  # $res = 'badauth test';
  $res = curl_exec($req);
  curl_close($req);
  $msg .= "  curl_exec result: $res";
} else {
  $msg .= "  sending skipped as last update was only $age_h h ago to avoid 'abuse ...' message\n";
  $msg .= "  returned result: $res";
}

# https://community.synology.com/enu/forum/17/post/57640, normal responses:
    # good - Update successfully.
    # nochg - Update successfully but the IP address have not changed.
    # nohost - The hostname specified does not exist in this user account.
    # abuse - The hostname specified is blocked for update abuse, too often requisted in short period
    # notfqdn - The hostname specified is not a fully-qualified domain name.
    # badauth - Authenticate failed.
    # 911 - There is a problem or scheduled maintenance on provider side
    # badagent - The user agent sent bad request(like HTTP method/parameters is not permitted)
    # badresolv - Failed to connect to because failed to resolve provider address.
    # badconn - Failed to connect to provider because connection timeout.

echo("$res"); # The script output needs to start(!!) with "nochg" or "good" to avoid error messages in the synology protocol list.
if ( (strpos($res, "good") !== 0) && (strpos($res, "nochg") !== 0)) { 
  syslog(LOG_ERR, "$argv[0]: $res, see /tmp/ddns.log");
}
fwrite($fLOG, "$msg");
fclose($fLOG);
