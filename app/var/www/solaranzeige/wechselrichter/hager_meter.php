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
//  Es dient dem Auslesen des SmartMeter  Hager ECR380D über die 
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
$Version = "";
$Device = "ME"; // ME = Smart Meter
$RemoteDaten = true;

$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("---------   Start  hager_meter.php  ------------------------- ","|--",6);
$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);

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

$funktionen->log_schreiben("WR_ID: ".$WR_ID,"+  ",7);


$Befehl = array(
  "DeviceID" => $WR_ID,
  "BefehlFunctionCode" => "04",
  "RegisterAddress" => "3001",
  "RegisterCount" => "0001" );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um mehrere Variablen des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad . "/database/" . $GeraeteNummer . ".Tagesdaten.txt";
$Tagesdaten = array("BezugGesamtHeute" => 0, "EinspeisungGesamtHeute" => 0);
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei " . $StatusFile . " nicht anlegen.", 5 );
  }
  $aktuelleDaten["Wh_BezugHeute"] = 0;
  $aktuelleDaten["Wh_EinspeisungHeute"] = 0;
}
else {
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $aktuelleDaten["Wh_BezugHeute"] = $Tagesdaten["BezugGesamtHeute"];
  $aktuelleDaten["Wh_EinspeisungHeute"] = $Tagesdaten["EinspeisungGesamtHeute"];
}



//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...",">  ",9);

  /**************************************************************************
  //  Ab hier wird der Energy Meter ausgelesen.
  //
  //  HAGER ECR380D
  //
  //
  //
  **************************************************************************/

  /****************************************************************************
  //  Ab hier wird der Zähler ausgelesen.
    function modbus_register_lesen( $COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl = "03" ) {

  //
  ****************************************************************************/


  $Befehl["DeviceID"] = $WR_ID;             // In HEX
  $Befehl["RegisterAddress"] = "1000";      // In HEX
  $Befehl["BefehlFunctionCode"] = "03";     // in HEX
  $Befehl["RegisterCount"] = "0010";        // in HEX
  $Befehl["Datentyp"] = "String";           // String
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Hersteller"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "1020";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "Hex";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Firmware"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "1032";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0010";
  $Befehl["Datentyp"] = "String";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Produkt"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "1010";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0010";
  $Befehl["Datentyp"] = "String";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Modell"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "B000";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_R"] = ($rc["Wert"]/100);

  $Befehl["RegisterAddress"] = "B001";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_S"] = ($rc["Wert"]/100);

  $Befehl["RegisterAddress"] = "B002";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_T"] = ($rc["Wert"]/100);


  $Befehl["RegisterAddress"] = "B006";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Frequenz"] = ($rc["Wert"]/100);

  $Befehl["RegisterAddress"] = "B009";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_R"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B00B";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_S"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B00D";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_T"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B019";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "S32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_R"] = ($rc["Wert"]*10);

  $Befehl["RegisterAddress"] = "B01B";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "S32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_S"] = ($rc["Wert"]*10);

  $Befehl["RegisterAddress"] = "B01D";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "S32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_T"] = ($rc["Wert"]*10);

  $Befehl["RegisterAddress"] = "B02B";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "S16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_R"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B02C";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "S16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_S"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B02D";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "S16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_T"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B000";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung"] = ($rc["Wert"]/100);

  $Befehl["RegisterAddress"] = "B00F";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom"] = ($rc["Wert"]/1000);

  $Befehl["RegisterAddress"] = "B011";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "S32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung"] = ($rc["Wert"]*10);

  $funktionen->log_schreiben("AC Leistung: ".$aktuelleDaten["AC_Leistung"]." Watt","   ",6);


  if ($aktuelleDaten["AC_Leistung"] >= 0) {
    $aktuelleDaten["Bezug"] = $aktuelleDaten["AC_Leistung"];

    $aktuelleDaten["Einspeisung"] = 0;
  }
  else {
    $aktuelleDaten["Bezug"] = 0;

    $aktuelleDaten["Einspeisung"] = abs($aktuelleDaten["AC_Leistung"]);
  }


  $aktuelleDaten["PF_Leistung"] = 0; // Dummy


  $Befehl["RegisterAddress"] = "B0B0";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["AnzTarife"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "B0B1";
  $Befehl["RegisterCount"] = "0001";
  $Befehl["Datentyp"] = "U16";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Tarif"] = $rc["Wert"];

  $Befehl["RegisterAddress"] = "B060";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug"] = ($rc["Wert"]*1000);


  $Befehl["RegisterAddress"] = "B180";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_R"] = 0;

  $Befehl["RegisterAddress"] = "B182";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_S"] = 0;

  $Befehl["RegisterAddress"] = "B184";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_T"] = 0;

  $Befehl["RegisterAddress"] = "B064";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung"] = ($rc["Wert"]*1000);

  $Befehl["RegisterAddress"] = "B186";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_R"] = 0;

  $Befehl["RegisterAddress"] = "B188";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_S"] = 0;

  $Befehl["RegisterAddress"] = "B18A";
  $Befehl["RegisterCount"] = "0002";
  $Befehl["Datentyp"] = "U32";
  $rc = $funktionen->modbus_rtu_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_T"] = 0;


  $aktuelleDaten["GesamterLeistungsbedarf"] = 0;



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
  $aktuelleDaten["WattstundenGesamtHeute"]  = 0;  // dummy
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/hager_meter_math.php")) {
    include 'hager_meter_math.php';  // Falls etwas neu berechnet werden muss.
  }


  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
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
  $aktuelleDaten["InfluxPort"] =  $InfluxPort;
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
    $Zeitspanne = (9 - (time() - $Start));
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
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
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


/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
  //  pro Tag zu speichern.
  //  Jede Nacht 0 Uhr Tageszähler auf 0 setzen
  ***************************************************************************/
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") {
    $Tagesdaten = array("BezugGesamtHeute" => 0, "EinspeisungGesamtHeute" => 0);
    $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
    $funktionen->log_schreiben( "Tagesdaten zurückgesetzt.", "o- ", 5 );
  }

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $Tagesdaten["BezugGesamtHeute"] = round(($Tagesdaten["BezugGesamtHeute"] + ($aktuelleDaten["Bezug"]) / 60),2);
  $Tagesdaten["EinspeisungGesamtHeute"] = round(($Tagesdaten["EinspeisungGesamtHeute"] + ($aktuelleDaten["Einspeisung"]) / 60),2);
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  $funktionen->log_schreiben( "BezugGesamtHeute: ".$Tagesdaten["BezugGesamtHeute"], "   ", 5 );
  $funktionen->log_schreiben( "EinspeisungGesamtHeute: ".$Tagesdaten["EinspeisungGesamtHeute"], "   ", 5 );
}


Ausgang:

$funktionen->log_schreiben("---------   Stop   hager_meter.php    ----------------------- ","|--",6);

return;


?>
