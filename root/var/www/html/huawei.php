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
//  Es dient dem Auslesen der Regler der Huawei Serie ohne -M0, -M1, -M2 am Ende.
//  über die USB Schnittstelle ( RS485 zu USB Adapter)
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


$Befehl = array(
  "DeviceID" => $WR_ID,
  "BefehlFunctionCode" => "03",
  "RegisterAddress" => "0000",
  "RegisterCount" => "0001" );



$Startzeit = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  huawei.php  ----------------------------- ","|--",6);

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

$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...",">  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //  Es gibt nur eine DeviceID
  //  DeviveID = 4 = Laderegler + Wechselrichter
  //  Modelle ohne -M0, -M1 -M2 am Ende
  ****************************************************************************/
  //

  $Befehl["RegisterAddress"] = dechex(32001);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Modell"] = hexdec($rc["data"]);
  $funktionen->log_schreiben("Gerätetyp: ".$aktuelleDaten["Modell"],">  ",5);


  $Befehl["RegisterAddress"] = dechex(32002);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["OutputMode"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32003);   
  $Befehl["RegisterCount"] = "000A";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Seriennummer"] = $funktionen->Hex2String($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32153);   
  $Befehl["RegisterCount"] = "000A";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Firmware"] = trim($funktionen->Hex2String($rc["data"]),"\0");



  $Befehl["RegisterAddress"] = dechex(32262);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung1"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32263);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom1"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32264);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung2"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32265);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom2"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32266);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung3"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32267);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom3"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32268);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung4"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32269);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom4"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32270);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung5"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32271);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom5"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32272);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung6"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32273);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom6"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32314);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung7"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32315);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom7"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32316);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung8"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32317);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom8"] = ($funktionen->hexdecs($rc["data"])/10);




  $Befehl["RegisterAddress"] = dechex(32277);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32278);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32279);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32280);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32281);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32282);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32283);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Frequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32284);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Powerfactor"] = ($funktionen->hexdecs($rc["data"])/1000);

  $Befehl["RegisterAddress"] = dechex(32285);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Effizienz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32286);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Temperatur"] = ($funktionen->hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32287);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Status"] = hexdec($rc["data"]); // Inverter Status Seite 7
  $funktionen->log_schreiben("Status Hex: ".$rc["data"],">  ",8);


  $Befehl["RegisterAddress"] = dechex(32290);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung"] = hexdec($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(32294);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Leistung"] = hexdec($rc["data"]);
  $funktionen->log_schreiben("aktuelle PV-Leistung: ".$aktuelleDaten["PV_Leistung"]." Watt",">  ",5);


  $Befehl["RegisterAddress"] = dechex(32300);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtHeute"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(32302);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtMonat"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(32304);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtJahr"] = (hexdec($rc["data"])*10);


  $Befehl["RegisterAddress"] = dechex(32319);   
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["State1"] = ($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(33022);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT1_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33024);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT2_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33026);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT3_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33070);   
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT4_Leistung"] = hexdec($rc["data"]);





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
  $aktuelleDaten["Produkt"]  = $aktuelleDaten["Modell"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/huawei_math.php")) {
    include 'huawei_math.php';  // Falls etwas neu berechnet werden muss.
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
  //  Zeit und Datum
  ****************************************************************************/
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]      = date("H:i:s");


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
    $Zeitspanne = (9 - (time() - $Startzeit));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Startzeit))/($Wiederholungen-$i+1))),"   ",3);
    sleep(floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }
  $i++;

} while (($Startzeit + 56) > time());


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

Ausgang:


$funktionen->log_schreiben("-------------   Stop   huawei.php    --------------------------- ","|--",6);

return;


?>
