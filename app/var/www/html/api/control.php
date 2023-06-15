<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2022]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient der Verarbeitung von API Aufrufen
//
//
*****************************************************************************/

/*****************************************************************************
//  Tracelevel:
//  0 = keine LOG Meldungen
//  1 = Nur Fehlermeldungen
//  2 = Fehlermeldungen und Warnungen
//  3 = Fehlermeldungen, Warnungen und Informationen
//  4 = Debugging
*****************************************************************************/
$Tracelevel = 3;

/****************************************************************************/
$apiDaten = array();
$Fehlernummer = "0";
$Fehlertext = "";
log_schreiben( "- - - - - - - - -    Start API Steuerung   - - - - - - - -", "|-->", 1 );
libxml_use_internal_errors( true );
$xmlA = file_get_contents( 'php://input' );

/****************************************************************************/
// Removing DOCTYPE
// Hier wird die Doctype Zeile herausgelöscht, falls sie
// enthalten ist. Simplexml kann damit nicht umgehen.
$xmlA = preg_replace( '/<!DOCTYPE.*?]>\s*/s', '', $xmlA );
if (isXML( $xmlA ) === true) {
  $api_eingang = new SimpleXMLElement( $xmlA );

  /***********************************************
  log_schreiben( formatXML( $api_eingang ), "1", 1 );
  log_schreiben( $api_eingang->version, "", 1 );
  log_schreiben( $api_eingang->timestamp, "", 1 );
  log_schreiben( $api_eingang->database["name"], "", 1 );
  log_schreiben( $api_eingang->database[0]->measurement["name"], "", 1 );
  log_schreiben( $api_eingang->database[0]->measurement->fieldname["name"], "", 1 );
  log_schreiben( $api_eingang->database[0]->measurement->fieldname[0]->value, "", 1 );
  log_schreiben( $api_eingang->database[0]->measurement->fieldname[0]->value["typ"], "", 1 );
  log_schreiben( count( $api_eingang->database[0]->measurement->fieldname ), "", 1 );
  ***********************************************/

  /***************************************************************************
  //  Auslesen des XML Dokumentes - Beispiel Dokument
  //
  //    [Version] => 1.0
  //    [Timestamp] => 1234567890
  //    [Anz_Datenbanken] => 2
  //    [Datenbank] => Array
  //      [1] => solaranzeige
  //      [2] => Vestel
  //    [1] => Array
  //      [Anz_Measurements] => 1
  //      [Measurement] => Array
  //        [1] => api
  //      [1] => Array
  //        [Anz_Fieldnames] => 2
  //        [Field] => Array
  //          [1] => Array
  //            [Name] => Batterie_Spannung
  //            [Wert] => 1223
  //          [2] => Array
  //            [Name] => Batterie_Strom
  //            [Wert] => -3.4
  //    [2] => Array
  //      [Anz_Measurements] => 1
  //      [Measurement] => Array
  //        [1] => Batterie
  //      [1] => Array
  //        [Anz_Fieldnames] => 5
  //        [Field] => Array
  //          [1] => Array
  //            [Name] => AC_Spannung
  //            [Wert] => 331223
  //          [2] => Array
  //            [Name] => AC_Strom
  //            [Wert] => -33.4
  //          [3] => Array
  //            [Name] => AC_Strom1
  //            [Wert] => -133.4
  //          [4] => Array
  //            [Name] => AC_Strom2
  //            [Wert] => -233.4
  //          [5] => Array
  //            [Name] => AC_Strom3
  //            [Wert] => -333.4
  //
  ***************************************************************************/
  $apiDaten["Version"] = (string) $api_eingang->version;
  $apiDaten["Timestamp"] = (string) $api_eingang->timestamp[0];
  $apiDaten["Routine"] = (string) $api_eingang->in_out;
  $apiDaten["Anz_Datenbanken"] = count( $api_eingang->database );
  if (strtoupper( $apiDaten["Routine"] ) == "IN") {
    //***********************************************************************
    //   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN
    //   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN   IN
    //***********************************************************************

    $InOut = "in";
    for ($i = 0; $i < $apiDaten["Anz_Datenbanken"]; $i++) {
      $apiDaten["Datenbank"][($i + 1)] = (string) $api_eingang->database[$i]['name'];
      $apiDaten[($i + 1)]["Anz_Measurements"] = count( $api_eingang->database[$i]->measurement );
      for ($j = 0; $j < $apiDaten[($i + 1)]["Anz_Measurements"]; $j++) {
        $apiDaten[($i + 1)]["Measurement"][($j + 1)] = (string) $api_eingang->database[$i]->measurement[$j]['name'];
        $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"] = count( $api_eingang->database[$i]->measurement[$j]->fieldname );
        $query = $apiDaten[($i + 1)]["Measurement"][($j + 1)]."  ";
        for ($k = 0; $k < $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"]; $k++) {
          $apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Typ"] = (string) $api_eingang->database[$i]->measurement[$j]->fieldname[$k]->value['type'];
          $apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Name"] = (string) $api_eingang->database[$i]->measurement[$j]->fieldname[$k]['name'];
          if ($apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Typ"] == "num") {
            $apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Wert"] = str_replace( ",", ".", (string) $api_eingang->database[$i]->measurement[$j]->fieldname[$k]->value );
          }
          else {
            $apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Wert"] = (string) $api_eingang->database[$i]->measurement[$j]->fieldname[$k]->value;
          }
          $query .= $apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Name"]."=".$apiDaten[($i + 1)][($j + 1)]["Field"][($k + 1)]["Wert"].",";
        }
        if ($apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"] == 0) {
          log_schreiben( "Es ist kein Feldname TAG Element angegeben.  ", "", 1 );
          $Fehlertext = "Es ist kein Datenpunkt angegeben.";
          $Fehlernummer = "1";
          log_schreiben( formatXML( $api_eingang ), "", 3 );
          break 2;
        }
        $query = substr( $query, 0, - 1 );
        if (isTimestamp( $apiDaten["Timestamp"] )) {
          $query = $query." ".$apiDaten["Timestamp"];
        }
        else {
          log_schreiben( "Timestamp: ".$apiDaten["Timestamp"]." ist nicht vorhanden oder ungültig.", "", 4 );
        }
        log_schreiben( "Query:  [ ".$query." ]", "", 4 );
        // Datenbankeintrag schreiben
        $rc = datenbank( $apiDaten["Datenbank"][($i + 1)], "http://localhost/write?db=".$apiDaten["Datenbank"][($i + 1)]."&precision=s", $query."\n" );
        if ($rc === false) {
          log_schreiben( "Query:  ".$query, "", 1 );
          log_schreiben( "In die Datenbank: [ ".$apiDaten["Datenbank"][($i + 1)]." ] konnte nicht geschrieben werden. Ist sie angelegt?", "", 1 );
          $Fehlertext = "Datenbank Eintrag nicht erfolgt. Bitte LOG Datei prüfen.";
          $Fehlernummer = "1";
          break 2;
        }
      }
      if ($apiDaten[($i + 1)]["Anz_Measurements"] == 0) {
        log_schreiben( "Es ist kein Measurement TAG Element angegeben.  ", "", 1 );
        $Fehlertext = "Es ist keine Measurement angegeben.";
        $Fehlernummer = "1";
        log_schreiben( formatXML( $api_eingang ), "", 3 );
        break;
      }
      $Fehlernummer = "0";
    }
    if ($apiDaten["Anz_Datenbanken"] == 0) {
      log_schreiben( "Es ist kein Datenbank TAG Element angegeben.  ", "", 1 );
      $Fehlertext = "Es ist keine Datenbank angegeben.";
      $Fehlernummer = "1";
      log_schreiben( formatXML( $api_eingang ), "", 3 );
    }
  }
  elseif (strtoupper( $apiDaten["Routine"] ) == "OUT") {
    //***********************************************************************
    //  OUT   OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT
    //  OUT   OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT  OUT
    //***********************************************************************
    $InOut = "out";
    log_schreiben( "Das Auslesen der Datenbanken ist noch nicht implementiert.", "", 1 );
//    echo formatXML( $api_eingang );
    $xmlAntwort="";
    for ($i = 0; $i < $apiDaten["Anz_Datenbanken"]; $i++) {
      if (empty($api_eingang->database[0]['name']))   {
        $rc = datenbank( "", "http://localhost/query?precision=s&q=".urlencode( 'show databases' ),"");
        $Fehlernummer = "2";
        if (isset($rc["results"][0]["error"])) {
          $xmlAntwort = '<error_code>1</error_code>';
          $xmlAntwort .= '<error>'.$rc["results"][0]["error"].'</error>';
        }
        else {
          $xmlAntwort .= "<database>";
          for ($m=0;$m < count($rc["results"][0]["series"][0]["values"]);$m++) {
            $xmlAntwort .= "<databasename>".$rc["results"][0]["series"][0]["values"][$m][0]."</databasename>";
          }
          $xmlAntwort .= "</database>";
        }
      }
      else {
        $apiDaten["Datenbank"][($i + 1)] = (string) $api_eingang->database[$i]['name'];
        $db = (string) $api_eingang->database[$i]['name'];
        $apiDaten[($i + 1)]["Anz_Measurements"] = count( $api_eingang->database[$i]->measurement );
      }
      for ($j = 0; $j < $apiDaten[($i + 1)]["Anz_Measurements"]; $j++) {
        if (empty($api_eingang->database[$i]->measurement[0]['name']))   {
          $rc = datenbank( "", "http://localhost/query?db=".$db."&precision=s&q=".urlencode( 'show measurements' ),"");
          $Fehlernummer = '2';
          if (isset($rc["results"][0]["error"])) {
            $xmlAntwort = '<error_code>1</error_code>';
            $xmlAntwort .= '<error>'.$rc["results"][0]["error"].'</error>';
          }
          else {
            $xmlAntwort .= '<database name="'.$db.'"><measurement>';
            for ($m=0;$m < count($rc["results"][0]["series"][0]["values"]);$m++) {
              $xmlAntwort .= '<measurementname>'.$rc["results"][0]["series"][0]["values"][$m][0].'</measurementname>';
            }
            $xmlAntwort .= '</measurement></database>';
          }
        }
        else {
          $apiDaten[($i + 1)]["Measurement"][($j + 1)] = (string) $api_eingang->database[$i]->measurement[$j]['name'];
          $mm = (string) $api_eingang->database[$i]->measurement[$j]['name'];
          $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"] = count( $api_eingang->database[$i]->measurement[$j]->fieldname );
          $query = $apiDaten[($i + 1)]["Measurement"][($j + 1)]."  ";
        }
        for ($k = 0; $k < $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"]; $k++) {
          if (empty($api_eingang->database[$i]->measurement[$j]->fieldname[0]['name']))   {
            // Es sollen alle Feldnamen abgefragt werden.
            $rc = datenbank( "", "http://localhost/query?db=".$db."&precision=s&q=".urlencode( 'show field keys from '.$mm ),"");
            $Fehlernummer = '2';

            if (isset($rc["results"][0]["error"])) {
              $xmlAntwort = '<error_code>1</error_code>';
              $xmlAntwort .= '<error>'.$rc["results"][0]["error"].'</error>';
            }
            else {
              $xmlAntwort .= '<database name="'.$db.'"><measurement name="'.$mm.'">';
              for ($m=0;$m < count($rc["results"][0]["series"][0]["values"]);$m++) {
                $xmlAntwort .= '<fieldname>'.$rc["results"][0]["series"][0]["values"][$m][0].'</fieldname>';
              }
              $xmlAntwort .= '</measurement></database>';
            }
          }
          else {
            // Es sollen einzelne oder alle Werte abgefragt werden.
            $Fehlernummer = '2';
            if (isset($rc["results"][0]["error"])) {
              $xmlAntwort = '<error_code>1</error_code>';
              $xmlAntwort .= '<error>'.$rc["results"][0]["error"].'</error>';
            }
            elseif ( $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"] == 1) {
              $field = $api_eingang->database[$i]->measurement[$j]->fieldname[0]["name"];
              if ($field == "*")  {
                // Es sollen alle Werte eines Mesurements abgefragt werden
                $rc = datenbank( "", "http://localhost/query?db=".$db."&precision=s&q=".urlencode( 'select last('.$field.') from '.$mm ),"");
                $xmlAntwort .= '<database name="'.$db.'"><measurement name="'.$mm.'">';
                for ($m=1;$m < count($rc["results"][0]["series"][0]["columns"]);$m++) {
                  $xmlAntwort .= '<fieldname name="'.substr($rc["results"][0]["series"][0]["columns"][$m],5).'">'.$rc["results"][0]["series"][0]["values"][0][$m].'</fieldname>';
                }
                $xmlAntwort .= '</measurement></database>';
              }
              else {
                // Es soll ein Wert eines Mesurements abgefragt werden
                $rc = datenbank( "", "http://localhost/query?db=".$db."&precision=s&q=".urlencode( 'select last('.$field.') as "'.$field.'" from '.$mm ),"");
                $xmlAntwort .= '<database name="'.$db.'"><measurement name="'.$mm.'">';
                for ($m=1;$m < count($rc["results"][0]["series"][0]["columns"]);$m++) {
                  $xmlAntwort .= '<fieldname name="'.$rc["results"][0]["series"][0]["columns"][$m].'">'.$rc["results"][0]["series"][0]["values"][0][$m].'</fieldname>';
                }
              $xmlAntwort .= '</measurement></database>';
              }
            }
            elseif ( $apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"] > 1) {
              // Es sollen viele Werte eines Mesurements abgefragt werden
              if ($k == 0) {
                $xmlAntwort .= '<database name="'.$db.'"><measurement name="'.$mm.'">';
              }
              $field = $api_eingang->database->measurement->fieldname[$k]["name"];
              $rc = datenbank( "", "http://localhost/query?db=".$db."&precision=s&q=".urlencode( 'select last('.$field.') as "'.$field.'" from '.$mm ),"");
              if ($rc["results"][0]["series"][0]["columns"][1] == "") {
                $xmlAntwort .= '<fieldname name="unbekannt"></fieldname>';
              }
              else {
                $xmlAntwort .= '<fieldname name="'.$rc["results"][0]["series"][0]["columns"][1].'">'.$rc["results"][0]["series"][0]["values"][0][1].'</fieldname>';
              }
              if ($k == ($apiDaten[($i + 1)][($j + 1)]["Anz_Fieldnames"])-1) {
                $xmlAntwort .= '</measurement></database>';
              }
            }
            else {
              // Kein Wert vorhanden. Falscher Datenbankname? Falscher Measurement Name? Datenbank leer?
              $xmlAntwort = '<error_code>1</error_code>';
              $xmlAntwort .= '<error>Die Query ergab keine Daten.</error>';
            }
          }
        }
      }
    }
  }
  else {
    log_schreiben( "Achtung! das Element <in-out> hat sich geändert in <in_out> Bitte falls nötig korrigieren.", "", 3 );
    log_schreiben( "Bis jetzt funktioniert nur folgendes TAG Element: <in_out>in</in_out>", "", 3 );
    $Fehlertext = "TAG Element in_out ist nicht oder falsch angegeben.";
    $Fehlernummer = "1";
  }

  /*****************************************************************************
  // $apiDaten[Datenbank][Measurement]["Field"][Feldname]["Name"] oder ["Value"]
  // Beispiel:
  // $apiDaten[2][1]["Field"][3]["Wert"]   => -133.4
  *****************************************************************************/
  log_schreiben( print_r( $apiDaten, 1 ), "", 4 );
}
else {
  log_schreiben( "Die XML Datei konnte nicht richtig gelesen werden.", 2, 0 );
  log_schreiben( formatXML( $xmlA ), "1", 1 );
  $Fehlertext = "Es handelt sich um keine gültige XML Datei.";
}
Ausgang:
//
$xmlString = '<?xml version="1.0" encoding="UTF-8"?>';
$xmlString .= '<solaranzeige>';
$xmlString .= '<version>1.0</version>';
$xmlString .= '<in_out>'.$InOut.'</in_out>';
if ($Fehlernummer == "0" or $Fehlernummer == "1") {
  $xmlString .= '<error_code>'.$Fehlernummer.'</error_code>';
  $xmlString .= '<error>'.$Fehlertext.'</error>';
}
else {
  $xmlString .= $xmlAntwort; 
}
$xmlString .= '</solaranzeige>';

