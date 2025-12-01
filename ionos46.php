#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns/:/tmp/:/var/log/
<?php # Attention: No empty line before this!!!! The output must start with 'good' or 'nochg' in the 1st line for an success message
# https://community.synology.com/enu/forum/1/post/130109
# https://www.computerbase.de/forum/threads/ddns-mit-ipv6.2057194/
# https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm
# https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a

# https://www.synology-forum.de/threads/dyndns-adresse-mit-reverse-proxy-endet-an-fritzbox.119221/page-2

# https://nocksoft.de/tutorials/dyndns-fuer-ipv6-server-hinter-fritzbox-konfigurieren/
# https://www.heise.de/ratgeber/IPv6-Freigaben-mit-Namensdienst-auf-Fritzboxen-nutzen-6234026.html
# 

# Remark: If the command "ip -6 addr" is reporting multiple IPv6 addresses, the 1st global one is used.
#  You may change the command and add the name the interface, to e.g. "ip -6 addr list ovs_eth1 ..."
#  The interface names can be extracted from the result of the "ip -6 addr" command output

# Parameters: 1=account (Ionos Token Part 1), 2=pwd (Ionos Token Part 2) 3=hostname (incl. sub domain), 4=ip (IPv4)

# if the IP addresses are sent too often to Strato, then you will get "abuse ..."
# DSM runs this normally once per day, but in some cases ervery few seconds
# ==> Workaround: If last update of the logfile is less than ageMin_h, don't send but simply return "nochg ..." to DSM
$syslogSuccess=false; # true: Generate LogCenter entry always, not only for failure
$syslogSkipped=false; # true: Generate LogCenter entry if it was tirggerd, but skipped
$LOG_NAME='/tmp/ddns_ionos.log';
$ageMin_h=2.0;


function isCGNATIPv4(string $ip): bool {
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return false;
    }
  $long = ip2long($ip);
  $start = ip2long("100.64.0.0");
  $end   = ip2long("100.127.255.255");
  return ($long >= $start && $long <= $end);
  }

function isGlobalIPv4_DSaware(string $ip): bool {
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return false;     // ungültige IPv4?
    }
  // Private und reservierte ausschließen
  if (filter_var($ip, FILTER_VALIDATE_IP, [ 'flags' => FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE]) === false) {
    return false;
    }
  if (isCGNATIPv4($ip)) {
    return false;
    }
  return true; // echte öffentliche IPv4
  }



$lastLogLine='';
if (file_exists($LOG_NAME)) {
  $age_h=(time()-filemtime($LOG_NAME))/3600;
} else {
  $age_h=9999;
}

# https://stackoverflow.com/questions/1062716/php-returning-the-last-line-in-a-file
$LOG_NAME_ESC = escapeshellarg($LOG_NAME); // for the security concious (should be everyone!)
$lastLogLine = `tail -n 1 $LOG_NAME_ESC`;
$lastLogLine = rtrim($lastLogLine, "\n");
# should be eg.:
# returned result: nochg 87.175.192.182 2003:c8:72e:a000:9209:d0ff:fe05:c05f
$fLOG = fopen($LOG_NAME, 'a+'); # open_basedir option required!
$date = date('Y-m-d H:i:s');
$user=get_current_user();
$msg="\n$date Start $argv[0] as $user\n";
# echo("\n\n$date Start\n");
if ($argc !== 5) {
  echo "Error: Bad param count $argc instead of 5!\n $argv[0]  <account> '<PW>' <host> <ipv4>\n";
  $msg .= "  Error: Bad param count $argc instead of 5!\n $argv[0] <account> '<PW>' <host> <ipv4>\n";
  fwrite($fLOG, "$msg");
  fclose($fLOG);
  exit("Error: Bad param count $argc instead of 5!\n");
}
else {
  $msg .= ", ParamCount $argc is ok.";
}
# $str   = @file_get_contents('/proc/uptime'); # Failed to open stream: Operation not permitted
$str=shell_exec('cat /proc/uptime');
$up_seconds = floatval($str);
$msg .= " System boot was before $up_seconds seconds\n";

$account = (string)$argv[1]; # Ionos Token Part 1
$pwd = (string)$argv[2]; # Ionos Token Part 2 ( there is a 128 chars limit )
$hostname = (string)$argv[3]; # full Domain
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
if ( $ipv6multi == "" ) { # Tethering?
  # Alternative Solution: Get the IPv6 of the disk station from outside:
  # Hint Google https://domains.google.com/checkip is no more working!
  # using https://ip6only.me/api/:
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://ip6only.me/api/');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); # Set so curl_exec returns the result instead of outputting it.
  $response = curl_exec($ch);
  curl_close($ch);
  $ipv6 = explode(",", $response)[1];
  }
