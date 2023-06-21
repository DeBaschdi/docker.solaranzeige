<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Joulie {
  /**************************************************************************
  //  Joulie 16    Joulie 16    Joulie 16    Joulie 16    Joulie 16
  //  BMS Joulie 16 Routinen
  //
  **************************************************************************/
  public static function joulie_auslesen($USB, $Input) {
    $Timestamp = time();
    $Ergebnis = array();
    $Antwort = "";
    $Dauer = 2; // Sekunden
    stream_set_blocking($USB, false);
    if (strtolower($Input) <> "trace") {
      fputs($USB, $Input."\r\n");
      usleep(100000); // 0,1 Sekunde
    }
    do {
      $Antwort .= fgets($USB, 4096); // 4096
      if (trim($Antwort) == "AB>>") {
        $Ergebnis[0] = $Antwort."  ".$Ergebnis[0];
        break;
      }
      elseif (trim($Antwort) == "Switched off FW trace") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "Switched on FW trace") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (substr(trim($Antwort), 0, 36) == "Stop auto balancing before") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "stopped auto balancing") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "auto balancing is already stopped") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "only accessible for service-user") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "unlocked for service!") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "Switched on relay") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "Switched off relay") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "started auto balancing") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (trim($Antwort) == "auto balancing is already running") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (substr(trim($Antwort), 0, 11) == "last error:") {
        $Ergebnis[0] = trim($Antwort);
      }
      elseif (substr(trim($Antwort), 0, 10) == ">>>>>>>>>>") {
        $Ergebnis[99] = trim($Antwort);
      }
      if (substr($Antwort, - 1) == "\n") {
        // echo $Antwort."\n";
        $Teile = explode(";", $Antwort);
        if (count($Teile) == 38 and strtolower($Input) == "trace") {
          Log::write("Trace: ".print_r($Teile, 1), "   ", 9);
          $Ergebnis = $Teile;
          break;
        }
        elseif (count($Teile) == 42 and strtolower($Input) == "trace") {
          // Firmware > 1.16
          Log::write("Trace: ".print_r($Teile, 1), "   ", 9);
          $Ergebnis = $Teile;
          break;
        }
        // echo $Antwort."\n";
        if (isset($Ergebnis[1])) {
          $Ergebnis[1] .= $Antwort;
        }
        else {
          $Ergebnis[1] = $Antwort;
        }
        $Antwort = "";
      }
      if ($Antwort <> "") {
        // echo $Antwort;
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($USB, true);
    return $Ergebnis;
  }

  public static function joulie_zahl($Wert) {
    switch ($Wert) {
      case "I":
        $Zahl = "0";
        break;
      case "C":
        $Zahl = "1";
        break;
      case "D":
        $Zahl = "2";
        break;
    }
    return $Zahl;
  }

  public static function joulie_outb($Wert) {
    $Ausgabe = array();
    $rc = explode("]", $Wert);
    $k = 1;
    for ($i = 1; $i <= 16; $i++) {
      $ret = explode(" ", $rc[$i]);
      $Ausgabe[$k] = substr($ret[1], 0, 4);
      $k++;
      $Ausgabe[$k] = substr($ret[2], 1, 1);
      $k++;
    }
    $rc = explode("\n", $Wert);
    for ($i = 1; $i <= count($rc); $i++) {
      if (substr($rc[$i], 0, 7) == "Current") {
        $Ausgabe[33] = substr(trim($rc[$i]), 10, - 9);
      }
      if (substr($rc[$i], 0, 7) == "Voltage") {
        $Ausgabe[36] = substr(trim($rc[$i]), 10, - 2);
      }
    }
    $Ausgabe[34] = 0;
    $Ausgabe[35] = 0;
    return $Ausgabe;
  }
}
?>