$xmlOut = simplexml_load_string($xmlString);
$body = $xmlOut->asXML();

log_schreiben( formatXML( $xmlOut ), "", 3 );
log_schreiben( "---------------------------------------------------------", "ENDE", 1 );

print($body);

exit;

/**************************************************************************
//
//    Funktionen       Funktionen       Funktionen       Funktionen
//
**************************************************************************/

/**************************************************************************
//  Log Eintrag in die Logdatei schreiben
//  $LogMeldung = Die Meldung ISO Format
//  $Loglevel=2   Loglevel 1-4   4 = Trace
**************************************************************************/
function log_schreiben( $LogMeldung, $Titel = "", $Loglevel = 3, $UTF8 = 0 ) {
  global $Tracelevel;
  $LogDateiName = "../../log/api.log";
  if (strlen( $Titel ) < 4) {
    switch ($Loglevel) {

      case 1:
        $Titel = "ERRO";
        break;

      case 2:
        $Titel = "WARN";
        break;

      case 3:
        $Titel = "INFO";
        break;

      case 4:
        $Titel = "TRCE";
        break;

      default:
        $Titel = "    ";
        break;
    }
  }
  if ($Loglevel <= $Tracelevel) {
    if ($UTF8) {
      $LogMeldung = utf8_encode( $LogMeldung );
    }
    if ($handle = fopen( $LogDateiName, 'a' )) {
      //  Schreibe in die geöffnete Datei.
      //  Bei einem Fehler bis zu 3 mal versuchen.
      for ($i = 1; $i < 4; $i++) {
        $rc = fwrite( $handle, date( "d.m. H:i:s" )." ".substr( $Titel, 0, 4 )." ".$LogMeldung."\n" );
        if ($rc) {
          break;
        }
        sleep( 1 );
      }
      fclose( $handle );
    }
  }
  return true;
}

