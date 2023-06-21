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
//  Es dient dem Auslesen des RCT Wechselrichtzers über die
//  LAN Schnittstelle.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
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
Log::write("-------------   Start  rct_wr.php    -------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
Log::write( "RCT: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 ); 


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

$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",7);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  function rct_auslesen($COM1,$Register,$Laenge,$Typ,$UnitID,$Befehl="03")
  //
  // $aktuelleDaten["AC_Spannung_R"]
  // $aktuelleDaten["AC_Spannung_S"]
  // $aktuelleDaten["AC_Spannung_T"]
  // $aktuelleDaten["Anz_PV_Strings"]
  // $aktuelleDaten["PV1_Spannung"]
  // $aktuelleDaten["PV2_Spannung"]
  // $aktuelleDaten["PV1_Strom"]
  // $aktuelleDaten["PV2_Strom"]
  // $aktuelleDaten["PV_Leistung"]
  // $aktuelleDaten["PV1_Leistung"]
  // $aktuelleDaten["PV2_Leistung"]
  // $aktuelleDaten["AC_Leistung"]
  // $aktuelleDaten["AC_Frequenz"]
  // $aktuelleDaten["WattstundenGesamtHeute"]
  // $aktuelleDaten["Hausverbrauch"]
  // $aktuelleDaten["AC_Leistung_R"]
  // $aktuelleDaten["AC_Leistung_S"]
  // $aktuelleDaten["AC_Leistung_T"]
  // $aktuelleDaten["Bezug"]
  // $aktuelleDaten["Einspeisung"]
  //
  //
  // Ab läuft stabil ab Firmware Version 2.3.5234
  //
  ****************************************************************************/

    $Command = "01";
    $Laenge = "04";
    $Form = "float";

    $ID = "CF053085";
    // rct_auslesen($COM,$Command,$Laenge,$ID,$Form = "float") {
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Spannung_R"] = $Ergebnis["Wert"];
    }
    else {
      Log::write("Lesefehler...","+  ",7);
      goto Ausgang;
    }

    $ID = "54B4684E";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Spannung_S"] = $Ergebnis["Wert"];
    }
    else {
      Log::write("Lesefehler....","+  ",7);
      goto Ausgang;
    }

    $ID = "2545E22D2D";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Spannung_T"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "B55BA2CE";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV1_Spannung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "B0041187";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV2_Spannung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "DB11855B";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV1_Leistung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "0CB5D21B";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV2_Leistung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "BD55905F";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["WattstundenGesamtHeute"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }


    $ID = "1AC87AA0";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Hausverbrauch"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }


    $ID = "27BE51D9";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Leistung_R"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "F5584F90";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Leistung_S"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "B221BCFA";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Leistung_T"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $aktuelleDaten["AC_Leistung"] = round($aktuelleDaten["AC_Leistung_R"] + $aktuelleDaten["AC_Leistung_S"] + $aktuelleDaten["AC_Leistung_T"]);


    $ID = "A7FA5C5D";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Batterie_Spannung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "959930BF";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["SOC"] = round(($Ergebnis["Wert"]*100),1);
    }
    else {
      goto Ausgang;
    }

    $ID = "8B9FF008";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["SOC_Zielwert"] = round(($Ergebnis["Wert"]*100),1);
    }
    else {
      goto Ausgang;
    }

    Log::write("SOC Zielwert: ".$aktuelleDaten["SOC_Zielwert"],"   ",10);

    $ID = "BF9B6042";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"String");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Softwareversion"] = ($Ergebnis["Wert"]);
    }
    else {
      goto Ausgang;
    }

    Log::write("Softwareversion: ".$aktuelleDaten["Softwareversion"],"   ",2);


    $ID = "400F015B";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Batterie_Leistung"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    if ($aktuelleDaten["Batterie_Leistung"] > 0 ) {
      $aktuelleDaten["Batterie_Entladung"] = $aktuelleDaten["Batterie_Leistung"]; 
      $aktuelleDaten["Batterie_Ladung"] = 0; 
    }
    else {
      $aktuelleDaten["Batterie_Ladung"] = abs($aktuelleDaten["Batterie_Leistung"]); 
      $aktuelleDaten["Batterie_Entladung"] = 0; 
    }


    $ID = "902AFAFB";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Temperatur"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "5F33284E";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"U32");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["DeviceStatus"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "37F9D5CA";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"Hex");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["FehlerCode"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "B1EF67CE";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"float");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["WattstundenGesamt"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "7924ABD9";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"String");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Firmware"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }


    $ID = "1C4A665F";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"float");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["AC_Frequenz"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }



    $ID = "FBD94C1F";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"float");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["Batterie_Ah"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }


    $ID = "91617C58";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["GridPower"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "E96F1844";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["ExternalPower"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "C0DF2978";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,"U32");
    if (is_array($Ergebnis)) {
      $aktuelleDaten["BatZyklen"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "FC724A9E";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV1_EnergieGesamt"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }

    $ID = "68EEFD3D";
    $Ergebnis = RCT::rct_auslesen($COM1,$Command,$Laenge,$ID,$Form);
    if (is_array($Ergebnis)) {
      $aktuelleDaten["PV2_EnergieGesamt"] = $Ergebnis["Wert"];
    }
    else {
      goto Ausgang;
    }


    if ($aktuelleDaten["GridPower"] > 0) {
      $aktuelleDaten["Bezug"] = $aktuelleDaten["GridPower"];
      $aktuelleDaten["Einspeisung"] = 0;
    }
    else {
      $aktuelleDaten["Einspeisung"] = abs($aktuelleDaten["GridPower"]);
      $aktuelleDaten["Bezug"] = 0;
    }



    $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["PV1_Leistung"] + $aktuelleDaten["PV2_Leistung"];




    $aktuelleDaten["Anz_PV_Strings"] = 2; // Dummy, diesen Wert liefert das Gerät nicht
    $aktuelleDaten["PV1_Strom"] = 0;      // Dummy, diesen Wert liefert das Gerät nicht
    $aktuelleDaten["PV2_Strom"] = 0;      // Dummy, diesen Wert liefert das Gerät nicht
    $aktuelleDaten["Anz_MPP_Trackers"] = $aktuelleDaten["Anz_PV_Strings"];
    $aktuelleDaten["ModellID"] = substr($aktuelleDaten["Firmware"],5,2);
    $aktuelleDaten["Effizienz"] = 0;      // Dummy, diesen Wert liefert das Gerät nicht
    $aktuelleDaten["Batterie_Status"] = 0;// Dummy, diesen Wert liefert das Gerät nicht
    $aktuelleDaten["Produkt"] = "RCT Wechselrichter";
    Log::write("Seriennummer: ".$aktuelleDaten["Firmware"],"   ",2);


    Log::write(var_export($aktuelleDaten,1),"   ",8);

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/






  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/rct_wr_math.php")) {
    include $basedir.'/custom/rct_wr_math.php';  // Falls etwas neu berechnet werden muss.
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
  if ($Wiederholungen <= $i or $i >= 1) {  
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    Log::write("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());




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



Ausgang:

fclose($COM1);

Log::write("-------------   Stop   rct_wr.php    -------------------------- ","|--",6);

return;






?>
