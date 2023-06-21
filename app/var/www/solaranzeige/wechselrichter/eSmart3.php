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
//  Es dient dem Auslesen des eSmart3 MPPT Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
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
$aktuelleDaten = array(
    "Firmware" => 0,
    "Produkt" => 0,
    "Geraetestatus" => 0,
    "Solarspannung" => 0, 
    "Solarstrom" => 0,
    "Solarleistung" => 0,
    "Temperatur" => 0
    );
$Start = time();  // Timestamp festhalten
Log::write("---------   Start  eSmart3.php    ---------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
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
Log::write("Hardware Version: ".$Version,"o  ",9);

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




/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/


if ($HF2211) {
  // HF2211 WLAN Gateway wird benutzt
  $USB1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);   // 5 Sekunden Timeout
  if ($USB1 === false) {
    Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
    Log::write("Exit.... ","XX ",3);
    goto Ausgang;
  }
}
else {
  $USB1 = USB::openUSB($USBRegler);
  if (!is_resource($USB1)) {
    Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",7);
    Log::write("Exit.... ","XX ",7);
    goto Ausgang;
  }
}
Log::write("USB Port: ".$USBRegler,"   ",7);


$StartByte = "AA"; 
$DeviceType = "01";   // MPPT Controler
$Adresse = "00";      // 00 = Broadcast Adresse. Nachricht an alle



if (1==1) {

  $i = 1;
  do {
    Log::write("Die Daten werden ausgelesen...","+  ",9);

    /**************************************************************************
    //  Ab hier wird der Regler ausgelesen.
    //
    //  Ergebniswerte:
    //  $aktuelleDaten["Firmware"]
    //  $aktuelleDaten["Produkt"]
    //  $aktuelleDaten["Batteriespannung"]
    //  $aktuelleDaten["Solarstrom"]
    //  $aktuelleDaten["Solarspannung"]
    //  $aktuelleDaten["Verbraucherstrom"]
    //  $aktuelleDaten["KilowattstundenGesamt"]
    //  $aktuelleDaten["Temperatur"]
    //
    **************************************************************************/



    $CommandID = "01";    // Get some Data
    $DataItem = "00";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "00001E";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = eSmart3::eSmart3_CRC($Datenstring);


    $rc = eSmart3::eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      Log::write("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, eSmart3::eSmart3_ergebnis_auswerten($rc));
    Log::write($rc,"   ",8);



    $CommandID = "01";    // Get some Data
    $DataItem = "01";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000014";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = eSmart3::eSmart3_CRC($Datenstring);


    $rc = eSmart3::eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      Log::write("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    Log::write($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, eSmart3::eSmart3_ergebnis_auswerten($rc));




    $CommandID = "01";    // Get some Data
    $DataItem = "02";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000032";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = eSmart3::eSmart3_CRC($Datenstring);


    $rc = eSmart3::eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      Log::write("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    Log::write($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, eSmart3::eSmart3_ergebnis_auswerten($rc));





    $CommandID = "01";    // Get some Data
    $DataItem = "08";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000020";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = eSmart3::eSmart3_CRC($Datenstring);


    $rc = eSmart3::eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      Log::write("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    Log::write($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, eSmart3::eSmart3_ergebnis_auswerten($rc));



    /****************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    ****************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


    if ($i == 1) 
      Log::write(var_export($aktuelleDaten,1),"   ",8);


    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if ( file_exists($basedir."/custom/eSmart3_math.php")) {
      include $basedir.'/custom/eSmart3_math.php';  // Falls etwas neu berechnet werden muss.
    }


    /**************************************************************************
    //
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    **************************************************************************/
    if ($MQTT) {
      Log::write("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
      require($basedir."/services/mqtt_senden.php");
    }


    /**************************************************************************
    //  Zeit und Datum
    **************************************************************************/
    //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
    $aktuelleDaten["Timestamp"] = time();
    $aktuelleDaten["Monat"]     = date("n");
    $aktuelleDaten["Woche"]     = date("W");
    $aktuelleDaten["Wochentag"] = strftime("%A",time());
    $aktuelleDaten["Datum"]     = date("d.m.Y");
    $aktuelleDaten["Uhrzeit"]      = date("H:i:s");



    /**************************************************************************
    //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
    //  falls nicht, sind das hier die default Werte.
    **************************************************************************/
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
      Log::write("OK. Daten gelesen.","   ",8);
      Log::write("Schleife ".$i." Ausgang...","   ",8);
      break;
    }

    $i++;
  } while (($Start + 20) > time());
}


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

Log::write("---------   Stop   eSmart3.php    ---------------------------- ","|--",6);

return;



?>