/***************************************************************************/
function xmlObjToArr( $obj ) {
  $namespace = $obj->getDocNamespaces( true );
  $namespace[NULL] = "";
  $children = array();
  $attributes = array();
  $name = strtolower( (string) $obj->getName( ));
  $text = trim( (string) $obj );
  if (strlen( $text ) <= 0) {
    $text = NULL;
  }
  // get info for all namespaces
  if (is_object( $obj )) {
    foreach ($namespace as $ns => $nsUrl) {
      // atributes
      $objAttributes = $obj->attributes( $ns, true );
      foreach ($objAttributes as $attributeName => $attributeValue) {
        $attribName = strtolower( trim( (string) $attributeName ));
        $attribVal = trim( (string) $attributeValue );
        if (!empty($ns)) {
          $attribName = $ns.':'.$attribName;
        }
        $attributes[$attribName] = $attribVal;
      }
      // children
      $objChildren = $obj->children( $ns, true );
      foreach ($objChildren as $childName => $child) {
        $childName = strtolower( (string) $childName );
        if (!empty($ns)) {
          $childName = $ns.':'.$childName;
        }
        $children[$childName][] = xmlObjToArr( $child );
      }
    }
  }
  return array('name' => $name, 'text' => $text, 'attributes' => $attributes, 'children' => $children);
}

