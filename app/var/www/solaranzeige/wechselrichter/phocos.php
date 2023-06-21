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
//  Es dient dem Auslesen der Regler der Phocos Serie über die USB 
//  Schnittstelle ( RS485 zu USB Adapter)
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


$Befehl = array(
  "DeviceID" => "01",
  "BefehlFunctionCode" => "03",
  "RegisterAddress" => "0000",
  "RegisterCount" => "0001" );



$Start = time();  // Timestamp festhalten
Log::write("-------------   Start  phocos.php  ----------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");


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
  Log::write("WattstundenGesamtHeute: ".round($aktuelleDaten["WattstundenGesamtHeute"],2),"   ",8);
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
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    Log::write("Konnte die Datei whProTag_pho.txt nicht anlegen.","XX ",5);
  }
}





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
  //  Zuerst der Batterieteil  (Laderegler)
  //  DeviveID = 1 = Laderegler
  //  DeviveID = 4 = Wechselrichter
  ****************************************************************************/
  //
  $Befehl["DeviceID"] = "01";
  $Befehl["RegisterAddress"] = "2711";   // Dezimal 10001
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Maschinentype"] = hexdec($rc["data"]);

  Log::write("Maschinentyp: PH".$aktuelleDaten["Maschinentype"],"   ",6);

  $Befehl["RegisterAddress"] = "2775";   // Dezimal 10101
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Laderegler_aktiv"] = hexdec($rc["data"])+0;

  $Befehl["RegisterAddress"] = "277C";   // Dezimal 10108
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Max_Ampere"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "277F";   // Dezimal 10111
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Ah"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B61";   // Dezimal 15201
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Regler_Mode"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B62";   // Dezimal 15202
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["MPPT_Status"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B63";   // Dezimal 15203
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Regler_Status"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B65";   // Dezimal 15205
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Solarspannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "3B66";   // Dezimal 15206
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batteriespannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "3B67";   // Dezimal 15207
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Ladereglerstrom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "3B68";   // Dezimal 15208
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Ladereglerleistung"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B69";   // Dezimal 15209
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Temperatur"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = "3B6B";   // Dezimal 15211
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Bat_Relay"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B6C";   // Dezimal 15212
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["PV_Relay"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B6D";   // Dezimal 15213
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Fehler"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B6E";   // Dezimal 15214
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Warnungen"] = hexdec($rc["data"]);

  $Befehl["RegisterAddress"] = "3B71";   // Dezimal 15217
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Gesamt_high"] = (hexdec($rc["data"])*1000);

  $Befehl["RegisterAddress"] = "3B72";   // Dezimal 15218
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Wh_Gesamt"] = (hexdec($rc["data"])*100) + $aktuelleDaten["Wh_Gesamt_high"];

  /********************************************************/
  
  $Befehl["DeviceID"] = "04";
  $Befehl["RegisterAddress"] = "6271";   // Dezimal 25201
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["WR_Status"] = hexdec($rc["data"])+ 0;

  $Befehl["RegisterAddress"] = "6275";   // Dezimal 25205
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Bat_Spannung"] = (hexdec($rc["data"])/10);


  $Befehl["RegisterAddress"] = "6276";   // Dezimal 25206
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Ausgangsspannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "6277";   // Dezimal 25207
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Netzspannung"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "627A";   // Dezimal 25210
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Ausgangsstrom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "627B";   // Dezimal 25211
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl); // Minus ist Bezug!
  $aktuelleDaten["Netzstrom"] = (Utils::hexdecs($rc["data"])/10);

  $Befehl["RegisterAddress"] = "627C";   // Dezimal 25212
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Last_Strom"] = (hexdec($rc["data"])/10);

  $Befehl["RegisterAddress"] = "627D";   // Dezimal 25213
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Ausgangsleistung"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = "627E";   // Dezimal 25214
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl); // Minus ist Bezug!
  $aktuelleDaten["Netzleistung"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = "627F";   // Dezimal 25215
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Ausgangslast"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = "6280";   // Dezimal 25216
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Last_Prozent"] = (hexdec($rc["data"]));

  $Befehl["RegisterAddress"] = "6289";   // Dezimal 25225
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["AC_Ausgangsfrequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = "628A";   // Dezimal 25226
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Netzfrequenz"] = (hexdec($rc["data"])/100);

  $Befehl["RegisterAddress"] = "62B9";   // Dezimal 25225
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterieleistung"] = Utils::hexdecs($rc["data"]);

  $Befehl["RegisterAddress"] = "62BA";   // Dezimal 25226
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen($USB1,$Befehl);
  $aktuelleDaten["Batterie_Strom"] = Utils::hexdecs($rc["data"]);

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
  $aktuelleDaten["Firmware"] = 0;
  $aktuelleDaten["Produkt"]  = $aktuelleDaten["Maschinentype"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/phocos_math.php")) {
    include $basedir.'/custom/phocos_math.php';  // Falls etwas neu berechnet werden muss.
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
  $whProTag = ($whProTag + ($aktuelleDaten["Ladereglerleistung"]/60));

  $rc = file_put_contents($StatusFile,$whProTag);
  Log::write("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}


Ausgang:


Log::write("-------------   Stop   phocos.php    --------------------------- ","|--",6);

return;


?>
