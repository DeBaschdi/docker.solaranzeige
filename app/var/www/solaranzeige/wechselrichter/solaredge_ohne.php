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
//  Es dient dem Auslesen des SolarEdge ohne Messzähler über die LAN Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
Log::write("-------------   Start  solaredge_serie.php    --------------- ","|--",6);

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
    Log::write("Konnte die Datei kwhProTag.txt nicht anlegen.","   ",5);
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
Log::write("Hardware Version: ".$Version,"o  ",1);

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

if(Utils::tageslicht() or $InfluxDaylight === false)  {
  //  Der Wechselrichter wird nur am Tage abgefragt.
  $COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
  if (!is_resource($COM1)) {
    Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
    Log::write("Exit.... ","XX ",3);
    goto Ausgang;
  }
}
else {
  Log::write("Es ist dunkel... ","X  ",7);
  goto Ausgang;
}


if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(dechex(substr($WR_Adresse,-2)),2,"0",STR_PAD_LEFT);
}


$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["M1_AC_Spannung"]
  //  $aktuelleDaten["AC_Spannung_R"]
  //  $aktuelleDaten["AC_Spannung_S"]
  //  $aktuelleDaten["AC_Spannung_T"]
  //  $aktuelleDaten["AC_Frequenz"]
  //  $aktuelleDaten["AC_Leistung"]
  //  $aktuelleDaten["AC_Wirkleistung"]
  //  $aktuelleDaten["AC_Scheinleistung"]
  //  $aktuelleDaten["AC_Leistung_Prozent"]
  //  $aktuelleDaten["AC_Verbrauch"]
  //  $aktuelleDaten["AC_Bezug"]
  //  $aktuelleDaten["AC_Einspeisung"]
  //  $aktuelleDaten["DC_Spannung"]
  //  $aktuelleDaten["DC_Strom"]
  //  $aktuelleDaten["DC_Leistung"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["Status"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["AC_Wh_Gesamt"]
  //
  ****************************************************************************/


  // Ab Speicher Adresse 40000  lesen
  $rc = SolarEdge::solaredge_lesen($COM1,$WR_ID."0300000070");
  Log::write("40000: ".$rc,"+  ",8);

  $aktuelleDaten["Transaction"] = substr($rc,0,4);
  $aktuelleDaten["Protocol"] = substr($rc,4,4);
  $aktuelleDaten["Laenge"] = substr($rc,8,4);
  $aktuelleDaten["Adresse"] = substr($rc,12,2);
  $aktuelleDaten["Befehl"] = substr($rc,14,2);
  $aktuelleDaten["Laenge_Speicheradresse"] = substr($rc,16,2);
  $aktuelleDaten["MODBUS_Map"] = Utils::Hex2String(substr($rc,18,8));
  $aktuelleDaten["C_SunSpec_DID"] = substr($rc,26,4);
  $aktuelleDaten["C_SunSpec_Length"] = hexdec(substr($rc,30,4));
  $aktuelleDaten["Produkt"] = trim(Utils::Hex2String(substr($rc,34,64)));              // 40021
  $aktuelleDaten["Modell"] = trim(Utils::Hex2String(substr($rc,98,64)));               // 40045
  // $aktuelleDaten["Unbekannt"] = substr($rc,162,32);
  $aktuelleDaten["Version"] = trim(Utils::Hex2String(substr($rc,194,32)));
  $aktuelleDaten["Seriennummer"] = trim(Utils::Hex2String(substr($rc,226,64)));
  $aktuelleDaten["Firmware"] = trim(Utils::Hex2String(substr($rc,194,32)));
  $aktuelleDaten["DeviceAddress"] = substr($rc,290,4);
  $aktuelleDaten["WR_Typ"] = hexdec(substr($rc,294,4));
  $aktuelleDaten["AC_STROM_Faktor"] = Utils::hexdecs(substr($rc,318,4));
  $aktuelleDaten["AC_Gesamtstrom"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,302,4)),$aktuelleDaten["AC_STROM_Faktor"]);
  $aktuelleDaten["AC_Strom_R"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,306,4)),$aktuelleDaten["AC_STROM_Faktor"]);
  $aktuelleDaten["AC_Strom_S"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,310,4)),$aktuelleDaten["AC_STROM_Faktor"]);
  $aktuelleDaten["AC_Strom_T"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,314,4)),$aktuelleDaten["AC_STROM_Faktor"]);
  $aktuelleDaten["AC_Spannung_Faktor"] = Utils::hexdecs(substr($rc,346,4));
  $aktuelleDaten["AC_Spannung_R-S"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,322,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Spannung_S-T"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,326,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Spannung_T-R"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,330,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Spannung_R"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,334,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Spannung_S"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,338,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Spannung_T"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,342,4)),$aktuelleDaten["AC_Spannung_Faktor"]);
  $aktuelleDaten["AC_Leistung_Faktor"] = Utils::hexdecs(substr($rc,354,4));
  $aktuelleDaten["AC_Leistung"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,350,4)),$aktuelleDaten["AC_Leistung_Faktor"]);
  $aktuelleDaten["AC_Frequenz_Faktor"] = Utils::hexdecs(substr($rc,362,4));
  $aktuelleDaten["AC_Frequenz"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,358,4)),$aktuelleDaten["AC_Frequenz_Faktor"]);
  $aktuelleDaten["AC_Scheinleistung_Faktor"] = Utils::hexdecs(substr($rc,370,4));
  $aktuelleDaten["AC_Scheinleistung"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,366,4)),$aktuelleDaten["AC_Scheinleistung_Faktor"]);
  $aktuelleDaten["AC_Wirkleistung"] =  $aktuelleDaten["AC_Leistung"];
  $aktuelleDaten["AC_Blindleistung_Faktor"] = Utils::hexdecs(substr($rc,378,4));
  $aktuelleDaten["AC_Blindleistung"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,374,4)),$aktuelleDaten["AC_Blindleistung_Faktor"]);
  $aktuelleDaten["AC_WR_Leistung_Faktor"] = Utils::hexdecs(substr($rc,386,4));
  $aktuelleDaten["AC_Leistung_Prozent"] = SolarEdge::solaredge_faktor(Utils::hexdecs(substr($rc,382,4)),$aktuelleDaten["AC_WR_Leistung_Faktor"]);
  $aktuelleDaten["AC_Wh_Gesamt_Faktor"] = Utils::hexdecs(substr($rc,398,4));
  $aktuelleDaten["AC_Wh_Gesamt"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,390,8)),$aktuelleDaten["AC_Wh_Gesamt_Faktor"]);
  $aktuelleDaten["DC_Strom_Faktor"] = Utils::hexdecs(substr($rc,406,4));
  $aktuelleDaten["DC_Strom"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,402,4)),$aktuelleDaten["DC_Strom_Faktor"]);
  $aktuelleDaten["DC_Spannung_Faktor"] = Utils::hexdecs(substr($rc,414,4));
  $aktuelleDaten["DC_Spannung"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,410,4)),$aktuelleDaten["DC_Spannung_Faktor"]);
  $aktuelleDaten["DC_Leistung_Faktor"] = Utils::hexdecs(substr($rc,422,4));
  $aktuelleDaten["DC_Leistung"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,418,4)),$aktuelleDaten["DC_Leistung_Faktor"]);
  $aktuelleDaten["Temperatur_Faktor"] = Utils::hexdecs(substr($rc,442,4));
  $aktuelleDaten["Temperatur"] = SolarEdge::solaredge_faktor(hexdec(substr($rc,430,4)),$aktuelleDaten["Temperatur_Faktor"]);
  $aktuelleDaten["Status"] = Utils::hexdecs(substr($rc,446,4));
  $aktuelleDaten["Status_Vendor"] = substr($rc,450,4);



  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/


  Log::write(print_r($aktuelleDaten,1),"   ",8);

  if ($aktuelleDaten["AC_Spannung_R"] == 0) {
    $aktuelleDaten["AC_Spannung_R"] = $aktuelleDaten["AC_Spannung_R-S"];
  }

  $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["DC_Leistung"];

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
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/solaredge_ohne_math.php")) {
    include $basedir.'/custom/solaredge_ohne_math.php';  // Falls etwas neu berechnet werden muss.
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
  $aktuelleDaten["Demodaten"] = false;





  Log::write(print_r($aktuelleDaten,1),"*- ",9);



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
      Log::write("Daten ausgelesen und gespeichert.","   ",9);
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
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["DC_Spannung"];
    Log::write("Daten werden zur HomeMatic übertragen...","   ",8);
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write("Nachrichten versenden...","   ",8);
    require($basedir."/services/meldungen_senden.php");
  }

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
if (file_exists($StatusFile) and isset($aktuelleDaten["AC_Leistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["AC_Leistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  Log::write("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}

fclose($COM1);

Ausgang:


Log::write("-------------   Stop   solaredge_serie.php    --------------- ","|--",6);

return;





?>
