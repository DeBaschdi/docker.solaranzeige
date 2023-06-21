<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class SolarXXL {

  /******************************************************************************
  //  Umwandlung der Binärdaten in lesbare Form
  ******************************************************************************/
  public static function solarxxl_daten($daten, $Times = false, $Negativ = false) {
    $DeviceID = substr($daten, 0, 2);
    $BefehlFunctionCode = substr($daten, 2, 2);
    $RegisterCount = substr($daten, 4, 2);
    if ($Negativ) {
      if ($RegisterCount == "01") {
        $Ergebnis = Utils::hexdecs(substr($daten, 6, 2));
      }
      elseif ($RegisterCount == "02") {
        $Ergebnis = Utils::hexdecs(substr($daten, 6, 4));
      }
      else {
        return false;
      }
    }
    else {
      if ($RegisterCount == "01") {
        $Ergebnis = hexdec(substr($daten, 6, 2));
      }
      elseif ($RegisterCount == "02") {
        $Ergebnis = hexdec(substr($daten, 6, 4));
      }
      else {
        return false;
      }
    }
    if ($Times == true) {
      $Ergebnis = $Ergebnis / 100;
    }
    return $Ergebnis;
  }
}
?>