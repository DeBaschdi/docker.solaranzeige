<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Utils {
  public static function _exec($cmd, & $out = null) {
    $desc = array(1 => array("pipe", "w"), 2 => array("pipe", "w"));
    $proc = proc_open($cmd, $desc, $pipes);
    $ret = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $retVal = proc_close($proc);
    if (func_num_args() == 2)
      $out = array($ret, $err);
    return $retVal;
  }
  
  /******************************************************************
   //
   //  Auslesen des Fronius Symo Wechselrichter
   //  $Benutzer =  UserID:Kennwort
   ******************************************************************/
  public static function read($host, $port, $DataString, $Header = "", $Benutzer = "") {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_URL, "http://".$host."/".$DataString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($Header <> "") {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $Header);
    }
    if ($Benutzer <> "") {
      curl_setopt($ch, CURLOPT_USERPWD, $Benutzer);
    }
    curl_setopt($ch, CURLOPT_PORT, $port);
    //  In $result wird ein XML Dokument zurück gegeben!
    //  Dort steht drin ob die Zentrale den Wert übernommen hat.
    $result = curl_exec($ch);
    $rc_info = curl_getinfo($ch);
    if ($rc_info["http_code"] == 404) {
      Log::write("Datenabfrage falsch! info: ".var_export($rc_info, 1), "   ", 9);
      return false;
    }
    elseif ($rc_info["http_code"] != 200) {
      Log::write("Datenabfrage falsch! info: ".var_export($rc_info, 1), "   ", 5);
      return false;
    }
    else {
      Log::write("http://".$host."/".$DataString, "   ", 10);
      Log::write("Daten zum Gerät gesendet. \n Antwort: ".$result, "   ", 9);
    }
    $Ausgabe = json_decode(utf8_encode($result), true);
    return $Ausgabe;
  }
  
  /**************************************************************************
  //  HTTP POST / GET Request
  //  [Request"] = POST
  //  ["Data"]   = Daten
  //  ["Header"] = Header
  //  ["Port"]   = Port
  //  ["Benutzer"] = UserID:Kennwort
  //
  **************************************************************************/
  public static function http_read($abfrage) {
    if (!isset($abfrage["Request"])) {
      $abfrage["Request"] = "POST";
    }
    $ch = curl_init($abfrage["URL"]);
    $i = 1;
    Log::write("Curl  ".print_r($abfrage, 1), "   ", 10);
    do {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $abfrage["Request"]);
      curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in second s
      curl_setopt($ch, CURLOPT_PORT, $abfrage["Port"]);
      if ($abfrage["Request"] == "POST") {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $abfrage["Data"]);
      }
      if (isset($abfrage["Header"])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $abfrage["Header"]);
      }
      if (isset($abfrage["Benutzer"])) {
        curl_setopt($ch, CURLOPT_USERPWD, $abfrage["Benutzer"]);
      }
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $result = curl_exec($ch);
      $rc_info = curl_getinfo($ch);
      $Ausgabe = json_decode($result, true);
      if (curl_errno($ch)) {
        Log::write("Curl Fehler! HTTP Daten nicht vom Gerät gelesen! No. ".curl_errno($ch), "   ", 5);
      }
      elseif ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        break;
      }
      elseif (empty($Ausgabe["error"])) {
        Log::write("HTTP Fehler -> nochmal versuchen.", "   ", 5);
        $i++;
        continue;
      }
      if ($abfrage["Request"] == "POST") {
        Log::write("Daten nicht vom Gerät gelesen! => [ ".$Ausgabe["error"]." ]", "   ", 5);
        Log::write("Daten => [ ".print_r($abfrage, 1)." ]", "   ", 5);
        Log::write("Daten nicht von dem Gerät gelesen! info: ".var_export($rc_info, 1), "   ", 9);
      }
      $i++;
      sleep(1);
    } while ($i < 3);
    curl_close($ch);
    if ($abfrage["Request"] == "POST") {
      return $Ausgabe;
    }
    else {
      return $rc_info;
    }
  }

  public static function _hex2float($num) {
    $binfinal = sprintf("%032b", hexdec($num));
    $sign = substr($binfinal, 0, 1);
    $exp = substr($binfinal, 1, 8);
    $mantissa = "1".substr($binfinal, 9);
    $mantissa = str_split($mantissa);
    $exp = bindec($exp) - 127;
    $significand = 0;
    for ($i = 0; $i < 24; $i++) {
      $significand += (1 / pow(2, $i)) * $mantissa[$i];
    }
    return $significand * pow(2, $exp) * ($sign * - 2 + 1);
  }

  public static function hex2float32($number) {
    $binfinal = sprintf("%032b", hexdec($number));
    $sign = substr($binfinal, 0, 1);
    $exp = substr($binfinal, 1, 8);
    $mantissa = "1".substr($binfinal, 9);
    $mantissa = str_split($mantissa);
    $exp = bindec($exp) - 127;
    $significand = 0;
    for ($i = 0; $i < 24; $i++) {
      $significand += (1 / pow(2, $i)) * $mantissa[$i];
    }
    return $significand * pow(2, $exp) * ($sign * - 2 + 1);
  }

  public static function hex2float64($strHex) {
    $hex = sscanf($strHex, "%02x%02x%02x%02x%02x%02x%02x%02x");
    $hex = array_reverse($hex);
    $bin = implode('', array_map('chr', $hex));
    $array = unpack("dnum", $bin);
    return $array ['num'];
  }

  /**************************************************************************
  //  HEX / String  Umwandlungsroutinen
  //
  **************************************************************************/
  public static function hex2str($hex) {
    $str = '';
    for ($i = 0; $i < strlen($hex); $i += 2)
      $str .= chr(hexdec(substr($hex, $i, 2)));
    return $str;
  }

  /************************************************************/
  public static function hexdecs($hex) {
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
    return $dec > $_dec ? - $_dec:$dec;
  }

  /*  Dezimal Umwandlung in Bitwise                          */
  //  Der Eingangswert muss Dezimal sein!
  public static function d2b($dec, $n = 16) {
    return str_pad(decbin($dec), $n, "0", STR_PAD_LEFT);
  }

  /**************************************************************************
  //  unterschiedliche CRC Berechnungen
  //
  //
  **************************************************************************/
  public static function crc8($ptr) {
    static $CRC8_Lookup = array(0x00, 0x8F, 0x27, 0xA8, 0x4E, 0xC1, 0x69, 0xE6, 0x9C, 0x13, 0xBB, 0x34, 0xD2, 0x5D, 0xF5, 0x7A);
    $ptr = hex2bin($ptr);
    $crc_table = $CRC8_Lookup;
    $currentCrc = 0x55;
    for ($i = 0; $i < strlen($ptr); $i++) {
      $currentCrc ^= ord($ptr[$i]);
      $currentCrc = (($currentCrc >> 4) ^ $crc_table[$currentCrc & 0x0F]);
      $currentCrc = (($currentCrc >> 4) ^ $crc_table[$currentCrc & 0x0F]);
    }
    return substr("00".dechex($currentCrc), - 2);
  }

  /*****************************************************************************
  // D_Daten + D_ServiceCode = CRCData
  //
  *****************************************************************************/
  public static function crc8Data($ptr) {
    $ptr = hex2bin($ptr);
    $currentCrc = 0x55;
    for ($i = 0; $i < strlen($ptr); $i++) {
      $currentCrc = ($currentCrc + ord($ptr[$i]));
    }
    return substr("00".dechex($currentCrc), - 2);
  }

  public static function crc16_steca($ptr) {
    static $CRC16_Lookup = array(0x0000, 0xACAC, 0xEC05, 0x40A9, 0x6D57, 0xC1FB, 0x8152, 0x2DFE, 0xDAAE, 0x7602, 0x36AB, 0x9A07, 0xB7F9, 0x1B55, 0x5BFC, 0xF750);
    $ptr = hex2bin($ptr);
    $crc_table = $CRC16_Lookup;
    $currentCrc = 0x5555;
    for ($i = 0; $i < strlen($ptr); $i++) {
      $currentCrc ^= ord($ptr[$i]);
      $currentCrc = (($currentCrc >> 4) ^ $crc_table[$currentCrc & 0x000F]);
      $currentCrc = (($currentCrc >> 4) ^ $crc_table[$currentCrc & 0x000F]);
    }
    return substr("000".dechex($currentCrc), - 4);
  }

  public static function checksum16($msg) {
    $bytes = unpack("C*", $msg);
    $sum = 0;
    foreach ($bytes as $b) {
      $sum += $b;
      $sum = $sum % pow(2, 16);
    }
    return $sum;
  }

  public static function crc16($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
      $crc ^= ord($data[$i]);
      for ($j = 8; $j != 0; $j--) {
        if (($crc & 0x0001) != 0) {
          $crc >>= 1;
          $crc ^= 0xA001;
        }
        else {
          $crc >>= 1;
        }
      }
    }
    $highCrc = floor($crc / 256);
    $lowCrc = ($crc - $highCrc * 256);
    return chr($lowCrc).chr($highCrc);
  }

  public static function crc16_us2000($Input) {
    $Summe = 0x0;
    for ($i = 0; $i < strlen($Input); $i++) {
      $Summe = $Summe + ord($Input[$i]);
    }
    // return strtoupper( substr( dechex( (~ $Summe) + 1 ), 4 )); // alte Zeile
    return strtoupper(dechex((~ $Summe + 1) & 0xffff));
  }

  public static function CRC16Normal($buffer) {
    $result = 0;
    if (($length = strlen($buffer)) > 0) {
      for ($offset = 0; $offset < $length; $offset++) {
        $result ^= (ord($buffer[$offset]) << 8);
        for ($bitwise = 0; $bitwise < 8; $bitwise++) {
          if (($result <<= 1) & 0x10000)
            $result ^= 0x1021;
          $result &= 0xFFFF;
        }
      }
    }
    return $result;
  }

  public static function Hex2String($hex) {
    $string = '';
    for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
      if ($hex[$i] == 0x00) {
        //  Wenn das Byte mit einer 0 beginnt...
        continue; // Eingefügt am 6.1.2022
      }
      $string .= chr(hexdec($hex[$i].$hex[$i + 1]));
    }
    return trim($string);
  }

  public static function string2hex($string) {
    $hex = '';
    for ($i = 0; $i < strlen($string); $i++) {
      if (strlen(dechex(ord($string[$i]))) == 1)
        $hex .= "0".dechex(ord($string[$i]));
      else
        $hex .= dechex(ord($string[$i]));
    }
    return $hex;
  }

  public static function VE_CRC($Daten) {
    return VE::VE_CRC($Daten);
  }

  public static function eSmart3_CRC($Daten) {
    $Checksum = 0;
    for ($i = 0; $i < strlen($Daten) - 1; $i += 2) {
      $Checksum = $Checksum + hexdec($Daten[$i].$Daten[$i + 1]);
    }
    return substr((dechex(0 - $Checksum)), - 2);
  }

  public static function hex2float($number) {
    $binfinal = sprintf("%032b", hexdec($number));
    $sign = substr($binfinal, 0, 1);
    $exp = substr($binfinal, 1, 8);
    $mantissa = "1".substr($binfinal, 9);
    $mantissa = str_split($mantissa);
    $exp = bindec($exp) - 127;
    $significand = 0;
    for ($i = 0; $i < 24; $i++) {
      $significand += (1 / pow(2, $i)) * $mantissa[$i];
    }
    return $significand * pow(2, $exp) * ($sign * - 2 + 1);
  }

  public static function onebytechecksum($string) {
    $zahl = 0;
    for ($i = 0; $i < strlen($string); $i++) {
      $zahl = $zahl + ord($string[$i]);
      if ($zahl > 256) {
        $zahl = $zahl - 256;
      }
    }
    return $zahl;
  }

  public static function kelvinToCelsius($temperature) {
    if (!is_numeric($temperature)) {
      return false;
    }
    // return round((($temperature - 273.15) * 1.8) + 32, 1);
    return round(($temperature - 273.15), 1);
  }

  public static function crc16_arc($data) {
    $crc = 0x0000; // Init
    $len = strlen($data);
    $i = 0;
    while ($len--) {
      $crc ^= Utils::reversebyte(ord($data[$i++])) << 8;
      $crc &= 0xffff;
      for ($j = 0; $j < 8; $j++) {
        $crc = ($crc & 0x8000) ? ($crc << 1) ^ 0x8005:$crc << 1;
        $crc &= 0xffff;
      }
    }
    //$crc ^= 0x0000;   // Final XOR
    $crc = Utils::reversebits($crc);
    $crc = dechex($crc);
    return str_pad($crc, 4, "0", STR_PAD_LEFT);
  }

  public static function reversebyte($byte) {
    $ob = 0;
    $b = (1 << 7);
    for ($i = 0; $i <= 7; $i++) {
      if (($byte & $b) !== 0) {
        $ob |= (1 << $i);
      }
      $b >>= 1;
    }
    return $ob;
  }

  public static function reversebits($cc) {
    $ob = 0;
    $b = (1 << 15);
    for ($i = 0; $i <= 15; $i++) {
      if (($cc & $b) !== 0) {
        $ob |= (1 << $i);
      }
      $b >>= 1;
    }
    return $ob;
  }

  public static function calcCRC(string $command) {
    $commandLength = strlen($command) / 2;
    if ($commandLength % 2 != 0) {
      // Command with an odd byte length (add 0x00 to make odd!) without(!) start byte (0x2B)
      $command = $command.'00';
      $commandLength = strlen($command) / 2;
    }
    $crc = 0xFFFF;
    for ($x = 0; $x < $commandLength; $x++) {
      $b = hexdec(substr($command, $x * 2, 2));
      for ($i = 0; $i < 8; $i++) {
        $bit = (($b >> (7 - $i) & 1) == 1);
        $c15 = ((($crc >> 15) & 1) == 1);
        $crc <<= 1;
        if ($c15 ^ $bit)
          $crc ^= 0x1021;
      }
      $crc &= 0xffff;
    }
    $crc = strtoupper(dechex($crc));
    // if the CRC is too short, add '0' at the beginning
    if (strlen($crc) == 2) {
      $crc = '00'.$crc;
    }
    if (strlen($crc) == 3) {
      $crc = '0'.$crc;
    }
    return $crc;
  }
  
  
  public static function tageslicht($Ort = 'hamburg') {
    // Ist an dem Ort gerade Sonnenaufgang / Sonnenuntergang?
    $Daemmerung = 0; // 3600 = 1 Stunde  600 = 10 Minuten
    switch (strtolower($Ort)) {
      case "essen":
        $Breite = 51.4556432;
        $Laenge = 7.01155520;
        break;
      case "hamburg":
        $Breite = 53.5510846;
        $Laenge = 9.99368180;
        break;
      case "bremerhaven":
        $Breite = 53.547748;
        $Laenge = 8.5700350;
        break;
      case "hirschau":
        $Breite = 49.550412;
        $Laenge = 11.947207;
        break;
      case "llucmajor":
        $Breite = 39.476000;
        $Laenge = 2.933000;
        break;
      case "würzburg":
        $Breite = 49.683385;
        $Laenge = 10.114229;
        break;
      default:
        $Breite = 53.5510846;
        $Laenge = 9.99368180;
        break;
    }
    $now = time();
    if (date("I")) {
      $gmt_offset = 2;
    }
    else {
      $gmt_offset = 1;
    }
    $zenith = 50 / 60;
    $zenith = $zenith + 90;
    $sunset = date_sunset($now, SUNFUNCS_RET_TIMESTAMP, $Breite, $Laenge, $zenith, $gmt_offset);
    $sunrise = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $Breite, $Laenge, $zenith, $gmt_offset);
    if (time() > ($sunrise - $Daemmerung) and time() < ($sunset + $Daemmerung)) {
      return true;
    }
    return false;
  }
  
  /************************************************************************/
  public static function cobs_decoder($Wert) {
    $Ergebnis = $Wert;
    $cobs = hexdec(substr($Wert, 10, 2));
    if (strlen($Wert) != ($cobs * 2) + 12) {
      $Laenge = strlen($Wert);
      $Zaehler = 10;
      for ($i = 0; $i <= strlen($Wert); $i++) {
        $Zaehler = $Zaehler + ($cobs * 2);
        if (substr($Wert, $Zaehler, 2) == "00") {
          break;
        }
        $Ergebnis = substr_replace($Ergebnis, "00", $Zaehler, 2);
        $cobs = hexdec(substr($Wert, $Zaehler, 2));
        // echo $Zaehler."\n";
        // echo $Wert."\n";
        // echo $Ergebnis."\n\n";
      }
    }
    return $Ergebnis;
  }
  
  
  public static function checksumJK32($data) {
    $crc = 0x0;
    for ($i = 0; $i < strlen($data); $i++) {
      $crc = $crc + ord($data[$i]);
    }
    $highCrc = floor($crc / 256);
    $lowCrc = ($crc - $highCrc * 256);
    return chr(0).chr(0).chr($highCrc).chr($lowCrc);
  }
  
  /*************************/
  public static function senec($Daten) {
    $teile = explode("_", $Daten);
    switch ($teile[0]) {
      case 'u1':
        $Ergebnis = hexdec($teile[1]);
        break;
      case "i1":
        $Ergebnis = hexdec($teile[1]);
        break;
      case "u3":
        $Ergebnis = hexdec($teile[1]);
        break;
      case "u6":
        $Ergebnis = hexdec($teile[1]);
        break;
      case "u8":
        $Ergebnis = hexdec($teile[1]);
        break;
      case "fl":
        if ($teile[1] == "00000000") {
          $Ergebnis = 0;
        }
        else {
          $Ergebnis = Utils::_hex2float($teile[1]);
        }
        break;
      case "st":
        $Ergebnis = $teile[1];
        break;
        //  Eingefügt Timo 09.05.2022
      case "i8":
        $Ergebnis = hexdec($teile[1]);
        break;
      default:
        $Ergebnis = 0;
        break;
    }
    return $Ergebnis;
  }
  
  
  public static function getEnvAsString(string $key, string $defaultValue) :string {
    $value = getenv($key);
    if ($value === FALSE) return $defaultValue;
    return $value;
  }
  
  public static function getEnvAsBoolean(string $key, bool $defaultValue) :bool {
    $value = getenv($key);
    if ($value === FALSE) return $defaultValue;
    return ($value === "true" || $value === "1" || $value === "on" || $value === "yes");
  }
  
  public static function getEnvAsInteger(string $key, int $defaultValue) :int {
    $value = getenv($key);
    if ($value === FALSE) return $defaultValue;
    return intval($value);
  }
  
  public static function getEnvAsFloat(string $key, float $defaultValue) :float {
    $value = getenv($key);
    if ($value === FALSE) return $defaultValue;
    return floatval($value);
  }
  
  public static function getEnvPlattform() :string {
    If (is_file("/sys/firmware/devicetree/base/model")) {
      //  Auf welcher Platine läuft die Software?
      $Platine = file_get_contents("/sys/firmware/devicetree/base/model");
    } else {
      $Platine = "Docker Image ".Utils::getEnvAsString("SA_VERSION","0.0.0");
    }
    
    return $Platine;
  }
}