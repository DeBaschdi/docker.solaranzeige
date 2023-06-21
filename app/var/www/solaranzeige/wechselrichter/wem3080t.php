<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
//  WEM3080T                         Copyright (C) [2022]  [Daniel Bechter]
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
//  Es dient dem Auslesen des IAMMETER WEM3080T (79) mit WLAN Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php.
//
*****************************************************************************/
$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Start = time();  // Timestamp festhalten
Log::write("-----------------   Start  wem3080t.php    ------------------ ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");

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
  $aktuelleDaten["WattstundenGesamtHeute"] = round($aktuelleDaten["WattstundenGesamtHeute"],2);
  Log::write("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"],"   ",8);
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])){
      $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date("H:i") == "00:00" or date("H:i") == "00:01") {   // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;       //  Tageszähler löschen
    $rc = file_put_contents($StatusFile,"0");
    Log::write("WattstundenGesamtHeute gelöscht.","    ",5);
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc == false) {
    Log::write("Konnte die Datei kwhProTag_ax.txt nicht anlegen.","   ",5);
  }
}

$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  Log::write("Exit.... ","XX ",3);
  goto Ausgang;
}


$i = 1;
do {
  Log::write("Reading registers from IAMMETER WEM3080T via Modbus TCP...","+  ",6);

  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "0", "38", "Hex");
  if ($rc == false and $i < 2) {
    Log::write("Error reading registers from IAMMETER WEM3080T. ","   ",5);
    continue;
  }
  
  $aktuelleDaten["Spannung_R"] = hexdec( substr( $rc["Wert"], 0 * 4, 4 )) / 100;
  $aktuelleDaten["Spannung_S"] = hexdec( substr( $rc["Wert"], 0xA * 4, 4 )) / 100;
  $aktuelleDaten["Spannung_T"] = hexdec( substr( $rc["Wert"], 0x14 * 4, 4 )) / 100;
  
  $aktuelleDaten["Strom_R"] = hexdec( substr( $rc["Wert"], 0x1 * 4, 4 )) / 100;
  $aktuelleDaten["Strom_S"] = hexdec( substr( $rc["Wert"], 0xB * 4, 4 )) / 100;
  $aktuelleDaten["Strom_T"] = hexdec( substr( $rc["Wert"], 0x15 * 4, 4 )) / 100;
  
  $aktuelleDaten["Wirkleistung_R"] = hexdec( substr( $rc["Wert"], 0x2 * 4, 8 ));
  $aktuelleDaten["Wirkleistung_S"] = hexdec( substr( $rc["Wert"], 0xC * 4, 8 ));
  $aktuelleDaten["Wirkleistung_T"] = hexdec( substr( $rc["Wert"], 0x16 * 4, 8 ));
  
  $aktuelleDaten["Wh_VerbrauchGesamt_R"] = hexdec( substr( $rc["Wert"], 0x4 * 4, 8 )) * 1000 / 800;
  $aktuelleDaten["Wh_VerbrauchGesamt_S"] = hexdec( substr( $rc["Wert"], 0xE * 4, 8 )) * 1000 / 800;
  $aktuelleDaten["Wh_VerbrauchGesamt_T"] = hexdec( substr( $rc["Wert"], 0x18 * 4, 8 )) * 1000 / 800;
  
  $aktuelleDaten["Wh_EinspeisungGesamt_R"] = hexdec( substr( $rc["Wert"], 0x6 * 4, 8 )) * 1000 / 800;
  $aktuelleDaten["Wh_EinspeisungGesamt_S"] = hexdec( substr( $rc["Wert"], 0x10 * 4, 8 )) * 1000 / 800;
  $aktuelleDaten["Wh_EinspeisungGesamt_T"] = hexdec( substr( $rc["Wert"], 0x1A * 4, 8 )) * 1000 / 800;
  
  $aktuelleDaten["PowerFactor_R"] = hexdec( substr( $rc["Wert"], 0x8 * 4, 4)) / 1000;
  $aktuelleDaten["PowerFactor_S"] = hexdec( substr( $rc["Wert"], 0x12 * 4, 4)) / 1000;
  $aktuelleDaten["PowerFactor_T"] = hexdec( substr( $rc["Wert"], 0x1C * 4, 4)) / 1000;

  $aktuelleDaten["Frequenz"] = hexdec( substr( $rc["Wert"], 0x1E * 4, 4)) / 100;
  $aktuelleDaten["LeistungGesamt"] = hexdec( substr( $rc["Wert"], 0x20 * 4, 8));
  $aktuelleDaten["WattstundenGesamt_Verbrauch"] = hexdec( substr( $rc["Wert"], 0x22 * 4, 8)) * 1000 / 800;
  $aktuelleDaten["WattstundenGesamt_Einspeisung"] = hexdec( substr( $rc["Wert"], 0x24 * 4, 8)) * 1000 / 800;

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Produkt"] = "IAMMETER WEM3080T";
  $aktuelleDaten["Type"] = "";
  $aktuelleDaten["Firmware"] = "";
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) {
    Log::write(var_export($aktuelleDaten,1),"   ",8);
    Log::write(print_r($Daten,1),"   ",9);
  }

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/iammeter_math.php")) {
    include $basedir.'/custom/iammeter_math.php';  // Falls etwas neu berechnet werden muss.
  }


  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($basedir."/services/mqtt_senden.php");
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
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((54 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((54 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
    break;
  }

  $i++;
} while (($Start + 54) > time());







if (isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    Log::write("Daten werden zur HomeMatic übertragen...","   ",8);
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write("Nachrichten versenden...","   ",8);
    require($basedir."/services/meldungen_senden.php");
  }

  Log::write("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  Log::write("Keine gültigen Daten empfangen.","!! ",6);
}

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists($StatusFile)) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["LeistungGesamt"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  Log::write("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}

Ausgang:

Log::write("-----------------   Stop   wem3080t.php    ------------------ ","|--",6);

return;




?>
