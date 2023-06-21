<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class PushoverTools {
  /**************************************************************************
  //  Pushover, WhatsApp und Signal Meldungen senden
  //
  //
  **************************************************************************/
  public static function po_influxdb_lesen($abfrage) {
    $ch = curl_init('http://localhost/query?epoch=s&'.$abfrage["Query"]);
    $i = 1;
    do {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in second s
      curl_setopt($ch, CURLOPT_PORT, "8086");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $result = curl_exec($ch);
      $rc_info = curl_getinfo($ch);
      $Ausgabe = json_decode($result, true);
      if (curl_errno($ch)) {
        Log::write("Curl Fehler! Meldungsdaten nicht von der InfluxDB gelesen! No. ".curl_errno($ch), "   ", 5);
      }
      elseif ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        Log::write("Meldungsdaten von der InfluxDB  gelesen. ", "*  ", 9);
        break;
      }
      elseif (empty($Ausgabe["error"])) {
        Log::write("InfluxDB Fehler -> nochmal versuchen.", "   ", 5);
        $i++;
        continue;
      }
      Log::write("Daten nicht von der InfluxDB gelesen! => [ ".$Ausgabe["error"]." ]", "   ", 5);
      Log::write("InfluxDB  => [ ".$abfrage['Query']." ]", "   ", 5);
      Log::write("Daten => [ ".print_r($abfrage, 1)." ]", "   ", 9);
      Log::write("Daten nicht von der InfluxDB gelesen! info: ".var_export($rc_info, 1), "   ", 9);
      $i++;
      sleep(1);
    } while ($i < 3);
    curl_close($ch);
    return $Ausgabe;
  }

  /**************************************************************************
  //
  **************************************************************************/
  public static function po_send_message($APP_ID, $UserToken, $Message, $Bild = 0, $DevName = "", $Messenger = "pushover") {
    // Versand nach Pusover Messenger
    if (strtolower($Messenger) == "pushover") {
      curl_setopt_array($ch = curl_init(), array(CURLOPT_URL => "https://api.pushover.net/1/messages.json", CURLOPT_POSTFIELDS => array("token" => $APP_ID, "user" => $UserToken, "html" => 1, "message" => $Message, "device" => $DevName)));
      $result = curl_exec($ch);
      $rc_info = curl_getinfo($ch);
      $Ausgabe = json_decode($result, true);
      if (curl_errno($ch)) {
        Log::write("Curl Fehler! Daten nicht zum Messenger gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
        return false;
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        Log::write("Daten zum Messenger  gesendet. ", "*  ", 10);
      }
      elseif ($rc_info["http_code"] == 401) {
        Log::write("UserID oder Kennwort ist falsch.", "*  ", 5);
        return false;
      }
      curl_close($ch);
    }
    if (strtolower($Messenger) == "signal") {
      $url = "https://api.callmebot.com/signal/send.php?phone=".$UserToken."&apikey=".$APP_ID."&text=".urlencode($Message);
      if ($ch = curl_init($url)) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler! Daten nicht zum Messenger gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
          return false;
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204 or $rc_info["http_code"] == 203) {
          Log::write("Daten zum Messenger SIGNAL gesendet. ", "*  ", 8);
        }
        if ($rc_info["http_code"] == 203) {
          Log::write("Kombination von APIKey und Rufnummer ist falsch. ", "*  ", 5);
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("UserID oder Kennwort ist falsch.", "*  ", 5);
          return false;
        }
        curl_close($ch);
        return true;
      }
      else {
        return false;
      }
    }
    if (strtolower($Messenger) == "whatsapp") {
      $url = 'https://api.callmebot.com/whatsapp.php?source=php&phone='.$UserToken.'&text='.urlencode($Message).'&apikey='.$APP_ID;
      if ($ch = curl_init($url)) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler! Daten nicht zum Messenger gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
          return false;
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204 or $rc_info["http_code"] == 203) {
          Log::write("Daten zum Messenger WhatsApp gesendet. ", "*  ", 8);
        }
        if ($rc_info["http_code"] == 203) {
          Log::write("Kombination von APIKey und Rufnummer ist falsch. ", "*  ", 5);
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("UserID oder Kennwort ist falsch.", "*  ", 5);
          return false;
        }
        curl_close($ch);
        return true;
      }
      else {
        return false;
      }
    }
    return true;
  }

  /**************************************************************************
  //
  **************************************************************************/
  public static function po_messageControl($Meldung, $Nummer = 0, $GeraeteNummer = 1, $Messenger = "pushover") {
    // Pushover, Signal und Whatsup Nachrichten
    global $basedir;
    $Neu = true;
    $Teile = array();

    /************************************************************************
    //  Die Status Datei wird dazu benutzt, um zu verhindern, dass eine
    //  Meldung zu oft gesendet wird.
    //
    ************************************************************************/
    $StatusFile = $basedir."/database/".$GeraeteNummer.".".$Messenger.".meldungen.txt";
    if (file_exists($StatusFile)) {

      /************************************************************************
      //  Daten einlesen ...
      ************************************************************************/
      $MeldungenRaw = file_get_contents($StatusFile);
      $Meldungen = explode("\n", $MeldungenRaw);
      Log::write(print_r($Meldungen, 1), "   ", 10);
      $Anzahl = count($Meldungen);
      for ($i = 0; $i < $Anzahl; $i++) {
        $Teile[$i] = explode("|", $Meldungen[$i]);
        Log::write($i.": ".print_r($Teile[$i], 1), "   ", 10);
        if ($Nummer == 0 and date("H:i") != "00:00") {
          if ($Teile[$i][2] == $Meldung) {
            return $Teile[$i];
          }
        }
        elseif (date("H:i") == "00:00") {
          //  Um Mitternacht wird die Anzahl von allen Einträgen auf 0 zurück gesetzt.
          $Teile[$i][1] = 0;
          $Neu = false;
        }
        else {
          if ($Teile[$i][2] == $Meldung) {
            $Teile[$i][0] = time();
            $Teile[$i][1] = $Nummer;
            $Neu = false;
          }
        }
      }
      if (date("H:i") == "00:00") {
        $Nummer = 0;
      }
      elseif ($Nummer == 0) {
        return false;
      }
      for ($i = 0; $i < $Anzahl; $i++) {
        if ($i == 0) {
          $rc = file_put_contents($StatusFile, $Teile[$i][0]."|".$Teile[$i][1]."|".$Teile[$i][2]);
        }
        else {
          $rc = file_put_contents($StatusFile, "\n".$Teile[$i][0]."|".$Teile[$i][1]."|".$Teile[$i][2], FILE_APPEND);
        }
      }
      if ($Neu) {
        if ($Nummer == 0) {
          $rc = file_put_contents($StatusFile, "\n"."1000000000|".$Nummer."|".$Meldung, FILE_APPEND);
        }
        else {
          $rc = file_put_contents($StatusFile, "\n".time()."|".$Nummer."|".$Meldung, FILE_APPEND);
        }
      }
    }
    else {

      /***************************************************************************
      //  Inhalt der Status Datei anlegen.
      //  Die Meldungen müssen mit einem NL (\n) getrennt werden.
      ***************************************************************************/
      $rc = file_put_contents($StatusFile, "1000000000|".$Nummer."|".$Meldung);
      if ($rc === false) {
        Log::write("Fehler! Konnte die Datei meldungen.txt nicht anlegen.", "XX ", 1);
        return false;
      }
    }
    return true;
  }
}
?>