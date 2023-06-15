#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Kostal Pico Wechselrichter über MODBUS RTU
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$USBRegler = "/dev/ttyUSB0";
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Startzeit = time(); // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  kostal_piko.php    --------------------- ", "|--", 6);
$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
//  Dummy, falls das Gerät diese Daten nicht liefert.
$aktuelleDaten["Batteriespannung"] = 0;
$aktuelleDaten["Batteriestrom"] = 0;
$aktuelleDaten["Batterieleistung"] = 0;

setlocale(LC_TIME, "de_DE.utf8");
//  Hardware Version ermitteln.
$Teile = explode(" ", $Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version, "o  ", 8);
switch ($Version) {
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
if ($funktionen->tageslicht() or $InfluxDaylight === false) {
  //  Der Wechselrichter wird nur am Tage abgefragt.
  if ($WR_IP == "0.0.0.0") {
    $USB1 = $funktionen->openUSB($USBRegler);
    if (!is_resource($USB1)) {
      $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1] ".$USBRegler, "XX ", 7);
      $funktionen->log_schreiben("Exit.... ", "XX ", 7);
      goto Ausgang;
    }
  }
}
else {
  $funktionen->log_schreiben("Es ist dunkel... ", "X  ", 7);
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...", "+  ", 3);
  if ($WR_IP == "0.0.0.0") {
    /****************************************************************************
    //  Ab hier wird der Wechselrichter ausgelesen.
    //  Mit RS-485 Adapter. $WR_IP in der usre.config.php
    //  muss "0.0.0.0" enthalten!
    //
    ****************************************************************************/
    $Start = "05";
    $Stop = "00";
    $Header = "62DEA0";
    $Adr = "01";
    $Para = "03";
    $Befehl = "45";
    $CKS = "DA";
    $CKST = $CKS;
    if ($CKS == "00") {
      $Para = "02";
      $CKST = "01";
    }
    $Laenge = 12;
    $rc = $funktionen->kostal_auslesen($USB1, $Start.$Header.$Adr.$Para.$Befehl.$CKST.$Stop, $Laenge);
    /**********************************************/
    if ($rc === false) {
      $aktuelleDaten["AC_Wh_Gesamt"] = 0;
    }
    else {
      $rc = $funktionen->cobs_decoder($rc);
      $Ergebnis = substr($rc, 12, 8);
      $aktuelleDaten["AC_Wh_Gesamt"] = $funktionen->kostal_umwandlung($Ergebnis);
    }
    $Befehl = "43";
    $CKST = "DC";
    $Laenge = 74;
    $rc = $funktionen->kostal_auslesen($USB1, $Start.$Header.$Adr.$Para.$Befehl.$CKST.$Stop, $Laenge);
    $rc = $funktionen->cobs_decoder($rc);
    $Ergebnis = substr($rc, 12, - 32);
    $aktuelleDaten["PV_Spannung_1"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 0, 4)) / 10;
    $aktuelleDaten["PV_Strom_1"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 4, 4)) / 100;
    $aktuelleDaten["PV_Leistung_1"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 8, 4));
    $aktuelleDaten["PV_Spannung_2"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 20, 4)) / 10;
    $aktuelleDaten["PV_Strom_2"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 24, 4)) / 100;
    $aktuelleDaten["PV_Leistung_2"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 28, 4));
    $aktuelleDaten["PV_Spannung_3"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 40, 4)) / 10;
    $aktuelleDaten["PV_Strom_3"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 44, 4)) / 100;
    $aktuelleDaten["PV_Leistung_3"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 48, 4));
    $aktuelleDaten["AC_Spannung_R"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 60, 4)) / 10;
    $aktuelleDaten["AC_Strom_R"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 64, 4)) / 100;
    $aktuelleDaten["AC_Leistung_R"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 68, 4));
    $aktuelleDaten["AC_Spannung_S"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 76, 4)) / 10;
    $aktuelleDaten["AC_Strom_S"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 80, 4)) / 100;
    $aktuelleDaten["AC_Leistung_S"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 84, 4));
    $aktuelleDaten["AC_Spannung_T"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 92, 4)) / 10;
    $aktuelleDaten["AC_Strom_T"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 96, 4)) / 100;
    $aktuelleDaten["AC_Leistung_T"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 100, 4));
    $Befehl = "90";
    $CKST = "8F";
    $Laenge = 32;
    $rc = $funktionen->kostal_auslesen($USB1, $Start.$Header.$Adr.$Para.$Befehl.$CKST.$Stop, $Laenge);
    $rc = $funktionen->cobs_decoder($rc);
    $Ergebnis = substr($rc, 12, - 2);
    $aktuelleDaten["Modell"] = trim($funktionen->hex2str(substr($Ergebnis, 0, 32)));
    $aktuelleDaten["Strings"] = hexdec(substr($Ergebnis, 32, 2));
    $aktuelleDaten["Phasen"] = hexdec(substr($Ergebnis, 46, 2));
    $Befehl = "57";
    $CKST = "C8";
    $Laenge = 16;
    $rc = $funktionen->kostal_auslesen($USB1, $Start.$Header.$Adr.$Para.$Befehl.$CKST.$Stop, $Laenge);
    $rc = $funktionen->cobs_decoder($rc);
    $Ergebnis = substr($rc, 12, - 12);
    $aktuelleDaten["Status"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 0, 2));
    $aktuelleDaten["Fehler"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 2, 2));
    $aktuelleDaten["FehlerCode"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 4, 4));
    $Befehl = "9D";
    $CKST = "82";
    $Laenge = 12;
    $rc = $funktionen->kostal_auslesen($USB1, $Start.$Header.$Adr.$Para.$Befehl.$CKST.$Stop, $Laenge);
    $rc = $funktionen->cobs_decoder($rc);
    $Ergebnis = substr($rc, 12, - 4);
    $aktuelleDaten["WattstundenGesamtHeute"] = $funktionen->kostal_umwandlung(substr($Ergebnis, 0, 8));
    $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["PV_Leistung_1"] + $aktuelleDaten["PV_Leistung_2"] + $aktuelleDaten["PV_Leistung_3"];

    if (!is_numeric($aktuelleDaten["AC_Leistung_R"])) {
        $aktuelleDaten["AC_Leistung_R"] = 0;
    }

  }
  else {
    /*****************************************************************************
    //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
    //  pro Tag zu speichern.
    //
    *****************************************************************************/
    $StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
    if (!file_exists($StatusFile)) {
      /***************************************************************************
      //  Inhalt der Status Datei anlegen, wenn nicht existiert.
      ***************************************************************************/
      $rc = file_put_contents($StatusFile, "0");
      if ($rc === false) {
        $funktionen->log_schreiben("Konnte die Datei whProTag_delta.txt nicht anlegen.", 5);
      }
      $aktuelleDaten["WattstundenGesamtHeute"] = 0;
    }
    else {
      $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
      $funktionen->log_schreiben("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8);
    }
    // wir laden die XML-Datei vom piko
    $xml = simplexml_load_file("http://".$WR_IP."/measurements.xml");
    // auswerten der XML-Datei
    $funktionen->log_schreiben(">>>> Kopfdaten:");
    foreach ($xml->Device[0]->attributes() as $key => $value) {
      $funktionen->log_schreiben($key.' = "'.$value.'"');
      $piko_kopf["$key"] = $value;
    }
    $funktionen->log_schreiben(">>>> aktuelle Werte:");
    foreach ($xml->Device[0]->Measurements->Measurement as $wert) {
      $funktionen->log_schreiben("Value: ".$wert->attributes()->Value." Unit: ".$wert->attributes()->Unit." Type: ".$wert->attributes()->Type);
      $key = $wert->attributes()->Type;
      $piko_wert["$key"] = $wert->attributes()->Value;
    }
    if (isset($piko_wert["DC_Voltage"]))   {
      $aktuelleDaten["PV_Spannung_1"] = $piko_wert["DC_Voltage"];
      $aktuelleDaten["PV_Strom_1"] = $piko_wert["DC_Current"];
    }
    else {
      $aktuelleDaten["PV_Spannung_1"] = $piko_wert["DC_Voltage1"];
      $aktuelleDaten["PV_Strom_1"] = $piko_wert["DC_Current1"];
    }
    $aktuelleDaten["Seriennummer"] = $piko_kopf["Serial"];
    $aktuelleDaten["PV_Leistung_1"] = 0;
    if (isset($piko_wert["DC_Voltage2"]))   {
      $aktuelleDaten["PV_Spannung_2"] = $piko_wert["DC_Voltage2"];
      $aktuelleDaten["PV_Strom_2"] = $piko_wert["DC_Current2"];
    }
    else {
      $aktuelleDaten["PV_Spannung_2"] = 0;
      $aktuelleDaten["PV_Strom_2"] = 0;
    }
    $aktuelleDaten["PV_Leistung_2"] = 0;
    $aktuelleDaten["PV_Spannung_3"] = 0;
    $aktuelleDaten["PV_Strom_3"] = 0;
    $aktuelleDaten["PV_Leistung_3"] = 0;
    $aktuelleDaten["AC_Spannung_R"] = $piko_wert["AC_Voltage"];
    $aktuelleDaten["AC_Strom_R"] = $piko_wert["AC_Current"];
    $aktuelleDaten["AC_Leistung_R"] = $piko_wert["AC_Power"];
    $aktuelleDaten["AC_Spannung_S"] = 0;
    $aktuelleDaten["AC_Strom_S"] = 0;
    $aktuelleDaten["AC_Leistung_S"] = 0;
    $aktuelleDaten["AC_Spannung_T"] = 0;
    $aktuelleDaten["AC_Strom_T"] = 0;
    $aktuelleDaten["AC_Leistung_T"] = 0;
    $aktuelleDaten["AC_Leistung"] = 0;
    //  $aktuelleDaten["AC_Frequenz"] =  $piko_wert["AC_Frequency"];
    $aktuelleDaten["PV_Leistung_1"] = $aktuelleDaten["PV_Spannung_1"] * $aktuelleDaten["PV_Strom_1"];
    $aktuelleDaten["PV_Leistung_2"] = $aktuelleDaten["PV_Spannung_2"] * $aktuelleDaten["PV_Strom_2"];
    $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["PV_Leistung_1"] + $aktuelleDaten["PV_Leistung_2"];

    if (!is_numeric($aktuelleDaten["AC_Strom_R"])) {
      $aktuelleDaten["AC_Strom_R"] = 0;
    }
    if (!is_numeric($aktuelleDaten["AC_Leistung_R"])) {
      $aktuelleDaten["AC_Leistung_R"] = 0;
    }
    if (!is_numeric($aktuelleDaten["PV_Strom_1"])) {
      $aktuelleDaten["PV_Strom_1"] = 0;
    }
    if (!is_numeric($aktuelleDaten["PV_Strom_2"])) {
      $aktuelleDaten["PV_Strom_2"] = 0;
    }
    if (isset($piko_wert["BDC_BAT_Voltage"]))   {
     $aktuelleDaten["Batteriespannung"] = $piko_wert["BDC_BAT_Voltage"];
     $aktuelleDaten["Batteriestrom"] = $piko_wert["BDC_BAT_Current"];
     $aktuelleDaten["Batterieleistung"] = $piko_wert["BDC_BAT_Power"];
    }
    else {
     $aktuelleDaten["Batteriespannung"] = 0;
     $aktuelleDaten["Batteriestrom"] = 0;
     $aktuelleDaten["Batterieleistung"] = 0;
    }
    $aktuelleDaten["Modell"] = $piko_kopf["Name"];
    $aktuelleDaten["Firmware"] = 0;
    $aktuelleDaten["FehlerCode"] = 0;
    $aktuelleDaten["Status"] = 0;
    $aktuelleDaten["Fehler"] = 0;
    $aktuelleDaten["Strings"] = 0;
    $aktuelleDaten["Phasen"] = 0;
    $aktuelleDaten["AC_Wh_Gesamt"] = 0;
    /*****************************************************************************
    //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
    //  pro Tag zu speichern.
    //  Der Aufwand wird betrieben, da der Wechselrichter mit sehr wenig Licht
    //  tagsüber sich ausschaltet und der Zähler sich zurück setzt.
    //  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
    //  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
    *****************************************************************************/
    if (file_exists($StatusFile)) {
      /***************************************************************************
      //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
      //  pro Tag zu speichern.
      //  Jede Nacht 0 Uhr Tageszähler auf 0 setzen
      ***************************************************************************/
      if (date("H:i") == "00:00" or date("H:i") == "00:01") {
        $rc = file_put_contents($StatusFile, "0");
        $funktionen->log_schreiben("WattstundenGesamtHeute  gesetzt.", "o- ", 5);
      }
      /***************************************************************************
      //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
      ***************************************************************************/
      $whProTag = file_get_contents($StatusFile);
      $whProTag = ($whProTag + ($aktuelleDaten["PV_Leistung"]) / 60);
      $rc = file_put_contents($StatusFile, round($whProTag, 2));
      $funktionen->log_schreiben("WattstundenGesamtHeute: ".round($whProTag, 2), "   ", 5);
    }
  }
  Ende:
  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/kostal_pico_math.php")) {
    include 'kostal_pico_math.php';  // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($Pfad."/mqtt_senden.php");
  }


  /****************************************************************************
  //  Die Daten werden für die die HomeMatic benötigt.
  ****************************************************************************/
  $aktuelleDaten["AC_Leistung"] = $aktuelleDaten["AC_Leistung_R"] + $aktuelleDaten["AC_Leistung_S"] + $aktuelleDaten["AC_Leistung_T"];
  $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["PV_Leistung_1"] + $aktuelleDaten["PV_Leistung_2"] + $aktuelleDaten["PV_Leistung_3"];
  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = 0;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Modell"];
  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1);
    require ($Pfad."/mqtt_senden.php");
  }
  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"] = date("n");
  $aktuelleDaten["Woche"] = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A", time());
  $aktuelleDaten["Datum"] = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"] = date("H:i:s");
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] = $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;
  $funktionen->log_schreiben(print_r($aktuelleDaten, 1), "*- ", 9);
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
    $Zeitspanne = (9 - (time() - $Startzeit));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Startzeit)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((56 - (time() - $Startzeit)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.", "   ", 9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...", "   ", 8);
    break;
  }
  $i++;
} while (($Startzeit + 54) > time());
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {
  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...", "   ", 8);
    require ($Pfad."/homematic.php");
  }
  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...", "   ", 8);
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.", "   ", 7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.", "!! ", 6);
}
Ausgang:

$funktionen->log_schreiben("-------------   Stop   kostal_piko.php     -------------------- ", "|--", 6);
return;

?>

