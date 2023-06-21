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
//  Es dient dem Auslesen des SmartMeter  Solarlog 380 über die 
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

$Tracelevel = 7;  //  1 bis 10  10 = Debug
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

Log::write("WR_ID: ".$WR_ID,"+  ",7);


$Befehl = array(
  "DeviceID" => $WR_ID,
  "BefehlFunctionCode" => "04",
  "RegisterAddress" => "3001",
  "RegisterCount" => "0001" );



$Start = time();  // Timestamp festhalten
Log::write("---------   Start  solarlog380pro.php  ------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
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
Log::write("Hardware Version: ".$Version,"o  ",8);

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



//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = USB::openUSB($USBRegler);
if (!is_resource($USB1)) {
  Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  Log::write("Exit.... ","XX ",7);
  goto Ausgang;
}


$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...",">  ",9);

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

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "1054";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0004";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Firmware"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2008";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_R"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "200C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_S"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2010";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_T"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2068";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_R"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "206C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_S"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2070";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_T"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2088";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_R"] = $rc*1000;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "208C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_S"] = $rc*1000;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2090";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_T"] = $rc*1000;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "20E8";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_R"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "20EC";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_S"] = $rc;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "20F0";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_T"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2008";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2068";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2080";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung"] = $rc*1000;

  Log::write("AC Leistung: ".$aktuelleDaten["AC_Leistung"]." Watt","   ",6);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "20E0";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["PF_Leistung"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2020";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Frequenz"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "2200";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Tarif"] = $rc;


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3020";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3028";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_R"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "302C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_S"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3030";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Bezug_Phase_T"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3040";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3048";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_R"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "304C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_S"] = ($rc*1000);

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3050";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Einspeisung_Phase_T"] = ($rc*1000);


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["RegisterAddress"] = "3000";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = SDM::sdm_auslesen($USB1,$Befehl);
  $aktuelleDaten["GesamterLeistungsbedarf"] = ($rc*1000);



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
  $aktuelleDaten["Produkt"] = "Pro380";
  $aktuelleDaten["Modell"]  = "Pro380";
  $aktuelleDaten["WattstundenGesamtHeute"]  = 0;  // dummy
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/solarlog380pro_math.php")) {
    include $basedir.'/custom/solarlog380pro_math.php';  // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (9 - (time() - $Start));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
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


Ausgang:


Log::write("---------   Stop   solarlog380pro.php    ----------------------- ","|--",6);

return;


?>
