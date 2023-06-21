<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class IVT {
  /****************************************************************
  //   Die IVT Regler USB Schnittstelle auslesen
  //
  ****************************************************************/
  public static function ivt_lesen($Device, $Befehl = '') {
    $CR = "\n"; // CR LF -> eventuell löschen
    $Antwort = "";
    stream_set_blocking($Device, false); // Es wird auf keine Daten gewartet.
    if (empty($Befehl)) {
      $Antwort = fgets($Device, 8192);
      $Dauer = 0; // nur 1 Durchlauf
    }
    else {
      $Dauer = 4; // 4 Sekunden
      $rc = fwrite($Device, $Befehl.$CR);
      usleep(500000); // 1/2 Sekunde
    }
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    do {
      $Antwort = fgets($Device, 4096);
      if (trim($Antwort) == "") {
        //    echo "Nichts empfangen .. \n";
        usleep(500000);
        $Antwort = "";
      }
      else {
        $Ergebnis .= $Antwort;
        // echo time()." ".bin2hex($Ergebnis);
        if (substr($Ergebnis, 0, 18) == "solar.pc.senddata." and strlen(bin2hex($Ergebnis)) == 74) {
          $Dauer = 0; // Ausgang vorbereiten..
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($Device, true);
    if (strlen(bin2hex($Ergebnis)) == 74) {
      // Daten
      return $Ergebnis;
    }
    if (strlen(bin2hex($Ergebnis)) == 4) {
      // ReglerTyp
      return $Ergebnis;
    }
    return false;
  }

  /******************************************************************
  //   Die IVT Daten entschlüsseln
  //
  ******************************************************************/
  public static function ivt_entschluesseln($rc) {
    $Ergebnis = array();
    $Ergebnis["Objekt"] = "";
    if (substr($rc, 0, 6) != "solar.") {
      return false;
    }
    $rc = substr($rc, 6);
    if (substr($rc, 0, 3) != "pc.") {
      return false;
    }
    $rc = substr($rc, 3);
    if (substr($rc, 0, 11) == "sendparams.") {
      $rc = bin2hex(substr($rc, 11));
      $Ergebnis["Volt1"] = number_format((hexdec(substr($rc, 0, 2)) + 79) / 10, 2, ",", "");
      $Ergebnis["Volt2"] = number_format((hexdec(substr($rc, 2, 2)) + 79) / 10, 2, ",", "");
      $Ergebnis["Volt3"] = number_format((hexdec(substr($rc, 4, 2)) + 79) / 10, 2, ",", "");
      $Ergebnis["Min1"] = number_format((hexdec(substr($rc, 6, 2)) - 1) * 15, 2, ",", "");
      $Ergebnis["Min2"] = number_format((hexdec(substr($rc, 8, 2)) - 1) * 15, 2, ",", "");
      $Ergebnis["MinVolt"] = number_format((hexdec(substr($rc, 10, 2)) + 79) / 10, 2, ",", "");
      $Ergebnis["MaxVolt"] = number_format((hexdec(substr($rc, 12, 2)) + 79) / 10, 2, ",", "");
      $Ergebnis["Temp"] = (hexdec(substr($rc, 14, 2)) - 1);
      $Ergebnis["Load_on_H"] = (hexdec(substr($rc, 16, 2)) - 1);
      $Ergebnis["Load_on_M"] = (hexdec(substr($rc, 18, 2)) - 1);
      $Ergebnis["Load_off_H"] = (hexdec(substr($rc, 20, 2)) - 1);
      $Ergebnis["Load_off_M"] = (hexdec(substr($rc, 22, 2)) - 1);
      $Ergebnis["Profile"] = (hexdec(substr($rc, 24, 2)) - 1);
      $Ergebnis["Mode"] = substr($rc, 26, 2);
      $Ergebnis["Jahr"] = (hexdec(substr($rc, 28, 2))) + 1999;
      $Ergebnis["Monat"] = (hexdec(substr($rc, 30, 2))) - 1;
      $Ergebnis["Tag"] = (hexdec(substr($rc, 32, 2))) - 1;
      $Ergebnis["Stunden"] = (hexdec(substr($rc, 34, 2))) - 1;
      $Ergebnis["Minuten"] = (hexdec(substr($rc, 36, 2))) - 1;
      $Ergebnis["Sekunden"] = (hexdec(substr($rc, 38, 2))) - 1;
      $Ergebnis["Ah1"] = (hexdec(substr($rc, 40, 2))) - 1;
      $Ergebnis["Ah2"] = (hexdec(substr($rc, 42, 2)) - 1) / 100;
      $Ergebnis["kwh1"] = (hexdec(substr($rc, 44, 2))) - 1;
      $Ergebnis["kwh2"] = (hexdec(substr($rc, 46, 2)) - 1) / 100;
    }
    if (substr($rc, 0, 9) == "senddata.") {
      $rc = bin2hex(substr($rc, 9));
      $Ergebnis["BatVL"] = (hexdec(substr($rc, 0, 2)) - 1);
      $Ergebnis["BatVR"] = (hexdec(substr($rc, 2, 2)) - 1);
      $Ergebnis["SolarVL"] = (hexdec(substr($rc, 4, 2)) - 1);
      $Ergebnis["SolarVR"] = (hexdec(substr($rc, 6, 2)) - 1);
      $Ergebnis["SolarAL"] = (hexdec(substr($rc, 8, 2)) - 1);
      $Ergebnis["SolarAR"] = (hexdec(substr($rc, 10, 2)) - 1);
      $Ergebnis["LoadAL"] = (hexdec(substr($rc, 12, 2)) - 1);
      $Ergebnis["LoadAR"] = (hexdec(substr($rc, 14, 2)) - 1);
      $Ergebnis["ahGesamtL"] = (hexdec(substr($rc, 16, 2)) - 1);
      $Ergebnis["ahGesamtR"] = (hexdec(substr($rc, 18, 2)) - 1);
      $Ergebnis["kwhGesamtL"] = (hexdec(substr($rc, 20, 2)) - 1);
      $Ergebnis["kwhGesamtR"] = (hexdec(substr($rc, 22, 2)) - 1);
      $Ergebnis["TempInt"] = (hexdec(substr($rc, 24, 2)) - 1);
      $Ergebnis["TempVorzeichen"] = 0;
      $Ergebnis["TempExt"] = (hexdec(substr($rc, 26, 2)) - 11);
      $Ergebnis["Stunden"] = (hexdec(substr($rc, 28, 2))) - 1;
      $Ergebnis["Minuten"] = (hexdec(substr($rc, 30, 2))) - 1;
      $Ergebnis["Sekunden"] = (hexdec(substr($rc, 32, 2))) - 1;
      $Ergebnis["Nummer"] = 0;
    }
    if (substr($rc, 0, 9) == "sendtype.") {
      $rc = bin2hex(substr($rc, 9));
      $Ergebnis["Alle"] = $rc;
    }
    return $Ergebnis;
  }
}
?>