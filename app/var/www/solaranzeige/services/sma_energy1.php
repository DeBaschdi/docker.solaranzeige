#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Auslesen des SMA Energy Meter über die LAN Schnittstelle.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}

require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  sma_energy.php    -------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");



//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
  case "2B":
  break;
  case "3B":
  break;
  case "3BPlus":
  break;
  case "4B":
  break;
  default:
  break;
}



//Create a UDP socket
if(!($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
{
  $errorcode = socket_last_error();
  $errormsg = socket_strerror($errorcode);
  $funktionen->log_schreiben("UDP Socket konnte nicht geöffnet werden","XX ",3);
  $funktionen->log_schreiben("Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  $funktionen->log_schreiben($errormsg,"XX ",3);
  goto Ausgang;
}

if( !socket_bind($socket, "0.0.0.0" , $WR_Port) ) {  // Bind an localhost
  $errorcode = socket_last_error();
  $errormsg = socket_strerror($errorcode);
  $funktionen->log_schreiben("UDP Socket Bind Fehler","XX ",3);
  $funktionen->log_schreiben($errormsg,"XX ",3);
  goto Ausgang;
}

$adress = "239.12.255.254"; // Multicast IP used by SMA
$rc = socket_set_option($socket,IPPROTO_IP,MCAST_JOIN_GROUP,array("group"=>$adress,"interface"=>0));
if ($rc === false) {
  $funktionen->log_schreiben("Read SMA Energymeter -> Unable to join multicast group","   ",5);
  goto Ausgang;
}

$rc = socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>1, "usec"=>500000));
if ($rc === false) {
  $funktionen->log_schreiben("Read SMA Energymeter -> Set Option Fehler","   ",5);
  goto Ausgang;
}

$funktionen->log_schreiben("UDP Socket Bind OK.","   ",8);


$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Energy Meter ausgelesen.
  //
  ****************************************************************************/
  $aktuelleDaten["KeineSonne"] = false;  // Dummy


  for ($t = 1; $t < 3; $t++) {
    // Receive some data
    // $rc = socket_recvfrom($sock, $buf, 512, MSG_DONTWAIT, $WR_IP, $WR_Port);
    $rc = socket_recvfrom($socket, $buf, 700, 0, $WR_IP, $WR_Port);
    if ($buf) {
      break;
    }
    usleep(50000);

  }

  $Daten = bin2hex($buf);

  $aktuelleDaten["SMA"] = trim($funktionen->Hex2String(substr($Daten,0,8)));
  $aktuelleDaten["TAG0"] = substr($Daten,12,4);
  $aktuelleDaten["Gruppe"] = substr($Daten,16,8);
  $aktuelleDaten["Datenlaenge"] = hexdec(substr($Daten,24,4));
  $aktuelleDaten["SMA_NET_2"] = substr($Daten,28,4);
  $aktuelleDaten["Protokoll_ID"] = substr($Daten,32,4);
  $aktuelleDaten["Susy-ID"] = hexdec(substr($Daten,36,4));
  $aktuelleDaten["SerNo"] = hexdec(substr($Daten,40,8));
  $aktuelleDaten["Ticker_ms"] = hexdec(substr($Daten,48,8));
  // Ab 56 Zeichen
  $aktuelleDaten["Wh_Bezug"]  = (hexdec(substr($Daten,strpos($Daten,"00010800",56)+8,16))/3600);
  $aktuelleDaten["Wh_Einspeisung"]  = (hexdec(substr($Daten,strpos($Daten,"00020800",56)+8,16))/3600);
  $aktuelleDaten["PF_Leistung"]  = (hexdec(substr($Daten,strpos($Daten,"000d0400",56)+8,8))/1000);
  $aktuelleDaten["Frequenz"]  = round((hexdec(substr($Daten,strpos($Daten,"000e0400",56)+8,8))/1000),2);

  // Ab 310 Zeichen
  $aktuelleDaten["BezugPhase_R"]  = (hexdec(substr($Daten,strpos($Daten,"00150400",310)+8,8))/10);
  $aktuelleDaten["EinspeisungPhase_R"]  = (hexdec(substr($Daten,strpos($Daten,"00160400",310)+8,8))/10);
  $aktuelleDaten["AC_Leistung_R"]  = ((hexdec(substr($Daten,strpos($Daten,"00150400",310)+8,8))/10) - (hexdec(substr($Daten,strpos($Daten,"00160400",0)+8,8))/10));
  $aktuelleDaten["AC_Strom_R"]  = (hexdec(substr($Daten,strpos($Daten,"001f0400",310)+8,8))/1000);
  $aktuelleDaten["AC_Spannung_R"]  = round((hexdec(substr($Daten,strpos($Daten,"00200400",0)+8,8))/1000),2);
  $aktuelleDaten["PF_R"]  = (hexdec(substr($Daten,strpos($Daten,"00210400",310)+8,8))/1000);
  // Ab 560 Zeichen
  $aktuelleDaten["BezugPhase_S"]  = (hexdec(substr($Daten,strpos($Daten,"00290400",560)+8,8))/10);
  $aktuelleDaten["EinspeisungPhase_S"]  = (hexdec(substr($Daten,strpos($Daten,"002a0400",560)+8,8))/10);
  $aktuelleDaten["AC_Leistung_S"]  = ((hexdec(substr($Daten,strpos($Daten,"00290400",560)+8,8))/10) - (hexdec(substr($Daten,strpos($Daten,"002a0400",0)+8,8))/10));
  $aktuelleDaten["AC_Strom_S"]  = (hexdec(substr($Daten,strpos($Daten,"00330400",560)+8,8))/1000);
  $aktuelleDaten["AC_Spannung_S"]  = round((hexdec(substr($Daten,strpos($Daten,"00340400",560)+8,8))/1000),2);
  $aktuelleDaten["PF_S"]  = (hexdec(substr($Daten,strpos($Daten,"00350400",560)+8,8))/1000);
  // Ab 800 Zeichen
  $aktuelleDaten["BezugPhase_T"]  = (hexdec(substr($Daten,strpos($Daten,"003d0400",800)+8,8))/10);
  $aktuelleDaten["EinspeisungPhase_T"]  = (hexdec(substr($Daten,strpos($Daten,"003e0400",800)+8,8))/10);
  $aktuelleDaten["AC_Leistung_T"]  = ((hexdec(substr($Daten,strpos($Daten,"003d0400",800)+8,8))/10) - (hexdec(substr($Daten,strpos($Daten,"003e0400",0)+8,8))/10));
  $aktuelleDaten["AC_Strom_T"]  = (hexdec(substr($Daten,strpos($Daten,"00470400",800)+8,8))/1000);
  $aktuelleDaten["AC_Spannung_T"]  = round((hexdec(substr($Daten,strpos($Daten,"00480400",800)+8,8))/1000),2);
  $aktuelleDaten["PF_T"]  = (hexdec(substr($Daten,strpos($Daten,"00490400",800)+8,8))/1000);

  $Firmware  = substr($Daten,strpos($Daten,"007f0400",0)+8,8);
  $aktuelleDaten["Firmware"] = substr($Firmware,4,1).".".substr($Firmware,5,1).".".strtoupper(substr($Firmware,6,1)).".".substr($Firmware,7,1);


  $aktuelleDaten["Bezug"]  = ((hexdec(substr($Daten,strpos($Daten,"00150400",0)+8,8))/10) + (hexdec(substr($Daten,strpos($Daten,"00290400",0)+8,8))/10) + (hexdec(substr($Daten,strpos($Daten,"003d0400",0)+8,8))/10));
  $aktuelleDaten["Einspeisung"]  = ((hexdec(substr($Daten,strpos($Daten,"00160400",0)+8,8))/10) + (hexdec(substr($Daten,strpos($Daten,"002a0400",0)+8,8))/10) + (hexdec(substr($Daten,strpos($Daten,"003e0400",0)+8,8))/10));
  $aktuelleDaten["AC_Strom"]  = ((hexdec(substr($Daten,strpos($Daten,"001f0400",0)+8,8))/1000) + (hexdec(substr($Daten,strpos($Daten,"00330400",0)+8,8))/1000) + (hexdec(substr($Daten,strpos($Daten,"00470400",0)+8,8))/1000));
  $aktuelleDaten["AC_Leistung"]  = ($aktuelleDaten["AC_Leistung_R"] + $aktuelleDaten["AC_Leistung_S"] + $aktuelleDaten["AC_Leistung_T"]);
  $aktuelleDaten["GesamterLeistungsbedarf"]  = ($aktuelleDaten["Wh_Bezug"] + $aktuelleDaten["Wh_Einspeisung"]);


  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/





  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Modell"] = "Energy Meter";
  $aktuelleDaten["WattstundenGesamtHeute"]  = 0; // Dummy
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/sma_energy_math.php")) {
    include 'sma_energy_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and $i == 1) {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]   = date("H:i:s");



  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] =  $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;


  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",10);


  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    // Test ob die Remote Verbindung zur Verfügung steht.
    if ($RemoteDaten) {
      $rc = $funktionen->influx_remote_test();
      if ($rc) {
        $rc = $funktionen->influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
  }




  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if (1 == 1) {        // ausgeschaltet 11.08.2021


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}


Ausgang:


$funktionen->log_schreiben("-------------   Stop   sma_energy.php    -------------------------- ","|--",6);

return;




?>
