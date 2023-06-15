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
$funktionen->log_schreiben("-------------   Start  huawei_M1.php  ----------------------------- ","|--",6);

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


$i = 0;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...",">  ",9);
  $i++;
  if ($i > 6) {
    $funktionen->log_schreiben("Fehler beim Auslesen....",">  ",5);
    goto Ausgang;
  }
  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //  Es gibt nur eine DeviceID
  //  DeviveID = 4 = Laderegler + Wechselrichter
  //  Modelle mit -M0, -M1 -M2 -M3 am Ende
  ****************************************************************************/
  //

  $Befehl["RegisterAddress"] = dechex(30000);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "000f";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Firmware"] = trim($funktionen->Hex2String($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(30015);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "000a";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Seriennummer"] = trim($funktionen->Hex2String($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(30070);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["ModellID"] = hexdec($rc["data"]);
  $funktionen->log_schreiben("Gerätetyp: ".$aktuelleDaten["Firmware"]."  Modell ID: ".$aktuelleDaten["ModellID"],">  ",5);


  $Befehl["RegisterAddress"] = dechex(30071);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Anz_PV_Strings"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(30072);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Anz_MPP_Trackers"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32000);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Status1"] = $rc["data"];
  $aktuelleDaten["Status1Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32002);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Status2"] = $rc["data"];
  $aktuelleDaten["Status2Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32003);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Status3"] = $rc["data"];
  $aktuelleDaten["Status3Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32008);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Alarm1"] = $rc["data"];
  $aktuelleDaten["Alarm1Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32009);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Alarm2"] = $rc["data"];
  $aktuelleDaten["Alarm2Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32010);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Alarm3"] = $rc["data"];
  $aktuelleDaten["Alarm3Bit"] = $funktionen->d2b(hexdec($rc["data"]));

  $aktuelleDaten["PV_Leistung"] = 0;

  for ($j = 1; $j <= hexdec($aktuelleDaten["Anz_PV_Strings"]); $j++) {
    $rc = array();
    $aktuelleDaten["PV".$j."_Spannung"] = 0;
    $aktuelleDaten["PV".$j."_Strom"] = 0;

    $Befehl["RegisterAddress"] = dechex((32014+($j*2)));   
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
    if ($rc == false) {
      //continue 2;
    }
    $aktuelleDaten["PV".$j."_Spannung"] = (hexdec($rc["data"])/10);
    $funktionen->log_schreiben("PV Spannung ".$j." = ".$aktuelleDaten["PV".$j."_Spannung"],">  ", 9);

    $Befehl["RegisterAddress"] = dechex((32015+($j*2)));
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
    if ($rc == false) {
      //continue 2;
    }
    if ($funktionen->hexdecs($rc["data"]) >= 0) {
      // Wenn ein negative Strom gemeldet wird, stimmt was nicht.
      $aktuelleDaten["PV".$j."_Strom"] = (hexdec($rc["data"])/100);
      $funktionen->log_schreiben("PV Strom ".$j." = ".(hexdec($rc["data"])/100),">  ", 9);
    }
    else {
      // Negative Werte beim PV Strom darf es nicht geben.
      // Dann wird der Strom auf 0 gesetzt.
      $funktionen->log_schreiben("PV Strom ".$j." in HEX  = ".$rc["data"]. " = 0",">  ", $j + 6 ); 
    }
    $aktuelleDaten["PV_Leistung"] = round(($aktuelleDaten["PV_Leistung"] + ($aktuelleDaten["PV".$j."_Strom"] * $aktuelleDaten["PV".$j."_Spannung"])),2);
  }

  $Befehl["RegisterAddress"] = dechex(32064);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["AC_Eingangsleistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(32069);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["AC_Spannung_R"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32070);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["AC_Spannung_S"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32071);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["AC_Spannung_T"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32080);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  if (substr($rc["data"],0,4) == "ffff") {
    $aktuelleDaten["AC_Leistung"] = 0; 
  }
  else {
    $aktuelleDaten["AC_Leistung"] = hexdec($rc["data"]);
  }


  $Befehl["RegisterAddress"] = dechex(32085);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["AC_Frequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32086);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Effizienz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = dechex(32087);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Temperatur"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = dechex(32088);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Isolation"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = dechex(32089);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["DeviceStatus"] = hexdec($rc["data"]);



  $Befehl["RegisterAddress"] = dechex(32090);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  //  FehlerCode in Dezimal !
  $aktuelleDaten["FehlerCode"] = hexdec($rc["data"]);


  $Befehl["RegisterAddress"] = dechex(32106);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamt"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(32114);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(37000);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Batterie_Status"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(37001);   
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }

  if ($aktuelleDaten["Batterie_Status"] == 2 ) {
    $aktuelleDaten["Batterie_Leistung"] = $funktionen->hexdecs($rc["data"]);
  }
  else {
    $aktuelleDaten["Batterie_Leistung"] = 0;
  }

  $Befehl["RegisterAddress"] = dechex(37004);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["SOC"] = (hexdec($rc["data"])/10);


  $Befehl["RegisterAddress"] = dechex(37113);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["Einspeisung_Bezug"] = $funktionen->hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = dechex(37119);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["WattstundengesamtExport"] = (hexdec($rc["data"])*10);

  $Befehl["RegisterAddress"] = dechex(37121);
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1,$Befehl);
  if ($rc == false) {
    continue;
  }
  $aktuelleDaten["WattstundengesamtImport"] = (hexdec($rc["data"])*10);


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
  $aktuelleDaten["Produkt"]  = $aktuelleDaten["Firmware"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ", 8);



  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/huawei_M1_math.php")) {
    include 'huawei_M1_math.php';  // Falls etwas neu berechnet werden muss.
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
          $RemoteDaten = true;
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
    if ($i < 6) {
      if (floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)) >= 0) {
        $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Startzeit))/($Wiederholungen-$i+1))),"   ",3);
        sleep(floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)));
      }
    }
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }

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


$funktionen->log_schreiben("-------------   Stop   huawei_M1.php    --------------------------- ","|--",6);

return;


?>
