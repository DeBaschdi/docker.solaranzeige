<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Wechselrichters Growatt über eine RS485
//  Schnittstelle mit USB Adapter. Protokoll Version 1  V3.5
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
$Start = time();  // Timestamp festhalten
Log::write("----------------------   Start  goodwe_ET.php   --------------------- ","|--",6);

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

if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
elseif(strlen($WR_Adresse) == 2)  {
  $WR_ID = str_pad(dechex(substr($WR_Adresse,-2)),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = dechex($WR_Adresse);
}


Log::write("WR_ID: ".$WR_ID,"+  ",8);


$USB1 = USB::openUSB($USBRegler);
if (!is_resource($USB1)) {
  Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  Log::write("Exit.... ","XX ",7);
  goto Ausgang;
}

$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  This is a map document of standard MODBUS RTU protocol for only 
  //  GoodWe energy storage inverters compatible with LV battery – 
  //  ES, EM, SBP series. Inverter Address: Can be assigned from 1-247. 
  //  247 is factory default assignment.
  //  Communication baud rate: The default baud rate is 9600 bps, 
  //  which is adjustable up  
  //
  ****************************************************************************/
  
  $Befehl["DeviceID"] = strtoupper($WR_ID);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = dechex(35000);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Modbus_Version"] = hexdec($rc["data"]);

  Log::write("DeviceID: (HEX) ".strtoupper($WR_ID),"   ",8);

  $Befehl["RegisterAddress"] = dechex(35002);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Output_Type"] = hexdec($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(35003);
  $Befehl["RegisterCount"] = "0008";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Seriennummer"] = Utils::hex2string($rc["data"]);
  Log::write("S/N: ".$aktuelleDaten["Seriennummer"],"   ",5);

  $Befehl["RegisterAddress"] = dechex(35011);
  $Befehl["RegisterCount"] = "0005";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Device_Type"] = Utils::hex2string($rc["data"]);
  if (empty($aktuelleDaten["Device_Type"])) {
    Log::write("Keine gültigen Daten empfangen.","!! ",4);
    goto Ausgang;
  }
  $Befehl["RegisterAddress"] = dechex(35103);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV1_Spannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35104);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV1_Strom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35105);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV1_Leistung"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(35107);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV2_Spannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35108);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV2_Strom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35109);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV2_Leistung"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(35111);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV3_Spannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35112);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV3_Strom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35113);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV3_Leistung"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(35115);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV4_Spannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35116);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV4_Strom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35117);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV4_Leistung"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(35119);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Mode"] = hexdec($rc["data"]);



  $Befehl["RegisterAddress"] = dechex(35121);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung"] = (hexdec($rc["data"])/10);
  $aktuelleDaten["AC_Spannung_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35122);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35123);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Netzfrequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(35125);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_R"] = Utils::hexdecs($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(35126);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung"] = (hexdec($rc["data"])/10);
  $aktuelleDaten["AC_Spannung_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35127);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35130);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_S"] = Utils::hexdecs($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(35131);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Spannung"] = (hexdec($rc["data"])/10);
  $aktuelleDaten["AC_Spannung_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35132);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Strom_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35135);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung_T"] = Utils::hexdecs($rc["data"]);




  $Befehl["RegisterAddress"] = dechex(35138);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Leistung"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35140);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Leistung"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35172);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Verbrauch"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35174);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Temperatur"] = (Utils::hexdecs($rc["data"])/10);


  $Befehl["RegisterAddress"] = dechex(35180);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Spannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35181);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Strom"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(35183);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Leistung"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35184);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Mode"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35187);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WR_Mode"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(35189);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["FehlerCode"] = hexdec($rc["data"]);

  $aktuelleDaten["FehlerBit"] = Utils::d2b($rc["data"]);



  $Befehl["RegisterAddress"] = dechex(35191);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Leistung_total"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(35193);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WattstundenGesamtHeute"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(35195);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Einspeisung_total"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(35199);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["EinspeisungHeute"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(35200);
  $Befehl["RegisterCount"] = "0002";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_NetzbezugGesamt"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(35202);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["BezugHeute"] = (hexdec($rc["data"])*100);

  $Befehl["RegisterAddress"] = dechex(36000);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MeterType"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(36008);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Einspeisung_Bezug"] = Utils::hexdecs($rc["data"]);




  $Befehl["RegisterAddress"] = dechex(37002);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["BMS_Status"] = hexdec($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(37007);
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["SOC"] = hexdec($rc["data"]);

  $aktuelleDaten["Diag_Binary"] = "0";  // Dummy
  $aktuelleDaten["Diag_Status"] = "0";  // Dummy

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/



  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";

 //  $aktuelleDaten["Diag_Binary"] = base_convert(dechex($aktuelleDaten["Diag_Status"]),16,2);
  Log::write("Produkt:  ".$aktuelleDaten["Device_Type"],"   ",5);
  Log::write("Wattstunden Heute:  ".$aktuelleDaten["WattstundenGesamtHeute"],"   ",5);

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Device_Type"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  Log::write(print_r($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/goodwe_et_math.php")) {
    include $basedir.'/custom/goodwe_et_math.php';  // Falls etwas neu berechnet werden muss.
  }
  elseif ( file_exists($basedir."/custom/goodwe_wr_math.php")) {
    // Das kann später einmal gelöscht werden. Es umgeht einen Fehler.
    include $basedir.'/custom/goodwe_wr_math.php';  // Falls etwas neu berechnet werden muss.
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
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
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
      Log::write("Schleife ".$i." Ausgang...","   ",8);
      break;
  }
  $i++;
} while (($Start + 54) > time());


if (isset($aktuelleDaten["Modbus_Version"]) and !empty($aktuelleDaten["Device_Type"])) {


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

Log::write("----------------------   Stop   goodwe_ET.php   --------------------- ","|--",6);

return;



?>