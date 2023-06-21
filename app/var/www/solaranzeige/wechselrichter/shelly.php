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
//  Es dient dem Auslesen des Shelly 3EM mit WLAN Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time(); // Timestamp festhalten
Log::write("-----------------   Start  shelly.php    ------------------ ", "|--", 6);
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
Log::write("Hardware Version: ".$Version, "o  ", 9);
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag.txt";
if (file_exists($StatusFile)) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  $aktuelleDaten["WattstundenGesamtHeute"] = round($aktuelleDaten["WattstundenGesamtHeute"], 2);
  Log::write("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8);
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])) {
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date("H:i") == "00:00" or date("H:i") == "00:01") { // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0; //  Tageszähler löschen
    $rc = file_put_contents($StatusFile, "0");
    Log::write("WattstundenGesamtHeute gelöscht.", "    ", 5);
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile, "0");
  if ($rc === false) {
    Log::write("Konnte die Datei kwhProTag_ax.txt nicht anlegen.", "   ", 5);
  }
}
$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3);
  Log::write("Exit.... ", "XX ", 3);
  goto Ausgang;
}
$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...", "+  ", 9);

  /****************************************************************************
  //  Ab hier wird das Shelly EM und 3EM ausgelesen.
  //
  //
  ****************************************************************************/
  // Füllen der Variablen falls es sich um einen Shelly EM handelt, der nur eine Phase hat.
  $aktuelleDaten["Type"] = 0;
  $aktuelleDaten["Firmware"] = 0;
  $aktuelleDaten["Spannung_R"] = 0;
  $aktuelleDaten["Spannung_S"] = 0;
  $aktuelleDaten["Spannung_T"] = 0;
  $aktuelleDaten["Strom_R"] = 0;
  $aktuelleDaten["Strom_S"] = 0;
  $aktuelleDaten["Strom_T"] = 0;
  $aktuelleDaten["Wh_VerbrauchGesamt_R"] = 0;
  $aktuelleDaten["Wh_VerbrauchGesamt_S"] = 0;
  $aktuelleDaten["Wh_VerbrauchGesamt_T"] = 0;
  $aktuelleDaten["Wh_EinspeisungGesamt_R"] = 0;
  $aktuelleDaten["Wh_EinspeisungGesamt_S"] = 0;
  $aktuelleDaten["Wh_EinspeisungGesamt_T"] = 0;
  $aktuelleDaten["PowerFactor_R"] = 1;
  $aktuelleDaten["PowerFactor_S"] = 1;
  $aktuelleDaten["PowerFactor_T"] = 1;
  $aktuelleDaten["Wirkleistung_R"] = 0;
  $aktuelleDaten["Wirkleistung_S"] = 0;
  $aktuelleDaten["Wirkleistung_T"] = 0;
  $aktuelleDaten["Ueberlastung"] = 0;
  $aktuelleDaten["Relaisstatus"] = 0;
  $aktuelleDaten["OK"] = 0;
  $URL = "shelly";
  $Daten = Utils::read($WR_IP, $WR_Port, $URL);
  if ($Daten != false) {
    if (isset($Daten["app"])) {
      $aktuelleDaten["Type"] = $Daten["app"];
      $aktuelleDaten["Firmware"] = $Daten["fw_id"];
    }
    else {
      $aktuelleDaten["Type"] = $Daten["type"];
      $aktuelleDaten["Firmware"] = $Daten["fw"];
    }

    Log::write("Shelly Typ: ".$aktuelleDaten["Type"], "   ", 5);

    if (strtoupper($aktuelleDaten["Type"]) == "PRO3EM") {

      $URL = "rpc/shelly.getstatus";
      $Daten = Utils::read($WR_IP, $WR_Port, $URL);
      if ($Daten === false) {
        goto Ausgang;
      }
      $aktuelleDaten["Spannung_R"] = $Daten["em:0"]["a_voltage"];
      $aktuelleDaten["Spannung_S"] = $Daten["em:0"]["b_voltage"];
      $aktuelleDaten["Spannung_T"] = $Daten["em:0"]["c_voltage"];
      $aktuelleDaten["Strom_R"] = $Daten["em:0"]["a_current"];
      $aktuelleDaten["Strom_S"] = $Daten["em:0"]["b_current"];
      $aktuelleDaten["Strom_T"] = $Daten["em:0"]["c_current"];
      $aktuelleDaten["Wirkleistung_R"] = $Daten["em:0"]["a_act_power"];
      $aktuelleDaten["Wirkleistung_S"] = $Daten["em:0"]["b_act_power"];
      $aktuelleDaten["Wirkleistung_T"] = $Daten["em:0"]["c_act_power"];
      $aktuelleDaten["PowerFactor_R"] = $Daten["em:0"]["a_pf"];
      $aktuelleDaten["PowerFactor_S"] = $Daten["em:0"]["b_pf"];
      $aktuelleDaten["PowerFactor_T"] = $Daten["em:0"]["c_pf"];
      $aktuelleDaten["LeistungGesamt"] = $Daten["em:0"]["total_act_power"];


      $aktuelleDaten["Wh_VerbrauchGesamt_R"] = $Daten["emdata:0"]["a_total_act_energy"];
      $aktuelleDaten["Wh_VerbrauchGesamt_S"] = $Daten["emdata:0"]["b_total_act_energy"];
      $aktuelleDaten["Wh_VerbrauchGesamt_T"] = $Daten["emdata:0"]["c_total_act_energy"];
      $aktuelleDaten["Wh_EinspeisungGesamt_R"] = $Daten["emdata:0"]["a_total_act_ret_energy"];
      $aktuelleDaten["Wh_EinspeisungGesamt_S"] = $Daten["emdata:0"]["b_total_act_ret_energy"];
      $aktuelleDaten["Wh_EinspeisungGesamt_T"] = $Daten["emdata:0"]["c_total_act_ret_energy"];
      $aktuelleDaten["WattstundenGesamt_Verbrauch"] = $Daten["emdata:0"]["total_act"];
      $aktuelleDaten["WattstundenGesamt_Einspeisung"] = $Daten["emdata:0"]["total_act_ret"];
    }
    elseif (strtoupper($aktuelleDaten["Type"]) == "SHEM") {
      $URL = "status";
      $Daten = Utils::read($WR_IP, $WR_Port, $URL);
      if ($Daten === false) {
        Log::write("Status Werte sind falsch... nochmal lesen.", "   ", 3);
        if ($i >= 6) {
          Log::write(var_export(Utils::read($WR_IP, $WR_Port, $URL), 1), "o=>", 9);
          break;
        }
        $i++;
        continue;
      }
      $aktuelleDaten["Spannung_R"] = $Daten["emeters"][0]["voltage"];
      if (isset($Daten["emeters"][0]["current"])) {
        $aktuelleDaten["Strom_R"] = $Daten["emeters"][0]["current"];
      }
      $aktuelleDaten["Wh_VerbrauchGesamt_R"] = $Daten["emeters"][0]["total"];
      $aktuelleDaten["Wh_EinspeisungGesamt_R"] = $Daten["emeters"][0]["total_returned"];
      if (isset($Daten["emeters"][0]["pf"])) {
        $aktuelleDaten["PowerFactor_R"] = $Daten["emeters"][0]["pf"];
      }
      $aktuelleDaten["Wirkleistung_R"] = $Daten["emeters"][0]["power"];
      $aktuelleDaten["OK"] = $Daten["relays"][0]["is_valid"];
      if ($aktuelleDaten["OK"] == false) {
        $aktuelleDaten["OK"] = 0;
      }
      $aktuelleDaten["Ueberlastung"] = $Daten["relays"][0]["overpower"];
      if ($aktuelleDaten["Ueberlastung"] == false) {
        $aktuelleDaten["Ueberlastung"] = 0;
      }
      $aktuelleDaten["Relaisstatus"] = $Daten["relays"][0]["ison"];
      if ($aktuelleDaten["Relaisstatus"] == false) {
        $aktuelleDaten["Relaisstatus"] = 0;
      }
      $aktuelleDaten["WattstundenGesamt_Verbrauch"] = $aktuelleDaten["Wh_VerbrauchGesamt_R"];
      $aktuelleDaten["WattstundenGesamt_Einspeisung"] = $aktuelleDaten["Wh_EinspeisungGesamt_R"];
      //  Negative Werte sind Einspeisung!
      $aktuelleDaten["LeistungGesamt"] = $aktuelleDaten["Wirkleistung_R"];
      if (isset($Daten["emeters"][1]["power"])) {
        $aktuelleDaten["Spannung_S"] = $Daten["emeters"][1]["voltage"];
        if (isset($Daten["emeters"][1]["current"])) {
          $aktuelleDaten["Strom_S"] = $Daten["emeters"][1]["current"];
        }
        $aktuelleDaten["Wh_VerbrauchGesamt_S"] = $Daten["emeters"][1]["total"];
        $aktuelleDaten["Wh_EinspeisungGesamt_S"] = $Daten["emeters"][1]["total_returned"];
        if (isset($Daten["emeters"][1]["pf"])) {
          $aktuelleDaten["PowerFactor_S"] = $Daten["emeters"][1]["pf"];
        }
        $aktuelleDaten["Wirkleistung_S"] = $Daten["emeters"][1]["power"];
        $aktuelleDaten["WattstundenGesamt_Verbrauch"] = $aktuelleDaten["Wh_VerbrauchGesamt_R"] + $aktuelleDaten["Wh_VerbrauchGesamt_S"];
        $aktuelleDaten["WattstundenGesamt_Einspeisung"] = $aktuelleDaten["Wh_EinspeisungGesamt_R"] + $aktuelleDaten["Wh_EinspeisungGesamt_S"];
        //  Negative Werte sind Einspeisung!
        $aktuelleDaten["LeistungGesamt"] = $aktuelleDaten["Wirkleistung_R"] + $aktuelleDaten["Wirkleistung_S"];
      }
    }
    elseif (strtoupper($aktuelleDaten["Type"]) == "SHEM-3") {
      $URL = "status";
      $Daten = Utils::read($WR_IP, $WR_Port, $URL);
      if ($Daten === false) {
        Log::write("Status Werte sind falsch... nochmal lesen.", "   ", 3);
        if ($i >= 6) {
          Log::write(var_export(Utils::read($WR_IP, $WR_Port, $URL), 1), "o=>", 9);
          break;
        }
        $i++;
        continue;
      }
      $aktuelleDaten["Spannung_R"] = $Daten["emeters"][0]["voltage"];
      $aktuelleDaten["Spannung_S"] = $Daten["emeters"][1]["voltage"];
      $aktuelleDaten["Spannung_T"] = $Daten["emeters"][2]["voltage"];
      if (isset($Daten["emeters"][0]["current"])) {
        $aktuelleDaten["Strom_R"] = $Daten["emeters"][0]["current"];
        $aktuelleDaten["Strom_S"] = $Daten["emeters"][1]["current"];
        $aktuelleDaten["Strom_T"] = $Daten["emeters"][2]["current"];
      }
      else {
        $aktuelleDaten["Strom_R"] = 0;
        $aktuelleDaten["Strom_S"] = 0;
        $aktuelleDaten["Strom_T"] = 0;
      }
      $aktuelleDaten["Wh_VerbrauchGesamt_R"] = $Daten["emeters"][0]["total"];
      $aktuelleDaten["Wh_VerbrauchGesamt_S"] = $Daten["emeters"][1]["total"];
      $aktuelleDaten["Wh_VerbrauchGesamt_T"] = $Daten["emeters"][2]["total"];
      $aktuelleDaten["Wh_EinspeisungGesamt_R"] = $Daten["emeters"][0]["total_returned"];
      $aktuelleDaten["Wh_EinspeisungGesamt_S"] = $Daten["emeters"][1]["total_returned"];
      $aktuelleDaten["Wh_EinspeisungGesamt_T"] = $Daten["emeters"][2]["total_returned"];
      if (isset($Daten["emeters"][0]["pf"])) {
        $aktuelleDaten["PowerFactor_R"] = $Daten["emeters"][0]["pf"];
        $aktuelleDaten["PowerFactor_S"] = $Daten["emeters"][1]["pf"];
        $aktuelleDaten["PowerFactor_T"] = $Daten["emeters"][2]["pf"];
      }
      else {
        $aktuelleDaten["PowerFactor_R"] = 1;
        $aktuelleDaten["PowerFactor_S"] = 1;
        $aktuelleDaten["PowerFactor_T"] = 1;
      }
      $aktuelleDaten["Wirkleistung_R"] = $Daten["emeters"][0]["power"];
      $aktuelleDaten["Wirkleistung_S"] = $Daten["emeters"][1]["power"];
      $aktuelleDaten["Wirkleistung_T"] = $Daten["emeters"][2]["power"];
      $aktuelleDaten["OK"] = $Daten["relays"][0]["is_valid"];
      if ($aktuelleDaten["OK"] == false) {
        $aktuelleDaten["OK"] = 0;
      }
      $aktuelleDaten["Ueberlastung"] = $Daten["relays"][0]["overpower"];
      if ($aktuelleDaten["Ueberlastung"] == false) {
        $aktuelleDaten["Ueberlastung"] = 0;
      }
      $aktuelleDaten["Relaisstatus"] = $Daten["relays"][0]["ison"];
      if ($aktuelleDaten["Relaisstatus"] == false) {
        $aktuelleDaten["Relaisstatus"] = 0;
      }
      $aktuelleDaten["WattstundenGesamt_Verbrauch"] = ($aktuelleDaten["Wh_VerbrauchGesamt_R"] + $aktuelleDaten["Wh_VerbrauchGesamt_S"] + $aktuelleDaten["Wh_VerbrauchGesamt_T"]);
      $aktuelleDaten["WattstundenGesamt_Einspeisung"] = ($aktuelleDaten["Wh_EinspeisungGesamt_R"] + $aktuelleDaten["Wh_EinspeisungGesamt_S"] + $aktuelleDaten["Wh_EinspeisungGesamt_T"]);
      //  Negative Werte sind Einspeisung!
      $aktuelleDaten["LeistungGesamt"] = ($aktuelleDaten["Wirkleistung_R"] + $aktuelleDaten["Wirkleistung_S"] + $aktuelleDaten["Wirkleistung_T"]);
    }
  }
  else {
    goto Ausgang;
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/
  Log::write("Gesamtleistung: ".$aktuelleDaten["LeistungGesamt"]." Watt", "   ", 6);

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  if (strtoupper($aktuelleDaten["Type"]) == "SHEM") {
    $aktuelleDaten["Produkt"] = "Shelly EM";
  }
  if (strtoupper($aktuelleDaten["Type"]) == "PRO3EM") {
    $aktuelleDaten["Produkt"] = "Shelly Pro 3EM";
  }
  else {
    $aktuelleDaten["Produkt"] = "Shelly 3EM";
  }
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  if ($i == 1) {
    Log::write(var_export($aktuelleDaten, 1), "   ", 8);
    Log::write(print_r($Daten, 1), "   ", 9);
  }

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists("/var/www/html/shelly_math.php")) {
    include $basedir.'/custom/shelly_math.php'; // Falls etwas neu berechnet werden muss.
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
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = InfluxDB::influx_local($aktuelleDaten);
  }
  if (is_file($basedir."/config/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((54 - (time() - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((54 - (time() - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.", "   ", 9);
    Log::write("Schleife ".$i." Ausgang...", "   ", 8);
    break;
  }
  $i++;
} while (($Start + 54) > time());
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists($StatusFile) and isset($aktuelleDaten["Firmware"])) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["LeistungGesamt"] / 60));
  $rc = file_put_contents($StatusFile, $whProTag);
  Log::write("WattstundenGesamtHeute: ".round($whProTag, 2), "   ", 5);
}
Ausgang:Log::write("-----------------   Stop   shelly.php    ------------------ ", "|--", 6);
return;
?>
