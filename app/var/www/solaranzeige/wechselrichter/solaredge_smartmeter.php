#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2021]  [Ulrich Kunz]
//                                   (2021 geschrieben von Egmont Schreiter )
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
//  Es dient dem Auslesen des SmartMeter  Solaredge WND-3Y-400-MB 3 über die
//  RS485 Schnittstelle
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
$Tracelevel = 7; // 1 bis 10  10 = Debug
$Version = "";
$Device = "ME"; // ME = Smart Meter
$RemoteDaten = true;

// Achtung Änderung! Die Adresse $WR_Adresse muss in Dezimal eingegeben werden!
if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( dechex( $WR_Adresse ), 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 2) {
  $WR_ID = str_pad( dechex( substr( $WR_Adresse, - 2 )), 2, "0", STR_PAD_LEFT );
}
else {
  $WR_ID = dechex( $WR_Adresse );
}

$funktionen->log_schreiben("WR_ID: ".$WR_ID, "+  ", 7);
$Befehl = array("DeviceID" => $WR_ID, "BefehlFunctionCode" => "04", "RegisterAddress" => "3001", "RegisterCount" => "0001");
$Start = time(); // Timestamp festhalten
$funktionen->log_schreiben("---------   Start  solaredge_WND-3Y-400-MB.php  ------------------------- ", "|--", 6);
$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
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
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]", "XX ", 7);
  $funktionen->log_schreiben("Exit.... ", "XX ", 7);
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...", ">  ", 9);
  /**************************************************************************
  //  Ab hier wird der Energy Meter ausgelesen.
  //
  //  Ergebniswerte:
  //
  //
  //
  //
  //
  //
  //
  //
  **************************************************************************/
  /****************************************************************************
  //  Ab hier wird der Zähler ausgelesen.
  //
  ****************************************************************************/
  $Befehl["DeviceID"] = $WR_ID; // bleibt gleich
  $Befehl["BefehlFunctionCode"] = 4; // bleibt gleich, egal ob 3 oder 4
  // zum debuggen:
  //   //$funktionen->log_schreiben("Befehl: ".var_export($Befehl,1)." (end)","   ",10);
  //$rc = $funktionen->sem_auslesen($USB1,$Befehl,1);
  //$funktionen->log_schreiben("  rc: ".$rc,"   ",10);
  //Frequenz
  $Befehl["RegisterAddress"] = 1221;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1); //16 bit uint
  $aktuelleDaten["Frequenz"] = $rc * 0.1;
  // Firmware
  $Befehl["RegisterAddress"] = 1708;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["Firmware"] = $rc;
  $Befehl["RegisterAddress"] = 1214;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_R"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1215;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_S"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1216;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_T"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1205;
  // EnergySumNR is the net real energy sum of all active phases, where “net” means negative energy will subtract from the total
  $Befehl["RegisterCount"] = 2;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["GesamterLeistungsbedarf"] = $rc * 0.1;
  $funktionen->log_schreiben("kWh Gesamt: ".$aktuelleDaten["GesamterLeistungsbedarf"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1207;
  // EnergyPosSumNR is equivalent to a traditional utility meter that can only spin in one direction.
  $Befehl["RegisterCount"] = 2;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["Wh_Bezug"] = $rc * 0.1;
  $funktionen->log_schreiben("kWh Bezug: ".$aktuelleDaten["Wh_Bezug"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1315;
  // EnergyNegSumNR
  $Befehl["RegisterCount"] = 2;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["Wh_Einspeisung"] = $rc * 0.1;
  $funktionen->log_schreiben("kWh Einspeisung: ".$aktuelleDaten["Wh_Einspeisung"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1209;
  // PowerSum
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  if ($rc >= 0) {
    $aktuelleDaten["Bezug"] = $rc * 0.1;
    $aktuelleDaten["Einspeisung"] = 0;
  }
  else {
    $aktuelleDaten["Bezug"] = 0;
    $aktuelleDaten["Einspeisung"] = $rc * 0.1;
  }
  $Befehl["RegisterAddress"] = 1609;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PowerIntScale"] = $rc;
  // $funktionen->log_schreiben("  PowerIntScale: ".$rc,"   ",10);
  $Befehl["RegisterAddress"] = 1209;
  $Befehl["RegisterCount"] = 1;
  $funktionen->log_schreiben("Befehl: ".var_export($Befehl, 1)." (end)", "   ", 10);
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $funktionen->log_schreiben("  rc: ".$rc, "   ", 10);
  $aktuelleDaten["AC_Leistung"] = $rc * $aktuelleDaten["PowerIntScale"];
  $funktionen->log_schreiben("AC Leistung: ".$aktuelleDaten["AC_Leistung"]." Watt", "   ", 6);
  if ($aktuelleDaten["AC_Leistung"] >= 0) {
    $aktuelleDaten["Bezug"] = $aktuelleDaten["AC_Leistung"];
    $aktuelleDaten["Einspeisung"] = 0;
  }
  else {
    $aktuelleDaten["Bezug"] = 0;
    $aktuelleDaten["Einspeisung"] = - $aktuelleDaten["AC_Leistung"];
  }
  $Befehl["RegisterAddress"] = 1604;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsA"] = $rc;
  $Befehl["RegisterAddress"] = 1605;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsB"] = $rc;
  $Befehl["RegisterAddress"] = 1606;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsC"] = $rc;
  $Befehl["RegisterAddress"] = 1622;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CurrentIntScale"] = $rc;
  $Befehl["RegisterAddress"] = 1351;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_R"] = $rc * $aktuelleDaten["CtAmpsA"] / $aktuelleDaten["CurrentIntScale"];
  $aktuelleDaten["AC_Strom"] = $rc * $aktuelleDaten["CtAmpsA"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1352;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_S"] = $rc * $aktuelleDaten["CtAmpsB"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1353;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_T"] = $rc * $aktuelleDaten["CtAmpsC"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1210;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_R"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1211;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_S"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1212;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_T"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1340;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_R"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1341;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_S"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1342;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_T"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1339;
  $Befehl["RegisterCount"] = 1;
  $rc = $funktionen->sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_Leistung"] = $rc * 0.01;
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
  $aktuelleDaten["Produkt"] = "WND3Y";
  $aktuelleDaten["Modell"] = "WND3Y";
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  if ($i == 1)
    $funktionen->log_schreiben(var_export($aktuelleDaten, 1), "   ", 8);
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists("/var/www/html/solaredge_WND-3Y-400-MB_math.php")) {
    include 'solaredge_WND-3Y-400-MB_math.php'; // Falls etwas neu berechnet werden muss.
  }
  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
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
      $funktionen->log_schreiben("InfluxDB_remote senden.", "   ", 1);
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
      $funktionen->log_schreiben("influx_local senden 1.", "   ", 1);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
    $funktionen->log_schreiben("influx_local senden 2.", "   ", 1);
  }
  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.", "   ", 9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...", "   ", 8);
    break;
  }
  $i++;
} while (($Start + 56) > time());
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {
  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
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
$funktionen->log_schreiben("---------   Stop   solaredge_WND-3Y-400-MB.php    ----------------------- ", "|--", 6);
return;
?>
