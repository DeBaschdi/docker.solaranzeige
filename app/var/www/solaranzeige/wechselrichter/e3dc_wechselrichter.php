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
//  Es dient dem Auslesen des E3DC Wechselrichter über die LAN Schnittstelle
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
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
Log::write("-------------   Start  E3DC Wechselrichter    --------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
Log::write( "E3DC: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 ); 

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
  if ($rc === false) {
    Log::write("Konnte die Datei kwhProTag_e3dc.txt nicht anlegen.","   ",5);
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


//  Abfrage des Simple-Mode
$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);  // 5 = Timeout in Sekunden
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP.",  Port: ".$WR_Port.",  Fehlermeldung: ".$errstr,"XX ",3);
  Log::write("Exit SunSpec-Mode.... ","XX ",3);
  goto Ausgang;
}


$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter im Simple-Mode ausgelesen.
  //
  ****************************************************************************/


  // Ab Speicher Adresse 40000  lesen
  //  Simple-Mode    Simple-Mode    Simple-Mode
  $rc = SolarEdge::solaredge_lesen($COM1,"010300000070");
  Log::write("40000: ".$rc,"+  ",9);


  $aktuelleDaten["MagicByte"] = substr($rc,18,4);
  $aktuelleDaten["Modbus_Firmware"] = substr($rc,22,4);
  $aktuelleDaten["Hersteller"] = trim(Utils::Hex2String(substr($rc,30,64)));
  $aktuelleDaten["Modell"] = trim(Utils::Hex2String(substr($rc,94,64)));
  $aktuelleDaten["Seriennummer"] = trim(Utils::Hex2String(substr($rc,158,64)));
  $aktuelleDaten["Firmware"] = trim(Utils::Hex2String(substr($rc,222,64)));

  $aktuelleDaten["PV_Leistung"] = hexdec(substr($rc,290,4).substr($rc,286,4));
  $aktuelleDaten["Batterie_Leistung"] = Utils::hexdecs(substr($rc,298,4).substr($rc,294,4));
  $aktuelleDaten["AC_Verbrauch"] = Utils::hexdecs(substr($rc,306,4).substr($rc,302,4));
  $aktuelleDaten["AC_Bezug"] = Utils::hexdecs(substr($rc,314,4).substr($rc,310,4));
  $aktuelleDaten["AC_Zusatzleistung"] = Utils::hexdecs(substr($rc,322,4).substr($rc,318,4));
  $aktuelleDaten["AC_Leistung_Wallbox"] = Utils::hexdecs(substr($rc,330,4).substr($rc,326,4));
  $aktuelleDaten["PV_Leistung_Wallbox"] = Utils::hexdecs(substr($rc,338,4).substr($rc,334,4));
  $aktuelleDaten["Autarkie"] = intdiv(Utils::hexdecs(substr($rc,342,4)), 256);
  $aktuelleDaten["Verbrauch"] = (Utils::hexdecs(substr($rc,342,4)) % 256);
  $aktuelleDaten["SOC"] = Utils::hexdecs(substr($rc,346,4));
  $aktuelleDaten["Power_Status"] = substr($rc,350,4);
  $aktuelleDaten["EMS_Status"] = substr($rc,354,4);
  $aktuelleDaten["EMS_Remote_Control"] = substr($rc,358,4);
  $aktuelleDaten["EMS_Control"] = substr($rc,362,4);
  $aktuelleDaten["Wallbox0"] = Utils::hexdecs(substr($rc,366,4));
  $aktuelleDaten["Wallbox1"] = Utils::hexdecs(substr($rc,370,4));
  $aktuelleDaten["Wallbox2"] = Utils::hexdecs(substr($rc,374,4));
  $aktuelleDaten["Wallbox3"] = Utils::hexdecs(substr($rc,378,4));
  $aktuelleDaten["Wallbox4"] = Utils::hexdecs(substr($rc,382,4));
  $aktuelleDaten["Wallbox5"] = Utils::hexdecs(substr($rc,386,4));
  $aktuelleDaten["Wallbox6"] = Utils::hexdecs(substr($rc,390,4));
  $aktuelleDaten["Wallbox7"] = Utils::hexdecs(substr($rc,394,4));
  $aktuelleDaten["DC_String1_Spannung"] = Utils::hexdecs(substr($rc,398,4));
  $aktuelleDaten["DC_String2_Spannung"] = Utils::hexdecs(substr($rc,402,4));
  $aktuelleDaten["DC_String3_Spannung"] = Utils::hexdecs(substr($rc,406,4));
  $aktuelleDaten["DC_String1_Strom"] = SolarEdge::solaredge_faktor(Utils::hexdecs(substr($rc,410,4)),-2);
  $aktuelleDaten["DC_String2_Strom"] = SolarEdge::solaredge_faktor(Utils::hexdecs(substr($rc,414,4)),-2);
  $aktuelleDaten["DC_String3_Strom"] = SolarEdge::solaredge_faktor(Utils::hexdecs(substr($rc,418,4)),-2);
  $aktuelleDaten["DC_String1_Leistung"] = Utils::hexdecs(substr($rc,422,4));
  $aktuelleDaten["DC_String2_Leistung"] = Utils::hexdecs(substr($rc,426,4));
  $aktuelleDaten["DC_String3_Leistung"] = Utils::hexdecs(substr($rc,430,4));
  $aktuelleDaten["Leistungsmesser"] = Utils::hexdecs(substr($rc,434,4));
  $aktuelleDaten["Phasenleistung_L1"] = Utils::hexdecs(substr($rc,438,4));
  $aktuelleDaten["Phasenleistung_L2"] = Utils::hexdecs(substr($rc,442,4));
  $aktuelleDaten["Phasenleistung_L3"] = Utils::hexdecs(substr($rc,446,4));
  $aktuelleDaten["Wallbox_CTRL"] = decbin(Utils::hexdecs(substr($rc,366,4))+8192);
  $aktuelleDaten["Wallbox_Aktiv"] = substr($aktuelleDaten["Wallbox_CTRL"],13,1);
  $aktuelleDaten["Wallbox_Modus"] = substr($aktuelleDaten["Wallbox_CTRL"],12,1);
  $aktuelleDaten["Wallbox_Laden"] = substr($aktuelleDaten["Wallbox_CTRL"],11,1);
  $aktuelleDaten["Wallbox_Auto"] = substr($aktuelleDaten["Wallbox_CTRL"],10,1);
  $aktuelleDaten["Wallbox_verriegelt"] = substr($aktuelleDaten["Wallbox_CTRL"],9,1);
  $aktuelleDaten["Wallbox_gesteckt"] = substr($aktuelleDaten["Wallbox_CTRL"],8,1);
  $aktuelleDaten["Wallbox_3Ph_16A"] = substr($aktuelleDaten["Wallbox_CTRL"],3,1);
  $aktuelleDaten["Wallbox_3Ph_32A"] = substr($aktuelleDaten["Wallbox_CTRL"],2,1);
  $aktuelleDaten["Wallbox_Kabel_Ph"] = substr($aktuelleDaten["Wallbox_CTRL"],1,1);
  $aktuelleDaten["EMS_CTRL"] = decbin(Utils::hexdecs(substr($rc,354,4))+128);
  $aktuelleDaten["EMS_Laden"] = substr($aktuelleDaten["EMS_CTRL"],7,1);
  $aktuelleDaten["EMS_Entladen"] = substr($aktuelleDaten["EMS_CTRL"],6,1);
  $aktuelleDaten["EMS_Notstrom"] = substr($aktuelleDaten["EMS_CTRL"],5,1);
  $aktuelleDaten["EMS_Wetter"] = substr($aktuelleDaten["EMS_CTRL"],4,1);
  $aktuelleDaten["EMS_Abregelung"] = substr($aktuelleDaten["EMS_CTRL"],3,1);
  $aktuelleDaten["EMS_Ladesperre"] = substr($aktuelleDaten["EMS_CTRL"],2,1);
  $aktuelleDaten["EMS_Entladesperre"] = substr($aktuelleDaten["EMS_CTRL"],1,1);  

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/



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
  $aktuelleDaten["Firmware"] = $aktuelleDaten["Modbus_Firmware"];
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Firmware"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    Log::write(print_r($aktuelleDaten,1),"*- ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/e3dc_wechselrichter_math.php")) {
    include $basedir.'/custom/e3dc_wechselrichter_math.php';  // Falls etwas neu berechnet werden muss.
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
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
      Log::write("Schleife ".$i." Ausgang...","   ",8);
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
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["DC_String1_Spannung"];
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
if (file_exists($StatusFile) and isset($aktuelleDaten["PV_Leistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["PV_Leistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  Log::write("Solarleistung: ".$aktuelleDaten["PV_Leistung"]." Watt -  WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}


Ausgang:

Log::write("-------------   Stop   E3DC Wechselricter     --------------- ","|--",6);

return;




?>
