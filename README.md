# Synology-DDNS-IPv6

[English Version](README_E.md)    
Das Synology DSM bietet immer noch nur eine sehr eingeschränkte Unterstützung für IPv6-DynDNS. Mit Ausnahme von synology.me wird nur IPv4 für all die vorkonfigurierten Anbieter unterstützt. Solange der Internetanbieter statische IPv6-Adressen zuweist, ist dies kein Problem. In Deutschland verwenden jedoch viele Anbieter dynamische IPv6-Präfixe für Privatpersonen, die sich bei jeder neuen Internetverbindung ändern können.

Ihr Internet-Router unterstützt möglicherweise DDNS einschließlich IPv6, aber dies wird keine Lösung sein, um von außen über IPv6 auf Ihr Synology zuzugreifen. Sie haben eine einzige globale IPv4-Adresse, gemeinsam für den Router und z.B. Ihr Synology-NAS. Aber Ihr Router und Ihr Synology haben unterschiedliche globale IPv6-Adressen. Der DDNS-Dienst des Routers macht für IPv6 i.d.R. den Router von außen zugänglich, nicht aber Ihr NAS. Daher ist eine DynDNS-Update-Routine einschließlich IPv6 auf dem Synology erforderlich, um Dienste auf dem Synology über IPv6 von außen verfügbar zu machen und nicht nur Ihren Router.

Auf Ihrem Synology-Gerät im Ordner /usr/syno/bin/ddns/ gibt es Skripte für einige Anbieter. Wenn Sie in der Systemsteuerung unter "Externer Zugriff", "DDNS" auf "Hinzufügen" klicken, finden Sie in der Dropdown-Liste für "Serviceanbieter" die Einträge aus der Datei `/etc.defaults/ddns_provider.conf`.

Mit den folgenden Schritten können Sie eine Subdomain Ihrer bei Strato, Ionos oder ipv64.net gehosteten Domain verwenden, um auf Ihr Synology einschließlich IPv6 zuzugreifen:
1) Befolgen Sie die Anweisungen, um eine Subdomain für das Synology mit DynDns-Domain einzurichten.

   - Strato    : https://www.strato.de/faq/hosting/so-einfach-richten-sie-dyndns-fuer-ihre-domains-ein/
   - Ionos     : https://www.ionos.de/hilfe/domains/ip-adresse-konfigurieren/dynamisches-dns-ddns-einrichten-bei-company-name
   - ipv64.net : https://ipv64.net/dyndns

Sie können auch das Setup-Skript verwenden, um die Schritte **2** und **3** automatisch durchzuführen:
Melden Sie sich dafür per SSH bei Ihrem Synology an und verwenden Sie:
`curl -sL https://raw.githubusercontent.com/JensHouses/synology-ddns-IPv6/main/setup.sh | sudo bash`
oder folgen sie den Schritten 2+3 manuell:

2) Kopieren Sie die PHP-Skripte aus diesem Repository nach `/usr/syno/bin/ddns/` und machen Sie sie ausführbar (chmod 755 ...)
   - `/usr/syno/bin/ddns/strato46.php`
   - `/usr/syno/bin/ddns/ionos46.php`
   - `/usr/syno/bin/ddns/ipv64.php`
   - 
3) Fügen Sie der Konfigurationsdatei /etc.defaults/ddns_provider.conf die Zeilen hinzu:

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
   
4) In der Systemsteuerung-> Externe Dienste können Sie nun `STRATO_4_6`, `IONOS46` oder `IPV64.NET` aus der Dropdown-Liste auswählen, Ihren Hostnamen (subdomain.ihredomain.de), Benutzernamen (ihredomain.de) und das Passwort eingeben.
   Bei der Verwendung von IONOS müssen Sie das Token evtl. in zwei separate Zeichenketten aufteilen und den ersten Teil in den Benutzernamen und den zweiten Teil ins Passwort einfügen. Das Passwortfeld erlaubt leider nur 128 Zeichen.

DSM führt das DDNS-Update normalerweise alle 24 Stunden aus. Aber manchmal alle paar Minuten und das verursacht eine "Abuse" Antwort und einen kritischen DSM Protokoll Center Eintrag. Um das zu vermeiden, wurde ein min Intervall $ageMin_h mit Voreinstellung auf 2,0 Stunden hinzugefügt.

**Wichtig:** 
- In der Kontrollspalte "Externe Adresse" wird weiterhin nur Ihre IPv4-Adresse angezeigt.
- Nach einem DSM-Versions-Update müssen u.U. die Schritte 2 und 3 wiederholt werden! Es kann auch in der Systemsteuerung im Aufgabenplaner eine "Ausgelöste Aufgabe" erstellt und das install.sh-Skript bei jedem Reboot als root-User erneut ausgeführt werden. Dann braucht man beim Versionsupdate nicht mehr daran zu denken.

Aber in /tmp/ddns_<dienstname>.log sollte die letzte Zeile mit der Antwort z.B. lauten:

`curl_exec result: nochg 192.XXX.X.X 2003:8106:1234:5678:abcd:ef01:2345:6789`

## Dank und Referenzen
- Dank an PhySix66 https://community.synology.com/enu/forum/1/post/130109
- Dank an mgutt und mweigel https://www.programmierer-forum.de/ipv6-ddns-mit-synology-nas-evtl-auf-andere-nas-router-bertragbar-t319393.htm
- Dank an hwkr https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a

