<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class ModBus {
  
  /**************************************************************************
   //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
   //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
   //  Auslesen des Kostal Wechselrichter
   //
   **************************************************************************/
  private static function kostal_com_lesen($COM, $Input = "") {
    $Antwort = "";
    $TransactionIdentifier = "0001";
    $ProtocilIdentifier = "0000";
    $MessageLenght = str_pad(dechex(strlen($Input) / 2), 4, "0", STR_PAD_LEFT);
    $sendenachricht = hex2bin($TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$Input);
    $rc = fwrite($COM, $sendenachricht);
    stream_set_timeout($COM, 50); // Timeout 50 Sekunden
    $i = 0;
    do {
      // Manche Geräte senden die Daten in Häppchen.
      $Antwort .= bin2hex(fread($COM, 1000)); // 1000 Bytes lesen, Timeout 50 Sekunden
      usleep(1000);
      $Laenge = ((hexdec(substr($Antwort, 8, 4)) * 2) + 12); // 12 = Header Länge
      $i++;
      $this->log_schreiben("Antwort = ".$Antwort, "o  ", 8);
      // **************  Eingebaut 14.08.2021 ******************************/
      if ($Antwort == "") {
        $i = 200;
        $this->log_schreiben("Modbus: Timeout", "o  ", 6);
      }
      // *******************************************************************/
    } while (strlen($Antwort) < $Laenge and $i < 200);
    // **************  Eingebaut 14.08.2021 ********************************/
    if ($i >= 200) {
      Log::write("Modbus: Keine Häppchen empfangen", "o  ", 6);
      $Antwort = "";
    }
    // *********************************************************************/
    return $Antwort;
  }
  
  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Funktionen für Modbus Geräte      MODBUS TCP
  //
  ****************************************************************************/
  public static function modbus_register_lesen($COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl = "03") {
    if (strlen($Register) == 5) {
      $Register = dechex($Register);
    }
    else {
      $Register = str_pad(dechex($Register), 4, "0", STR_PAD_LEFT);
    }
    $rc = ModBus::kostal_com_lesen($COM1, $GeraeteAdresse.$Befehl.$Register.$Laenge);
    $Daten["Register"] = $Register;
    $Daten["RawDaten"] = $rc;
    $Daten["Transaction"] = substr($rc, 0, 4);
    $Daten["Protocol"] = substr($rc, 4, 4);
    $Daten["Laenge"] = substr($rc, 8, 4);
    $Daten["Adresse"] = substr($rc, 12, 2);
    $Daten["Befehl"] = substr($rc, 14, 2);
    $Daten["DatenLaenge"] = hexdec(substr($rc, 16, 2));
    $Daten["Wert"] = 0;
    $Daten["KeineSonne"] = false;
    if (substr($rc, 18, 8) == "ffffffff" and $Daten["DatenLaenge"] == 4) {
      $Daten["Wert"] = "0.0";
      $Daten["KeineSonne"] = true;
    }
    elseif (substr($rc, 18, 8) == "80000000") {
      $Daten["Wert"] = "0.00";
      $Daten["KeineSonne"] = true;
    }
    elseif ($Daten["Befehl"] == "83") {
      // Die Speicherstelle ist nicht bekannt. Es ist ein Fehler aufgetreten.
    }
    elseif ($Daten["Transaction"] == "0001" and $Daten["Adresse"] == strtolower($GeraeteAdresse) and $Daten["Befehl"] == $Befehl) {
      //  Prüfen ob der Wert richtig sein kann.
      switch ($Typ) {
        case "String":
          // $Daten["Wert"] = Utils::Hex2String( substr( $rc, 18, (strpos($rc,'00',18)-18)));  // noch nicht geprüft.
          $Daten["Wert"] = Utils::Hex2String(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "U16-1":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "S16-1":
          $Daten["Wert"] = Utils::hexdecs(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "U16":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"]));
          break;
        case "S16":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"]));
          if ($Daten["Wert"] > 32767) {
            $Daten["Wert"] = $Daten["Wert"] - 65536;
          }
          break;
        case "S32":
          $Daten["Wert"] = Utils::hexdecs(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "S32-1":
          $Daten["Wert"] = Utils::hexdecs(substr($rc, 22, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"]));
          break;
        case "Float":
          $Daten["Wert"] = round(Utils::hex2float(substr($rc, 22, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"])), 2);
          break;
        case "U32":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "U32-1":
          $Daten["Wert"] = hexdec(substr($rc, 22, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"]));
          break;
        case "U64":
          $Daten["Wert"] = hexdec(substr($rc, 24, 2).substr($rc, 26, 2).substr($rc, 18, 2).substr($rc, 22, 2));
          break;
        case "U64-1":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "S64":
          $Daten["Wert"] = Utils::hexdecs(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "String2":
          $Daten["Wert"] = Utils::Hex2String(trim(substr($rc, 18, $Daten["DatenLaenge"] * 2)));
          break;
        case "String3":
          $Daten["Wert"] = Utils::Hex2String(str_replace("00", "", trim(substr($rc, 18, $Daten["DatenLaenge"] * 2))));
          break;
        case "Hex":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "SF16":
          $SF = hexdec(substr($rc, 18, $Daten["DatenLaenge"]));
          switch ($SF) {
            case 3:
              $Daten["Wert"] = 1000;
              break;
            case 2:
              $Daten["Wert"] = 100;
              break;
            case 1:
              $Daten["Wert"] = 10;
              break;
            case 0:
              $Daten["Wert"] = 1;
              break;
            case 65535:
              $Daten["Wert"] = 0.1;
              break;
            case 65534:
              $Daten["Wert"] = 0.01;
              break;
            case 65533:
              $Daten["Wert"] = 0.001;
              break;
          }
          break;
        case "Dec16Bit":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "Dec32Bit":
          $Daten["Wert"] = hexdec(substr($rc, 22, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"]));
          break;
        case "HexString":
          $Daten["Wert"] = strtoupper(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "F32":
          $Daten["Wert"] = Utils::hex2float32(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "F64":
          $Daten["Wert"] = Utils::hex2float64(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        default:
          $Daten["Wert"] = substr($rc, 18, $Daten["DatenLaenge"] * 2);
          break;
      }
    }
    else {
      return false;
    }
    return $Daten;
  }

  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Allgemeine Funktionen für Modbus Geräte
  //
  //
  ****************************************************************************/
  public static function modbus_tcp_lesen($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase = 600000) {
    $Daten = array();
    $DatenOK = false;
    if (strlen($RegisterAdresse) == 5) {
      $Register = dechex($RegisterAdresse);
    }
    else {
      $Register = str_pad(dechex($RegisterAdresse), 4, "0", STR_PAD_LEFT);
    }
    stream_set_blocking($COM1, false);
    $ProtocilIdentifier = "0000";
    $RegisterAnzahl = str_pad($RegisterAnzahl, 4, "0", STR_PAD_LEFT);
    $MessageLenght = str_pad(dechex(strlen($GeraeteAdresse.$FunktionsCode.$Register.$RegisterAnzahl) / 2), 4, "0", STR_PAD_LEFT);
    for ($j = 0; $j < 5; $j++) {
      $TransactionIdentifier = str_pad(rand(1, 199), 4, "0", STR_PAD_LEFT);
      $sendenachricht = hex2bin($TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$GeraeteAdresse.$FunktionsCode.$Register.$RegisterAnzahl);
      $Antwort = "";
      $rc = fwrite($COM1, $sendenachricht);
      usleep($Timebase); // normal 600000
      Log::write("Befehl =>  ".bin2hex($sendenachricht), "   ", 8);
      $k = 0;
      do {
        // Manche Geräte senden die Daten in Häppchen.
        $Antwort .= bin2hex(fread($COM1, 1000)); // 1000 Bytes lesen
        Log::write("Antwort =>  ".$Antwort, "   ", 8);
        $Laenge = ((hexdec(substr($Antwort, 8, 4)) * 2) + 12); // 12 = Header Länge
        $k++;
        if (substr($Antwort, 0, 4) <> $TransactionIdentifier) {
          $Antwort = "";
        }
        if (substr($Antwort, 14, 2) == "83") {
          break;
        }
        if (substr($Antwort, 14, 2) == "84") {
          break;
        }
        if (substr($Antwort, 14, 2) == "82") {
          break;
        }
        if (strlen($Antwort) > $Laenge) {
          $Antwort = substr($Antwort, $Laenge);
          Log::write("Antwort abgeschnistten =>  ".$Antwort, "   ", 8);
          break;
        }
        usleep($Timebase + 200000);
      } while (strlen($Antwort) <> $Laenge and $k < 7);
      if (strlen($Antwort) == 0) {
        Log::write("Keine Antwort, nochmal =>  ", "   ", 8);
        continue;
      }
      $Daten["Register"] = $Register;
      $Daten["RawDaten"] = $Antwort;
      $Daten["Transaction"] = substr($Antwort, 0, 4);
      $Daten["Protocol"] = substr($Antwort, 4, 4);
      $Daten["Laenge"] = substr($Antwort, 8, 4);
      $Daten["Adresse"] = substr($Antwort, 12, 2);
      $Daten["Befehl"] = substr($Antwort, 14, 2);
      $Daten["DatenLaenge"] = hexdec(substr($Antwort, 16, 2));
      $Daten["Wert"] = 0;
      $Daten["KeineSonne"] = false;
      if ($Daten["Befehl"] == 83) {
        //  Die Registeradresse kann nicht ausgelesen werden.
        $DatenOK = true;
        break;
      }
      elseif ($Daten["Befehl"] == 82) {
        //  Die Registeradresse kann nicht ausgelesen werden.
        $DatenOK = true;
        break;
      }
      elseif ($Daten["Befehl"] == 84) {
        //  Die Registeradresse kann nicht ausgelesen werden.
        $DatenOK = true;
        break;
      }
      elseif ($Daten["Befehl"] == $FunktionsCode and strlen($Antwort) == $Laenge) {
        $DatenOK = true;
        break;
      }
    }
    stream_set_blocking($COM1, true);
    if ($DatenOK == true) {
      if ($Daten["Transaction"] == $TransactionIdentifier and $Daten["Adresse"] == strtolower($GeraeteAdresse) and $Daten["Befehl"] == $FunktionsCode) {
        //  Prüfen ob der Wert richtig sein kann.
        switch ($DatenTyp) {
          case "String":
            $Daten["Wert"] = Utils::Hex2String(substr($Antwort, 18, (strpos($Antwort, '00', 18) - 18)));
            break;
          case "U8":
            $Daten["Wert"] = hexdec(substr($Antwort, 18, 2));
            break;
          case "U16":
            $Daten["Wert"] = hexdec(substr($Antwort, 18, $Daten["DatenLaenge"] * 2));
            break;
          case "U32":
            $Daten["Wert"] = hexdec(substr($Antwort, 18, $Daten["DatenLaenge"] * 2));
            break;
          case "U32S":
            $Daten["Wert"] = hexdec(substr($Antwort, 22, $Daten["DatenLaenge"]).substr($Antwort, 18, $Daten["DatenLaenge"]));
            break;
          case "I16":
            $Daten["Wert"] = hexdec(substr($Antwort, 18, $Daten["DatenLaenge"] * 2));
            if ($Daten["Wert"] > 32767) {
              $Daten["Wert"] = $Daten["Wert"] - 65536;
            }
            break;
          case "I32":
            $Daten["Wert"] = Utils::hexdecs(substr($Antwort, 18, $Daten["DatenLaenge"] * 2));
            break;
          case "I32S":
            $Daten["Wert"] = Utils::hexdecs(substr($Antwort, 22, $Daten["DatenLaenge"]).substr($Antwort, 18, $Daten["DatenLaenge"]));
            break;
          case "Float32":
            $Daten["Wert"] = round(Utils::hex2float32(substr($Antwort, 18, $Daten["DatenLaenge"] * 2)), 2);
            break;
          case "Hex":
            $Daten["Wert"] = substr($Antwort, 18, $Daten["DatenLaenge"] * 2);
            break;
          case "ASCII":
            $Daten["Wert"] = chr(hexdec(substr($Antwort, 18, $Daten["DatenLaenge"] * 2)));
            break;
          case "Zeichenkette":
            $Daten["Wert"] = hex2bin(substr($Antwort, 18, $Daten["DatenLaenge"] * 2));
            break;
          default:
            $Daten["Wert"] = 0;
            break;
        }
        return $Daten;
      }
      else {
        return $Daten;
      }
    }
    return false;
  }

  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Allgemeine Funktionen für Modbus Geräte
  //
  // modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", "40c0000040c0000040c00000");
  ****************************************************************************/
  public static function modbus_tcp_schreiben($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $Befehlsdaten, $Timebase = 600000) {
    $Daten = array();
    $DatenOK = false;
    if (strlen($RegisterAdresse) == 5) {
      $Register = dechex($RegisterAdresse);
    }
    else {
      $Register = str_pad(dechex($RegisterAdresse), 4, "0", STR_PAD_LEFT);
    }
    stream_set_blocking($COM1, false);
    $ProtocilIdentifier = "0000";
    $RegisterAnzahl = str_pad($RegisterAnzahl, 4, "0", STR_PAD_LEFT);
    $AnzahlBytes = str_pad(dechex(strlen($Befehlsdaten) / 2), 2, "0", STR_PAD_LEFT);
    $MessageLenght = str_pad(dechex(strlen($GeraeteAdresse.$FunktionsCode.$Register.$RegisterAnzahl.$AnzahlBytes.$Befehlsdaten) / 2), 4, "0", STR_PAD_LEFT);
    for ($j = 0; $j < 5; $j++) {
      $TransactionIdentifier = str_pad(rand(1, 199), 4, "0", STR_PAD_LEFT);
      $sendenachricht = hex2bin($TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$GeraeteAdresse.$FunktionsCode.$Register.$RegisterAnzahl.$AnzahlBytes.$Befehlsdaten);
      $Antwort = "";
      $rc = fwrite($COM1, $sendenachricht);
      usleep($Timebase); // normal 600000
      Log::write("Befehl =>  ".bin2hex($sendenachricht), "   ", 8);
      $k = 0;
      do {
        // Manche Geräte senden die Daten in Häppchen.
        $Antwort .= bin2hex(fread($COM1, 1000)); // 1000 Bytes lesen
        Log::write("Antwort =>  ".$Antwort, "   ", 8);
        $Laenge = ((hexdec(substr($Antwort, 8, 4)) * 2) + 12); // 12 = Header Länge
        $k++;
        if (substr($Antwort, 0, 4) <> $TransactionIdentifier) {
          $Antwort = "";
        }
        if (substr($Antwort, 14, 2) == "90") {
          break;
        }
        usleep($Timebase + 200000);
      } while (strlen($Antwort) <> $Laenge and $k < 7);
      if (strlen($Antwort) == 0) {
        Log::write("Keine Antwort, nochmal =>  ", "   ", 8);
        continue;
      }
      $Daten["Register"] = $Register;
      $Daten["RawDaten"] = $Antwort;
      $Daten["Transaction"] = substr($Antwort, 0, 4);
      $Daten["Protocol"] = substr($Antwort, 4, 4);
      $Daten["Laenge"] = substr($Antwort, 8, 4);
      $Daten["Adresse"] = substr($Antwort, 12, 2);
      $Daten["Befehl"] = substr($Antwort, 14, 2);
      $Daten["Register"] = hexdec(substr($Antwort, 16, 4));
      $Daten["Wert"] = hexdec(substr($Antwort, 20, 4));
      $Daten["KeineSonne"] = false;
      Log::write("Länge1: ".strlen($Antwort)." Länge2: ".$Laenge." \n ".print_r($Daten, 1), "   ", 9);
      if ($Daten["Befehl"] == $FunktionsCode and strlen($Antwort) == $Laenge) {
        $DatenOK = true;
        break;
      }
      elseif ($Daten["Befehl"] == 90) {
        //  Die Registeradresse kann nicht ausgelesen werden.
        $DatenOK = true;
        break;
      }
    }
    stream_set_blocking($COM1, true);
    return $DatenOK;
  }

  /**************************************************************************
  //  MODBUS RTU auslesen
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  //  $Input["DatenTyp"]
  //  $Input[""]
  **************************************************************************/
  public static function modbus_rtu_auslesen($USB, $Input) {
    stream_set_blocking($USB, false);
    if (strlen($Input["RegisterAddress"]) != 4) {
      $Input["RegisterAddress"] = str_pad($Input["RegisterAddress"], 4, "0", STR_PAD_LEFT);
    }
    $address = "";
    $Return ["ok"] = false;
    $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]);
    $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
    // Befehl in HEX!
    // echo "** ".bin2hex($BefehlBin)."\n";
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
        if (substr(bin2hex($buffer), 0, 2) == strtolower($Input["DeviceID"]) and substr(bin2hex($buffer), 2, 2) == $Input["BefehlFunctionCode"]) {
          Log::write($i." ".bin2hex($buffer), "   ", 10);
          // ohne Start und Stop Bit
          $hex = bin2hex($buffer);
          $Return ["address"] = substr($hex, 0, 2);
          $Return ["functioncode"] = substr($hex, 2, 2);
          $Return ["lenght"] = (hexdec(substr($hex, 4, 2)) * 2); // Anzahl Stellen in Dezimal)
          $Return ["rawdata"] = substr($hex, 6, $Return ["lenght"]);
          $Laenge1 = (strlen($hex) - 10);
          $Laenge2 = ($Return ["lenght"]);
          //echo $Laenge1." OK ---\n";
          //echo $Laenge2." OK ---\n";
          //echo (hexdec(substr( $hex, 4, 2 )) * 2)."\n";
          if ($Laenge2 == $Laenge1) {
            $Return ["ok"] = true;
            // Wenn die Länge übereinstimmt
            break 2;
          }
        }
        elseif (strlen($buffer) == 0) {
          usleep(50000);
        }
      }
    }
    stream_set_blocking($USB, true);
    if ($Return ["ok"] == true) {
      Log::write(print_r($Return, 1), "   ", 10);
      switch ($Input["Datentyp"]) {
        case "String":
          $Return ["Wert"] = Utils::Hex2String($Return ["rawdata"]);
          break;
        case "Hex":
          $Return ["Wert"] = $Return ["rawdata"];
          break;
        case "U16":
          if ($Return ["rawdata"] == "ffff") {
            $Return ["Wert"] = 0;
          }
          else {
            $Return ["Wert"] = hexdec($Return ["rawdata"]);
          }
          break;
        case "U32":
          if ($Return ["rawdata"] == "ffffffff") {
            $Return ["Wert"] = 0;
          }
          else {
            $Return ["Wert"] = hexdec($Return ["rawdata"]);
          }
          break;
        case "S16":
          $Return ["Wert"] = Utils::hexdecs($Return ["rawdata"]);
          break;
        case "S32":
          $Return ["Wert"] = Utils::hexdecs($Return ["rawdata"]);
          break;
        default:
          $Return ["Wert"] = $Return ["rawdata"];
          break;
      }
    }
    else {
      return false;
    }

    /************/
    return $Return;
  }
}
?>