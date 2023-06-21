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

Log::write("WR_ID: ".$WR_ID, "+  ", 7);
$Befehl = array("DeviceID" => $WR_ID, "BefehlFunctionCode" => "04", "RegisterAddress" => "3001", "RegisterCount" => "0001");
$Start = time(); // Timestamp festhalten
Log::write("---------   Start  solaredge_WND-3Y-400-MB.php  ------------------------- ", "|--", 6);
Log::write("Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8);
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
Log::write("Hardware Version: ".$Version, "o  ", 8);
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
$USB1 = USB::openUSB($USBRegler);
if (!is_resource($USB1)) {
  Log::write("USB Port kann nicht geöffnet werden. [1]", "XX ", 7);
  Log::write("Exit.... ", "XX ", 7);
  goto Ausgang;
}
$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...", ">  ", 9);
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
  //   //Log::write("Befehl: ".var_export($Befehl,1)." (end)","   ",10);
  //$rc = SolarEdge::sem_auslesen($USB1,$Befehl,1);
  //Log::write("  rc: ".$rc,"   ",10);
  //Frequenz
  $Befehl["RegisterAddress"] = 1221;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1); //16 bit uint
  $aktuelleDaten["Frequenz"] = $rc * 0.1;
  // Firmware
  $Befehl["RegisterAddress"] = 1708;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["Firmware"] = $rc;
  $Befehl["RegisterAddress"] = 1214;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_R"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1215;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_S"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1216;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Spannung_T"] = $rc * 0.1;
  $Befehl["RegisterAddress"] = 1205;
  // EnergySumNR is the net real energy sum of all active phases, where “net” means negative energy will subtract from the total
  $Befehl["RegisterCount"] = 2;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["GesamterLeistungsbedarf"] = $rc * 0.1;
  Log::write("kWh Gesamt: ".$aktuelleDaten["GesamterLeistungsbedarf"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1207;
  // EnergyPosSumNR is equivalent to a traditional utility meter that can only spin in one direction.
  $Befehl["RegisterCount"] = 2;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["Wh_Bezug"] = $rc * 0.1;
  Log::write("kWh Bezug: ".$aktuelleDaten["Wh_Bezug"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1315;
  // EnergyNegSumNR
  $Befehl["RegisterCount"] = 2;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 3);
  $aktuelleDaten["Wh_Einspeisung"] = $rc * 0.1;
  Log::write("kWh Einspeisung: ".$aktuelleDaten["Wh_Einspeisung"]." Wh", "   ", 6);
  $Befehl["RegisterAddress"] = 1209;
  // PowerSum
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
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
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PowerIntScale"] = $rc;
  // Log::write("  PowerIntScale: ".$rc,"   ",10);
  $Befehl["RegisterAddress"] = 1209;
  $Befehl["RegisterCount"] = 1;
  Log::write("Befehl: ".var_export($Befehl, 1)." (end)", "   ", 10);
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  Log::write("  rc: ".$rc, "   ", 10);
  $aktuelleDaten["AC_Leistung"] = $rc * $aktuelleDaten["PowerIntScale"];
  Log::write("AC Leistung: ".$aktuelleDaten["AC_Leistung"]." Watt", "   ", 6);
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
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsA"] = $rc;
  $Befehl["RegisterAddress"] = 1605;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsB"] = $rc;
  $Befehl["RegisterAddress"] = 1606;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CtAmpsC"] = $rc;
  $Befehl["RegisterAddress"] = 1622;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["CurrentIntScale"] = $rc;
  $Befehl["RegisterAddress"] = 1351;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_R"] = $rc * $aktuelleDaten["CtAmpsA"] / $aktuelleDaten["CurrentIntScale"];
  $aktuelleDaten["AC_Strom"] = $rc * $aktuelleDaten["CtAmpsA"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1352;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_S"] = $rc * $aktuelleDaten["CtAmpsB"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1353;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Strom_T"] = $rc * $aktuelleDaten["CtAmpsC"] / $aktuelleDaten["CurrentIntScale"];
  $Befehl["RegisterAddress"] = 1210;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_R"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1211;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_S"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1212;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["AC_Leistung_T"] = $rc * $aktuelleDaten["PowerIntScale"];
  $Befehl["RegisterAddress"] = 1340;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_R"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1341;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_S"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1342;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
  $aktuelleDaten["PF_T"] = $rc * 0.01;
  $Befehl["RegisterAddress"] = 1339;
  $Befehl["RegisterCount"] = 1;
  $rc = SolarEdge::sem_auslesen($USB1, $Befehl, 1);
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
    Log::write(var_export($aktuelleDaten, 1), "   ", 8);
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists("/var/www/html/solaredge_WND-3Y-400-MB_math.php")) {
    include $basedir.'/custom/solaredge_WND-3Y-400-MB_math.php'; // Falls etwas neu berechnet werden muss.
  }
  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write("MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1);
    require($basedir."/services/mqtt_senden.php");
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
      $rc = InfluxDB::influx_remote_test();
      if ($rc) {
        $rc = InfluxDB::influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
      Log::write("InfluxDB_remote senden.", "   ", 1);
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local($aktuelleDaten);
      Log::write("influx_local senden 1.", "   ", 1);
    }
  }
  else {
    $rc = InfluxDB::influx_local($aktuelleDaten);
    Log::write("influx_local senden 2.", "   ", 1);
  }
  if (is_file($basedir."/config/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Start));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.", "   ", 9);
    Log::write("Schleife ".$i." Ausgang...", "   ", 8);
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
    Log::write("Daten werden zur HomeMatic übertragen...", "   ", 8);
    require($basedir."/services/homematic.php");
  }
  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write("Nachrichten versenden...", "   ", 8);
    require($basedir."/services/meldungen_senden.php");
  }
  Log::write("OK. Datenübertragung erfolgreich.", "   ", 7);
}
else {
  Log::write("Keine gültigen Daten empfangen.", "!! ", 6);
}
Ausgang:
Log::write("---------   Stop   solaredge_WND-3Y-400-MB.php    ----------------------- ", "|--", 6);
return;
?>
