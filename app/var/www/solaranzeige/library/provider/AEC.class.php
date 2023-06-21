<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class AEC {
  /******************************************************************
  //  AEconversion    AEconversion    AEconversion    AEconversion
  //
  //  AEconversion Inverter auslesen
  //
  ******************************************************************/
  public static function aec_inverter_lesen($Device, $Befehl = '') {
    $CR = "\r"; // CR
    $Antwort = "";
    $OK = false;
    stream_set_blocking($Device, false); // Es wird auf keine Daten gewartet.
    if (empty($Befehl)) {
      $rc = fwrite($Device, $CR);
      usleep(500000); // 1/2 Sekunde
      $Antwort = fgets($Device, 8192);
      $Dauer = 0; // nur 1 Durchlauf
    }
    else {
      $Dauer = 3; // 4 Sekunden
      $rc = fwrite($Device, $Befehl.$CR);
      sleep(1); //   1 Sekunde
    }
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    do {
      $Antwort = fgets($Device, 8192);
      if (trim($Antwort) == "") {
        // echo "Nichts empfangen .. \n";
        usleep(5000);
        $Antwort = "";
      }
      else {
        $Ergebnis .= $Antwort;
        // echo "Länge: ".strlen($Ergebnis)."\n".$Antwort."\n";
        if (substr($Ergebnis, 1, 3) == substr($Befehl, 1, 3)) {
          $Dauer = 0; // Ausgang vorbereiten..
          // echo $Ergebnis."\n";
          $OK = true;
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($Device, true);
    if ($OK == true) {
      return $Ergebnis;
    }
    return false;
  }
}
?>