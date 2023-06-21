<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Infini {

  /******************************************************************
  //  InfiniSolar V Serie     InfiniSolar V Serie      InfiniSolar V
  //  Auslesen des InfiniSolar V Serie Wechselrichter
  //
  ******************************************************************/
  public static function infini_lesen($USB, $Input, $Senden = false) {
    stream_set_blocking($USB, false);
    $Antwort = "";
    // Der CRC Wert wird errechnet  (CRC/XMODEM)
    $CRC_raw = str_pad(dechex($this->CRC16Normal($Input)), 4, "0", STR_PAD_LEFT);
    if (substr($CRC_raw, 0, 2) == "0a") {
      $CRC_raw = "0b".substr($CRC_raw, 2, 2);
    }
    elseif (substr($CRC_raw, 0, 2) == "0d") {
      $CRC_raw = "0e".substr($CRC_raw, 2, 2);
    }
    elseif (substr($CRC_raw, 0, 2) == "00") {
      $CRC_raw = "01".substr($CRC_raw, 2, 2);
    }
    if (substr($CRC_raw, 2, 2) == "0a") {
      $CRC_raw = substr($CRC_raw, 0, 2)."0b";
    }
    elseif (substr($CRC_raw, 2, 2) == "0d") {
      $CRC_raw = substr($CRC_raw, 0, 2)."0e";
    }
    elseif (substr($CRC_raw, 2, 2) == "00") {
      $CRC_raw = substr($CRC_raw, 0, 2)."01";
    }
    $Input2 = chr(hexdec(substr($CRC_raw, 0, 2))).chr(hexdec(substr($CRC_raw, 2, 2))).chr(hexdec("0D"));
    $this->log_schreiben("Input2: ".bin2hex($Input2)." CRC: ".$CRC_raw, "   ", 10);
    fgets($USB, 50); // 50
    if (strlen($Input) > 15) {
      $this->log_schreiben("Befehl ist länder als 16 Zeichen:".strlen($Input), "   ", 10);
      fputs($USB, substr($Input, 0, 4));
      usleep(10000); // 10000
      fputs($USB, substr($Input, 4, 4));
      usleep(10000); // 10000
      fputs($USB, substr($Input, 8, 4));
      usleep(10000); // 10000
      fputs($USB, substr($Input, 12, 4));
      usleep(10000); // 10000
      fputs($USB, substr($Input, 16));
      usleep(10000); // 10000
    }
    elseif (strlen($Input) > 10) {
      $this->log_schreiben("Befehl ist länder als 10 Zeichen:".strlen($Input), "   ", 10);
      fputs($USB, substr($Input, 0, 4));
      usleep(5000); // 5000
      fputs($USB, substr($Input, 4, 4));
      usleep(5000); // 5000
      fputs($USB, substr($Input, 8));
      usleep(5000); // 5000
    }
    elseif (strlen($Input) > 5) {
      $this->log_schreiben("Befehl ist länder als 5 Zeichen:".strlen($Input), "   ", 10);
      fputs($USB, substr($Input, 0, 4));
      usleep(10000); // 10000
      fputs($USB, substr($Input, 4));
      usleep(10000); // 10000
    }
    else {
      fputs($USB, $Input);
      usleep(10000); // 10000
    }
    // Der CRC Wert und CR wird gesendet
    fputs($USB, $Input2);
    usleep(10000); //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...
    $this->log_schreiben("Befehl: ".$Input.$CRC_raw."0D", "   ", 8);
    for ($k = 1; $k < 200; $k++) {
      $rc = fgets($USB, 4096); // 4096  orgi 1024
      usleep(30000); // 30000
      $Antwort .= $rc;
      // echo bin2hex($rc)."\n";
      $this->log_schreiben("Antwort raw: ".$Antwort, "   ", 9);
      if (substr($Antwort, 0, 2) == "^D" and strlen($Antwort) > 5) {
        if (strlen($Antwort) > substr($Antwort, 2, 3)) {
          $Laenge = (substr($Antwort, 2, 3) - 3);
          stream_set_blocking($USB, true);
          return substr($Antwort, 5, $Laenge);
        }
      }
      if (substr($Antwort, 0, 2) == "^0" and $Senden == false) {
        break;
      }
      if (substr($Antwort, 0, 2) == "^0" and $Senden == true) {
        return "NAK";
      }
      if (substr($Antwort, 0, 2) == "^1" and $Senden == true) {
        return "ACK";
      }
    }
    stream_set_blocking($USB, true);
    // echo bin2hex($Antwort)."\n";
    return false;
  }

  /**************************************************************
  //   InfiniSolar Daten entschlüsseln
  //
  **************************************************************/
  public static function infini_entschluesseln($Befehl, $Daten) {
    $Ergebnis = array();
    switch ($Befehl) {
      case "GS":
        $Teile = explode(",", $Daten);
        $Ergebnis["Netzspannung"] = ($Teile[0] / 10);
        $Ergebnis["Netzfrequenz"] = ($Teile[1] / 10);
        $Ergebnis["AC_Ausgangsspannung"] = ($Teile[2] / 10);
        $Ergebnis["AC_Ausgangsfrequenz"] = ($Teile[3] / 10);
        $Ergebnis["AC_Scheinleistung"] = $Teile[4];
        $Ergebnis["AC_Wirkleistung"] = $Teile[5];
        $Ergebnis["Ausgangslast"] = $Teile[6];
        $Ergebnis["Batteriespannung"] = ($Teile[7] / 10);
        $Ergebnis["Batterieentladestrom"] = ($Teile[10]); //  geteilt durch 10 entfernt 26.11.2020
        $Ergebnis["Batterieladestrom"] = ($Teile[11]); //  geteilt durch 10 entfernt 26.11.2020
        $Ergebnis["Batteriekapazitaet"] = ($Teile[12]); //  geteilt durch 10 entfernt 26.11.2020
        $Ergebnis["Temperatur"] = $Teile[13];
        $Ergebnis["MPPT1_Temperatur"] = $Teile[14];
        $Ergebnis["MPPT2_Temperatur"] = $Teile[15];
        $Ergebnis["Solarleistung1"] = $Teile[16];
        $Ergebnis["Solarleistung2"] = $Teile[17];
        $Ergebnis["Solarspannung1"] = ($Teile[18] / 10);
        $Ergebnis["Solarspannung2"] = ($Teile[19] / 10);
        $Ergebnis["Ladestatus1"] = $Teile[21];
        $Ergebnis["Ladestatus2"] = $Teile[22];
        $Ergebnis["Batteriestromrichtung"] = $Teile[23];
        $Ergebnis["WR_Stromrichtung"] = $Teile[24];
        $Ergebnis["Netzstromrichtung"] = $Teile[25];
        break;
      case "MOD":
        $Ergebnis["Modus"] = $Daten;
        break;
      case "PI":
        $Ergebnis["Firmware"] = $Daten;
        break;
      case "T":
        // Hier kann Datum und Uhrzeit entschlüsselt werden
        $Ergebnis["Stunden"] = substr($Daten, 8, 2);
        $Ergebnis["Minuten"] = substr($Daten, 10, 2);
        $Ergebnis["Sekunden"] = substr($Daten, 12, 2);
        break;
      case "FWS":
        $Warnungen = array();
        $k = 1;
        $Ergebnis["Warnungen"] = 0;
        $Teile = explode(",", $Daten);
        $Ergebnis["Fehlercode"] = $Teile[0];
        for ($i = 1; $i < 16; $i++) {
          if ($Teile[$i] == 1) {
            // Es gibt eine oder mehrere Warnungen
            $Warnungen[$k] = $i;
            $k++;
          }
        }
        $k = ($k - 1);
        if ($k == 1) {
          $Ergebnis["Warnungen"] = $Warnungen[$k];
        }
        elseif ($k > 1) {
          //  Es sind mehrere Warnungen vorhanden
          $Ergebnis["Warnungen"] = $Warnungen[rand(1, $k)];
        }
        break;
    }
    return $Ergebnis;
  }
}
?>