else {
  $lines = explode("\n", $ipv6multi);
  $msg .= "  used IPv6: '$lines[0]'";
  # msg .= ", IP2: $lines[1]";
  $ipv6=$lines[0];
  }

if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
  if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    # msg .= "$ipv6 is a valid IPv6 address\n";
  } else {
    $cmd = "ip -6 addr | grep $ipv6";
    $cmd .= " 2>&1";
    $ipv6b = shell_exec($cmd);
    $msg .= "  trying to use $ipv6b\n    even it's possibly not a valid global IPv6 address but an private Range\n";
  }
} else {
  echo("$ipv6 is not a valid IPv6 address\n");
  $msg .= "  $ipv6 is not a valid IPv6 address\n";
}

# https://www.ionos.de/hilfe/domains/ip-adresse-konfigurieren/dynamisches-dns-ddns-einrichten-bei-company-name
# https://ipv4.api.hosting.ionos.com/dns/v1/dyndns?q=<token>&ipv4=<ipaddr>&ipv6=<ip6addr>

$url = 'https://ipv4.api.hosting.ionos.com/dns/v1/dyndns?q=' . $account . $pwd . '&';
$unchanged=true;
$myips = '';
if (isGlobalIPv4_DSaware($ip)) {
  $myips = 'ipv4=' . $ip;
  }
else {
  $msg .= "'$ip' is not a public IPv4 (PRIVATE, RESERVED or CGNAT)\n";
  }
if($ipv6 != '') { # IPv4 and IPv6 available
  if ($myips != '') {
    $myips .= '&';
    }  
  $myips .= 'ipv6=$ipv6';
  # $unchanged=str_contains($lastLogLine, $ipv6);
  $unchanged=(strpos($lastLogLine, $ipv6) !== false);
  $msg .= "last_log_line (previous run): '$lastLogLine'\n";
  $msg .= "actual ipv6='$ipv6': $unchanged\n";
  }
if ($myips == "") {
  syslog(LOG_ERR, "$argv[0]: No public IP address found, see $LOG_NAME");
  # 0x13100012: "System failed to register [@1] to [@2] in DDNS server [@3] because of [@4]."
  $cmd = "synologset1 sys err 0x13100012 \"$myips\" \"$hostname\" \"$0\" \"no public IP address found\"";
  # $cmd = "xxx";
  shell_exec($cmd);
  $msg .= "actual IPv4='$ip', ipv6='$ipv6', but none of them seems to be available from internet\n";
  fwrite($fLOG, "$msg");
  fclose($fLOG);
  exit("Error: No public IP address found!\n");
  }
$url .=  $myips;
$msg .= "  used url: $url\n";

$res="nochg $ip $ipv6\n"; # preset, optionally changed later
# $msg .= "  last sending before $age_h h\n";
if (strpos($lastLogLine, $ip) == false ) {  
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
  $http_code = curl_getinfo($req, CURLINFO_HTTP_CODE);
  curl_close($req);
  #$msg .= "  curl_exec http result: $http_code";
#  if ( (strpos($res, "good") !== 0) && (strpos($res, "nochg") !== 0) ) { 
#    syslog(LOG_ERR, "$argv[0]: $res, see /tmp/ddns.log");
#    # 0x13100012: "System failed to register [@1] to [@2] in DDNS server [@3] because of [@4]."
#    $cmd = "synologset1 sys err 0x13100012 \"$myips\" \"$hostname\" \"$0\" \"$res\"";
#    # $cmd = "xxx";
#    shell_exec($cmd);
#  } elseif ($syslogSuccess === true) {
#    # 13100011: "System successfully registered [@1] to [@2] in DDNS server [@3]."
#    $cmd = "synologset1 sys info 0x13100011 \"$myips\" \"$hostname\" \"" . __FILE__ . "\"";
#    shell_exec($cmd);
#  }  
} else {
  $msg .= "  sending skipped as last update was only " . number_format($age_h, 3) . " h ago to avoid 'abuse ...' message\n";
  $msg .= "  old result was: $res";
  if ($syslogSkipped === true) {
    # 0x11100000: [@1]"
    $cmd = "synologset1 sys info 0x11100000 \"". __FILE__ . ": New DynDNS registration to $hostname skipped to avoid 'abuse ..' because unchanged $myips and done before " . number_format($age_h, 3) . " hours.\"";
    shell_exec($cmd);
  }
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

if ($http_code = 200) {
	echo("good"); # The script output needs to start(!!) with "nochg" or "good" to avoid error messages in the synology protocol list.
} else {
	echo("$res");
}
if ( (strpos($res, "good") !== 0) && (strpos($res, "nochg") !== 0)) {
  syslog(LOG_ERR, "$argv[0]: '$res', see $LOG_NAME");
}
fwrite($fLOG, "$msg");
fclose($fLOG);

?>