/**
* formats the xml output readable
*
* @param $simpleXmlObject instance of SimpleXmlObject to pretty-print
* @return string of indented xml-elements
*/
function formatXML( & $simpleXmlObject ) {
  if (!is_object( $simpleXmlObject )) {
    return "";
  }
  //Format XML to save indented tree rather than one line
  $dom = new DOMDocument( '1.0' );
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML( $simpleXmlObject->asXML( ));
  return $dom->saveXML( );
}

/***************************************************************************/
function xml2array( $xmlObject, $out = array()) {
  foreach ((array) $xmlObject as $index => $node)
    $out[$index] = (is_object( $node )) ? xml2array( $node ):$node;
  return $out;
}

/***************************************************************************/
function isXML( $xml ) {
  libxml_use_internal_errors( true );
  $doc = new DOMDocument( '1.0', 'utf-8' );
  $doc->loadXML( $xml );
  $errors = libxml_get_errors( );
  if (empty($errors)) {
    return true;
  }
  $error = $errors[0];
  if ($error->level < 3) {
    return true;
  }
  $explodedxml = explode( "r", $xml );
  $badxml = $explodedxml[($error->line) - 1];
  $message = $error->message.' at line '.$error->line.'. Bad XML: '.htmlentities( $badxml );
  return $message;
}

