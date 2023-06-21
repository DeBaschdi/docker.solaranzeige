<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class SDM {
  /**************************************************************************
  //  SDM630
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  **************************************************************************/
  public static function sdm_auslesen($USB, $Input, $Studer = false) {
    stream_set_blocking($USB, false);
    $address = "";
    $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]);
    $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
    // Befehl in HEX!
    // echo "** ".$Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]."\n";
    for ($k = 1; $k < 3; $k++) {
      $buffer = fgets($USB, 500);
      $buffer = "";
      fputs($USB, $BefehlBin);
      usleep(50000); // 0,05 Sekunden warten
      for ($i = 1; $i < 30; $i++) {
        $buffer .= fgets($USB, 100);
        usleep(10000);
        $buffer .= fgets($USB, 100);
        // echo $i." ".bin2hex($buffer)."\n";
        if (substr(bin2hex($buffer), 0, 2) == "00" and substr(bin2hex($buffer), - 2) == "00") {
          // Mit Start und Stop Bit '00'

          /*************************************/
          if (substr(bin2hex($buffer), 0, 6) == "000000") {
            // Das ist ein Fehler! "00" ist am Anfang zu viel.
            $hex = bin2hex($buffer);
            $hex = substr($hex, 4);
          }
          elseif (substr(bin2hex($buffer), 0, 4) == "0000") {
            // Das ist ein Fehler! "00" ist am Anfang zu viel.
            $hex = bin2hex($buffer);
            $hex = substr($hex, 2);
          }
          else {
            $hex = bin2hex($buffer);
          }
          $start = substr($hex, 0, 2);
          $address = substr($hex, 2, 2);
          $functioncode = substr($hex, 4, 2);
          $lenght = substr($hex, 6, 2);
          $data = substr($hex, 8, $lenght * 2);
          $stop = substr($hex, - 2);
          break 2;
        }
        elseif (substr(bin2hex($buffer), 0, 2) == $Input["DeviceID"] and substr(bin2hex($buffer), 2, 2) == $Input["BefehlFunctionCode"]) {
          // ohne Start und Stop Bit

          /*************************************/
          $hex = bin2hex($buffer);
          $address = substr($hex, 0, 2);
          $functioncode = substr($hex, 2, 2);
          $lenght = hexdec(substr($hex, 4, 2));
          $data = substr($hex, 6, $lenght * 2);
          break 2;
        }
        elseif (strlen($buffer) > 0) {
          //  Fehlerausgang

          /*************************************/
          break;
        }
      }
    }

    /*************************
    echo  $hex."\n";
    echo  $start."\n";
    echo  $address."\n";
    echo  $functioncode."\n";
    echo  $lenght."\n";
    echo  $data."\n";
    echo  $stop."\n";
    ************************/
    stream_set_blocking($USB, true);
    if ($address == $Input["DeviceID"] and $functioncode == $Input["BefehlFunctionCode"]) {
      if ($Studer) {
        return $data;
      }
      else {
        return round(Utils::hex2float($data), 3);
      }
    }
    Log::write("Fehler! ".bin2hex($buffer), "   ", 5);
    return false;
  }
}
?>