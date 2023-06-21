<?php
/****************************************************************************/
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
//  Es dient dem Auslesen der Regler der Huawei Serie  -M0, -M1, -M2,
//  über die LAN Schnittstelle 
//  Das Auslesen dauert jedoch ca. 50 Sekunden!!! Bitte beachten.    *******/
//  Bei 2 Kaskaden Geräte in der crontab */2 * * * *  eingeben.
//  Bei 3 Geräte */3 * * * * eingeben.
//  Damit die Geräte nur alle 2 bzw. 3 Minuten ausgelesen werden.
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
/****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Version = "";

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

$Startzeit = time();  // Timestamp festhalten
Log::write("-------------   Start  huawei_LAN.php  ----------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
Log::write( "Huawei: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 ); 

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

$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);   // 5 = Timeout in Sekunden
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  Log::write("Exit.... ","XX ",9);
  goto Ausgang;
}

//  Warten bis LAN Connect erfolgreich war.
usleep(800000); // normal 800000,   bei Kaskade 500000

$i = 0;
do {
  Log::write("Die Daten werden ausgelesen...",">  ",9);
  $i++;
  sleep(1);
  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  // function modbus_register_lesen($COM1,$Register,$Laenge,$Typ,$UnitID,$Befehl="03")
  //
  // Auf UnitID und Befehl achten!   UnitID muss 03 sein. Ist hier fest vergeben.
  //  Befehl 03 = single Byte read
  //  UnitID 03 = default
  ****************************************************************************/
  $aktuelleDaten["KeineSonne"] = false;  // Dummy

  $Timebase = 100000; // Je nach Dongle Firmware zwischen 60000 und 200000

  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "30000", "51", "Hex", $Timebase );
  if ($rc == false and $i < 2) {
    Log::write("Fehler! Keine gültigen Daten empfangen. ","   ",5);
    continue;
  }
  $aktuelleDaten["Modell"] = Utils::hex2string( substr( $rc["Wert"], 0, 30 ));
  $aktuelleDaten["Firmware"] =  Utils::hex2string( substr( $rc["Wert"], 0, 30 ));
  $aktuelleDaten["Seriennummer"] = Utils::hex2string( substr( $rc["Wert"], 60, 24 ));
  $aktuelleDaten["PN"] =  Utils::hex2string( substr( $rc["Wert"], 100, 24 ));
  $aktuelleDaten["ModellID"] = hexdec( substr( $rc["Wert"], 280, 4 ));
  Log::write("Gerätetyp: ".$aktuelleDaten["Modell"]."  Modell ID: ".$aktuelleDaten["ModellID"],">  ",5);
  $aktuelleDaten["Anz_PV_Strings"] = hexdec( substr( $rc["Wert"], 284, 4 ));
  $aktuelleDaten["Anz_MPP_Trackers"] = hexdec( substr( $rc["Wert"], 288, 4 ));

  sleep(1);

  if ($rc == false and $i >= 2) {
    Log::write("Fehler! Gerät antwortet nicht.","   ",5);
    Log::write(print_r($rc,1),"   ",5);
    goto Ausgang;
  }

  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "32000", "74", "Hex", $Timebase );
  if ($rc == false and $i < 2) {
    Log::write("Fehler! Keine gültigen Daten empfangen. ","   ",5);
    continue;
  }

  $aktuelleDaten["Status1"] = hexdec( substr( $rc["Wert"], 0, 4 ));
  $aktuelleDaten["Status1Bit"] = Utils::d2b(hexdec(substr( $rc["Wert"], 0, 4 )));
  $aktuelleDaten["Alarm1"] =  hexdec( substr( $rc["Wert"], 32, 4 ));
  $aktuelleDaten["Alarm1Bit"] = Utils::d2b(hexdec(substr( $rc["Wert"], 32, 4 )));

  Log::write("Alarm 1 Bits: ".$aktuelleDaten["Alarm1Bit"],">  ",8);
  $aktuelleDaten["PV_Leistung"] = 0;


  for ($j = 1; $j <= hexdec($aktuelleDaten["Anz_PV_Strings"]); $j++) {
    $aktuelleDaten["PV".$j."_Spannung"] = 0;
    $aktuelleDaten["PV".$j."_Strom"] = 0;

    $aktuelleDaten["PV".$j."_Spannung"] = Utils::hexdecs( substr( $rc["Wert"], 56+($j*8), 4 ))/10;
    $aktuelleDaten["PV".$j."_Strom"] = Utils::hexdecs( substr( $rc["Wert"], 60+($j*8), 4 ))/100;

    $aktuelleDaten["PV".$j."_Leistung"] = round(($aktuelleDaten["PV".$j."_Strom"] * $aktuelleDaten["PV".$j."_Spannung"]),2);

    $aktuelleDaten["PV_Leistung"] = round(($aktuelleDaten["PV_Leistung"] + ($aktuelleDaten["PV".$j."_Strom"] * $aktuelleDaten["PV".$j."_Spannung"])),2);
  }

  $aktuelleDaten["AC_Eingangsleistung"] = Utils::hexdecs( substr( $rc["Wert"], 256, 8 ));
  $aktuelleDaten["AC_Spannung_R"] = (hexdec( substr( $rc["Wert"], 276, 4 ))/10);
  $aktuelleDaten["AC_Spannung_S"] = (hexdec( substr( $rc["Wert"], 280, 4 ))/10);
  $aktuelleDaten["AC_Spannung_T"] = (hexdec( substr( $rc["Wert"], 284, 4 ))/10);
  $aktuelleDaten["AC_Leistung"] = Utils::hexdecs( substr( $rc["Wert"], 320, 8 ));
  $aktuelleDaten["AC_Frequenz"] = (hexdec( substr( $rc["Wert"], 340, 4 ))/100);
  $aktuelleDaten["Effizienz"] = (Utils::hexdecs( substr( $rc["Wert"], 344, 4 ))/100);
  $aktuelleDaten["Temperatur"] = (hexdec( substr( $rc["Wert"], 348, 4 ))/10);
  $aktuelleDaten["DeviceStatus"] = hexdec( substr( $rc["Wert"], 356, 4 ));
  $aktuelleDaten["FehlerCode"] = hexdec( substr( $rc["Wert"], 360, 4 ));
  $aktuelleDaten["WattstundenGesamt"] = (hexdec( substr( $rc["Wert"], 424, 8 ))*10);
  $aktuelleDaten["WattstundenGesamtHeute"] = (hexdec( substr( $rc["Wert"], 456, 8 ))*10);



  sleep(1);
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "37000", "7D", "Hex", $Timebase );
  if ($rc == false and $i < 2) {
    Log::write("Fehler! Keine gültigen Daten empfangen. ","   ",5);
    continue;
  }
  $aktuelleDaten["Batterie_Status"] = hexdec( substr( $rc["Wert"], 0, 4 ));
  if ($aktuelleDaten["Batterie_Status"] == 2 ) {
    $aktuelleDaten["Batterie_Leistung"] = Utils::hexdecs( substr( $rc["Wert"], 4, 8 ));
  }
  else {
    $aktuelleDaten["Batterie_Leistung"] = 0;
  }
  $aktuelleDaten["SOC"] = (hexdec( substr( $rc["Wert"], 16, 4 ))/10);
  $aktuelleDaten["Einspeisung_Bezug"] = Utils::hexdecs( substr( $rc["Wert"], 452, 8 ));
  $aktuelleDaten["WattstundengesamtExport"] = (hexdec( substr( $rc["Wert"], 476, 8 ))*10);
  $aktuelleDaten["WattstundengesamtImport"] = (hexdec( substr( $rc["Wert"], 484, 8 ))*10);



  if ($aktuelleDaten["Einspeisung_Bezug"] >= 0)  {
    $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Einspeisung_Bezug"];
    $aktuelleDaten["Bezug"] = 0;
  }
  else   {
    $aktuelleDaten["Einspeisung"] = 0;
    $aktuelleDaten["Bezug"] = abs($aktuelleDaten["Einspeisung_Bezug"]);
  }


  if ($aktuelleDaten["Batterie_Leistung"] >= 0)  {
    $aktuelleDaten["Batterie_Ladung"] = $aktuelleDaten["Batterie_Leistung"];
    $aktuelleDaten["Batterie_Entladung"] = 0;
  }
  else   {
    $aktuelleDaten["Batterie_Ladung"] = 0;
    $aktuelleDaten["Batterie_Entladung"] = abs($aktuelleDaten["Batterie_Leistung"]);
  }




  //  Achtung! Der Hausverbrauch wird nur von einem Gerät errechnet. Bei einer Kaskade stimmt der Wert nicht und muss selber in Grafana summiert werden.
  //  Bei einer Kaskade müssen die Werte summiert werden und dann errechnet werden.
  $aktuelleDaten["Hausverbrauch"] = ($aktuelleDaten["AC_Eingangsleistung"] + $aktuelleDaten["Bezug"] + $aktuelleDaten["Batterie_Entladung"] - $aktuelleDaten["Einspeisung"] - $aktuelleDaten["Batterie_Ladung"]);

  if ($aktuelleDaten["Hausverbrauch"] < 0) {
    $aktuelleDaten["Hausverbrauch"] = 0;
  }

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
  if ( file_exists($basedir."/custom/huawei_LAN_math.php")) {
    include $basedir.'/custom/huawei_LAN_math.php';  // Falls etwas neu berechnet werden muss.
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
      // sleep($Zeitspanne);
      // Der Huawei mit sDongle ist sehr langsam. Deshalb keine Pause.
    }
    break;
  }
  else {
    if (floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)) > 0) {
      Log::write("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Startzeit))/($Wiederholungen-$i+1))),"   ",3);
      sleep(floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)));
    }
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
    break;
  }
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


Log::write("-------------   Stop   huawei_LAN.php    --------------------------- ","|--",6);

return;


?>
