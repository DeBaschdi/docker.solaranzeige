<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class InfluxDB {

  /**************************************************************************
  //  Test, ob die remote Influx Datenbank zu erreichen ist
  //  Es wird die Influx Datenbank kontaktiert!
  //  Auf dem remote Server wird kein Apache benötigt.
  **************************************************************************/
  public static function influx_remote_test() {
    Global $InfluxAdresse, $InfluxPort;
    // Damit sich der remote Datenbank Zugriff etwas verteilt.
    usleep(rand(100000, 2000000));
    $timeout = 5.5; // 5.1 ist ein guter Wert.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://".$InfluxAdresse);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_PORT, $InfluxPort);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $http_respond = curl_exec($ch);
    $http_respond = trim(strip_tags($http_respond));
    $rc = curl_getinfo($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $timeout = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    if ($rc["primary_port"] == $InfluxPort) {
      if ($http_code < "405") {
        Log::write("Test remote InfluxDB - Dauer: ".$timeout." Sekunden", "o  ", 8);
        Log::write("URL konnte erreicht werden. Http Code: ".$http_code, "o  ", 9);
        return true;
      }
    }
    Log::write("URL konnte nicht erreicht werden.", "!  ", 5);
    Log::write("URL: http://".$InfluxAdresse, "!  ", 9);
    Log::write("Test Domain - Dauer: ".$timeout." Sekunden", "!  ", 5);
    Log::write("Test Domain - RC: ".print_r($rc, 1), "!  ", 9);
    return false;
  }

  /**************************************************************************
  //  Daten in die lokale Influx Datenbank schreiben
  //
  //
  **************************************************************************/
  public static function influx_local($daten) {
    $query = "";
    if (!isset($daten["InfluxDBLokal"])) {
      $daten["InfluxDBLokal"] = "solaranzeige";
    }
    if (!isset($daten["zentralerTimestamp"])) {
      $daten["zentralerTimestamp"] = time();
    }
    //  Jetzt müssen die Daten in die lokale InfluxDB übertragen werden.
    //
    //  Zuerst die Statistikdaten übertragen. Einmal am Tage um 23:58 Uhr.
    if (date("i") == '00' or date("i") == '10' or date("i") == '20' or date("i") == '30' or date("i") == '40' or date("i") == '50' or date("i") == '59') {
      // if (1 == 1) {
      Log::write("Alle 10 Minuten werden die Statistikdaten übertragen.", "   ", 5);
      $query = "Statistik Bezeichnung=\"WhTag\",Datum=\"".date('d.m.Y')."\",Woche=".date('W').",Monat=".date('n');
      $query .= ",Wochentag=\"".strftime('%A', time())."\"";
      $query .= ",Heute_TS=".mktime(0, 0, 0, date("m"), date("d"), date("Y"))."000000000";
      $query .= ",Gestern_TS=".mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"))."000000000";
      $query .= ",DiesesJahr_TS=".mktime(0, 0, 0, 1, 1, date("Y"))."000000000";
      $query .= ",LetztesJahr_TS=".mktime(0, 0, 0, 1, 1, date("Y") - 1)."000000000";
      $query .= ",DieserMonat_TS=".mktime(0, 0, 0, date("m"), 1, date("Y"))."000000000";
      $query .= ",LetzterMonat_TS=".mktime(0, 0, 0, date("m") - 1, 1, date("Y"))."000000000";
      $query .= ",DieseWoche_TS=".strtotime("last monday", strtotime("tomorrow"))."000000000";
      $query .= ",LetzteWoche_TS=".strtotime("last monday -7 days", strtotime("tomorrow"))."000000000";
      $query .= ",HeuteVJ_TS=".mktime(0, 0, 0, date("m"), date("d"), date("Y") - 1)."000000000";
      $query .= ",TagImMonat=".date("j");
      $query .= ",TagImJahr=".(date("z") + 1);
      $query .= ",Jahr=".date("Y");
      $query .= ",Stunde=".date("G");
      $query .= "  ".$daten["zentralerTimestamp"];
      $ch = curl_init('http://localhost/write?db='.$daten["InfluxDBLokal"].'&precision=s');
      $i = 1;
      do {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in second s
        curl_setopt($ch, CURLOPT_PORT, "8086");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch);
        $Ausgabe = json_decode($result, true);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler[1]! Daten nicht zur lokalen InfluxDB ".$daten["InfluxDBLokal"]." gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("Influx UserID oder Kennwort ist falsch.", "*  ", 5);
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          Log::write("Lokale InfluxDB Fehler -> nochmal versuchen.", "   ", 5);
          $i++;
          continue;
        }
        Log::write("Daten nicht zur lokalen InfluxDB [ ".$daten["InfluxDBLokal"]." ] gesendet! => [ ".$Ausgabe["error"]." ]", "   ", 5);
        Log::write("InfluxDB  => [ ".$query." ]", "   ", 5);
        Log::write("Daten => [ ".print_r($daten, 1)." ]", "   ", 9);
        Log::write("Daten nicht zur lokalen InfluxDB gesendet! info: ".var_export($rc_info, 1), "   ", 5);
        $i++;
        sleep(5);
      } while ($i < 3);
      curl_close($ch);
      unset($ch);
    }
    if (!isset($daten["DB_nein"])) {
      // Wenn es diese Variable gibt, sollen die Werte nicht abgespeichert werden.
      Log::write("Aktuelle Daten: \n".print_r($daten, 1), "   ", 9);
      $query = InfluxDB::query_erzeugen($daten);
      if (isset($daten["ZusatzQuery"])) {
        $query = $daten["ZusatzQuery"]."\n".$query;
      }
      Log::write("Query: \n".$query, "   ", 9);
      $ch = curl_init('http://localhost/write?db='.$daten["InfluxDBLokal"].'&precision=s');
      $i = 1;
      do {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 16); //timeout in second s
        curl_setopt($ch, CURLOPT_PORT, "8086");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch);
        $Ausgabe = json_decode($result, true);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler[2]! Daten nicht zur lokalen InfluxDB ".$daten["InfluxDBLokal"]." gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          Log::write("Daten zur lokalen InfluxDB [ ".$daten["InfluxDBLokal"]." ] gesendet. ", "*  ", 7);
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("Influx UserID oder Kennwort ist falsch.", "*  ", 5);
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          Log::write("InfluxDB Fehler -> nochmal versuchen.", "   ", 5);
          $i++;
          continue;
        }
        Log::write("InfluxDB  => [ ".$query." ]", "   ", 5);
        Log::write("Daten => [ ".print_r($daten, 1)." ]", "   ", 9);
        Log::write("Daten nicht zur lokalen InfluxDB gesendet! info: ".var_export($rc_info, 1), "   ", 5);
        $i++;
        sleep(5);
      } while ($i < 3);
      curl_close($ch);
    }
    return true;
  }

  /**************************************************************************
  //  Daten in die remote Influx Datenbank schreiben
  //
  //
  **************************************************************************/
  public static function influx_remote($daten) {
    if (!isset($daten["zentralerTimestamp"])) {
      $daten["zentralerTimestamp"] = time();
    }
    $query = "";
    //  Jetzt müssen die Daten in die InfluxDB übertragen werden.
    //
    //
    //  Zuerst die Statistikdaten übertragen. Einmal am Tage um 00:00 Uhr.
    if (date("i") == '00' or date("i") == '10' or date("i") == '20' or date("i") == '30' or date("i") == '40' or date("i") == '50') {
      // Log::write("Aktuelle Statistik:\n".print_r($daten,1),"   ",9);
      Log::write("Alle 10 Minuten werden die Statistikdaten remote übertragen.", "   ", 5);
      $query = "Statistik Bezeichnung=\"WhTag\",Datum=\"".date('d.m.Y')."\",Woche=".date('W').",Monat=".date('n');
      $query .= ",Wochentag=\"".strftime('%A', time())."\"";
      $query .= ",Heute_TS=".mktime(0, 0, 0, date("m"), date("d"), date("Y"))."000000000";
      $query .= ",Gestern_TS=".mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"))."000000000";
      $query .= ",DiesesJahr_TS=".mktime(0, 0, 0, 1, 1, date("Y"))."000000000";
      $query .= ",LetztesJahr_TS=".mktime(0, 0, 0, 1, 1, date("Y") - 1)."000000000";
      $query .= ",DieserMonat_TS=".mktime(0, 0, 0, date("m"), 1, date("Y"))."000000000";
      $query .= ",LetzterMonat_TS=".mktime(0, 0, 0, date("m") - 1, 1, date("Y"))."000000000";
      $query .= ",DieseWoche_TS=".strtotime("last monday", strtotime("tomorrow"))."000000000";
      $query .= ",LetzteWoche_TS=".strtotime("last monday -7 days", strtotime("tomorrow"))."000000000";
      $query .= ",TagImMonat=".date("j");
      $query .= ",TagImJahr=".(date("z") + 1);
      $query .= ",Jahr=".date("Y");
      $query .= ",Stunde=".date("G");
      $query .= "  ".$daten["zentralerTimestamp"];
      if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
        $ch = curl_init('https://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s');
      }
      else {
        $ch = curl_init('http://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s');
      }
      $i = 1;
      do {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); //timeout in second s
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_PORT, $daten["InfluxPort"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        if (!empty($daten["InfluxUser"]) and !empty($daten["InfluxPassword"])) {
          curl_setopt($ch, CURLOPT_USERPWD, $daten["InfluxUser"].":".$daten["InfluxPassword"]);
        }
        if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch);
        $Ausgabe = json_decode($result, true);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler! Daten nicht zur entfernten InfluxDB gesendet! Curl ErrNo. ".curl_errno($ch), "   ", 5);
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("Influx UserID oder Kennwort ist falsch.", "*  ", 5);
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          Log::write("InfluxDB Fehler -> nochmal versuchen.", "   ", 5);
          $i++;
          continue;
        }
        Log::write("InfluxDB  => [ ".$query." ]", "   ", 5);
        Log::write("Daten => [ ".print_r($daten, 1)." ]", "   ", 9);
        Log::write("Daten nicht zur InfluxDB gesendet! info: ".var_export($rc_info, 1), "   ", 5);
        $i++;
        sleep(1);
      } while ($i < 3);
      curl_close($ch);
      unset($ch);
    }
    // Nur beim Victron Laderegler wird Nachts nicht gesendet.
    if (Utils::tageslicht("hamburg") or $daten["InfluxDaylight"] === false) {
      Log::write("Aktuelle Daten: \n".print_r($daten, 1), "   ", 9);
      $query = InfluxDB::query_erzeugen($daten);
      if (isset($daten["ZusatzQuery"])) {
        $query = $daten["ZusatzQuery"]."\n".$query;
      }
      if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
        $ch = curl_init('https://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s');
      }
      else {
        $ch = curl_init('http://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s');
      }
      $i = 1;
      do {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); //timeout in second s
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_PORT, $daten["InfluxPort"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        if (!empty($daten["InfluxUser"]) and !empty($daten["InfluxPassword"])) {
          curl_setopt($ch, CURLOPT_USERPWD, $daten["InfluxUser"].":".$daten["InfluxPassword"]);
        }
        if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $rc_info = curl_getinfo($ch);
        $Ausgabe = json_decode($result, true);
        if (curl_errno($ch)) {
          Log::write("Curl Fehler! Daten nicht zur entfernten InfluxDB gesendet! No. ".curl_errno($ch), "   ", 5);
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          Log::write("Daten zur entfernten InfluxDB [ ".$daten["InfluxDBName"]." ] gesendet. ", "*  ", 7);
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          Log::write("Influx UserID oder Kennwort ist falsch.", "*  ", 5);
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          Log::write("InfluxDB Fehler -> nochmal versuchen.", "   ", 5);
          $i++;
          continue;
        }
        Log::write("Daten nicht zur InfluxDB gesendet! => [ ".$Ausgabe["error"]." ]", "   ", 5);
        Log::write("InfluxDB  => [ ".$query." ]", "   ", 5);
        Log::write("Daten => [ ".print_r($daten, 1)." ]", "   ", 9);
        Log::write("Daten nicht zur InfluxDB gesendet! info: ".var_export($rc_info, 1), "   ", 5);
        $i++;
        sleep(1);
      } while ($i < 3);
      curl_close($ch);
    }
    return true;
  }

  /**************************************************************************
  //  Daten aus einer Datenbank lesen
  //
  //
  **************************************************************************/
  function influx_datenbank($db, $init, $query) {
    // Datenbankfunktion zum Lesen und Schreiben in die Datenbank
    //
    // $db = Datenbankname
    // $init = http://localhost/write?db=Datenbankname&precision=s (Schreiben oder lesen)
    // $query = Die Query die ausgeführt werden soll
    $Status = false;
    $ch = curl_init($init);
    $i = 1;
    do {
      $i++;
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_TIMEOUT, 15); //timeout in second s
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
      curl_setopt($ch, CURLOPT_PORT, "8086");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $result = curl_exec($ch);
      $rc_info = curl_getinfo($ch);
      $Ausgabe = json_decode($result, true);
      if (curl_errno($ch)) {
        log_schreiben("Curl Fehler[2]! Daten nicht zur lokalen InfluxDB ".$db." gesendet! Curl ErrNo. ".curl_errno($ch), "", 1, 1);
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        $Status = true;
        break;
      }
      elseif ($rc_info["http_code"] == 401) {
        log_schreiben("Influx UserID oder Kennwort ist falsch.", "", 1, 1);
        break;
      }
      elseif (empty($Ausgabe["error"])) {
        log_schreiben("InfluxDB Fehler -> nochmal versuchen.", "", 1, 1);
        continue;
      }
      sleep(2);
    } while ($i < 3);
    curl_close($ch);
    if ($Status == true) {
      return $Ausgabe;
    }
    else {
      return false;
    }
  }

  /**************************************************************************
  //  Hier werden Demo Daten vorbereitet.
  //
  //
  **************************************************************************/
  public static function demo_daten_erzeugen($Regler) {
    $aktuelleDaten = array('Firmware' => 1.0, 'Produkt' => "Solaranzeige", 'Batteriespannung' => 0, 'Batterieentladestrom' => 0, 'Batterieladestrom' => 0, 'Batteriekapazitaet' => 0, 'BatterieladestromMaxHeute' => 0, 'Verbraucherstrom' => 0, 'SolarspannungMaxHeute' => 0, 'WattstundenGesamtHeute' => 0, 'WattstundenGesamt' => 0, 'AmperestundenGesamt' => 0, 'Ladestatus' => 0, 'Netzspannung' => 0, 'Netzfrequenz' => 0, 'AC_Ausgangsspannung' => 0, 'AC_Ausgangsfrequenz' => 0, 'AC_Scheinleistung' => 0, 'AC_Wirkleistung' => 0, 'AC_Ausgangslast' => 0, 'AC_Ausgangsstrom' => 0, 'AC_Leistung' => 0, 'Ausgangslast' => 0, 'Solarstrom' => 0, 'Solarspannung' => 0, 'Solarleistung' => 0, 'maxWattHeute' => 0, 'maxAmpHeute' => 0, 'Temperatur' => 0, 'Optionen' => 0, 'Modus' => "B", 'DeviceStatus' => 0, 'ErrorCodes' => 0, 'Regler' => $Regler, 'Objekt' => "Solaranzeige", 'Timestamp' => time(), 'Monat' => date("n"), 'Woche' => date("W"), 'Wochentag' => strftime("%A", time()), 'Datum' => date("d.m.Y"), 'Uhrzeit' => date("H:i:s"), 'Demodaten' => true, 'InfluxAdresse' => 'localhost', 'InfluxUser' => 'admin', 'InfluxPassword' => 'solaranzeige', 'InfluxDBName' => 'solaranzeige');
    return $aktuelleDaten;
  }

  /**************************************************************************
  //  Hier werden Demo Daten erzeugt.
  //
  //
  **************************************************************************/
  public static function query_erzeugen($daten) {
    $Summe = 0;
    $now = time();
    $gmt_offset = 1 + date("I");
    $zenith = 50 / 60;
    $zenith = $zenith + 90;
    $Sonnenaufgang = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, 50.1143999, 8.6585178, $zenith, $gmt_offset);
    $query = "";
    switch ($daten["Regler"]) {
      case 1: // SCplus und SCDplus
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"]."\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Entladestrom=".$daten["Batterieentladestrom"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Batterieladestrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Ah_Gesamt=".$daten["AmperestundenGesamt"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 2: // STECA Laderegler
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Ladestrom=".round($daten["Batterieladestrom"], 2);
        $query .= ",Entladestrom=".round($daten["Batterieentladestrom"], 2);
        $query .= ",Batteriestrom=".round($daten["BatteriestromGesamt"], 2);
        $query .= ",SOC=".$daten["SOCProzent"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung1"];
        $query .= ",Spannung_String_1=".$daten["Solarspannung1"];
        $query .= ",Spannung_String_2=".$daten["Solarspannung2"];
        $query .= ",Strom=".round($daten["Solarstrom"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Ladestatus=".$daten["Lademodus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",StatusLast=".$daten["StatusLast"];
        $query .= ",Relais1=".$daten["Relais1"];
        $query .= ",Relais2=".$daten["Relais2"];
        $query .= ",TagNacht=".$daten["TagNacht"];
        $query .= ",Fehlercode=".$daten["Fehlerstatus"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Ah_Eingang_Heute=".$daten["EnergieEingangTag"];
        $query .= ",Ah_Eingang_Gesamt=".$daten["EnergieEingangGesamt"];
        $query .= ",Ah_Ausgang_Heute=".$daten["EnergieAusgangTag"];
        $query .= ",Ah_Ausgang_Gesamt=".$daten["EnergieAusgangGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 3: // Tracer serie
      case 14: // Rover Laderegler
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Entladestrom=".$daten["Batterieentladestrom"];
        $query .= ",Entladeleistung=".$daten["Batterieentladeleistung"];
        $query .= ",Temperatur=".$daten["BatterieTemperatur"];
        $query .= ",SpannungMaxHeute=".$daten["BatterieMaxVoltHeute"];
        $query .= ",SpannungMinHeute=".$daten["BatterieMinVoltHeute"];
        $query .= ",SOC=".$daten["BatterieSOC"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        if ($daten["Regler"] == 3) {
          $query .= ",maxVoltHeute=".$daten["SolarspannungMaxHeute"];
        }
        else {
          $query .= ",maxAmpereHeute=".$daten["SolarstromMaxHeute"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Ladestatus=".$daten["Ladestatus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        if ($daten["Regler"] == 3) {
          $query .= ",Optionen=".bindec($daten["Optionen"]);
        }
        else {
          $query .= ",Fehlercode=".bindec($daten["Fehlercode"]);
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Verbrauch_Wh_Heute=".$daten["VerbrauchGesamtHeute"];
        $query .= ",Verbrauch_Wh_Gesamt=".$daten["VerbrauchGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 4: // Victron Laderegler
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"]."\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Entladestrom=".$daten["Batterieentladestrom"];
        $query .= ",maxAmpHeute=".$daten["BatterieladestromMaxHeute"];
        $query .= ",Temperatur=".$daten["BatterieSenseTemp"];
        $query .= "  ".$daten["zentralerTimestamp"]."\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",maxVoltHeute=".$daten["SolarspannungMaxHeute"];
        $query .= ",maxWattHeute=".$daten["maxWattHeute"];
        $query .= "  ".$daten["zentralerTimestamp"]."\n";
        $query .= "Service ";
        $query .= "Ladestatus=".$daten["Ladestatus"];
        $query .= ",StatusLoadausgang=".$daten["LoadStatus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",Optionen=".bindec($daten["Optionen"]);
        $query .= "  ".$daten["zentralerTimestamp"]."\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 5: // AEconversion Wicro Wechselrichter
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Device_Status=".$daten["Geraetestatus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 6: // Victron BMV
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",SpannungAux=".$daten["BatteriespannungAux"];
        $query .= ",Strom=".$daten["Batteriestrom"];
        $query .= ",Leistung=".$daten["Leistung"];
        $query .= ",Kapazitaet=".$daten["SOC"];
        $query .= ",Amperestunden=".$daten["Amperestunden"];
        $query .= ",EntladetiefeDurchschnitt=".$daten["EntladetiefeDurchschnitt"];
        $query .= ",EntladungMax=".$daten["EntladungMax"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Restlaufzeit=".$daten["TTG"];
        $query .= ",Ladestatus=".$daten["Ladestatus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",ZeitVollladung=".$daten["ZeitVollladung"];
        $query .= ",Ladezyklen=".$daten["Ladezyklen"];
        $query .= ",AnzahlSynchronisationen=".$daten["AnzahlSynchronisationen"];
        $query .= ",Alarm=".$daten["Alarm"];
        $query .= ",Relais=".$daten["Relais"];
        $query .= ",Restlaufzeit=".$daten["TTG"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_GesamtLadung=".$daten["WattstundenGesamtLadung"];
        $query .= ",Wh_GesamtEntladung=".$daten["WattstundenGesamtEntladung"];
        $query .= ",AmperestundenGesamt=".$daten["AmperestundenGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 7: // Effecta AX Serie
      case 26: // MPPSolar 5048 MK
      case 59: // EASUN POWER
      case 77: // AX Licom Box
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Firmware1=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung=".$daten["Netzspannung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Spannung_WR=".$daten["Batteriespannung"]; // muss noch geändert werden!
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
        $query .= ",Entladestrom=".$daten["Batterieentladestrom"];
        ;
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        if (isset($daten["Solarspannung2"])) {
          $query .= ",Spannung2=".$daten["Solarspannung2"];
          $query .= ",Strom2=".$daten["Solarstrom2"];
          $query .= ",Leistung2=".$daten["Solarleistung2"];
          $query .= ",Leistung1=".$daten["Solarleistung1"];
        }
        if (isset($daten["PV1_Spannung"])) {
          $query .= ",Spannung1=".$daten["PV1_Spannung"];
          $query .= ",Strom1=".$daten["PV1_Strom"];
          $query .= ",Leistung1=".$daten["PV1_Leistung"];
          $query .= ",Spannung2=".$daten["PV2_Spannung"];
          $query .= ",Strom2=".$daten["PV2_Strom"];
          $query .= ",Leistung2=".$daten["PV2_Leistung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        if ($daten["Regler"] == 26) {
          $query .= ",Fehlermeldung=\"".$daten["Fehlermeldung"]."\"";
        } // *
        $query .= ",Modus=\"".$daten["Modus"]."\"";
        if ($daten["Regler"] == 7) {
          $query .= ",OutputMode=".$daten["Set_Output_source_priority"];
        }
        if ($daten["Regler"] == 77) {
          $query .= ",OutputMode=".$daten["Output_Mode"];
          $query .= ",Max_Ladestrom=".$daten["Max_Ladestrom"];
          $query .= ",Max_AC_Ladestrom=".$daten["Max_AC_Ladestrom"];
          $query .= ",GeraeteTyp=\"".$daten["GeraeteTyp"]."\"";
          $query .= ",Status=\"".$daten["Status"]."\"";
          $query .= ",QuellePrioritaet=".$daten["QuellePrioritaet"];
          $query .= ",Fehlercode=".hexdec($daten["FehlerCode"]);
          $query .= ",Fehlermeldung=\"".$daten["FehlerCode"]."\"";
        }
        else {
          $query .= ",Device_Status=".$daten["DeviceStatus"]; // *
          $query .= ",Ladestatus=".$daten["Ladestatus"];
          $query .= ",Fehlercode=0"; // *
          $query .= ",Warnungen=".bindec($daten["Optionen"]); // *
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Infini Solar V Serie */
      case 8:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung=".$daten["Netzspannung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Spannung_WR=".$daten["Batteriespannung"]; // muss noch geändert werden!
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
        $query .= ",Entladestrom=".$daten["Batterieentladestrom"];
        ;
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung1"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",Spannung1=".$daten["Solarspannung1"];
        $query .= ",Spannung2=".$daten["Solarspannung2"];
        $query .= ",Leistung1=".$daten["Solarleistung1"];
        $query .= ",Leistung2=".$daten["Solarleistung2"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Laststatus=0"; // *
        $query .= ",Ladestatus=".$daten["Ladestatus1"]; // *
        $query .= ",Ladestatus2=".$daten["Ladestatus2"]; // *
        $query .= ",Stromrichtung_Batt=".$daten["Batteriestromrichtung"]; // *
        $query .= ",Stromrichtung_WR=".$daten["WR_Stromrichtung"]; // *
        $query .= ",Stromrichtung_Netz=".$daten["Netzstromrichtung"]; // *
        $query .= ",Modus=".$daten["Modus"];
        $query .= ",Device_Status=0"; // *
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",MPPT1_Temperatur=".$daten["MPPT1_Temperatur"];
        $query .= ",MPPT2_Temperatur=".$daten["MPPT2_Temperatur"];
        $query .= ",Fehlercode=".$daten["Fehlercode"]; // *
        $query .= ",Warnungen=".$daten["Warnungen"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* MPP Solar MPI 3 Phasen   */
      case 9:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung_R=".$daten["Netzspannung_R"];
        $query .= ",Spannung_S=".$daten["Netzspannung_S"];
        $query .= ",Spannung_T=".$daten["Netzspannung_T"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Ausgangsspannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Ausgangsspannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Ausgangsspannung_T"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Scheinleistung_R=".$daten["AC_Scheinleistung_R"];
        $query .= ",Scheinleistung_S=".$daten["AC_Scheinleistung_S"];
        $query .= ",Scheinleistung_T=".$daten["AC_Scheinleistung_T"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Wirkleistung_R=".$daten["AC_Wirkleistung_R"];
        $query .= ",Wirkleistung_S=".$daten["AC_Wirkleistung_S"];
        $query .= ",Wirkleistung_T=".$daten["AC_Wirkleistung_T"];
        $query .= ",Eingangsleistung=".$daten["AC_Eingangsleistung"];
        $query .= ",Eingangsleistung_R=".$daten["AC_Eingangsleistung_R"];
        $query .= ",Eingangsleistung_S=".$daten["AC_Eingangsleistung_S"];
        $query .= ",Eingangsleistung_T=".$daten["AC_Eingangsleistung_T"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Spannung_WR=".$daten["Batteriespannung"]; // muss noch geändert werden!
        $query .= ",Ladestrom=".$daten["Batteriestrom"];
        $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
        $query .= ",Entladestrom=".$daten["Batteriestrom"];
        $query .= ",MaxLadestrom=".$daten["BatterieMaxLadestrom"];
        $query .= ",MaxLadestromSet=".$daten["BatterieMaxLadestromSet"];
        $query .= ",MaxEntladestromSet=".$daten["BatterieMaxEntladestromSet"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung1"];
        $query .= ",Spannung1=".$daten["Solarspannung1"];
        $query .= ",Spannung2=".$daten["Solarspannung2"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",Leistung1=".$daten["Solarleistung1"];
        $query .= ",Leistung2=".$daten["Solarleistung2"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Strom1=".$daten["Solarstrom1"];
        $query .= ",Strom2=".$daten["Solarstrom2"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Laststatus=0";
        $query .= ",Ladestatus=".$daten["Solar_Status1"];
        $query .= ",Ladestatus2=".$daten["Solar_Status2"];
        $query .= ",Stromrichtung_Batt=".$daten["Batteriestromrichtung"];
        $query .= ",Stromrichtung_WR=".$daten["WR_Stromrichtung"];
        $query .= ",Stromrichtung_Netz=".$daten["Netzstromrichtung"];
        $query .= ",Modus=".$daten["Modus"];
        $query .= ",Device_Status=0"; // *
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",Batterie_Temperatur=".$daten["Batterie_Temperatur"];
        $query .= ",Fehlercode=".$daten["ErrorCodes"]; // *
        $query .= ",Warnungen=".$daten["Warnungen"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Gesamt_Monat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_Gesamt_Jahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",kWh_Total=".$daten["KiloWattstundenTotal"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* SolarMax   */
      case 10:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Spannung_R=".$daten["AC_Ausgangsspannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Ausgangsspannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Ausgangsspannung_T"];
        $query .= ",Strom_R=".$daten["AC_Ausgangsstrom_R"];
        $query .= ",Strom_S=".$daten["AC_Ausgangsstrom_S"];
        $query .= ",Strom_T=".$daten["AC_Ausgangsstrom_T"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",Spannung_String_1=".$daten["Solarspannung_String_1"];
        $query .= ",Spannung_String_2=".$daten["Solarspannung_String_2"];
        $query .= ",Spannung_String_3=".$daten["Solarspannung_String_3"];
        $query .= ",Strom_String_1=".$daten["Solarstrom_String_1"];
        $query .= ",Strom_String_2=".$daten["Solarstrom_String_2"];
        $query .= ",Strom_String_3=".$daten["Solarstrom_String_3"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Betriebsstunden=".$daten["Betriebsstunden"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= ",Errorcode=".$daten["ErrorCodes"];
        $query .= ",Status=".$daten["Geraetestatus"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt_Gestern=".$daten["WattstundenGesamtGestern"];
        $query .= ",Wh_Gesamt_Monat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_Gesamt_Jahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Phoenix Victron   */
      case 11:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Warnungen=".$daten["Warnungen"];
        $query .= ",Mode=".$daten["Mode"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Fronius Symo Wechselrichter    */
      case 12:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        if (isset($daten["Solarspannung_String_1"])) {
          $query .= ",Spannung_String_1=".$daten["Solarspannung_String_1"];
          $query .= ",Strom_String_1=".$daten["Solarstrom_String_1"];
          $query .= ",Leistung_String_1=".$daten["Solarleistung_String_1"];
        }
        if (isset($daten["Solarspannung_String_2"])) {
          $query .= ",Spannung_String_2=".$daten["Solarspannung_String_2"];
          $query .= ",Strom_String_2=".$daten["Solarstrom_String_2"];
          $query .= ",Leistung_String_2=".$daten["Solarleistung_String_2"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        if ($daten["Storage"] == 1) {
          $query .= "Batterie ";
          $query .= "Max_Kapazitaet=".$daten["Batterie_Max_Kapazitaet"];
          $query .= ",Strom=".$daten["Batterie_Strom_DC"];
          $query .= ",Spannung=".$daten["Batterie_Spannung_DC"];
          $query .= ",Status_Zellen=".$daten["Batterie_Status_Batteriezellen"];
          $query .= ",Temperatur=".$daten["Batterie_Zellentemperatur"];
          $query .= ",Status=".$daten["Batterie_StateOfCharge_Relative"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "Device_Status=".$daten["Geraetestatus"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",Fehlercode=".$daten["ErrorCodes"];
        $query .= ",ModulPVLeistung=".$daten["ModulPVLeistung"];
        $query .= ",Meter_Location=\"".$daten["Meter_Location"]."\"";
        $query .= ",Mode=\"".$daten["Mode"]."\"";
        $query .= ",Autonomie=".round($daten["Rel_Autonomy"], 1);
        $query .= ",Eigenverbrauch=".$daten["Rel_SelfConsumption"];
        $query .= ",Autonomie=".$daten["Rel_Autonomy"];
        $query .= ",Akkustand_SOC=".$daten["Akkustand_SOC"];
        if (isset($daten["Gen24Status"])) {
          $query .= ",Gen24_Status=\"".$daten["Gen24Status"]."\"";
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Gesamt_Jahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",SummeWattstundenGesamtHeute=".round($daten["SummeWattstundenGesamtHeute"], 1);
        $query .= ",SummeWattstundenGesamt=".round($daten["SummeWattstundenGesamt"], 1);
        $query .= ",SummeWattstundeGesamtJahr=".round($daten["SummeWattstundenGesamtJahr"], 1);
        $query .= ",SummePowerGrid=".$daten["SummePowerGrid"];
        $query .= ",SummePowerLoad=".round($daten["SummePowerLoad"], 1);
        $query .= ",SummePowerAkku=".$daten["SummePowerAkku"];
        $query .= ",SummePowerPV=".$daten["SummePowerPV"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        if ($daten["Meter"] > 0 and isset($daten["Meter1_Wirkleistung"])) {
          $query .= "Meter ";
          $query .= "PVLeistung=".$daten["Solarleistung"];
          $query .= ",Wirkleistung=".$daten["Meter1_Wirkleistung"];
          $query .= ",Scheinleistung=".$daten["Meter1_Scheinleistung"];
          $query .= ",Blindleistung=".$daten["Meter1_Blindleistung"];
          $query .= ",EnergieProduziert=".$daten["Meter1_EnergieProduziert"];
          $query .= ",EnergieVerbraucht=".$daten["Meter1_EnergieVerbraucht"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Verbrauch=".$daten["Verbrauch"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Meter"] > 1 or isset($daten["Meter2_Wirkleistung"])) {
          $query .= "Meter2 ";
          $query .= "PVLeistung=".$daten["Solarleistung"];
          $query .= ",Wirkleistung=".$daten["Meter2_Wirkleistung"];
          $query .= ",Scheinleistung=".$daten["Meter2_Scheinleistung"];
          $query .= ",Blindleistung=".$daten["Meter2_Blindleistung"];
          $query .= ",EnergieProduziert=".$daten["Meter2_EnergieProduziert"];
          $query .= ",EnergieVerbraucht=".$daten["Meter2_EnergieVerbraucht"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Meter"] > 2 or isset($daten["Meter3_Wirkleistung"])) {
          $query .= "Meter3 ";
          $query .= "PVLeistung=".$daten["Solarleistung"];
          $query .= ",Wirkleistung=".$daten["Meter3_Wirkleistung"];
          $query .= ",Scheinleistung=".$daten["Meter3_Scheinleistung"];
          $query .= ",Blindleistung=".$daten["Meter3_Blindleistung"];
          $query .= ",EnergieProduziert=".$daten["Meter3_EnergieProduziert"];
          $query .= ",EnergieVerbraucht=".$daten["Meter3_EnergieVerbraucht"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        // if ($daten["Ohmpilot"] == 1 and $daten["Gen24"] == false) { // geändert 1.5.2023
        if ($daten["Ohmpilot"] == 1) { //
          $query .= "Ohmpilot ";
          $query .= "Wirkleistung=".$daten["Ohmpilot_Wirkleistung"];
          $query .= ",EnergieGesamt=".$daten["Ohmpilot_EnergieGesamt"];
          $query .= ",Temperatur=".$daten["Ohmpilot_Temperatur"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Storage"] == 1 and $daten["Gen24"] == false) {
          $query .= "Batterie ";
          $query .= "Max_Kapazitaet=".$daten["Batterie_Max_Kapazitaet"];
          $query .= ",Strom=".$daten["Batterie_Strom_DC"];
          $query .= ",Spannung=".$daten["Batterie_Spannung_DC"];
          $query .= ",Hersteller=\"".$daten["Batterie_Hersteller"]."\"";
          $query .= ",Seriennummer=\"".$daten["Batterie_Seriennummer"]."\"";
          $query .= ",LadestatusProzent=".$daten["Batterie_StateOfCharge_Relative"];
          $query .= ",Zellenstatus=".$daten["Batterie_Status_Batteriezellen"];
          $query .= ",Temperatur=".$daten["Batterie_Zellentemperatur"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        break;

        /* Joulie-16 BMS   */
      case 13:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Z1V=".$daten["Zelle1Volt"];
        $query .= ",Z1S=".$daten["Zelle1Status"];
        $query .= ",Z2V=".$daten["Zelle2Volt"];
        $query .= ",Z2S=".$daten["Zelle2Status"];
        $query .= ",Z3V=".$daten["Zelle3Volt"];
        $query .= ",Z3S=".$daten["Zelle3Status"];
        $query .= ",Z4V=".$daten["Zelle4Volt"];
        $query .= ",Z4S=".$daten["Zelle4Status"];
        $query .= ",Z5V=".$daten["Zelle5Volt"];
        $query .= ",Z5S=".$daten["Zelle5Status"];
        $query .= ",Z6V=".$daten["Zelle6Volt"];
        $query .= ",Z6S=".$daten["Zelle6Status"];
        $query .= ",Z7V=".$daten["Zelle7Volt"];
        $query .= ",Z7S=".$daten["Zelle7Status"];
        $query .= ",Z8V=".$daten["Zelle8Volt"];
        $query .= ",Z8S=".$daten["Zelle8Status"];
        $query .= ",Z9V=".$daten["Zelle9Volt"];
        $query .= ",Z9S=".$daten["Zelle9Status"];
        $query .= ",Z10V=".$daten["Zelle10Volt"];
        $query .= ",Z10S=".$daten["Zelle10Status"];
        $query .= ",Z11V=".$daten["Zelle11Volt"];
        $query .= ",Z11S=".$daten["Zelle11Status"];
        $query .= ",Z12V=".$daten["Zelle12Volt"];
        $query .= ",Z12S=".$daten["Zelle12Status"];
        $query .= ",Z13V=".$daten["Zelle13Volt"];
        $query .= ",Z13S=".$daten["Zelle13Status"];
        $query .= ",Z14V=".$daten["Zelle14Volt"];
        $query .= ",Z14S=".$daten["Zelle14Status"];
        $query .= ",Z15V=".$daten["Zelle15Volt"];
        $query .= ",Z15S=".$daten["Zelle15Status"];
        $query .= ",Z16V=".$daten["Zelle16Volt"];
        $query .= ",Z16S=".$daten["Zelle16Status"];
        $query .= ",Strom=".$daten["Strom"];
        $query .= ",Spannung=".$daten["Spannung"];
        $query .= ",Kapazitaet=".$daten["Kapazitaet"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Balancing=".$daten["Balancing"];
        $query .= ",Fehlercode=\"".$daten["Fehlercode"]."\"";
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  PylonTech US2000x  und US3000C  */
      case 15:
      case 41:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        for ($i = 1; $i <= $daten["Packs"]; $i++) {
          $query .= "Pack".$i." ";
          $query .= "Zellen=".$daten["Pack".$i."_Zellen"];
          $query .= ",Zelle1=".$daten["Pack".$i."_Zelle1"];
          $query .= ",Zelle2=".$daten["Pack".$i."_Zelle2"];
          $query .= ",Zelle3=".$daten["Pack".$i."_Zelle3"];
          $query .= ",Zelle4=".$daten["Pack".$i."_Zelle4"];
          $query .= ",Zelle5=".$daten["Pack".$i."_Zelle5"];
          $query .= ",Zelle6=".$daten["Pack".$i."_Zelle6"];
          $query .= ",Zelle7=".$daten["Pack".$i."_Zelle7"];
          $query .= ",Zelle8=".$daten["Pack".$i."_Zelle8"];
          $query .= ",Zelle9=".$daten["Pack".$i."_Zelle9"];
          $query .= ",Zelle10=".$daten["Pack".$i."_Zelle10"];
          $query .= ",Zelle11=".$daten["Pack".$i."_Zelle11"];
          $query .= ",Zelle12=".$daten["Pack".$i."_Zelle12"];
          $query .= ",Zelle13=".$daten["Pack".$i."_Zelle13"];
          $query .= ",Zelle14=".$daten["Pack".$i."_Zelle14"];
          $query .= ",Zelle15=".$daten["Pack".$i."_Zelle15"];
          $query .= ",Temp_Anz=".$daten["Pack".$i."_Temp_Anz"];
          $query .= ",Temp1=".$daten["Pack".$i."_Temp1"];
          $query .= ",Temp2=".$daten["Pack".$i."_Temp2"];
          $query .= ",Temp3=".$daten["Pack".$i."_Temp3"];
          $query .= ",Temp4=".$daten["Pack".$i."_Temp4"];
          $query .= ",Temp5=".$daten["Pack".$i."_Temp5"];
          if ($daten["Pack".$i."_Temp_Anz"] >= 6) {
            $query .= ",Temp6=".$daten["Pack".$i."_Temp6"];
          }
          $query .= ",Strom=".$daten["Pack".$i."_Strom"];
          $query .= ",Spannung=".$daten["Pack".$i."_Spannung"];
          $query .= ",Ah_left=".$daten["Pack".$i."_Ah_left"];
          $query .= ",Ah_total=".$daten["Pack".$i."_Ah_total"];
          if ($daten["Regler"] == 41) {
            $query .= ",Ah_left_2=".$daten["Pack".$i."_Ah_left_2"];
            $query .= ",Ah_total_2=".$daten["Pack".$i."_Ah_total_2"];
          }
          $query .= ",Cycle=".$daten["Pack".$i."_cycle"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "Warnungen_Pack".$i." ";
          $query .= "Zelle1=".$daten["Pack".$i."_Warn_Zelle1"];
          $query .= ",Zelle2=".$daten["Pack".$i."_Warn_Zelle2"];
          $query .= ",Zelle3=".$daten["Pack".$i."_Warn_Zelle3"];
          $query .= ",Zelle4=".$daten["Pack".$i."_Warn_Zelle4"];
          $query .= ",Zelle5=".$daten["Pack".$i."_Warn_Zelle5"];
          $query .= ",Zelle6=".$daten["Pack".$i."_Warn_Zelle6"];
          $query .= ",Zelle7=".$daten["Pack".$i."_Warn_Zelle7"];
          $query .= ",Zelle8=".$daten["Pack".$i."_Warn_Zelle8"];
          $query .= ",Zelle9=".$daten["Pack".$i."_Warn_Zelle9"];
          $query .= ",Zelle10=".$daten["Pack".$i."_Warn_Zelle10"];
          $query .= ",Zelle11=".$daten["Pack".$i."_Warn_Zelle11"];
          $query .= ",Zelle12=".$daten["Pack".$i."_Warn_Zelle12"];
          $query .= ",Zelle13=".$daten["Pack".$i."_Warn_Zelle13"];
          $query .= ",Zelle14=".$daten["Pack".$i."_Warn_Zelle14"];
          $query .= ",Zelle15=".$daten["Pack".$i."_Warn_Zelle15"];
          $query .= ",Temp1=".$daten["Pack".$i."_Warn_Temp1"];
          $query .= ",Temp2=".$daten["Pack".$i."_Warn_Temp2"];
          $query .= ",Temp3=".$daten["Pack".$i."_Warn_Temp3"];
          $query .= ",Temp4=".$daten["Pack".$i."_Warn_Temp4"];
          $query .= ",Temp5=".$daten["Pack".$i."_Warn_Temp5"];
          if ($daten["Pack".$i."_Temp_Anz"] >= 6) {
            $query .= ",Temp6=".$daten["Pack".$i."_Warn_Temp6"];
          }
          $query .= ",Ladestrom=".$daten["Pack".$i."_Warn_LadeStrom"];
          $query .= ",Spannung=".$daten["Pack".$i."_Warn_Spannung"];
          $query .= ",Entladestrom=".$daten["Pack".$i."_Warn_Entladestrom"];
          $query .= ",Status1=".$daten["Pack".$i."_Warn_Status1"];
          $query .= ",Status2=".$daten["Pack".$i."_Warn_Status2"];
          $query .= ",Status3=".$daten["Pack".$i."_Warn_Status3"];
          $query .= ",Status4=".$daten["Pack".$i."_Warn_Status4"];
          $query .= ",Status5=".$daten["Pack".$i."_Warn_Status5"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "Anzahl_Packs=".$daten["Packs"];
        for ($i = 1; $i <= $daten["Packs"]; $i++) {
          $query .= ",Pack".$i."_Status=".$daten["Pack".$i."_Status"];
        }
        $Summe = 0;
        for ($i = 1; $i <= $daten["Packs"]; $i++) {
          $Summe = $Summe + $daten["Pack".$i."_Strom"];
        }
        $query .= ",Be_Entladung=".$Summe;
        // Summe Ampere/h alle Batterien
        $Summe = 0;
        for ($i = 1; $i <= $daten["Packs"]; $i++) {
          if ($daten["Regler"] == 41 and isset($daten["Pack".$i."_Ah_left_2"]) and $daten["Pack".$i."_Ah_left_2"] > 0) {
            $Summe = $Summe + $daten["Pack".$i."_Ah_left_2"];
          }
          else {
            $Summe = $Summe + $daten["Pack".$i."_Ah_left"];
          }
        }
        $query .= ",Restkapazitaet_Gesamt=".$Summe;
        // SOC alle Batterien
        // SOC berechnen us3000 74Ah - us2000 50Ah
        if ($daten["Regler"] == 41) {
          $query .= ",SOC=".$daten["SOC"];
        }
        else {
          $query .= ",SOC=".$Summe / ((50 * $daten["Packs"]) / 100);
        }
        $Summe = 0;
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        if ($daten["Regler"] == 41) {
          $query .= "SOC=".$daten["SOC"];
        }
        else {
          $query .= "SOC=".$Summe / ((50 * $daten["Packs"]) / 100);
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  SolarEdge   */
      case 16:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        if (isset($daten["Batterie1Modell"])) {
          $query .= "Batterie ";
          $query .= "SOC=".$daten["Batterie1StatusSOE"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *            $query .= "Batterie_1 ";
          $query .= "Batterie_1 ";
          $query .= "Temperatur=".$daten["Batterie1Temp"];
          $query .= ",Spannung=".$daten["Batterie1Spannung"];
          $query .= ",Strom=".$daten["Batterie1Strom"];
          $query .= ",Leistung=".$daten["Batterie1Leistung"];
          $query .= ",SOH=".$daten["Batterie1StatusSOH"];
          $query .= ",SOE=".$daten["Batterie1StatusSOE"];
          $query .= ",Status=".$daten["Batterie1Status"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        if (isset($daten["Batterie2Modell"])) {
          $query .= "Batterie_2 ";
          $query .= "Temperatur=".$daten["Batterie2Temp"];
          $query .= ",Spannung=".$daten["Batterie2Spannung"];
          $query .= ",Strom=".$daten["Batterie2Strom"];
          $query .= ",Leistung=".$daten["Batterie2Leistung"];
          $query .= ",SOH=".$daten["Batterie2StatusSOH"];
          $query .= ",SOE=".$daten["Batterie2StatusSOE"];
          $query .= ",Status=".$daten["Batterie2Status"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        if (isset($aktuelleDaten["M1_AC_Spannung"])) {
          $query .= "Spannung=".$daten["M1_AC_Spannung"];
        }
        else {
          $query .= "Spannung=".$daten["AC_Spannung_R"];
        }
        $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Wirkungsgrad=".$daten["AC_Leistung_Prozent"];
        $query .= ",Verbrauch=".$daten["AC_Verbrauch"];
        $query .= ",Bezug=".$daten["AC_Bezug"];
        $query .= ",Einspeisung=".$daten["AC_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["DC_Spannung"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= ",Strom=".$daten["DC_Strom"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",Status=".$daten["Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["AC_Wh_Gesamt"];
        $query .= ",Wh_Gesamt_Export=".$daten["M1_AC_Exportgesamt_Wh"];
        $query .= ",Wh_Gesamt_Import=".$daten["M1_AC_Importgesamt_Wh"];
        if (isset($daten["M2_Version"])) {
          $query .= ",Wh_Gesamt_Export_M2=".$daten["M2_AC_Exportgesamt_Wh"];
          $query .= ",Wh_Gesamt_Import_M2=".$daten["M2_AC_Importgesamt_Wh"];
        }
        if (isset($daten["M3_Version"])) {
          $query .= ",Wh_Gesamt_Export_M3=".$daten["M3_AC_Exportgesamt_Wh"];
          $query .= ",Wh_Gesamt_Import_M3=".$daten["M3_AC_Importgesamt_Wh"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* KOSTAL Plenticore Serie   */
      case 17:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        if ($daten["AnzahlPhasen"] == 3) {
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        }
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= ",Verbrauch=".$daten["Verbrauch"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Ueberschuss=".$daten["Ueberschuss"];
        $query .= ",Solarleistung=".$daten["AC_Solarleistung"];
        $query .= ",Verbrauch_Netz=".$daten["Verbrauch_Netz"];
        $query .= ",Verbrauch_Batterie=".$daten["Verbrauch_Batterie"];
        $query .= ",Verbrauch_PV=".$daten["Verbrauch_PV"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Strom=".$daten["Batteriestrom"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= ",Temperatur=".$daten["Batterie_Temperatur"];
        $query .= ",Anzahl_Zyklen=".$daten["Bat_Cycles"];
        $query .= ",Lade_Entladeleistung=".$daten["Bat_Charge_Power"];
        $query .= ",BatterieStatus=\"".$daten["BatterieStatus"]."\"";
        $query .= ",Bat_Act_SOC=".$daten["Bat_Act_SOC"];
        if ($daten["Softwarestand"] > "01.44") {
          $query .= ",Max_Charge_Limit=".$daten["Max_Charge_Limit"];
          $query .= ",Max_Discharge_Limit=".$daten["Max_Discharge_Limit"];
          $query .= ",Max_SOC_Rel=".$daten["Max_SOC_Rel"];
          $query .= ",Min_SOC_Rel=".$daten["Min_SOC_Rel"];
          $query .= ",ExternalControl=".$daten["ExternalControl"];
          $query .= ",Bat_Work_Capacity=".$daten["Bat_Work_Capacity"];
          $query .= ",Bat_Seriennummer=\"".$daten["Bat_Seriennummer"]."\"";
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Gesamtleistung=".$daten["PV_Leistung"];
        $query .= ",Spannung_Str_1=".$daten["PV1_Spannung"];
        $query .= ",Leistung_Str_1=".$daten["PV1_Leistung"];
        $query .= ",Strom_Str_1=".$daten["PV1_Strom"];
        if ($daten["AnzahlStrings"] > 1) {
          $query .= ",Spannung_Str_2=".$daten["PV2_Spannung"];
          $query .= ",Strom_Str_2=".$daten["PV2_Strom"];
          $query .= ",Leistung_Str_2=".$daten["PV2_Leistung"];
        }
        if ($daten["AnzahlStrings"] > 2) {
          $query .= ",Spannung_Str_3=".$daten["PV3_Spannung"];
          $query .= ",Strom_Str_3=".$daten["PV3_Strom"];
          $query .= ",Leistung_Str_3=".$daten["PV3_Leistung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Status=".$daten["Status"];
        $query .= ",Temp_WR_Cab=".$daten["Temp_WR_Cab"];
        $query .= ",Temp_WR_Sink=".$daten["Temp_WR_Sink"];
        $query .= ",Temp_WR_Trans=".$daten["Temp_WR_Trans"];
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
        $query .= ",DC_Gesamtleistung=".$daten["Total_DC_Power"];
        $query .= ",Laufzeit=".$daten["Laufzeit"];
        $query .= ",WirkungsgradWR=".$daten["WirkungsgradWR"];
        $query .= ",Energiemanager_Status=".$daten["Energiemanager_Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Gesamt_Monat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_Gesamt_Jahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Gesamtverbrauch_Netz=".$daten["Gesamtverbrauch_Netz"];
        $query .= ",Wh_Gesamtverbrauch_PV=".$daten["Gesamtverbrauch_PV"];
        $query .= ",Wh_Gesamtverbrauch_Batterie=".$daten["Gesamtverbrauch_Batterie"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Wechselrichter S10E nd S10 mini von E3/DC  */
      case 18:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Verbrauch=".$daten["AC_Verbrauch"];
        $query .= ",Bezug=".$daten["AC_Bezug"];
        $query .= ",Solarleistung=".$daten["PV_Leistung"];
        $query .= ",AC_Wallbox_Leistung=".$daten["AC_Leistung_Wallbox"];
        $query .= ",AutarkieProzent=".$daten["Autarkie"];
        $query .= ",VerbrauchProzent=".$daten["Verbrauch"];
        $query .= ",Leistungsmesser=".$daten["Leistungsmesser"];
        $query .= ",Leistung_L1_in_Watt=".$daten["Phasenleistung_L1"];
        $query .= ",Leistung_L2_in_Watt=".$daten["Phasenleistung_L2"];
        $query .= ",Leistung_L3_in_Watt=".$daten["Phasenleistung_L3"];
        $query .= ",ExterneLeistung=".$daten["AC_Zusatzleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "SOC=".$daten["SOC"];
        $query .= ",Leistung=".$daten["Batterie_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Gesamtleistung=".$daten["PV_Leistung"];
        $query .= ",String1_Spannung=".$daten["DC_String1_Spannung"];
        $query .= ",String1_Strom=".$daten["DC_String1_Strom"];
        $query .= ",String1_Leistung=".$daten["DC_String1_Leistung"];
        $query .= ",String2_Spannung=".$daten["DC_String2_Spannung"];
        $query .= ",String2_Strom=".$daten["DC_String2_Strom"];
        $query .= ",String2_Leistung=".$daten["DC_String2_Leistung"];
        $query .= ",String3_Spannung=".$daten["DC_String3_Spannung"];
        $query .= ",String3_Strom=".$daten["DC_String3_Strom"];
        $query .= ",String3_Leistung=".$daten["DC_String3_Leistung"];
        $query .= ",PV_Wallbox_Leistung=".$daten["PV_Leistung_Wallbox"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        if (isset($daten["Wallbox_CTRL"])) {
          $query .= "Wallbox ";
          $query .= "CTRL=".$daten["Wallbox_CTRL"];
          $query .= ",Aktiv=".$daten["Wallbox_Aktiv"];
          $query .= ",Modus=".$daten["Wallbox_Modus"];
          $query .= ",Laden=".$daten["Wallbox_Laden"];
          $query .= ",Auto=".$daten["Wallbox_Auto"];
          $query .= ",verriegelt=".$daten["Wallbox_verriegelt"];
          $query .= ",gesteckt=".$daten["Wallbox_gesteckt"];
          $query .= ",3Ph_16A=".$daten["Wallbox_3Ph_16A"];
          $query .= ",3Ph_32A=".$daten["Wallbox_3Ph_32A"];
          $query .= ",Kabel_Ph=".$daten["Wallbox_Kabel_Ph"];
          $query .= ",AC_Leistung=".$daten["AC_Leistung_Wallbox"];
          $query .= ",PV_Leistung=".$daten["PV_Leistung_Wallbox"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if (isset($daten["EMS_CTRL"])) {
          $query .= "Notstrom ";
          $query .= "CTRL=".$daten["EMS_CTRL"];
          $query .= ",Laden=".$daten["EMS_Laden"];
          $query .= ",Entladen=".$daten["EMS_Entladen"];
          $query .= ",Notstrom=".$daten["EMS_Notstrom"];
          $query .= ",Wetter=".$daten["EMS_Wetter"];
          $query .= ",Abregelung=".$daten["EMS_Abregelung"];
          $query .= ",Ladesperre=".$daten["EMS_Ladesperre"];
          $query .= ",Entladesperre=".$daten["EMS_Entladesperre"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "Modell=\"".$daten["Modell"]."\"";
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
        $query .= ",PowerStatus=".$daten["Power_Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 19: // eSmart3 Laderegler
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".substr($daten["Firmware"], 1);
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Ladestrom=".$daten["Batterieladestrom"];
        $query .= ",Entladestrom=".$daten["Verbraucherstrom"];
        $query .= ",Entladeleistung=".$daten["Verbraucherleistung"];
        $query .= ",Temperatur=".$daten["Batterietemperatur"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",Fehler=".$daten["ErrorCode"];
        $query .= ",Ladestatus=".$daten["Ladestatus"];
        $query .= ",Auslastung=".$daten["Auslastung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_GesamtMonat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  SolarEdge ohne MODBUS Zähler   */
      case 20:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Wirkungsgrad=".$daten["AC_Leistung_Prozent"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["DC_Spannung"];
        $query .= ",Leistung=".$daten["DC_Leistung"];
        $query .= ",Strom=".$daten["DC_Strom"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",Status=".$daten["Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["AC_Wh_Gesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Kostal Pico   */
      case 21:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
        $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
        $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "String1_Spannung=".$daten["PV_Spannung_1"];
        $query .= ",String2_Spannung=".$daten["PV_Spannung_2"];
        $query .= ",String3_Spannung=".$daten["PV_Spannung_3"];
        $query .= ",String1_Strom=".$daten["PV_Strom_1"];
        $query .= ",String2_Strom=".$daten["PV_Strom_2"];
        $query .= ",String3_Strom=".$daten["PV_Strom_3"];
        $query .= ",String1_Leistung=".$daten["PV_Leistung_1"];
        $query .= ",String2_Leistung=".$daten["PV_Leistung_2"];
        $query .= ",String3_Leistung=".$daten["PV_Leistung_3"];
        $query .= ",String1_Nummer=\"1\"";
        $query .= ",String2_Nummer=\"2\"";
        $query .= ",String3_Nummer=\"3\"";
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Strom=".$daten["Batteriestrom"];
        $query .= ",Leistung=".$daten["Batterieleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "FehlerCode=".$daten["FehlerCode"];
        $query .= ",Status=".$daten["Status"];
        $query .= ",Fehler=".$daten["Fehler"];
        $query .= ",Strings=".$daten["Strings"];
        $query .= ",Phasen=".$daten["Phasen"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["AC_Wh_Gesamt"];
        $query .= ",AC_Leistung_Gesamt=".$daten["AC_Leistung"];
        $query .= ",PV_Leistung_Gesamt=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  KOSTAL Smart Energy Meter   */
      case 22:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["Voltage_L1"];
        $query .= ",Spannung_S=".$daten["Voltage_L2"];
        $query .= ",Spannung_T=".$daten["Voltage_L3"];
        $query .= ",Strom_R=".$daten["Current_L1"];
        $query .= ",Strom_S=".$daten["Current_L2"];
        $query .= ",Strom_T=".$daten["Current_L3"];
        $query .= ",Leistung_pos=".$daten["Active_powerP"];
        $query .= ",Leistung_pos_R=".$daten["Active_powerP_L1"];
        $query .= ",Leistung_neg_R=".$daten["Active_powerM_L1"];
        $query .= ",Leistung_pos_S=".$daten["Active_powerP_L2"];
        $query .= ",Leistung_neg_S=".$daten["Active_powerM_L2"];
        $query .= ",Leistung_pos_T=".$daten["Active_powerP_L3"];
        $query .= ",Leistung_neg_T=".$daten["Active_powerM_L3"];
        $query .= ",Blindleistung_pos=".$daten["Reactive_powerP"];
        $query .= ",Scheinleistung_pos=".$daten["Apparent_powerP"];
        $query .= ",Leistung_neg=".$daten["Active_powerM"];
        $query .= ",Blindleistung_neg=".$daten["Reactive_powerM"];
        $query .= ",Scheinleistung_neg=".$daten["Apparent_powerM"];
        $query .= ",Frequenz=".$daten["Frequency"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt_Leistung_pos=".$daten["Active_energyP"];
        $query .= ",Wh_Gesamt_Blindleistung_pos=".$daten["Reactive_energyP"];
        $query .= ",Wh_Gesamt_Scheinleistung_pos=".$daten["Apparent_energyP"];
        $query .= ",Wh_Gesamt_Leistung_neg=".$daten["Active_energyM"];
        $query .= ",Wh_Gesamt_Blindleistung_neg=".$daten["Reactive_energyM"];
        $query .= ",Wh_Gesamt_Scheinleistung_neg=".$daten["Apparent_energyM"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* SONOFF POW R2, TH16 R2 und Gosund SP1  */
        // SonoffModul 1 = Sonoff basic
        //             4 = Sonoff TH 10/16
        //             6 = Sonoff POW R2
        //            43 = Sonoff POW R2
        //            46 = Shelly 1
        //            53 = Jogis Delock
        //            55 = Gosund SP1
        //           200 = Shelly 2.5
        //           201 = Shelly 1PM
        //           204 = Sonoff POW R3
        //
      case 23:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        // --------------------------
        if ($daten["SonoffModul"] == 0 or $daten["SonoffModul"] == 43 or $daten["SonoffModul"] == 53 or $daten["SonoffModul"] == 55 or $daten["SonoffModul"] == 6) { // POW + Gosung SP1
          $query .= "AC ";
          $query .= "Spannung=".$daten["AC_Spannung"];
          $query .= ",Strom=".$daten["AC_Strom"];
          $query .= ",Leistung=".$daten["AC_Leistung"];
          $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
          $query .= ",Blindleistung=".$daten["AC_Blindleistung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 200 or $daten["SonoffModul"] == 204) { // Shelly 2.5 oder Sonoff POW R3
          $query .= "AC ";
          $query .= "Spannung=".$daten["AC_Spannung"];
          $query .= ",Strom1=".$daten["AC_Strom0"];
          $query .= ",Leistung1=".$daten["AC_Leistung0"];
          $query .= ",Scheinleistung1=".$daten["AC_Scheinleistung0"];
          $query .= ",Blindleistung1=".$daten["AC_Blindleistung0"];
          $query .= ",Strom2=".$daten["AC_Strom1"];
          $query .= ",Leistung2=".$daten["AC_Leistung1"];
          $query .= ",Scheinleistung2=".$daten["AC_Scheinleistung1"];
          $query .= ",Blindleistung2=".$daten["AC_Blindleistung1"];
          if ($daten["SonoffModul"] == 200) {
            $query .= ",Frequenz=".$daten["AC_Frequenz"];
          }
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 201) { // Shelly 1PM
          $query .= "AC ";
          $query .= "Spannung=".$daten["AC_Spannung"];
          $query .= ",Strom=".$daten["AC_Strom"];
          $query .= ",Leistung=".$daten["AC_Leistung"];
          $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
          $query .= ",Blindleistung=".$daten["AC_Blindleistung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 300) { // IR_Lesekopf
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= ",Leistung_R=".$daten["L1_Watt"];
          $query .= ",Leistung_S=".$daten["L2_Watt"];
          $query .= ",Leistung_T=".$daten["L3_Watt"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 301) { // Sensor MT631 IR Zähler
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 302) { // IR_Lesekopf
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 303) { // IR_Lesekopf
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 304 or $daten["SonoffModul"] == 305) { // IR_Lesekopf
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= ",Leistung_R=".$daten["Leistung_R"];
          $query .= ",Leistung_S=".$daten["Leistung_S"];
          $query .= ",Leistung_T=".$daten["Leistung_T"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 306) { // IR_Lesekopf
          $query .= "AC ";
          $query .= "Leistung=".$daten["Leistung"];
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
          $query .= ",Volt_R=".$daten["Volt_R"];
          $query .= ",Volt_S=".$daten["Volt_S"];
          $query .= ",Volt_T=".$daten["Volt_T"];
          $query .= ",Ampere_R=".$daten["Ampere_R"];
          $query .= ",Ampere_S=".$daten["Ampere_S"];
          $query .= ",Ampere_T=".$daten["Ampere_T"];
          $query .= ",Phasenwinkel_R=".$daten["Phasenwinkel_R"];
          $query .= ",Phasenwinkel_S=".$daten["Phasenwinkel_S"];
          $query .= ",Phasenwinkel_T=".$daten["Phasenwinkel_T"];
          $query .= ",Frequenz=".$daten["Frequenz"];
          if ($daten["Sensor"] == "LK13SML") {
            $query .= ",Leistung_R=".$daten["Volt_R"];
            $query .= ",Leistung_S=".$daten["Volt_S"];
            $query .= ",Leistung_T=".$daten["Volt_T"];
          }
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        // --------------------------
        $query .= "Service ";
        $query .= "Status=\"".$daten["Status"]."\"";
        if ($daten["SonoffModul"] == 4) { //TH16 R2
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",Masseinheit=\"".$daten["Masseinheit"]."\"";
          $query .= ",Luftfeuchte=".$daten["Luftfeuchte"];
        }
        if ($daten["SonoffModul"] == 200) { // Shelly 2.5
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",Powerstatus1=".$daten["Powerstatus0"];
          $query .= ",Powerstatus2=".$daten["Powerstatus1"];
        }
        if ($daten["SonoffModul"] == 201) { // Shelly 1PM
          $query .= ",Temperatur=".$daten["Temperatur"];
        }
        if ($daten["SonoffModul"] == 0 or $daten["SonoffModul"] == 53) { // Alles unbekannte
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",Powerstatus1=".$daten["Powerstatus0"];
          $query .= ",Powerstatus2=".$daten["Powerstatus1"];
          $query .= ",Powerstatus=".$daten["Powerstatus"];
        }
        else {
          $query .= ",Powerstatus=".$daten["Powerstatus"];
        }
        if ($daten["SonoffModul"] == 46) { // 3 Temperatursensoren
          if (isset($daten["Temperatur"])) {
            $query .= ",Temperatur1=".$daten["Temperatur"];
            $query .= ",Masseinheit=\"".$daten["Masseinheit"]."\"";
          }
          if (isset($daten["Temperatur-1"])) {
            $query .= ",Temperatur1=".$daten["Temperatur-1"];
            $query .= ",Masseinheit=\"".$daten["Masseinheit"]."\"";
          }
          if (isset($daten["Temperatur-2"])) {
            $query .= ",Temperatur2=".$daten["Temperatur-2"];
          }
          if (isset($daten["Temperatur-3"])) {
            $query .= ",Temperatur3=".$daten["Temperatur-3"];
          }
        }
        if ($daten["SonoffModul"] == 307) { // IR_Lesekopf WAERME
          $query .= ",Leistung=".$daten["Leistung"];
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",Durchfluss=".$daten["Durchfluss"];
          $query .= ",Ruecklauf_Temp=".$daten["Ruecklauf_Temp"];
          $query .= ",Temp_differenz=".$daten["Temp_differenz"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        // --------------------------
        if ($daten["SonoffModul"] == 201 or $daten["SonoffModul"] == 204 or $daten["SonoffModul"] == 53) {
          $query .= "Summen ";
          $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
          $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
          $query .= ",Wh_Gestern=".$daten["WattstundenGesamtGestern"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 43 or $daten["SonoffModul"] == 55 or $daten["SonoffModul"] == 200 or $daten["SonoffModul"] == 0) {
          $query .= "Summen ";
          $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
          $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 300 or $daten["SonoffModul"] == 302) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt_Eingang=".$daten["Energie_in"];
          $query .= ",Wh_Gesamt_Ausgang=".$daten["Energie_out"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 301) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt_Eingang=".$daten["Energie_in"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 303) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt_Eingang=".$daten["Energie_in"];
          $query .= ",Wh_Gesamt_Ausgang=".$daten["Energie_out"];
          $query .= ",Wh_Gesamt_Eingang_1d=".$daten["Energie_in1d"];
          $query .= ",Wh_Gesamt_Eingang_7d=".$daten["Energie_in7d"];
          $query .= ",Wh_Gesamt_Eingang_30d=".$daten["Energie_in30d"];
          $query .= ",Wh_Gesamt_Eingang_365d=".$daten["Energie_in365d"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 304 or $daten["SonoffModul"] == 305) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt_Eingang=".$daten["Energie_inGesamt"];
          $query .= ",Wh_Gesamt_Ausgang=".$daten["Energie_out"];
          $query .= ",Wh_Gesamt_Eingang_T1=".$daten["Energie_inT1"];
          $query .= ",Wh_Gesamt_Eingang_T2=".$daten["Energie_inT2"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 306) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt_Eingang=".$daten["Energie_in"];
          $query .= ",Wh_Gesamt_Ausgang=".$daten["Energie_out"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        elseif ($daten["SonoffModul"] == 307) { // IR_Lesekopf
          $query .= "Summen ";
          $query .= "Wh_Gesamt=".$daten["Energie"];
          $query .= ",Vol_Gesamt=".$daten["Volumen"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        break;

        /* Infini 3KW Hybrid Wechselrichter  */
      case 24:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung=".$daten["Netzspannung"];
        $query .= ",Strom=".$daten["Netzstrom"];
        $query .= ",Leistung=".$daten["Netzleistung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Leistung=".$daten["AC_Ausgangsleistung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Ausgangslast=".$daten["AC_Ausgangslast"];
        $query .= ",Stromrichtung=".$daten["Bezug_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
        $query .= ",Stromrichtung=".$daten["Laden_Entladen"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung1=".$daten["Solarspannung1"];
        $query .= ",Spannung2=".$daten["Solarspannung2"];
        $query .= ",Spannung3=".$daten["Solarspannung3"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",Leistung1=".$daten["Solarleistung1"];
        $query .= ",Leistung2=".$daten["Solarleistung2"];
        $query .= ",Leistung3=".$daten["Solarleistung3"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Modus=\"".$daten["Modus"]."\"";
        $query .= ",Temperatur=".$daten["Temperatur"];
        $query .= ",Status=".$daten["DeviceStatus"];
        $query .= ",Fehlermeldung=\"".$daten["Fehlermeldung"]."\""; // *
        $query .= ",Warnungen=\"".$daten["Warnungen"]."\""; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",kWh_Total=".$daten["KiloWattstundenTotal"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 25: // Sonnen Batterie
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Netzladung=".$daten["Batterie_Netzladung"];
        $query .= ",Batterieladung=".$daten["Batterieladung"];
        $query .= ",PV_Ladung=".$daten["Batterie_PV_Ladung"];
        $query .= ",User_SOC=".$daten["User_SOC"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= ",Entladung=".$daten["Batterieentladung"];
        $query .= ",Leistung=".$daten["Lade_Entladeleistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Erzeugung=".$daten["PV_Erzeugung"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= ",Einspeisung=".$daten["PV_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "AC ";
        $query .= "Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["Ausgangsleistung"];
        $query .= ",Einspeisung=".$daten["AC_Einspeisung"];
        $query .= ",Bezug=".$daten["AC_Bezug"];
        $query .= ",Spannung=".$daten["AC_Spannung"];
        $query .= ",Verbrauch=".$daten["Verbrauch"];
        $query .= ",Einspeisung_Bezug=".$daten["Einspeisung_Bezug"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Systemstatus=\"".$daten["Systemstatus"]."\"";
        $query .= ",Operating_Mode=".$daten["Operating_Mode"];
        $query .= ",Netzbezug=".$daten["Netzbezug"];
        $query .= ",PV_Einspeisung=".$daten["PV_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* SMA Geräte   */
      case 27:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Softwarepaket"];
          $query .= ",Produkt=\"".$daten["Geraeteklasse"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        if (isset($daten["Modell"]) and $daten["Modell"] == "Island" or $daten["Modell"] == "Tripower SE") {
          $query .= "Batterie ";
          $query .= "Temperatur=".$daten["Temperatur"];
          $query .= ",Spannung=".$daten["Batteriespannung"];
          $query .= ",Strom=".$daten["Batteriestrom"];
          $query .= ",Batterieladung=".$daten["Batterieladung"];
          $query .= ",SOC=".$daten["SOC"];
          $query .= ",SOE=".$daten["SOE"];
          $query .= ",Batteriestatus=".$daten["Batteriestatus"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        if (isset($daten["Modell"]) and $daten["Modell"] == "Tripower" or $daten["Modell"] == "Tripower SE") {
          $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        }
        $query .= ",Bezug=".$daten["AC_Leistung_Bezug"];
        $query .= ",Einspeisung=".$daten["AC_Leistung_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        if (isset($daten["Modell"]) and $daten["Modell"] == "Tripower" or $daten["Modell"] == "Tripower SE") {
          $query .= "PV ";
          $query .= "Leistung=".$daten["DC_Leistung"];
          $query .= ",Spannung1=".$daten["DC_Spannung1"];
          $query .= ",Leistung1=".$daten["DC_Leistung1"];
          $query .= ",Strom1=".$daten["DC_Strom1"];
          $query .= ",Spannung2=".$daten["DC_Spannung2"];
          $query .= ",Leistung2=".$daten["DC_Leistung2"];
          $query .= ",Strom2=".$daten["DC_Strom2"];
          $query .= ",Spannung3=".$daten["DC_Spannung3"];
          $query .= ",Leistung3=".$daten["DC_Leistung3"];
          $query .= ",Strom3=".$daten["DC_Strom3"];
          $query .= ",Spannung4=".$daten["DC_Spannung4"];
          $query .= ",Leistung4=".$daten["DC_Leistung4"];
          $query .= ",Strom4=".$daten["DC_Strom4"];
          $query .= ",Spannung5=".$daten["DC_Spannung5"];
          $query .= ",Leistung5=".$daten["DC_Leistung5"];
          $query .= ",Strom5=".$daten["DC_Strom5"];
          $query .= ",Spannung6=".$daten["DC_Spannung6"];
          $query .= ",Leistung6=".$daten["DC_Leistung6"];
          $query .= ",Strom6=".$daten["DC_Strom6"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",LiveBit=".$daten["LiveBit"];
        $query .= ",Status=".$daten["Geraetestatus"];
        $query .= ",Betriebszustand=".$daten["Betriebszustand"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["Wh_Gesamt"];
        if (isset($daten["Modell"]) and $daten["Modell"] == "Tripower" or $daten["Modell"] == "Tripower SE") { // *
          $query .= ",Wh_Heute_Registerwert=".$daten["Wh_GesamtHeute"];
          $query .= ",Wh_Gesamt_Export=".$daten["Einspeisung_Wh"];
          $query .= ",Wh_Gesamt_Import=".$daten["Bezug_Wh"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
      case 28: // HDRi marlec Laderegler
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie1 ";
        $query .= "Spannung=".$daten["Batteriespannung1"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie2 ";
        $query .= "Spannung=".$daten["Batteriespannung2"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "DC ";
        $query .= "WG_Strom=".$daten["WGStrom"];
        $query .= ",PV_Strom=".$daten["PVStrom"];
        $query .= ",WG_PV_Strom=".$daten["NetStrom"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "OperatingMode1=".$daten["OperatingMode1"];
        $query .= ",OperatingMode2=".$daten["OperatingMode2"];
        $query .= ",OperatingMode3=".$daten["OperatingMode3"];
        $query .= ",Betriebsstunden=".$daten["Betriebsstunden"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "WG_Ah=".$daten["WGAh"];
        $query .= ",PV_Ah=".$daten["PVAh"];
        $query .= ",WG_PV_Ah=".$daten["NetAh"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* go-e Charger Wallbox    */
      case 29:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["nrg"][0];
        $query .= ",Spannung_S=".$daten["nrg"][1];
        $query .= ",Spannung_T=".$daten["nrg"][2];
        $query .= ",Strom_R=".($daten["nrg"][4] / 10);
        $query .= ",Strom_S=".($daten["nrg"][5] / 10);
        $query .= ",Strom_T=".($daten["nrg"][6] / 10);
        $query .= ",Leistung_R=".($daten["nrg"][7] * 100);
        $query .= ",Leistung_S=".($daten["nrg"][8] * 100);
        $query .= ",Leistung_T=".($daten["nrg"][9] * 100);
        $query .= ",Leistung_gesamt=".($daten["nrg"][11] * 10);
        $query .= ",Leistungsfaktor_R=".$daten["nrg"][12];
        $query .= ",Leistungsfaktor_S=".$daten["nrg"][13];
        $query .= ",Leistungsfaktor_T=".$daten["nrg"][14];
        $query .= ",Leistungsfaktor_N=".$daten["nrg"][15];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=".$daten["car"];
        if (isset($daten["tmp"][0])) {
          $query .= ",Temperatur=".$daten["tmp"][0];
          if (isset($daten["tmp"][1])) {
            $query .= ",Temperatur2=".$daten["tmp"][1];
          }
          if (isset($daten["tmp"][2])) {
            $query .= ",Temperatur3=".$daten["tmp"][2];
          }
          if (isset($daten["tmp"][3])) {
            $query .= ",Temperatur4=".$daten["tmp"][3];
          }
        }
        else {
          $query .= ",Temperatur=".$daten["tmp"];
        }
        $query .= ",StationBereit=".$daten["alw"];
        $query .= ",MaxAmpere=".$daten["amp"];
        $query .= ",ErrorCode=".$daten["err"];
        $query .= ",Zugangskontrolle=".$daten["ast"];
        $query .= ",Abschaltung=".$daten["stp"];
        $query .= ",RFID_Karte=".$daten["uby"];
        $query .= ",Karteninhaber=\"".$daten["Karteninhaber"]."\"";
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".($daten["eto"] * 100);
        $query .= ",Wh_Ladevorgang=".round(($daten["dws"] * 10 / 3600), 0);
        $query .= ",Wh_Karte1=".($daten["eca"] * 100);
        $query .= ",Wh_Karte2=".($daten["ecr"] * 100);
        $query .= ",Wh_Karte3=".($daten["ecd"] * 100);
        $query .= ",Wh_Karte4=".($daten["ec4"] * 100);
        $query .= ",Wh_Karte5=".($daten["ec5"] * 100);
        $query .= ",Wh_Karte6=".($daten["ec6"] * 100);
        $query .= ",Wh_Karte7=".($daten["ec7"] * 100);
        $query .= ",Wh_Karte8=".($daten["ec8"] * 100);
        $query .= ",Wh_Karte9=".($daten["ec9"] * 100);
        $query .= ",Wh_Karte10=".($daten["ec1"] * 100);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Keba Wallbox   */
      case 30:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["U1"];
        $query .= ",Spannung_S=".$daten["U2"];
        $query .= ",Spannung_T=".$daten["U3"];
        $query .= ",Strom_R=".($daten["I1"] / 1000);
        $query .= ",Strom_S=".($daten["I2"] / 1000);
        $query .= ",Strom_T=".($daten["I3"] / 1000);
        $query .= ",Leistung_gesamt=".($daten["P"] / 1000);
        $query .= ",Leistungsfaktor=".($daten["PF"] / 10);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=".$daten["State"];
        $query .= ",MaxAmpere=".($daten["Max curr"] / 1000);
        $query .= ",MaxAmpereHardware=".($daten["Curr HW"] / 1000);
        $query .= ",MaxAmpereUser=".($daten["Curr user"] / 1000);
        $query .= ",ErrorCode1=".$daten["Error1"];
        $query .= ",ErrorCode2=".$daten["Error2"];
        $query .= ",Ladekabel=".$daten["Plug"];
        $query .= ",EnableSys=".$daten["Enable sys"];
        $query .= ",EnableUser=".$daten["Enable user"];
        $query .= ",AnzPhasen=".$daten["AnzPhasen"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".($daten["E total"] / 10);
        $query .= ",Wh_Ladevorgang=".round(($daten["E pres"] / 10), 0);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Shelly 3EM  */
      case 31:
      case 79: // IAMMETER WEM3080T
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["Spannung_R"];
        $query .= ",Spannung_S=".$daten["Spannung_S"];
        $query .= ",Spannung_T=".$daten["Spannung_T"];
        $query .= ",Strom_R=".$daten["Strom_R"];
        $query .= ",Strom_S=".$daten["Strom_S"];
        $query .= ",Strom_T=".$daten["Strom_T"];
        $query .= ",Leistung_VerbrauchGesamt_R=".$daten["Wh_VerbrauchGesamt_R"];
        $query .= ",Leistung_VerbrauchGesamt_S=".$daten["Wh_VerbrauchGesamt_S"];
        $query .= ",Leistung_VerbrauchGesamt_T=".$daten["Wh_VerbrauchGesamt_T"];
        $query .= ",Leistung_EinspeisungGesamt_R=".$daten["Wh_EinspeisungGesamt_R"];
        $query .= ",Leistung_EinspeisungGesamt_S=".$daten["Wh_EinspeisungGesamt_S"];
        $query .= ",Leistung_EinspeisungGesamt_T=".$daten["Wh_EinspeisungGesamt_T"];
        $query .= ",PowerFactor_R=".$daten["PowerFactor_R"];
        $query .= ",PowerFactor_S=".$daten["PowerFactor_S"];
        $query .= ",PowerFactor_T=".$daten["PowerFactor_T"];
        $query .= ",Wirkleistung_R=".$daten["Wirkleistung_R"];
        $query .= ",Wirkleistung_S=".$daten["Wirkleistung_S"];
        $query .= ",Wirkleistung_T=".$daten["Wirkleistung_T"];
        $query .= ",Gesamtleistung=".$daten["LeistungGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        if ($daten["Regler"] == 31) { // WEM3080T does not have a relay
          $query .= "Service ";
          $query .= "Relaisstatus=".$daten["Relaisstatus"];
          $query .= ",Ueberlastung=".$daten["Ueberlastung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt_Verbrauch=".$daten["WattstundenGesamt_Verbrauch"];
        $query .= ",Wh_Gesamt_Einspeisung=".$daten["WattstundenGesamt_Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  KACO Wechselrichter TL3 Serie   */
      case 32:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Ausgangsspannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Ausgangsspannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Ausgangsspannung_T"];
        $query .= ",Strom_R=".$daten["AC_Ausgangsstrom_R"];
        $query .= ",Strom_S=".$daten["AC_Ausgangsstrom_S"];
        $query .= ",Strom_T=".$daten["AC_Ausgangsstrom_T"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Blindleistung=".$daten["AC_Blindleistung"];
        $query .= ",Wirkungsgrad=".$daten["AC_Leistungsfaktor"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Leistung=".$daten["Solarleistung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",Status=".$daten["Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Labornetzteil JT-8600  */
      case 33:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "DC ";
        $query .= "Spannung=".$daten["DC_Volt"];
        $query .= ",Strom=".$daten["DC_Ampere"];
        $query .= ",SetSpannung=".$daten["DC_setVolt"];
        $query .= ",SetStrom=".$daten["DC_setAmpere"];
        $query .= ",MaxSpannung=".$daten["DC_maxVolt"];
        $query .= ",MaxStrom=".$daten["DC_maxAmpere"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",Status=".$daten["Geraetestatus"];
        $query .= ",Konstante=".$daten["DC_Konstante"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        break;

        /*  SDM630 + SDM230 Energy Meter + Siemens PAC2200 + Solarlog 380 usw. */
        // Carlo Gavazzi EM24 Meter + Hager ECR380D

        /*  Fast alle Smart Meter                                              */
      case 34:
      case 50:
      case 51:
      case 53:
      case 54:
      case 58:
      case 61:
      case 74:
      case 75:
        //if (date( "i" ) == "01" or $daten["Demodaten"] or date( "H" ) == date( "H", $Sonnenaufgang )) {
        $query .= "Info ";
        $query .= "Firmware=\"".$daten["Firmware"]."\"";
        $query .= ",Produkt=\"".$daten["Modell"]."\"";
        $query .= ",Objekt=\"".$daten["Objekt"]."\"";
        $query .= ",Datum=\"".$daten["Datum"]."\"";
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        //}
        $query .= "AC ";
        if ($daten["Regler"] == 50) {
          $query .= "Spannung=".$daten["AC_Spannung"];
        }
        else {
          $query .= "Spannung=".$daten["AC_Spannung_R"];
        }
        $query .= ",Strom=".$daten["AC_Strom"];
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Leistungsfaktor=".$daten["PF_Leistung"];
        if ($daten["Regler"] == 50 or $daten["Regler"] == 58) {
        }
        else {
          $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
          $query .= ",Strom_R=".$daten["AC_Strom_R"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
          $query .= ",Leistungsfaktor_R=".$daten["PF_R"];
          $query .= ",Leistungsfaktor_S=".$daten["PF_S"];
          $query .= ",Leistungsfaktor_T=".$daten["PF_T"];
        }
        if ($daten["Regler"] == 50 or $daten["Regler"] == 51 or $daten["Regler"] == 54 or $daten["Regler"] == 61 or $daten["Regler"] == 74 or $daten["Regler"] == 75) {
          $query .= ",Bezug=".$daten["Bezug"];
          $query .= ",Einspeisung=".$daten["Einspeisung"];
        }
        if ($daten["Regler"] == 53) {
          $query .= ",Bezug=".$daten["Wh_Bezug"];
          $query .= ",Einspeisung=".$daten["Wh_Einspeisung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_GesamtBezug=".round($daten["Wh_Bezug"], 1);
        $query .= ",Wh_GesamtEinspeisung=".$daten["Wh_Einspeisung"];
        if ($daten["Regler"] == 53) {
          $query .= ",Bezug_R=".$daten["Wh_Bezug_Phase_R"];
          $query .= ",Bezug_S=".$daten["Wh_Bezug_Phase_S"];
          $query .= ",Bezug_T=".$daten["Wh_Bezug_Phase_T"];
          $query .= ",Einspeisung_R=".$daten["Wh_Einspeisung_Phase_R"];
          $query .= ",Einspeisung_S=".$daten["Wh_Einspeisung_Phase_S"];
          $query .= ",Einspeisung_T=".$daten["Wh_Einspeisung_Phase_T"];
        }
        if ($daten["Regler"] == 74 or $daten["Regler"] == 75) {
          $query .= ",Wh_BezugHeute=".$daten["Wh_BezugHeute"];
          $query .= ",Wh_EinspeisungHeute=".$daten["Wh_EinspeisungHeute"];
        }
        else {
          $query .= ",GesamtLeistungsbedarf=".$daten["GesamterLeistungsbedarf"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Wallbe Wallbox , Phoenix Contact Wallbox und Vestel Wallbox */
      case 35:
      case 47:
      case 69:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["Spannung_R"];
        $query .= ",Spannung_S=".$daten["Spannung_S"];
        $query .= ",Spannung_T=".$daten["Spannung_T"];
        $query .= ",Strom_R=".$daten["Strom_R"];
        $query .= ",Strom_S=".$daten["Strom_S"];
        $query .= ",Strom_T=".$daten["Strom_T"];
        $query .= ",Leistung_gesamt=".$daten["Leistung"];
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",Ladestrom=".$daten["Ladestrom"];
        if ($daten["Regler"] == 69) { // Vestel Wallbox
          $query .= ",AktuelleLadeleistung=".$daten["LadeLeistung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=\"".$daten["Status"]."\"";
        $query .= ",StatusHex=".bin2hex($daten["Status"]);
        $query .= ",ErrorCode=".$daten["ErrorCode"];
        $query .= ",LadungAktiv=".$daten["LadungAktiv"];
        $query .= ",Ladezeit=".$daten["Ladezeit"];
        $query .= ",LadestationEin=".$daten["LadestationEin"];
        $query .= ",PerModbusAktiviert=".$daten["PerModbusAktiviert"];
        $query .= ",Ladung_erlaubt=".$daten["Ladung_erlaubt"];
        $query .= ",Kabel_angeschlossen=".$daten["Kabel_angeschlossen"];
        $query .= ",Kabel_entriegelt=".$daten["Kabel_entriegeln"];
        $query .= ",MaxLadestrom=".$daten["MaxLadestrom"];
        $query .= ",Ladebedingungen_OK=".$daten["LadebedingungenOK"];
        if ($daten["Regler"] == 69) { // Vestel Wallbox
          $query .= ",Kabelstatus=".$daten["Kabelstatus"];
          $query .= ",AnzPhasenAktiv=".$daten["AnzPhasen"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["GesamtLeistung"];
        $query .= ",Wh_Ladevorgang=".$daten["LadeLeistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Delta Wechselrichter SL 2500  [ Variant 1 ]   */
      case 36:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Spannung"];
        $query .= ",Strom=".$daten["AC_Strom"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        if ($daten["Variant"] == 1) {
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "PV ";
          $query .= "Spannung=".$daten["Solarspannung"];
          $query .= ",Leistung=".($daten["Solarspannung"] * $daten["Solarstrom"]);
          $query .= ",Strom=".$daten["Solarstrom"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "Service ";
          $query .= "Temperatur_DC=".$daten["DC_Temperatur"];
          $query .= ",Temperatur_AC=".$daten["AC_Temperatur"];
          $query .= ",Modell=\"".$daten["Modell"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Variant"] >= 212 and $daten["Variant"] <= 222) {
          $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
          $query .= ",Strom_R=".$daten["AC_Strom_R"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Frequenz_R=".$daten["AC_Frequenz_R"];
          $query .= ",Frequenz_S=".$daten["AC_Frequenz_S"];
          $query .= ",Frequenz_T=".$daten["AC_Frequenz_T"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "PV ";
          $query .= "Spannung1=".$daten["Solarspannung1"];
          $query .= ",Spannung2=".$daten["Solarspannung2"];
          $query .= ",Leistung=".($daten["Solarleistung"]);
          $query .= ",Leistung1=".($daten["Solarleistung1"]);
          $query .= ",Leistung2=".($daten["Solarleistung2"]);
          $query .= ",Strom1=".$daten["Solarstrom1"];
          $query .= ",Strom2=".$daten["Solarstrom2"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "Service ";
          $query .= "Temperatur_DC=".$daten["DC_Temperatur"];
          $query .= ",Modell=\"".$daten["Modell"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Betriebsstunden=".$daten["Betriebsstunden"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Simple EVSE Wallbox         */
      case 37:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Strom_R=".($daten["Strom_R"]);
        $query .= ",Strom_S=".($daten["Strom_S"]);
        $query .= ",Strom_T=".($daten["Strom_T"]);
        $query .= ",Leistung=".($daten["aktuelleLeistung"]);
        $query .= ",Stromvorgabe=".($daten["aktuelleStromvorgabe"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=".$daten["Stationsstatus"];
        $query .= ",MaxAmpere=".($daten["MaxAmpere"]);
        $query .= ",freigeschaltet=".$daten["freigeschaltet"];
        $query .= ",Ladedauer=".$daten["Ladedauer"];
        $query .= ",immerAktiv=".$daten["immerAktiv"];
        $query .= ",User=\"".$daten["letzterUser"]."\"";
        $query .= ",UID=\"".$daten["letzteUID"]."\"";
        $query .= ",Km_Ladevorgang=".$daten["Km_Ladevorgang"];
        $query .= ",Stromvorgabe=".($daten["aktuelleStromvorgabe"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".($daten["Wh_Meter"] * 1000);
        $query .= ",Wh_Ladevorgang=".$daten["Wh_Ladevorgang"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* ALPHA ESS Wechselrichter   */
      case 38:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if (isset($daten["Alpha_ESS"]) and $daten["Alpha_ESS"] == 1) {
          // Neue Datenbank Struktur
          $query .= "Netz ";
          $query .= "Leistung_R=".$daten["AC_Wirkleistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Wirkleistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Wirkleistung_T"];
          $query .= ",Gesamtleistung=".$daten["AC_Wirkleistung"];
          $query .= ",EinspeisungGesamt=".$daten["NetzeinspeisungGesamt"];
          $query .= ",BezugGesamt=".$daten["NetzbezugGesamt"];
          $query .= ",PVAC_Leistung_R=".$daten["PV_Wirkleistung_R"];
          $query .= ",PVAC_Leistung_S=".$daten["PV_Wirkleistung_S"];
          $query .= ",PVAC_Leistung_T=".$daten["PV_Wirkleistung_T"];
          $query .= ",PVAC_Gesamtleistung=".$daten["AC_LeistungGesamt"];
          $query .= ",PVAC_PV-ErzeugungGesamt=".$daten["PV_EinspeisungGesamt"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Inverter ";
          $query .= "Spannung_R=".$daten["AC_Spannung_R"];
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
          $query .= ",Strom_R=".$daten["AC_Strom_R"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
          $query .= ",Leistung=".$daten["AC_Leistung"];
          $query .= ",Frequenz=".$daten["BackupFrequenz"];
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",Fehler=\"".$daten["WR_Fehler"]."\""; // *
          $query .= ",Warnungen=\"".$daten["WR_Warnungen"]."\"";
          $query .= ",PV_Spannung1=".$daten["PV_Spannung1"];
          $query .= ",PV_Spannung2=".$daten["PV_Spannung2"];
          $query .= ",PV_Spannung3=".$daten["PV_Spannung3"];
          $query .= ",PV_Strom1=".$daten["PV_Strom1"];
          $query .= ",PV_Strom2=".$daten["PV_Strom2"];
          $query .= ",PV_Strom3=".$daten["PV_Strom3"];
          $query .= ",PV_Leistung=".$daten["PV_Leistung"];
          $query .= ",PV_Leistung1=".$daten["PV_Leistung1"];
          $query .= ",PV_Leistung2=".$daten["PV_Leistung2"];
          $query .= ",PV_Leistung3=".$daten["PV_Leistung3"];
          $query .= ",Total_PV_Energy=".$daten["PV_LeistungGesamt"];
          $query .= ",Backup_Spannung_R=".$daten["AC_BackupSpannung_R"];
          $query .= ",Backup_Spannung_S=".$daten["AC_BackupSpannung_S"];
          $query .= ",Backup_Spannung_T=".$daten["AC_BackupSpannung_T"];
          $query .= ",Backup_Leistung_R=".$daten["AC_BackupLeistung_R"];
          $query .= ",Backup_Leistung_S=".$daten["AC_BackupLeistung_S"];
          $query .= ",Backup_Leistung_T=".$daten["AC_BackupLeistung_T"];
          $query .= ",Backup_Strom_R=".$daten["AC_BackupStrom_R"];
          $query .= ",Backup_Strom_S=".$daten["AC_BackupStrom_S"];
          $query .= ",Backup_Strom_T=".$daten["AC_BackupStrom_T"];
          $query .= ",Backup_LeistungGesamt=".$daten["AC_BackupLeistung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Batterie ";
          $query .= "Spannung=".$daten["Batteriespannung"];
          $query .= ",Strom=".$daten["Batteriestrom"]; // *
          $query .= ",SOC=".$daten["SOC"]; // *
          $query .= ",Batteriestatus=\"".$daten["Batteriestatus"]."\"";
          $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
          $query .= ",Fehler=\"".$daten["Batteriefehler"]."\""; // *
          $query .= ",Warnungen=\"".$daten["Batteriewarnungen"]."\""; // *
          $query .= ",Ladeleistung=".$daten["BatterieladeenergieGesamt"];
          $query .= ",Entladeleistung=".$daten["BatterieentladeenergieGesamt"];
          $query .= ",Netzladung=".$daten["Netzladeenergie"];
          $query .= ",Batterieleistung=".$daten["Batterieleistung"];
          $query .= ",BatterieRemainingTime=".$daten["BatterieRemainingTime"];
          $query .= ",BatterieImplementationChargeSOC=".$daten["BatterieImplementationChargeSOC"];
          $query .= ",BatterieImplementationDischargeSOC=".$daten["BatterieImplementationDischargeSOC"];
          $query .= ",BatterieRemainingChargeSOC=".$daten["BatterieRemainingChargeSOC"];
          $query .= ",BatterieRemainingDischargeSOC=".$daten["BatterieRemainingDischargeSOC"];
          $query .= ",Batteriemodus=".$daten["Batteriemodus"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "System ";
          $query .= "Einspeisung_prozentual=".$daten["Netzeinspeisung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Summen ";
          $query .= "PV_GesamtHeute=".$daten["WattstundenGesamtHeute"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        else {
          //  Alte Datenbank Struktur
          $query .= "Netz ";
          $query .= "Spannung_R=".$daten["AC_Spannung_R"];
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
          $query .= ",Strom_R=".$daten["AC_Strom_R"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
          $query .= ",Leistung=".$daten["AC_Leistung"];
          $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
          $query .= ",Frequenz=".$daten["BackupFrequenz"];
          //     $query .= ",BezugGesamt=".$daten["AC_LeistungGesamt"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "AC ";
          $query .= "Spannung_R=".$daten["AC_BackupSpannung_R"];
          $query .= ",Spannung_S=".$daten["AC_BackupSpannung_S"];
          $query .= ",Spannung_T=".$daten["AC_BackupSpannung_T"];
          $query .= ",Frequenz=".$daten["BackupFrequenz"];
          $query .= ",Leistung_R=".$daten["AC_BackupLeistung_R"];
          $query .= ",Leistung_S=".$daten["AC_BackupLeistung_S"];
          $query .= ",Leistung_T=".$daten["AC_BackupLeistung_T"];
          $query .= ",Strom_R=".$daten["AC_BackupStrom_R"];
          $query .= ",Strom_S=".$daten["AC_BackupStrom_S"];
          $query .= ",Strom_T=".$daten["AC_BackupStrom_T"];
          $query .= ",Leistung=".$daten["AC_BackupLeistung"];
          $query .= ",PV_AC_Wirkleistung_R=".$daten["PV_Wirkleistung_R"];
          $query .= ",PV_AC_Wirkleistung_S=".$daten["PV_Wirkleistung_S"];
          $query .= ",PV_AC_Wirkleistung_T=".$daten["PV_Wirkleistung_T"];
          $query .= ",PV_AC_Total_Active_Power=".$daten["PV_LeistungGesamt"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Batterie ";
          $query .= "Spannung=".$daten["Batteriespannung"];
          $query .= ",Kapazitaet=".$daten["Batteriekapazitaet"];
          $query .= ",Strom=".$daten["Batteriestrom"]; // *
          $query .= ",SOC=".$daten["SOC"]; // *
          $query .= ",Batterieleistung=".$daten["Batterieleistung"];
          $query .= ",BatterieRemainingTime=".$daten["BatterieRemainingTime"];
          $query .= ",BatterieImplementationChargeSOC=".$daten["BatterieImplementationChargeSOC"];
          $query .= ",BatterieImplementationDischargeSOC=".$daten["BatterieImplementationDischargeSOC"];
          $query .= ",BatterieRemainingChargeSOC=".$daten["BatterieRemainingChargeSOC"];
          $query .= ",BatterieRemainingDischargeSOC=".$daten["BatterieRemainingDischargeSOC"];
          $query .= ",Batteriemodus=".$daten["Batteriemodus"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "PV ";
          $query .= "Spannung1=".$daten["PV_Spannung1"];
          $query .= ",Spannung2=".$daten["PV_Spannung2"];
          $query .= ",Spannung3=".$daten["PV_Spannung3"];
          $query .= ",Strom1=".$daten["PV_Strom1"];
          $query .= ",Strom2=".$daten["PV_Strom2"];
          $query .= ",Strom3=".$daten["PV_Strom3"];
          $query .= ",Leistung=".$daten["PV_Leistung"];
          $query .= ",Leistung1=".$daten["PV_Leistung1"];
          $query .= ",Leistung2=".$daten["PV_Leistung2"];
          $query .= ",Leistung3=".$daten["PV_Leistung3"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Service ";
          $query .= "Batteriestatus=\"".$daten["Batteriestatus"]."\"";
          $query .= ",Temperatur=".$daten["Temperatur"];
          $query .= ",WR_Fehler=\"".$daten["WR_Fehler"]."\""; // *
          $query .= ",WR_Warnungen=\"".$daten["WR_Warnungen"]."\""; // *
          $query .= ",Bat_Fehler=\"".$daten["Batteriefehler"]."\""; // *
          $query .= ",Bat_Warnungen=\"".$daten["Batteriewarnungen"]."\""; // *
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
          $query .= "Summen ";
          $query .= "Wh_NetzeinspeisungGesamt=".$daten["NetzeinspeisungGesamt"];
          $query .= ",Wh_NetzbezugGesamt=".$daten["NetzbezugGesamt"];
          $query .= ",PV_GesamtHeute=".$daten["WattstundenGesamtHeute"];
          $query .= ",PV_AC_Total_Energy_feed_to_Grid=".$daten["PV_EinspeisungGesamt"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        break;

        /* openWB Wallbox   */
      case 39:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["VPhase1"];
        $query .= ",Spannung_S=".$daten["VPhase2"];
        $query .= ",Spannung_T=".$daten["VPhase3"];
        $query .= ",Strom_R=".$daten["APhase1"];
        $query .= ",Strom_S=".$daten["APhase2"];
        $query .= ",Strom_T=".$daten["APhase3"];
        $query .= ",Leistung_gesamt=".$daten["W"];
        $query .= ",aktuelleLeistung=".$daten["W"];
        $query .= ",Hausverbrauch=".$daten["WHouseConsumption"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=".$daten["Stationsstatus"];
        $query .= ",MaxAmpere=".$daten["AConfigured"];
        $query .= ",LadestationName=\"".$daten["strChargePointName"]."\"";
        $query .= ",LadeStecker=".$daten["boolPlugStat"];
        $query .= ",Ladevorgang=".$daten["boolChargeStat"];
        $query .= ",Ladestatus=".$daten["Ladestatus"];
        $query .= ",SOC=".$daten["%Soc"];
        $query .= ",ZaehlerPhasen_akt=".$daten["countPhasesInUse"];
        $query .= ",geladene_km=".$daten["kmCharged"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".($daten["kWhCounter"] * 1000);
        $query .= ",Wh_Ladeenergie=".($daten["kWhActualCharged"] * 1000);
        $query .= ",Wh_Ladevorgang=".($daten["kWhChargedSincePlugged"] * 1000);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Phocos PH1800 Wechselrichter , PV18 VHM Wechselrichter   */
      case 40:
      case 42:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Maschinentype"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung=".$daten["Netzspannung"];
        $query .= ",Strom=".$daten["Netzstrom"];
        $query .= ",Leistung=".$daten["Netzleistung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
        $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
        $query .= ",Leistung=".$daten["AC_Ausgangsleistung"];
        $query .= ",Strom=".$daten["AC_Ausgangsstrom"];
        $query .= ",Ausgangslast=".$daten["AC_Ausgangslast"];
        if ($daten["Regler"] == 42) {
          $query .= ",Last=".$daten["Last_Prozent"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Soll_Ah=".$daten["Batterie_Ah"];
        $query .= ",Leistung=".$daten["Batterieleistung"];
        $query .= ",Ladereglerleistung=".$daten["Ladereglerleistung"];
        $query .= ",Ladereglerstrom=".$daten["Ladereglerstrom"];
        $query .= ",Relais=".$daten["Bat_Relay"]; // *
        $query .= ",Strom=".$daten["Batterie_Strom"]; // *
        $query .= ",SpannungAmInverter=".$daten["Bat_Spannung"]; // *
        $query .= ",Max_Ampere=".$daten["Max_Ampere"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Relais=".$daten["PV_Relay"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "ReglerMode=".$daten["Regler_Mode"];
        $query .= ",Geraetestatus=".$daten["WR_Status"];
        $query .= ",Temperatur=".$daten["Temperatur"];
        if ($daten["Regler"] == 42) {
          $query .= ",AC_Temperatur=".$daten["AC_Temperatur"];
        }
        $query .= ",MPPTStatus=".$daten["MPPT_Status"];
        $query .= ",ReglerStatus=".$daten["Regler_Status"];
        $query .= ",BatterieRelais=".$daten["Bat_Relay"];
        $query .= ",PVRelais=".$daten["PV_Relay"];
        $query .= ",Fehler=".$daten["Fehler"]; // *
        $query .= ",Warnungen=".$daten["Warnungen"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["Wh_Gesamt"];
        $query .= ",Wh_GesamtHeute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Senec Geräte   */
      case 43:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
        $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
        $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",Hausverbrauch=".$daten["Hausverbrauch"];
        $query .= ",Einspeisung_Bezug=".$daten["Netz_Leistung"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Bezug=".$daten["Bezug"];
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",Eingangsleistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Bat_Spannung"];
        $query .= ",SOC=".$daten["Bat_SOC"];
        $query .= ",Leistung=".$daten["Bat_Leistung"];
        $query .= ",Strom=".$daten["Bat_Strom"];
        $query .= ",Anzahl=".$daten["Anz_Batterien"];
        for ($i = 1; $i <= $daten["Anz_Batterien"]; $i++) {
          $query .= ",Strom".$i."=".$daten["Bat".$i."_Strom"];
          $query .= ",Spannung".$i."=".$daten["Bat".$i."_Spannung"];
          $query .= ",SOC".$i."=".$daten["Bat".$i."_SOC"];
          $query .= ",Spannung1_Bat".$i."=".$daten["Bat".$i."_Cell_Volt1"];
          $query .= ",Spannung2_Bat".$i."=".$daten["Bat".$i."_Cell_Volt2"];
          $query .= ",Spannung3_Bat".$i."=".$daten["Bat".$i."_Cell_Volt3"];
          $query .= ",Spannung4_Bat".$i."=".$daten["Bat".$i."_Cell_Volt4"];
          $query .= ",Spannung5_Bat".$i."=".$daten["Bat".$i."_Cell_Volt5"];
          $query .= ",Spannung6_Bat".$i."=".$daten["Bat".$i."_Cell_Volt6"];
          $query .= ",Spannung7_Bat".$i."=".$daten["Bat".$i."_Cell_Volt7"];
          $query .= ",Spannung8_Bat".$i."=".$daten["Bat".$i."_Cell_Volt8"];
          $query .= ",Spannung9_Bat".$i."=".$daten["Bat".$i."_Cell_Volt9"];
          $query .= ",Spannung10_Bat".$i."=".$daten["Bat".$i."_Cell_Volt10"];
          $query .= ",Spannung11_Bat".$i."=".$daten["Bat".$i."_Cell_Volt11"];
          $query .= ",Spannung12_Bat".$i."=".$daten["Bat".$i."_Cell_Volt12"];
          $query .= ",Spannung13_Bat".$i."=".$daten["Bat".$i."_Cell_Volt13"];
          $query .= ",Spannung14_Bat".$i."=".$daten["Bat".$i."_Cell_Volt14"];
          $query .= ",Temp1_Bat".$i."=".$daten["Bat".$i."_Cell_Temp1"];
          $query .= ",Temp2_Bat".$i."=".$daten["Bat".$i."_Cell_Temp2"];
          $query .= ",Temp3_Bat".$i."=".$daten["Bat".$i."_Cell_Temp3"];
          $query .= ",Temp4_Bat".$i."=".$daten["Bat".$i."_Cell_Temp4"];
          $query .= ",Temp5_Bat".$i."=".$daten["Bat".$i."_Cell_Temp5"];
          $query .= ",Temp6_Bat".$i."=".$daten["Bat".$i."_Cell_Temp6"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Begrenzung=".$daten["PV_Begrenzung"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= ",MPPT1_Strom=".$daten["MPPT1_Strom"];
        $query .= ",MPPT2_Strom=".$daten["MPPT2_Strom"];
        $query .= ",MPPT3_Strom=".$daten["MPPT3_Strom"];
        $query .= ",MPPT1_Spannung=".$daten["MPPT1_Spannung"];
        $query .= ",MPPT2_Spannung=".$daten["MPPT2_Spannung"];
        $query .= ",MPPT3_Spannung=".$daten["MPPT3_Spannung"];
        $query .= ",MPPT1_Leistung=".$daten["MPPT1_Leistung"];
        $query .= ",MPPT2_Leistung=".$daten["MPPT2_Leistung"];
        $query .= ",MPPT3_Leistung=".$daten["MPPT3_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Wallbox ";
        $query .= "Leistung_WB=".$daten["Wallbox_Leistung"];
        $query .= ",Wh_WallboxleistungGesamt=".$daten["Wallbox_Gesamtleistung"];
        $query .= ",L1_Leistung=".$daten["L1_Leistung"];
        $query .= ",L2_Leistung=".$daten["L2_Leistung"];
        $query .= ",L3_Leistung=".$daten["L3_Leistung"];
        $query .= ",WB_Status=".$daten["WB_Status"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Status=".$daten["Status"];
        $query .= ",Statusmeldung=\"".$daten["Statusmeldung"]."\"";
        $query .= ",Betriebsstunden=".$daten["Betriebsstunden"];
        $query .= ",Anz_Wallboxen=".$daten["Anz_Wallboxen"];
        $query .= ",Batterie_Temperatur=".$daten["Batterie_Temperatur"];
        $query .= ",Gehaeuse_Temperatur=".$daten["Gehaeuse_Temperatur"];
        $query .= ",CPU_Temperatur=".$daten["CPU_Temperatur"];
        $query .= ",FAN_Speed=".$daten["FAN_Speed"];
        $query .= ",Ladezyklen_BMS1=".$daten["Ladezyklen_BMS1"];
        $query .= ",Ladezyklen_BMS2=".$daten["Ladezyclen_BMS2"];
        $query .= ",Ladezyklen_BMS3=".$daten["Ladezyklen_BMS3"];
        $query .= ",Ladezyklen_BMS4=".$daten["Ladezyklen_BMS4"];
        $query .= ",Softwareversion=".$daten["Softwareversion"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_NetzeinspeisungGesamt=".$daten["EinspeisungGesamt"];
        $query .= ",Wh_NetzbezugGesamt=".$daten["NetzbezugGesamt"];
        $query .= ",Wh_PVLeistungGesamt=".$daten["PV_Gesamtleistung"];
        $query .= ",Wh_GesamtHeute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",BatterieGeladenHeute=".$daten["BatterieGeladenHeute"];
        $query .= ",BatterieEntladenHeute=".$daten["BatterieEntladenHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Webasto Wallbox   */
      case 44:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Strom_R=".$daten["Strom_R"];
        $query .= ",Strom_S=".$daten["Strom_S"];
        $query .= ",Strom_T=".$daten["Strom_T"];
        $query .= ",Leistung=".$daten["Leistung"];
        $query .= ",Ladestrom=".$daten["AktuelleStromvorgabe"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=\"".$daten["Ladestatus"]."\"";
        $query .= ",ErrorCode=".$daten["ErrorCode"];
        $query .= ",LadungAktiv=".$daten["LadungAktiv"];
        $query .= ",EVSEStatus=".$daten["EVSEStatus"];
        $query .= ",Kabelstatus=".$daten["Kabelstatus"];
        $query .= ",KabelLimitAmp=".$daten["KabelLimit"];
        $query .= ",HardwareLimitAmp=".$daten["HardwareLimit"];
        $query .= ",MinLadestrom=".$daten["MinLadestrom"];
        $query .= ",MaxAmpere=".$daten["AktuelleStromvorgabe"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["GesamtLeistung"];
        $query .= ",Wh_Ladevorgang=".$daten["LadeLeistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*   Any-Grid  und EASUN SMG  ==>  1 Phasen Wechselrichter mit Batterie    */
      case 45:
      case 71:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Maschinentype"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung=".$daten["Netzspannung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        if ($daten["Regler"] == 71) {
          $query .= ",Leistung=".$daten["Netzleistung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "AC ";
        if ($daten["Regler"] == 45) {
          $query .= "Spannung=".$daten["AC_Ausgangsspannung"];
          $query .= ",Frequenz=".$daten["AC_Ausgangsfrequenz"];
          $query .= ",Leistung=".$daten["AC_Ausgangsleistung"];
          $query .= ",Ausgangslast=".$daten["AC_Ausgangslast"];
        }
        if ($daten["Regler"] == 71) {
          $query .= "Spannung=".$daten["Out_Spannung"];
          $query .= ",Frequenz=".$daten["Out_Frequenz"];
          $query .= ",Leistung=".$daten["Out_Leistung"];
          $query .= ",Ausgangsleistung=".$daten["AC_Ausgangsleistung"];
          $query .= ",Mains_max=".$daten["Mains_max"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batteriespannung"];
        $query .= ",Strom=".$daten["Batterie_Strom"]; // *
        if ($daten["Regler"] == 45) {
          $query .= ",Max_Ampere=".$daten["Max_Ampere"]; // *
          $query .= ",Entladestrom=".$daten["Batterieentladestrom"]; // *
        }
        if ($daten["Regler"] == 71) {
          $query .= ",Bat_Charge_priority=".$daten["Bat_Charge_priority"];
          $query .= ",Charge_max=".$daten["Charge_max"];
        }
        $query .= ",SOC=".$daten["SOC"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["Solarspannung"];
        $query .= ",Strom=".$daten["Solarstrom"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "ReglerMode=".$daten["Regler_Mode"];
        $query .= ",Fehler=".$daten["Fehler"]; // *
        $query .= ",Priority=".$daten["DeviceStatus"]; // *
        $query .= ",OutputMode=".$daten["Mode"]; // *
        $query .= ",Geraetestatus=\"".$daten["Modus"]."\"";
        if ($daten["Regler"] == 45) {
          $query .= ",OutputMode=".$daten["Mode"]; // *
          $query .= ",InverterStatus=\"".$daten["Status"]."\""; // *
          $query .= ",IntModus=".$daten["IntModus"]; // *
        }
        if ($daten["Regler"] == 71) {
          $query .= ",Warnungen=".$daten["Warnungen"]; // *
          $query .= ",Temperatur=".$daten["Temperatur"]; // *
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_GesamtHeute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Huawei Wechselrichter   */
      case 46:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Powerfactor=".$daten["AC_Powerfactor"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung1=".$daten["PV_Spannung1"];
        $query .= ",Spannung2=".$daten["PV_Spannung2"];
        $query .= ",Spannung3=".$daten["PV_Spannung3"];
        $query .= ",Strom1=".$daten["PV_Strom1"];
        $query .= ",Strom2=".$daten["PV_Strom2"];
        $query .= ",Strom3=".$daten["PV_Strom3"];
        $query .= ",Spannung4=".$daten["PV_Spannung4"];
        $query .= ",Spannung5=".$daten["PV_Spannung5"];
        $query .= ",Spannung6=".$daten["PV_Spannung6"];
        $query .= ",Strom4=".$daten["PV_Strom4"];
        $query .= ",Strom5=".$daten["PV_Strom5"];
        $query .= ",Strom6=".$daten["PV_Strom6"];
        $query .= ",Spannung7=".$daten["PV_Spannung7"];
        $query .= ",Spannung8=".$daten["PV_Spannung8"];
        $query .= ",Strom7=".$daten["PV_Strom7"];
        $query .= ",Strom8=".$daten["PV_Strom8"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= ",MPPT1_Leistung=".$daten["MPPT1_Leistung"];
        $query .= ",MPPT2_Leistung=".$daten["MPPT2_Leistung"];
        $query .= ",MPPT3_Leistung=".$daten["MPPT3_Leistung"];
        $query .= ",MPPT4_Leistung=".$daten["MPPT4_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",WR_Fehler=\"".$daten["State1"]."\""; // *
        $query .= ",Modell=".$daten["Modell"]; // *
        $query .= ",Status=".$daten["Status"]; // *
        $query .= ",Effizienz=\"".$daten["Effizienz"]."\""; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_GesamtHeute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_GesamtMonat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_GesamtJahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Growatt Wechselrichter    */
      case 48:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Modell"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
        if ($daten["Protokollversion"] != 4) {
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
        }
        else {
          $query .= ",Scheinleistung=".$daten["AC_Scheinleistung_R"];
          $query .= ",Ladeleistung=".$daten["AC_Charge"];
          $query .= ",Eingangsleistung=".$daten["AC_Eingangsleistung"];
          $query .= ",Eingangsscheinleistung=".$daten["AC_Eingangsscheinleistung"];
          $query .= ",Ladestrom=".$daten["AC_Ladestrom"];
          $query .= ",Entladeleistung=".$daten["AC_Entladeleistung"];
          $query .= ",Ausgangsspannung=".$daten["AC_Ausgangsspannung"];
          $query .= ",Ausgangsfrequenz=".$daten["AC_Ausgangsfrequenz"];
          $query .= ",Ausgangslast=".$daten["Ausgangslast"];
          $query .= ",Inverterstrom=".$daten["Inverterstrom"];
        }
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "String1_Spannung=".$daten["PV_Spannung1"];
        $query .= ",String2_Spannung=".$daten["PV_Spannung2"];
        $query .= ",String1_Strom=".$daten["PV_Strom1"];
        $query .= ",String2_Strom=".$daten["PV_Strom2"];
        $query .= ",String1_Leistung=".$daten["PV_Leistung1"];
        $query .= ",String2_Leistung=".$daten["PV_Leistung2"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        if ($daten["Protokollversion"] == 3) {
          $query .= ",PV1_Leistung_Heute=".$daten["PV1_Energie_Heute"];
          $query .= ",PV2_Leistung_Heute=".$daten["PV2_Energie_Heute"];
          $query .= ",PV1_Leistung_Gesamt=".$daten["PV1_Energie_Gesamt"];
          $query .= ",PV2_Leistung_Gesamt=".$daten["PV2_Energie_Gesamt"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        if ($daten["Protokollversion"] == 2 or $daten["Protokollversion"] == 3) {
          $query .= "Batterie ";
          $query .= "EntladenHeute=".$daten["EntladenHeute"];
          $query .= ",GeladenHeute=".$daten["GeladenHeute"];
          if ($daten["Protokollversion"] == 3) {
            $query .= ",EntladenGesamt=".$daten["EntladenGesamt"];
            $query .= ",GeladenGesamt=".$daten["GeladenGesamt"];
            $query .= ",EntladeLeistung=".$daten["EntladeLeistung"];
            $query .= ",LadeLeistung=".$daten["LadeLeistung"];
            $query .= ",Batteriespannung=".$daten["Batteriespannung"];
            $query .= ",SOC=".$daten["SOC"];
            $query .= ",BMS_Status=".$daten["BMS_Status"];
            $query .= ",BMS_ErrorCode=".$daten["BMS_ErrorCode"];
            $query .= ",BMS_SOC=".$daten["BMS_SOC"];
            $query .= ",BMS_SOH=".$daten["BMS_SOH"];
            $query .= ",BMS_Spannung=".$daten["BMS_Spannung"];
            $query .= ",BMS_Strom=".$daten["BMS_Strom"];
            $query .= ",BMS_Temperatur=".$daten["BMS_Temperatur"];
            if (isset($daten["Zellenspannung1"])) {
              $query .= ",Zellenspannung1=".$daten["Zellenspannung1"];
              $query .= ",Zellenspannung2=".$daten["Zellenspannung2"];
              $query .= ",Zellenspannung3=".$daten["Zellenspannung3"];
              $query .= ",Zellenspannung4=".$daten["Zellenspannung4"];
              $query .= ",Zellenspannung5=".$daten["Zellenspannung5"];
              $query .= ",Zellenspannung6=".$daten["Zellenspannung6"];
              $query .= ",Zellenspannung7=".$daten["Zellenspannung7"];
              $query .= ",Zellenspannung8=".$daten["Zellenspannung8"];
              $query .= ",Zellenspannung9=".$daten["Zellenspannung9"];
              $query .= ",Zellenspannung10=".$daten["Zellenspannung10"];
              $query .= ",Zellenspannung11=".$daten["Zellenspannung11"];
              $query .= ",Zellenspannung12=".$daten["Zellenspannung12"];
              $query .= ",Zellenspannung13=".$daten["Zellenspannung13"];
              $query .= ",Zellenspannung14=".$daten["Zellenspannung14"];
              $query .= ",Zellenspannung15=".$daten["Zellenspannung15"];
              $query .= ",Zellenspannung16=".$daten["Zellenspannung16"];
            }
          }
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Protokollversion"] == 4) {
          $query .= "Batterie ";
          $query .= "Spannung=".$daten["Batteriespannung"];
          $query .= ",SOC=".$daten["BatterieSOC"];
          $query .= ",DC_Spannung=".$daten["DC_Spannung"];
          $query .= ",DC_DC_Temperatur=".$daten["DC-DC-Temperatur"];
          $query .= ",DSP_Port_Spannung=".$daten["Batt_DSP_Port"];
          $query .= ",DSP_Bus_Spannung=".$daten["Batt_DSP_Bus"];
          $query .= ",Leistung=".$daten["Batterie_Leistung"];
          $query .= ",Entladung=".$daten["Batterie_Entladung"];
          $query .= ",Ladung=".$daten["Batterie_Ladung"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "FehlerCode=".$daten["FehlerCode"];
        $query .= ",Warnungen=".$daten["Warnungen"];
        $query .= ",Status=".$daten["Status"];
        if ($daten["Protokollversion"] != 4) {
          $query .= ",AnzahlStrings=".$daten["Anz.MPPT"];
          $query .= ",AnzahlPhasen=".$daten["Anz.Phasen"];
        }
        $query .= ",Temperatur=".$daten["Temperatur"];
        if ($daten["Protokollversion"] == 3) {
          $query .= ",SystemWorkMode=".$daten["SystemWorkMode"];
          $query .= ",InverterStatus=".$daten["Inverter_Status"];
        }
        if ($daten["Protokollversion"] == 4) {
          $query .= ",MaxChargeCurrent=".$daten["MaxChargeCurrent"];
          $query .= ",BulkChargeVolt=".$daten["BulkChargeVolt"];
          $query .= ",FloatChargeVolt=".$daten["FloatChargeVolt"];
          $query .= ",BatLowUtiVolt=".$daten["BatLowUtiVolt"];
          $query .= ",FloatChargCurrent=".$daten["FloatChargCurrent"];
          $query .= ",BatteryType=".$daten["BatteryType"];
          $query .= ",MPPT_Fan_Speed=".$daten["MPPT_Fan_Speed"];
          $query .= ",Inv_Fan_Speed=".$daten["Inv_Fan_Speed"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 1);
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        if ($daten["Protokollversion"] == 3) {
          $query .= ",Gesamtverbrauch=".$daten["Gesamtverbrauch"];
          $query .= ",Gesamteinspeisung=".$daten["Gesamteinspeisung"];
          $query .= ",LoadLeistungGesamt=".$daten["LoadLeistungGesamt"];
          $query .= ",Energieerzeugung_Heute=".$daten["Energieerzeugung_Heute"];
          $query .= ",EnergieerzeugungGesamt=".$daten["EnergieerzeugungGesamt"];
          $query .= ",Energieeinspeisung_Heute=".$daten["Energieeinspeisung_Heute"];
          $query .= ",EnergieeinspeisungGesamt=".$daten["EnergieeinspeisungGesamt"];
          $query .= ",Local_load_energy_today=".$daten["Local_load_energy_today"];
          $query .= ",Local_load_energy_total=".$daten["Local_load_energy_total"];
        }
        if ($daten["Protokollversion"] == 4) {
          $query .= ",BattEntladeenergieGesamt=".$daten["Batt_EntladeenergieGesamt"];
          $query .= ",NetzBezugHeute=".$daten["Netz_Bezug_Heute"];
          $query .= ",NetzBezugGesamt=".$daten["Netz_Bezug_Gesamt"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Huawei SmartLogger 3000   */
      case 49:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Netz ";
        $query .= "Spannung_R=".$daten["Netz_Spannung_R"];
        $query .= ",Spannung_S=".$daten["Netz_Spannung_S"];
        $query .= ",Spannung_T=".$daten["Netz_Spannung_T"];
        $query .= ",Strom_R=".$daten["Netz_Strom_R"];
        $query .= ",Strom_S=".$daten["Netz_Strom_S"];
        $query .= ",Strom_T=".$daten["Netz_Strom_T"];
        $query .= ",Leistung_R=".$daten["Netz_Leistung_R"];
        $query .= ",Leistung_S=".$daten["Netz_Leistung_S"];
        $query .= ",Leistung_T=".$daten["Netz_Leistung_T"];
        $query .= ",Leistung=".$daten["Netz_Leistung"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Verbrauch=".$daten["Verbrauch"];
        $query .= ",Bezug=".$daten["Bezug"];
        $query .= ",CO2_Ersparnis=".$daten["CO2_Ersparnis"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Leistung=".$daten["PV_Leistung"];
        $query .= ",Strom=".$daten["DC_Strom"];
        $query .= ",Sonnenstunden=".$daten["Sonnenstunden"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "AlarmInfo1=".$daten["AlarmInfo1"];
        $query .= ",AlarmInfo2=".$daten["AlarmInfo2"];
        $query .= ",Status=".$daten["Status"];
        $query .= ",WR_Effektivitaet=".$daten["WR_Effektivitaet"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_EinspeisungGesamt=".$daten["Wh_Einspeisung"];
        $query .= ",Wh_BezugGesamt=".$daten["Wh_Bezug"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Goodwe Wechselrichter   */
      case 52:
      case 64:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Spannung"];
        $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
        $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
        $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Frequenz=".$daten["Netzfrequenz"];
        $query .= ",Einspeisung_Bezug=".$daten["Einspeisung_Bezug"];
        $query .= ",Verbrauch=".$daten["Verbrauch"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "Spannung=".$daten["Batterie_Spannung"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= ",Leistung=".$daten["Batterie_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "PV1_Spannung=".$daten["PV1_Spannung"];
        $query .= ",PV1_Leistung=".$daten["PV1_Leistung"];
        $query .= ",PV2_Spannung=".$daten["PV2_Spannung"];
        $query .= ",PV2_Leistung=".$daten["PV2_Leistung"];
        $query .= ",PV3_Spannung=".$daten["PV3_Spannung"];
        $query .= ",PV3_Leistung=".$daten["PV3_Leistung"];
        $query .= ",PV_Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "BMS_Status=".$daten["BMS_Status"];
        $query .= ",WR_Mode=".$daten["WR_Mode"];
        $query .= ",MeterType=".$daten["MeterType"];
        $query .= ",Batterie_Mode=".$daten["Batterie_Mode"];
        $query .= ",Diag_Binary=\"".$daten["Diag_Binary"]."\"";
        $query .= ",Diag_Status=".$daten["Diag_Status"];
        if (isset($daten["Batterie_Temperatur"])) {
          $query .= ",Batterie_Temperatur=".$daten["Batterie_Temperatur"];
        }
        if (isset($daten["WR_Temperatur"])) {
          $query .= ",WR_Temperatur=".$daten["WR_Temperatur"];
        }
        if (isset($daten["Seriennummer"])) {
          $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_NetzeinspeisungHeute=".$daten["EinspeisungHeute"];
        $query .= ",Wh_NetzbezugHeute=".$daten["BezugHeute"];
        $query .= ",Wh_PVLeistungGesamt=".$daten["PV_Leistung_total"];
        $query .= ",Wh_EinspeisungGesamt=".round($daten["Einspeisung_total"], 2);
        $query .= ",Wh_GesamtHeute=".$daten["WattstundenGesamtHeute"]; //neu hinzugefügt
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"]; //neu hinzugefügt
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Studer Wechselrichter   */
      case 55:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        for ($i = 1; $i <= $daten["Anz-Xtender"]; $i++) {
          $query .= "WR".$i."_AC ";
          $query .= "Spannung=".$daten["Inverter".$i."_AC_Spannung"];
          $query .= ",Strom=".$daten["Inverter".$i."_AC_Strom"];
          $query .= ",Leistung=".$daten["Inverter".$i."_AC_Leistung"];
          $query .= ",Frequenz=".$daten["Inverter".$i."_AC_Frequenz"];
          $query .= ",Status=".$daten["Inverter".$i."_Status"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
          $query .= "WR".$i."_Batterie ";
          $query .= "Spannung=".$daten["Inverter".$i."_BatterieSpannung"];
          $query .= ",Ladestrom=".$daten["Inverter".$i."_BatterieLadestrom"];
          $query .= ",Wh_EntladungHeute=".$daten["Inverter".$i."_BatterieEntladungHeute"];
          $query .= ",SOC=".$daten["Inverter".$i."_BatterieSOC"];
          $query .= ",Status=".$daten["Inverter".$i."_BatterieStatus"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Anz-BSP"] > 0) {
          $query .= "BSP ";
          $query .= "Spannung=".$daten["BSP_Spannung"];
          $query .= ",Strom=".$daten["BSP_Strom"];
          $query .= ",SOC=".$daten["BSP_SOC"];
          $query .= ",Leistung=".$daten["BSP_Leistung"];
          $query .= ",Temperatur=".$daten["BSP_Temperatur"];
          $query .= ",Ah_geladenHeute=".$daten["BSP_Ah_geladenHeute"];
          $query .= ",Ah_entladenHeute=".$daten["BSP_Ah_entladenHeute"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        for ($i = 1; $i <= $daten["Anz-VarioTrack"]; $i++) {
          $query .= "VT".$i."PV ";
          $query .= "Spannung=".$daten["VT".$i."_PV_Spannung"];
          $query .= ",Strom=".$daten["VT".$i."_PV_Strom"];
          $query .= ",Leistung=".$daten["VT".$i."_PV_Leistung"];
          $query .= ",Type=".$daten["VT".$i."_Type"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        if ($daten["Anz-VarioTrack"] > 0) {
          $query .= "PV ";
          $query .= "Spannung=".$daten["VT1_PV_Spannung"];
          $query .= ",Strom=".$daten["VT_PV_Strom"];
          $query .= ",Leistung=".$daten["VT_PV_Leistung"];
          $query .= ",Type=".$daten["VT1_Type"];
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "Anz-Xtender=".$daten["Anz-Xtender"];
        $query .= ",Anz-VarioTrack=".$daten["Anz-VarioTrack"];
        $query .= ",Anz-VarioString=".$daten["Anz-VarioString"];
        $query .= ",Anz-BSP=".round($daten["Anz-BSP"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_GesamtLeistungHeute=".$daten["Wh_LeistungHeute"];
        $query .= ",Wh_GesamtVerbrauchHeute=".$daten["Wh_VerbrauchHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Allgemeiner Wechselrichter mit Batterie
        /* Huawei WR M1 Modelle  , RCT WR , Sungrow WR, Sofar WR  Solis */
      case 56:
      case 62:
      case 65:
      case 70:
      case 82:
      case 84:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        if ($daten["Regler"] == 84) {
          $query .= ",Strom_R=".$daten["AC_Strom_R"];
          $query .= ",Strom_S=".$daten["AC_Strom_S"];
          $query .= ",Strom_T=".$daten["AC_Strom_T"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
        }
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Bezug=".$daten["Bezug"];
        $query .= ",Hausverbrauch=".$daten["Hausverbrauch"];
        if ($daten["Regler"] == 65) {
          $query .= ",ExternalPower=".$daten["ExternalPower"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "Leistung=".$daten["PV_Leistung"];
        if ($daten["Regler"] == 65 or $daten["Regler"] == 84) {
          for ($i = 1; $i <= $daten["Anz_PV_Strings"]; $i++) {
            $query .= ",Spannung".$i."=".$daten["PV".$i."_Spannung"];
            $query .= ",Strom".$i."=".$daten["PV".$i."_Strom"];
            $query .= ",Leistung".$i."=".($daten["PV".$i."_Leistung"]);
            $query .= ",EnergieGesamt".$i."=".($daten["PV".$i."_EnergieGesamt"]);
          }
        }
        else {
          for ($i = 1; $i <= $daten["Anz_PV_Strings"]; $i++) {
            $query .= ",Spannung".$i."=".$daten["PV".$i."_Spannung"];
            $query .= ",Strom".$i."=".$daten["PV".$i."_Strom"];
            $query .= ",Leistung".$i."=".($daten["PV".$i."_Spannung"] * $daten["PV".$i."_Strom"]);
          }
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "LadeLeistung=".$daten["Batterie_Ladung"];
        $query .= ",EntladeLeistung=".$daten["Batterie_Entladung"];
        $query .= ",Status=".$daten["Batterie_Status"];
        $query .= ",SOC=".$daten["SOC"];
        if ($daten["Regler"] == 65) {
          $query .= ",SOC_Zielwert=".$daten["SOC_Zielwert"];
          $query .= ",Anz_Zyklen=".$daten["BatZyklen"];
        }
        if ($daten["Regler"] == 70 or $daten["Regler"] == 84) {
          $query .= ",Batterie_Spannung=".$daten["Batterie_Spannung"];
          $query .= ",Batterie_Strom=".$daten["Batterie_Strom"];
          $query .= ",Batterie_Leistung=".$daten["Batterie_Leistung"];
          $query .= ",Batterie_Temperatur=".$daten["Batterie_Temperatur"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",WR_Fehler=".$daten["FehlerCode"];
        $query .= ",Status=".$daten["DeviceStatus"];
        $query .= ",Anz_MPPT=".$daten["Anz_MPP_Trackers"];
        $query .= ",Modell_ID=".$daten["ModellID"];
        $query .= ",Modell=\"".$daten["Firmware"]."\"";
        $query .= ",Effizienz=".$daten["Effizienz"];
        if ($daten["Regler"] == 84) {
          $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
          $query .= ",Geraetestatus=\"".$daten["Geraetestatus"]."\"";
          $query .= ",Modell=\"".$daten["Modell"]."\"";
        }
        if ($daten["Regler"] == 56) {
          $query .= ",Isolation=".$daten["Isolation"];
          $query .= ",Alarm1=".$daten["Alarm1"];
          $query .= ",Alarm2=".$daten["Alarm2"];
          $query .= ",Alarm3=".$daten["Alarm3"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_GesamtHeute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        if ($daten["Regler"] == 70) {
          $query .= ",PV_Energie_Heute=".$daten["PV_Energie_Heute"];
          $query .= ",PV_Energie_Monat=".$daten["PV_Energie_Monat"];
        }
        if ($daten["Regler"] == 56 or $daten["Regler"] == 62 or $daten["Regler"] == 82) {
          $query .= ",WattstundengesamtImport=".$daten["WattstundengesamtImport"];
          $query .= ",WattstundengesamtExport=".$daten["WattstundengesamtExport"];
        }
        if ($daten["Regler"] == 84) {
          $query .= ",Wh_BatterieLadungHeute=".$daten["BatterieLadungHeute"];
          $query .= ",Wh_BatterieEntladungHeute=".$daten["BatterieEntladungHeute"];
          $query .= ",Wh_EinspeisungHeute=".$daten["EinspeisungHeute"];
          $query .= ",Wh_BezugHeute=".$daten["BezugHeute"];
          $query .= ",Wh_HausverbrauchHeute=".$daten["HausverbrauchHeute"];
        }
        if ($daten["Regler"] == 82) {
          $query .= ",Wh_BezugHeute=".$daten["Wh_BezugHeute"];
          $query .= ",Wh_EinspeisungHeute=".$daten["Wh_EinspeisungHeute"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Daly BMS Chinaware   */
      case 57:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Batterie ";
        $query .= "Strom=".$daten["Ampere"];
        $query .= ",SOC=".$daten["SOC"];
        $query .= ",Ladestrom=".$daten["Ladestrom"];
        $query .= ",Entladestrom=".$daten["Entladestrom"];
        $query .= ",Ah_Rest=".$daten["Ah_Rest"];
        $query .= ",Ladezyklen=".$daten["Lade/Entlade_Zyklen"];
        $query .= ",Spannung=".$daten["Batteriespannung"];
        for ($i = 1; $i <= $daten["Zellenanzahl"]; $i++) {
          $query .= ",Spannung_Zelle".$i."=".$daten["Spannung_Zelle".$i];
        }
        for ($i = 1; $i <= $daten["Anz_TempSensoren"]; $i++) {
          $query .= ",Temperatur_Sensor".$i."=".$daten["Temperatur".$i];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Anz_TempSensoren=".$daten["Anz_TempSensoren"];
        $query .= ",Zellenanzahl=".$daten["Zellenanzahl"];
        $query .= ",Status=".$daten["Ladung-Entladung"];
        $query .= ",BMS_Zyklen=".$daten["BMS_Zyklen"];
        $query .= ",Ladung_MOS_Status=".$daten["Ladung_MOS_Status"];
        $query .= ",Entladung_MOS_Status=".$daten["Entladung_MOS_Status"];
        $query .= ",Max_Temp=".$daten["Max_Temperatur"];
        $query .= ",Max_Temp_Zelle=".$daten["MaxTemp_ZellenNr"];
        $query .= ",Min_Temp=".$daten["Min_Temperatur"];
        $query .= ",Min_Temp_Zelle=".$daten["MinTemp_ZellenNr"];
        $query .= ",Max_Spannung=".$daten["Max_Spannung"];
        $query .= ",Max_Spannung_Zelle=".$daten["Max_Spannung_ZellenNr"];
        $query .= ",Min_Spannung=".$daten["Min_Spannung"];
        $query .= ",Min_Spannung_Zelle=".$daten["Min_Spannung_ZellenNr"];
        $query .= ",FehlerCode=\"".$daten["FehlerCode"]."\"";
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Hardy Barth Wallbox    */
      case 60:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["Spannung_R"];
        $query .= ",Spannung_S=".$daten["Spannung_S"];
        $query .= ",Spannung_T=".$daten["Spannung_T"];
        $query .= ",Strom_R=".($daten["Strom_R"]);
        $query .= ",Strom_S=".($daten["Strom_S"]);
        $query .= ",Strom_T=".($daten["Strom_T"]);
        $query .= ",Leistung_R=".($daten["Leistung_R"]);
        $query .= ",Leistung_S=".($daten["Leistung_S"]);
        $query .= ",Leistung_T=".($daten["Leistung_T"]);
        $query .= ",Leistung_gesamt=".($daten["Wh_Leistung_aktuell"]);
        $query .= ",Leistungsfaktor_R=".$daten["Leistungsfaktor_R"];
        $query .= ",Leistungsfaktor_S=".$daten["Leistungsfaktor_S"];
        $query .= ",Leistungsfaktor_T=".$daten["Leistungsfaktor"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=\"".$daten["Status"]."\"";
        $query .= ",Mode=\"".$daten["Mode"]."\"";
        $query .= ",MaxAmpere=".$daten["MaxAmpere"];
        $query .= ",Kabel=".$daten["Connected"];
        $query .= ",ModeID=".$daten["ModeID"];
        $query .= ",StateID=".$daten["StateID"];
        $query .= ",AmpereVorgabe=".$daten["AmpereVorgabe"];
        $query .= ",LadungAmpere=".$daten["LadungAmpere"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".$daten["Wh_Gesamtleistung"];
        $query .= ",Wh_Ladevorgang=".$daten["Wh_Ladevorgang"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* cFos Wallbox   */
      case 63:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["Spannung_R"];
        $query .= ",Spannung_S=".$daten["Spannung_S"];
        $query .= ",Spannung_T=".$daten["Spannung_T"];
        $query .= ",Strom_R=".($daten["Strom_R"]);
        $query .= ",Strom_S=".($daten["Strom_S"]);
        $query .= ",Strom_T=".($daten["Strom_T"]);
        $query .= ",Leistung_gesamt=".($daten["Leistung"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Stationsstatus=".$daten["WB_Status"];
        $query .= ",MaxAmpere=".($daten["AktuellerLadestrom"]);
        $query .= ",MaxAmpereHardware=".($daten["HardwareLimitAmp"] / 1000);
        $query .= ",MaxAmpereUser=".($daten["KabelLimitAmp"] / 1000);
        $query .= ",Ladekabel=\"".$daten["Kabelstatus"]."\"";
        $query .= ",EnableSys=".$daten["EVSEAktiv"];
        $query .= ",EnableUser=".$daten["EVSEStatus"];
        $query .= ",AnzPhasen=".$daten["Anz_Phasen"];
        $query .= ",Ladestatus=".$daten["Ladestatus"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Gesamt=".($daten["GesamtEnergie"]);
        $query .= ",Wh_Ladevorgang=".($daten["WattstundenProLadung"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;
        // Kostal Piko CI
      case 66:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=".$daten["Firmware"];
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        if ($daten["AnzahlPhasen"] == 3) {
          $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
          $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        }
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Leistung=".$daten["AC_Leistung"];
        $query .= ",Scheinleistung=".$daten["AC_Scheinleistung"];
        $query .= ",Wirkleistung=".$daten["AC_Wirkleistung"];
        $query .= ",Ausgangslast=".$daten["Ausgangslast"];
        $query .= ",Verbrauch=".$daten["Verbrauch"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Ueberschuss=".$daten["Ueberschuss"];
        $query .= ",Solarleistung=".$daten["AC_Solarleistung"];
        $query .= ",Verbrauch_Netz=".$daten["Verbrauch_Netz"];
        $query .= ",Verbrauch_PV=".$daten["Verbrauch_PV"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Gesamtleistung=".$daten["PV_Leistung"];
        $query .= ",Spannung_Str_1=".$daten["PV1_Spannung"];
        $query .= ",Leistung_Str_1=".$daten["PV1_Leistung"];
        $query .= ",Strom_Str_1=".$daten["PV1_Strom"];
        if ($daten["AnzahlStrings"] > 1) {
          $query .= ",Spannung_Str_2=".$daten["PV2_Spannung"];
          $query .= ",Strom_Str_2=".$daten["PV2_Strom"];
          $query .= ",Leistung_Str_2=".$daten["PV2_Leistung"];
        }
        if ($daten["AnzahlStrings"] > 2) {
          $query .= ",Spannung_Str_3=".$daten["PV3_Spannung"];
          $query .= ",Strom_Str_3=".$daten["PV3_Strom"];
          $query .= ",Leistung_Str_3=".$daten["PV3_Leistung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Status=".$daten["Status"];
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
        $query .= ",DC_Gesamtleistung=".$daten["Total_DC_Power"];
        $query .= ",Laufzeit=".$daten["Laufzeit"];
        $query .= ",WirkungsgradWR=".$daten["WirkungsgradWR"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".round($daten["WattstundenGesamtHeute"], 2);
        $query .= ",Wh_Gesamt_Monat=".$daten["WattstundenGesamtMonat"];
        $query .= ",Wh_Gesamt_Jahr=".$daten["WattstundenGesamtJahr"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Gesamtverbrauch_Netz=".$daten["Gesamtverbrauch_Netz"];
        $query .= ",Wh_Gesamtverbrauch_PV=".$daten["Gesamtverbrauch_PV"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* Goodwe Wechselrichter  XS Serie */
      case 67:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Spannung_R"];
        $query .= ",Strom=".$daten["AC_Strom_R"];
        $query .= ",Frequenz=".$daten["Netzfrequenz_R"];
        $query .= ",Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        $query .= ",Frequenz_R=".$daten["Netzfrequenz_R"];
        $query .= ",Frequenz_S=".$daten["Netzfrequenz_S"];
        $query .= ",Frequenz_T=".$daten["Netzfrequenz_T"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "PV ";
        $query .= "MPPT1_Spannung=".$daten["MPPT1_Spannung"];
        $query .= ",MPPT1_Strom=".$daten["MPPT1_Strom"];
        $query .= ",MPPT2_Spannung=".$daten["MPPT2_Spannung"];
        $query .= ",MPPT2_Strom=".$daten["MPPT2_Strom"];
        $query .= ",PV1_Spannung=".$daten["PV1_Spannung"];
        $query .= ",PV1_Strom=".$daten["PV1_Strom"];
        $query .= ",PV2_Spannung=".$daten["PV2_Spannung"];
        $query .= ",PV2_Strom=".$daten["PV2_Strom"];
        $query .= ",PV3_Spannung=".$daten["PV3_Spannung"];
        $query .= ",PV3_Strom=".$daten["PV3_Strom"];
        $query .= ",PV4_Spannung=".$daten["PV4_Spannung"];
        $query .= ",PV4_Strom=".$daten["PV4_Strom"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "Temperatur=".$daten["WR_Temperatur"];
        $query .= ",WR_Mode=".$daten["WR_Mode"];
        $query .= ",PV_Mode=".$daten["PV_Mode"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_NetzeinspeisungHeute=".$daten["EinspeisungHeute"];
        $query .= ",Wh_GesamtHeute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Gesamtertragszeit=".$daten["Gesamtertragszeit"];
        $query .= ",Wh_EinspeisungGesamt=".round($daten["Einspeisung_total"], 2);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* VARTA Speicher   */
      case 68:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "PV ";
        $query .= "Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "AC ";
        $query .= "Leistung=".$daten["Netz_Leistung"];
        $query .= ",Einspeisung=".$daten["Einspeisung"];
        $query .= ",Bezug=".$daten["Bezug"];
        if ($daten["Tableversion"] > 12) {
          $query .= ",Frequenz=".$daten["Frequenz"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "SOC=".$daten["SOC"];
        $query .= ",Energie_installiert=".$daten["Energie_installiert"];
        $query .= ",Leistung=".$daten["DC_Leistung"];
        if ($daten["Tableversion"] > 12) {
          $query .= ",verfuegbare_Ladeleistung=".$daten["verfuegbare_Ladeleistung"];
          $query .= ",verfuegbare_Entladeleistung=".$daten["verfuegbare_Entladeleistung"];
          $query .= ",verfuegbare_Ladeenergie=".$daten["verfuegbare_Ladeenergie"];
          $query .= ",verfuegbare_Entladeenergie=".$daten["verfuegbare_Entladeenergie"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "AnzBatterieModule=".$daten["AnzBatterieModule"];
        $query .= ",Status=".$daten["Status"];
        $query .= ",Seriennummer=".$daten["Seriennummer"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_GesamtHeute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["WattstundenGesamt"];
        $query .= ",Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* HomeMatic Gaszähler .. und andere   */
      case 72:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        for ($i = 1; $i <= $daten["Anzahl_Geraete"]; $i++) {
          $query .= "HM_Geraet".$i." ";
          foreach ($daten[$daten["Measurement".$i]] as $key => $value) {
            $query .= $key."=\"".$value."\",";
          }
          foreach ($daten["HM_Seriennummer".$i] as $key => $value) {
            if (substr($key, - 4) == "Unit") {
              $query .= $key."=\"".$value."\",";
            }
            elseif (substr($key, - 5) == ".Text") {
              $query .= substr($key, 0, - 5)."=\"".$value."\",";
            }
            else {
              $query .= $key."=".$value.",";
            }
          }
          $query = substr($query, 0, - 1);
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        for ($i = 1; $i <= $daten["Anzahl_Variablen"]; $i++) {
          $query .= "HM_Systemvariable".$i." ";
          foreach ($daten["HM_Systemvariable".$i] as $key => $value) {
            $query .= $key."=\"".$value."\",";
          }
          $query = substr($query, 0, - 1);
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        break;

        /*  3 Phasen Wechselrichter ohne Batterie  **
        **  SofarSolar 5.5 KTL-X                   **
        **  Solax X3 Hybrid                        */
      case 73:
      case 80:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Hardware=\"".$daten["Hardware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung_R=".$daten["AC_Spannung_R"];
        $query .= ",Spannung_S=".$daten["AC_Spannung_S"];
        $query .= ",Spannung_T=".$daten["AC_Spannung_T"];
        $query .= ",Frequenz=".$daten["AC_Frequenz"];
        $query .= ",Strom_R=".$daten["AC_Strom_R"];
        $query .= ",Strom_S=".$daten["AC_Strom_S"];
        $query .= ",Strom_T=".$daten["AC_Strom_T"];
        if ($daten["Regler"] == 73) {
          $query .= ",Leistung=".$daten["PV_Leistung"];
        }
        else {
          $query .= ",Leistung=".$daten["AC_Leistung"];
          $query .= ",Leistung_R=".$daten["AC_Leistung_R"];
          $query .= ",Leistung_S=".$daten["AC_Leistung_S"];
          $query .= ",Leistung_T=".$daten["AC_Leistung_T"];
          $query .= ",Bezug_Einspeisung=".$daten["Bezug_Einspeisung"];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung1=".$daten["PV1_Spannung"];
        $query .= ",Spannung2=".$daten["PV2_Spannung"];
        $query .= ",Strom1=".$daten["PV1_Strom"];
        $query .= ",Strom2=".$daten["PV2_Strom"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= ",Leistung1=".$daten["PV1_Leistung"];
        $query .= ",Leistung2=".$daten["PV2_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",WR_Fehler=".$daten["Fehler1"]; // *
        $query .= ",Modell=\"".$daten["Modell"]."\""; // *
        $query .= ",Status=".$daten["Status"]; // *
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\""; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        if ($daten["Regler"] == 80) {
          $query .= "Batterie ";
          $query .= "Batterie_Temperatur=".$daten["Batterie_Temperatur"];
          $query .= ",Batterie_Temperatur_Max=".$daten["BatterieTemperaturMax"]; // *
          $query .= ",Batterie_Temperatur_Min=".$daten["BatterieTemperaturMin"]; // *
          $query .= ",Batterie_Spannung1=".$daten["Batterie_Spannung1"]; // *
          $query .= ",Batterie_Strom1=".$daten["Batterie_Strom1"]; // *
          $query .= ",Batterie_Ladung1=".$daten["Batterie_Ladung1"]; // *
          $query .= ",Batterie_SOC=".$daten["Batterie_SOC"]; // *
          $query .= ",Charge_Discharge_Power=".$daten["Charge_Discharge_Power"]; // *
          $query .= ",ChargeableElectricCapacity=".$daten["ChargeableElectricCapacity"]; // *
          $query .= ",DischargeableElectricCapacity=".$daten["DischargeableElectricCapacity"]; // *
          $query .= ",Status=".$daten["Status"]; // *
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n"; // *
        }
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["Energie_Total"];
        if ($daten["Regler"] == 73) {
          $query .= ",Laufzeit_Heute=".$daten["Laufzeit_Heute"];
        }
        else {
          $query .= ",Laufzeit_Gesamt=".$daten["Laufzeit_Gesamt"];
        }
        if ($daten["Regler"] == 80) {
          $query .= ",EinspeisungGesamt=".$daten["EinspeisungGesamt"];
          $query .= ",BezugGesamt=".$daten["BezugGesamt"];
          $query .= ",AC_LesitungGesamt=".$daten["AC_LeistungGesamt"];
          $query .= ",EchargeToday=".$daten["EchargeToday"];
          $query .= ",EchargeTotal=".$daten["EchargeTotal"];
          $query .= ",SolarEnergyTotal=".$daten["SolarEnergyTotal"];
          $query .= ",SolarEnergyToday=".$daten["SolarEnergyToday"];
          $query .= ",EchargeToday=".$daten["EchargeToday"];
          $query .= ",OutputEnergy_Charge=".$daten["OutputEnergy_Charge"]; // *
          $query .= ",OutputEnergy_Charge_today=".$daten["OutputEnergy_Charge_today"]; // *
          $query .= ",InputEnergy_Charge=".$daten["InputEnergy_Charge"]; // *
          $query .= ",InputEnergy_Charge_today=".$daten["InputEnergy_Charge_today"]; // *
          $query .= ",Batterie_Leistung_Gesamt=".$daten["Batterie_Leistung_Gesamt"]; // *
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  1 Phasen Microwechselrichter  **
        **  Zur Zeit ausgeschaltet                                           */
      case 767:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Spannung=".$daten["AC_Spannung"];
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",LimitPower=".$daten["LimitPower"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "PV ";
        $query .= "Spannung=".$daten["PV_Spannung"];
        $query .= ",Strom=".$daten["PV_Strom"];
        $query .= ",Leistung=".$daten["PV_Leistung"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Service ";
        $query .= "Temperatur=".$daten["Temperatur"];
        $query .= ",WR_Fehler=".$daten["Fehler"]; // *
        $query .= ",Status=".$daten["Status"]; // *
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\""; // *
        $query .= ",OnOff=".$daten["OnOff"]; // *
        $query .= ",Portnummer=".$daten["Portnummer"]; // *
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n"; // *
        $query .= "Summen ";
        $query .= "Wh_Heute=".$daten["WattstundenGesamtHeute"];
        $query .= ",Wh_Gesamt=".$daten["Energie_Total"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  Innogy Wallbox  Compleo eBoxen  */

        /*  Minimal Wallbox **/
      case 78:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Hersteller=\"".$daten["Hersteller"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Strom_R=".($daten["Strom_R"]);
        $query .= ",Strom_S=".($daten["Strom_S"]);
        $query .= ",Strom_T=".($daten["Strom_T"]);
        $query .= ",Leistung=".($daten["Leistung"]);
        $query .= ",Leistung_R=".($daten["Leistung_R"]);
        $query .= ",Leistung_S=".($daten["Leistung_S"]);
        $query .= ",Leistung_T=".($daten["Leistung_T"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "StationAktiv=".$daten["Station_aktiv"];
        $query .= ",MaxAmpere=".($daten["Strom_R"]);
        $query .= ",Ladekabel=".$daten["Kabelstatus"];
        $query .= ",Ladestatus=\"".$daten["Status"]."\"";
        $query .= ",AnzPhasenAktiv=".$daten["Anz_Phasen"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Summen ";
        $query .= "Wh_Ladevorgang=".($daten["WattstundenProLadung"]);
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /*  my_PV THOR und THOR 9s  */
      case 81:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Hersteller=\"".$daten["Hersteller"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "AC ";
        $query .= "Strom_R=".($daten["Strom_R"]);
        $query .= ",Strom_S=".($daten["Strom_S"]);
        $query .= ",Strom_T=".($daten["Strom_T"]);
        $query .= ",Spannung_R=".($daten["Spannung_R"]);
        $query .= ",Spannung_S=".($daten["Spannung_S"]);
        $query .= ",Spannung_T=".($daten["Spannung_T"]);
        $query .= ",Gesamtleistung=".($daten["LeistungGesamt"]);
        $query .= ",Solarleistung=".($daten["SolarLeistung"]);
        $query .= ",Netzleistung=".($daten["NetzLeistung"]);
        $query .= ",PWM_OUT=".($daten["PWM_OUT"]);
        $query .= ",Zaehler_Leistung=".($daten["Zaehler_Leistung"]);
        $query .= ",Frequenz=".$daten["Frequenz"];
        $query .= ",Spannung_out=".$daten["Spannung_out"];
        $query .= ",Leistung=".$daten["Power"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Service ";
        $query .= "DeviceState=".$daten["DeviceState"];
        $query .= ",Betriebsstatus=".$daten["Betriebsstatus"];
        $query .= ",Mode=".$daten["Mode"];
        $query .= ",NachtMode=".$daten["Nachtmode"];
        $query .= ",Status=".$daten["Status"];
        $query .= ",Seriennummer=\"".$daten["Seriennummer"]."\"";
        $query .= ",Temperatur1=".$daten["Temperatur1"];
        $query .= ",Temperatur2=".$daten["Temperatur2"];
        $query .= ",Temperatur3=".$daten["Temperatur3"];
        $query .= ",HW1TempMax=".$daten["HW1TempMax"];
        $query .= ",HW1TempMin=".$daten["HW1TempMin"];
        $query .= ",HW2TempMax=".$daten["HW2TempMax"];
        $query .= ",HW2TempMin=".$daten["HW2TempMin"];
        $query .= ",HW3TempMax=".$daten["HW3TempMax"];
        $query .= ",HW3TempMin=".$daten["HW3TempMin"];
        $query .= ",Thor_Nummer=".$daten["AC_Thor_Nummer"];
        $query .= ",LegionellenInterval=".$daten["LegionellenInterval"];
        $query .= ",LegionellenStart=".$daten["LegionellenStart"];
        $query .= ",LegionellenStop=".$daten["LegionellenStop"];
        $query .= ",LegionellenTemp=".$daten["LegionellenTemp"];
        $query .= ",LegionellenMode=".$daten["LegionellenMode"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /* JK BMS   */
      case 83:
        if (date("i") == "01" or $daten["Demodaten"] or date("H") == date("H", $Sonnenaufgang)) {
          $query .= "Info ";
          $query .= "Firmware=\"".$daten["Firmware"]."\"";
          $query .= ",Produkt=\"".$daten["Produkt"]."\"";
          $query .= ",Objekt=\"".$daten["Objekt"]."\"";
          $query .= ",Datum=\"".$daten["Datum"]."\"";
          $query .= "  ".$daten["zentralerTimestamp"];
          $query .= "\n";
        }
        $query .= "Service ";
        $query .= "SOC=".$daten["SOC"];
        $query .= ",Ladezyklen=".$daten["Ladezyklen"];
        $query .= ",TotalBatCycleCap=".$daten["TotalBatCycleCap"];
        $query .= ",Max_Spannung=".$daten["Max_Spannung"];
        $query .= ",Min_Spannung=".$daten["Min_Spannung"];
        $query .= ",SpannungDiff=".$daten["SpannungDiff"];
        $query .= ",Zellenanzahl=".$daten["Zellenanzahl"];
        $query .= ",NrTempsens=".$daten["NrTempsens"];
        $query .= ",TempBMS=".$daten["TempBMS"];
        $query .= ",Temp1=".$daten["Temp1"];
        $query .= ",Temp2=".$daten["Temp2"];
        $query .= ",BatWarning=".$daten["BatWarning"];
        $query .= ",BatStatus=".$daten["BatStatus"];
        $query .= ",NrBatStrings=".$daten["NrBatStrings"];
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        $query .= "Batterie ";
        $query .= "BatStrom=".$daten["BatStrom"];
        $query .= ",BatSpannung=".$daten["BatSpannung"];
        $query .= ",BatLeistung=".$daten["BatLeistung"];
        for ($i = 1; $i <= $daten["Zellenanzahl"]; $i++) {
          $query .= ",Spannung_Zelle".$i."=".$daten["Spannung_Zelle".$i];
        }
        $query .= "  ".$daten["zentralerTimestamp"];
        $query .= "\n";
        break;

        /***********************************/
        //*** Neue vereinfachte Routine  ***/
        //*** Pellet Öfen                ***/
        //*** Hoymiles                   ***/
        //*** Venus OS GX                ***/
        //*** SofarSolar alle Modelle    ***/
        //*** Ahoy-DTU                   ***/
        //*** OpenDTU                    ***/
        //*** Nilan Wärmepumpe           ***/
        //*** Seplos                     ***/
        //*** FSP BMS                    ***/
        //*** Deye Hybrid Wechselrichter ***/
        //***                            ***/
        //***                            ***/
        //***                            ***/
        //***                            ***/
      case 76:
      case 85:
      case 86:
      case 87:
      case 88:
      case 89:
      case 90:
      case 93:
      default:
        foreach ($daten as $key => $value) {
          if (is_array($daten[$key])) {
            $query .= $key."  ";
            foreach ($daten[$key] as $key1 => $value1) {
              if (substr($key1, - 5) == ".Text") {
                $query .= substr($key1, 0, - 5)."=\"".$value1."\",";
              }
              else {
                if (strtolower($value1) == "false") {
                  $query .= $key1."=0,";
                }
                elseif (strtolower($value1) == "true") {
                  $query .= $key1."=1,";
                }
                else {
                  $query .= $key1."=".$value1.",";
                }
              }
            }
            if (isset($daten["zentralerTimestamp"])) {
              $query = substr($query, 0, - 1)." ".$daten["zentralerTimestamp"]."\n";
            }
            else {
              $query = substr($query, 0, - 1)." ".$daten["Info"]["zentralerTimestamp"]."\n";
            }
          }
        }
        break;
    }
    return $query;
  }
}
?>