/***************************************************************************/
function datenbank( $db, $init, $query ) {
  global $Tracelevel;
  // Wenn $query leer ist, dann lesende Abfrage
  $Status = false;
  $ch = curl_init( $init );
  $i = 1;
  do {
    $i++;
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 8 ); //timeout in second s
    curl_setopt( $ch, CURLOPT_PORT, "8086" );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec( $ch );
    $rc_info = curl_getinfo( $ch );
    $Ausgabe = json_decode( $result, true );
    if (curl_errno( $ch )) {
      log_schreiben( "Curl Fehler[2]! Daten nicht zur lokalen InfluxDB ".$db." gesendet! Curl ErrNo. ".curl_errno( $ch ), "", 3 );
    }
    if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
      $Status = true;
      break;
    }
    elseif ($rc_info["http_code"] == 401) {
      log_schreiben( "Influx UserID oder Kennwort ist falsch.", "", 3 );
      break;
    }
    elseif (empty($Ausgabe["error"])) {
      log_schreiben( "InfluxDB Fehler -> nochmal versuchen.", "", 3 );
      continue;
    }
    log_schreiben( "InfluxDB  => [ ".$query." ] i: ".$i, "", 4 );
    log_schreiben( "Daten nicht zur lokalen InfluxDB gesendet! info: ".var_export( $rc_info, 1 ), "", 4 );
    sleep( 2 );
  } while ($i < 3);

  curl_close( $ch );
  if (empty($query)) {
    return $Ausgabe;
  }
  return $Status;
}

/***************************************************************************/
function isTimestamp( $timestamp ) {
  if (ctype_digit( $timestamp )&&strtotime( date( 'Y-m-d H:i:s', $timestamp )) === (int) $timestamp) {
    return true;
  }
  else {
    return false;
  }
}
?>