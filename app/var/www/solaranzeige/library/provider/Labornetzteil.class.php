<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Labornetzteil {
  /******************************************************************
  //
  //  Auslesen des Labornetzteil   JT-DPM8600
  //
  ******************************************************************/
  public static function ln_lesen($USB, $WR_Adresse, $Input = "") {
    $Adresse = str_pad(substr($WR_Adresse, - 2), 2, 0, STR_PAD_LEFT);
    stream_set_blocking($USB, false); // Es wird auf keine Daten gewartet.
    fwrite($USB, ":".$Adresse.$Input.",\r\n");
    for ($k = 0; $k < 30; $k++) {
      $Antwort = trim(fread($USB, 1024));
      if (substr($Antwort, 0, 1) == ":" and substr($Antwort, - 1) == ".") {
        $Teil = explode("=", $Antwort);
        $Ergebnis = substr($Teil[1], 0, - 1);
        break;
      }
      usleep(200000);
    }
    stream_set_blocking($USB, true); // Zurück setzen.
    return $Ergebnis;
  }
}
?>