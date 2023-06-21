<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class US2000 {
  public static function us2000_daten_entschluesseln($Daten) {
    $Ergebnis = array();
    $Ergebnis["Ver"] = substr($Daten, 0, 2);
    $Ergebnis["ADR"] = substr($Daten, 2, 2);
    $Ergebnis["CID1"] = substr($Daten, 4, 2);
    $Ergebnis["CID2"] = substr($Daten, 6, 2);
    $Ergebnis["LENHEX"] = substr($Daten, 8, 4);
    $Ergebnis["LEN"] = hexdec(substr($Daten, 9, 3));
    if ($Ergebnis["LEN"] > 0) {
      $Ergebnis["INFO"] = substr($Daten, 12, $Ergebnis["LEN"]);
    }
    if ($Ergebnis["CID2"] <> 0) {
      $Ergebnis["Fehler"] = true;
    }
    else {
      $Ergebnis["Fehler"] = false;
    }
    return $Ergebnis;
  }

  /****************************************************************************
  //  Hier wird das Polytech US2000 Protokoll ausgelesen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //
  ****************************************************************************/
  public static function us2000_auslesen($USB, $Input) {
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    $Dauer = 8; // Sekunden
    stream_set_blocking($USB, false);
    if (strtolower($Input) <> "trace") {
      fputs($USB, $Input);
      usleep(100000); // 0,1 Sekunde
    }
    do {
      $Antwort .= fgets($USB, 1024); // 4096
      $Antwort = trim($Antwort, "\0\n"); //  Eingefügt 30.12.2021
      if (substr($Antwort, 0, 1) == "~" and substr($Antwort, - 1) == "\r") {
        // echo trim($Antwort);
        stream_set_blocking($USB, true);
        return trim(substr($Antwort, 1));
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($USB, true);
    return false;
  }
}
?>