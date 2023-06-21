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
Log::write("-------------   Start  huawei.php  ----------------------------- ","|--",6);

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

$USB1 = USB::openUSB($USBRegler);
if (!is_resource($USB1)) {
  Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  Log::write("Exit.... ","XX ",7);
  goto Ausgang;
}


$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...",">  ",9);

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
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Modell"] = hexdec($rc["data"]);
  Log::write("Gerätetyp: ".$aktuelleDaten["Modell"],">  ",5);


  $Befehl["RegisterAddress"] = dechex(32002);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["OutputMode"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32003);   
  $Befehl["RegisterCount"] = "000A";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Seriennummer"] = Utils::Hex2String($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32153);   
  $Befehl["RegisterCount"] = "000A";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Firmware"] = trim(Utils::Hex2String($rc["data"]),"\0");



  $Befehl["RegisterAddress"] = dechex(32262);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung1"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32263);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom1"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32264);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung2"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32265);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom2"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32266);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung3"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32267);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom3"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32268);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung4"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32269);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom4"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32270);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung5"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32271);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom5"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32272);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung6"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32273);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom6"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32314);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung7"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32315);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom7"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32316);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Spannung8"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32317);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Strom8"] = (Utils::hexdecs($rc["data"])/10);




  $Befehl["RegisterAddress"] = dechex(32277);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32278);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32279);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32280);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32281);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32282);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32283);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Frequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32284);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Powerfactor"] = (Utils::hexdecs($rc["data"])/1000);

  $Befehl["RegisterAddress"] = dechex(32285);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Effizienz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32286);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Temperatur"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32287);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Status"] = hexdec($rc["data"]); // Inverter Status Seite 7
  Log::write("Status Hex: ".$rc["data"],">  ",8);


  $Befehl["RegisterAddress"] = dechex(32290);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung"] = hexdec($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(32294);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Leistung"] = hexdec($rc["data"]);
  Log::write("aktuelle PV-Leistung: ".$aktuelleDaten["PV_Leistung"]." Watt",">  ",5);


  $Befehl["RegisterAddress"] = dechex(32300);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtHeute"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(32302);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtMonat"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(32304);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtJahr"] = (hexdec($rc["data"])*10);


  $Befehl["RegisterAddress"] = dechex(32319);   
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["State1"] = ($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(33022);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT1_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33024);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT2_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33026);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT3_Leistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(33070);   
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
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

  Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/huawei_math.php")) {
    include $basedir.'/custom/huawei_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    Log::write("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($basedir."/services/mqtt_senden.php");
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
    $Zeitspanne = (9 - (time() - $Startzeit));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Startzeit))/($Wiederholungen-$i+1))),"   ",3);
    sleep(floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
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


Log::write("-------------   Stop   huawei.php    --------------------------- ","|--",6);

return;


?>
