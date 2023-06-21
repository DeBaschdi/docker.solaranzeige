<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class SolarEdge {

  /*************************************************************************
  //
  //  SolarEdge    SolarEdge       SolarEdge       SolarEdge       SolarEdge
  //
  *************************************************************************/
  public static function solaredge_lesen($COM, $Input = "") {
    $TransactionIdentifier = "0001";
    $ProtocilIdentifier = "0000";
    $MessageLenght = str_pad(dechex(strlen($Input) / 2), 4, "0", STR_PAD_LEFT);
    $sendenachricht = hex2bin($TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$Input);
    $rc = fwrite($COM, $sendenachricht);
    $Antwort = bin2hex(fread($COM, 1000)); // 1000 Bytes lesen
    return $Antwort;
  }

  /*************************************************************************
  //  SolarEdge    SolarEdge       SolarEdge       SolarEdge       SolarEdge
  *************************************************************************/
  public static function solaredge_faktor($wert, $hex) {
    $Faktor = 0;
    if ($wert == 65535) { //  Hex FFFF
      return "0";
    }
    // ignore non hex characters
    $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
    // converted decimal value:
    $dec = hexdec($hex);
    //   maximum decimal value based on length of hex + 1:
    //   number of bits in hex number is 8 bits for each 2 hex -> max = 2^n
    //   use 'pow(2,n)' since '1 << n' is only for integers and therefore limited to integer size.
    $max = pow(2, 4 * (strlen($hex) + (strlen($hex) % 2)));
    // complement = maximum - converted hex:
    $_dec = $max - $dec;
    // if dec value is larger than its complement we have a negative value (first bit is set)
    if ($dec > $_dec) {
      $zahl = $dec;
    }
    else {
      $zahl = - $dec;
    }
    switch ($zahl) {
      case 0:
        $Faktor = $wert;
        break;
      case - 1:
        $Faktor = $wert / 10;
        break;
      case - 2:
        $Faktor = $wert / 100;
        break;
      case - 3:
        $Faktor = $wert / 1000;
        break;
      case - 4:
        $Faktor = $wert / 10000;
        break;
      case - 5:
        $Faktor = $wert / 100000;
        break;
      case - 6:
        $Faktor = $wert / 1000000;
        break;
      case 1:
        $Faktor = $wert * 10;
        break;
      case 2:
        $Faktor = $wert * 100;
        break;
      case 3:
        $Faktor = $wert * 1000;
        break;
      case 4:
        $Faktor = $wert * 10000;
        break;
      case 5:
        $Faktor = $wert * 100000;
        break;
      case 6:
        $Faktor = $wert * 1000000;
        break;
    }
    return $Faktor;
  }

  /**************************************************************************
  //  Solaredge WND Smart Meter      (2021 geschrieben von Egmont Schreiter )
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"] type int, 3 oder 4, ist egal
  //  $Input["RegisterAddress"] type int
  //  $Input["RegisterCount"] type int
  //  Type: 1 - 16Bit int
  //        2 - 32Bit-float
  //        3 - 32Bit int
  **************************************************************************/
  public static function sem_auslesen($USB, $Input, $returntype = 1) {
    stream_set_blocking($USB, false);
    // Modbusadresse in hexadecimal wandeln 16Bit, 2Byte, 4 hex digits umwandeln, 1 subtrahieren
    // Siehe Handbuch https://r1spn12mh523ib7ly1ip4xkn-wpengine.netdna-ssl.com/wp-content/uploads/2016/10/WNC-Modbus-Manual-V18c.pdf
    // Seite 32:
    // In the Modbus specification, register numbers are documented as “one based”, but transmit-
    // ted as “zero based”. For example, we document that EnergySum appears at address 1001.
    // If you are using any Modbus software or Modbus aware device, you should use “1001” as the
    // register address. However, if you are writing your own low-level Modbus driver or firmware,
    // you will need to subtract one from the register number when creating the Modbus frame (or
    // Operating Instructionspacket), so the actual register number that appears on the RS-485 bus will be “1000” (or in
    // hexadecimal, 0x03E8).
    if (0) {
      $BefehlBin = hex2bin(sprintf('%s%02X%04X%04X', $Input["DeviceID"], 6, 1609 - 1, 0x00)); // einmalig das Register PowerIntScale auf null = auto setzen
      $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
      $buffer = fgets($USB, 500);
    }
    $BefehlBin = hex2bin(sprintf('%s%02X%04X%04X', $Input["DeviceID"], $Input["BefehlFunctionCode"], $Input["RegisterAddress"] - 1, $Input["RegisterCount"]));
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
        if (0 == strncmp("02840232c1", bin2hex($buffer), 10)) {
          error_log("  !!!02840232c1");
          return false;
        }
        if (0 == strncmp("02830230f1", bin2hex($buffer), 10)) {
          error_log("  !!!02830230f1");
          return false;
        }
        if (substr(bin2hex($buffer), 0, 2) == $Input["DeviceID"] and substr(bin2hex($buffer), 2, 2) == $Input["BefehlFunctionCode"]) {
          // ohne Start und Stop Bit
          $hex = bin2hex($buffer);
          $address = substr($hex, 0, 2);
          $functioncode = substr($hex, 2, 2);
          $lenght = substr($hex, 4, 2);
          $data = substr($hex, 6, $lenght * 2);
          break 2;
        }
        elseif (strlen($buffer) > 0) {
          error_log("  strlen(buffer) > 0");
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
      if ($returntype == 1) {
        $wert = hexdec($data);
        if ($wert > 32767) { // Most of the integer registers are 16 bit signed integers
          // that can report positive or negative values from -32,768 to +32,767.
          $wert = $wert - 65535;
        }
        return $wert;
      }
      if ($returntype == 2) {
        return round(Utils::hex2float($data), 3);
      }
      if ($returntype == 3) {
        // The following registers provide the most commonly used measurements in integer units. The
        // energy registers are 32 bit signed integer values, which require two registers, the first register
        // provides the lower 16 bits, and the second register provides the upper 16 bits of the 32 bit value.
        // See Basic Registers (p. 40) below for detailed information.
        $wert = hexdec($data);
        if ($wert > 0x7FFFFFFF) { // Most of the integer registers are 16 bit signed integers
          // that can report positive or negative values from -32,768 to +32,767.
          $wert = $wert - 0xffffffff;
        }
        return $wert;
      }
    }
    return false;
  }
}
?>