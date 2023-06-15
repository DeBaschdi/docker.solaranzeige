<?php
/******************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2018]   [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Übertragen von Meldungen an den Messenger Pushover
//  Detail Informationen finden Sie im Dokument "Nachrichten_senden.pdf"
//
//  Welche Meldungen und wann übertragen werden, wird hier festgelegt.
//  Dieses ist nur als Beispiel zu sehen. Da Jeder ganz bestimmte Meldungen
//  übertragen haben möchte, müssen Sie hier selber die Programmierung
//  übernehmen. Vielleicht hilft auch der Eine oder Andere im Support Forum.
//
//  Diese Funktion ist nur eingeschaltet, wenn in der user.config.php
//  $Meldungen = true  eingetragen ist.
//  Zur Unterscheidung kann die Variable $GeraeteNummer benutzt werden.
//
******************************************************************************/

// $Tracelevel = 10;  //  1 bis 10  10 = Debug

//  Ist der Standort in der user.config.php angegeben?
//  Wenn nicht dan Standort Frankfurt nehmen
if (isset($Breitengrad)) {
  $breite = $Breitengrad;
}
else {
  $breite = 50.1143999;
}

if (isset($Laengengrad)) {
  $laenge = $Laengengrad;
}
else {
  $laenge=8.6585178;
}


/******************************************************************************
//  Es werden die wichtigsten Daten auf der Influx Datenbank gelesen, die für
//  die Versendung der Messenger Nachrichten benötigt werden.
//
******************************************************************************/

if ($InfluxDB_local === true) {

  //
  //  Wann ist Mitternacht?
  $HeuteMitternacht = strtotime('today midnight');
  $funktionen->log_schreiben("Mitternacht: ".date("d.m.Y H:i:s",$HeuteMitternacht)." Timestamp: ".$HeuteMitternacht,"*  ",9);

  //
  //  Sonnenaufgang und Sonnenuntergang berechnen (default Standort ist Frankfurt)
  $now=time();
  $gmt_offset = 1+date("I");
  $zenith = 50/60;
  $zenith = $zenith + 90;
  $Sonnenuntergang = date_sunset($now, SUNFUNCS_RET_TIMESTAMP, $breite, $laenge, $zenith, $gmt_offset);
  $Sonnenaufgang = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $breite, $laenge, $zenith, $gmt_offset);


  /****************************************************************************
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  ****************************************************************************/

  //**************************************************************************
  //  SONNENUNTERGANG      SONNENUNTERGANG      SONNENUNTERGANG      SONNEN
  //  Nach Sonnenuntergang wird der Ertrag von Heute + Batteriespannung + 
  //  SOC gesendet.
  //**************************************************************************
  //  Step 1
  //  Es wird abgefragt, ob die Meldung Heute schon einmal gesendet wurde.
  //  In dem Beispiel wird davon ausgegangen, dass die Meldung nur einmal am
  //  Tage gesendet wird. Ist der 2. Parameter eine 0 dann wird nichts
  //  verändert sondern nur abgefragt. Ist es eine Zahl größer 0 dann wird
  //  Der Zähler gespeichert. Damit kann man die Anzahl der Meldungen
  //  für eine bestimmte Nachricht steuern.
  //  Die Rückgabe ist:
  //  -------------------
  //  $rc[0] = Timestamp an dem die Meldung gesendet wurde.
  //  $rc[1] = Anzahl der gesendeten Meldungen.
  //  $rc[2] = Meldungsname. In diesem Fall "Ertrag"
  $rc = $funktionen->po_messageControl("Sonnenuntergang",0,$GeraeteNummer);

  $funktionen->log_schreiben("Eintrag: Sonnenuntergang  Datum: ".date("d.m.Y",$rc[0])." Anzahl: ".$rc[1],"*  ",8);

  if ($rc === false or date("Ymd",$rc[0]) <> date("Ymd")) {


    //  Entweder es wurde noch nie der Ertrag gesendet oder es wird geprüft
    //  ob Heute schon gesendet wurde. Man könnte hier auch die Anzahl der Nachrichten abfragen,
    //  wenn man mehrmals am Tage diese Meldung schicken möchte.


    //  Step 2
    //  Hier wird die Meldung generiert!
    //  Das kann über die Datenbank geschehen, es können aber auch direkt die Variablen
    // des Hauptspripts "§aktuelleDaten["..."] benutzt werden.

    $aktuelleDaten["Query"] = "db=".$InfluxDBLokal."&q=".urlencode("select last(Wh_Heute) from Summen where time > ".$HeuteMitternacht."000000000  and time <= now() limit 5");


    if (($Sonnenuntergang + 600) < time()) {
    // if (1 == 1) {
      // Die Influx Datenbank abfragen, ob ein bestimmtes Ereignis passiert ist.
      $rc = $funktionen->po_influxdb_lesen($aktuelleDaten);
      $funktionen->log_schreiben(var_export($rc,1),"*  ",9);
      $funktionen->log_schreiben($aktuelleDaten["Query"],"*  ",9);
      $Meldungen["Wh_Heute"] = $rc["results"][0]["series"][0]["values"][0][1];
      $Meldungen["Timestamp"] = $rc["results"][0]["series"][0]["values"][0][0];

      $funktionen->log_schreiben(print_r($Meldungen,1),"*  ",9);

      //  Step 3
      //  Die Nachricht, die gesendet werden soll, wird hier zusammen
      //  gebaut.
      $Nachricht = "Solaranzeige Gerät [".$GeraeteNummer."] <br>Sonnenuntergang: Heute am ".date("d.m.Y H:i",$Sonnenuntergang)." wurden ".$Meldungen["Wh_Heute"]." Wh erzeugt. ";

      //
      //  Step 4
      //  Wann soll die Nachricht gesendet werden?
      if (isset($rc["results"][0]["series"][0])) {
      //  Die Query liefert ein Ergebnis, das wird an dieser JSON Variable erkannt.
      // ---------- if (($Sonnenuntergang + 600) < time()) {
        // if (1==1) {
        //  10 Minuten nach Sonnenuntergang.
        //  Ertrag senden ...
        $funktionen->log_schreiben(strip_tags($Nachricht),"*  ",6);
        //  Step 5
        //  Soll die Nachricht an mehrere Empfänger gesendet werden?
        for ($Ui = 1; $Ui <= count($User_Key); $Ui++) {
          //  Die Nachricht wird an alle Empfänger gesendet, die in der
          //  user.config.php stehen.
          $rc = $funktionen->po_send_message($API_Token,$User_Key[$Ui],$Nachricht);
          if ($rc) {
            $funktionen->log_schreiben("Nachricht wurde versendet an User_Key[".$Ui."]","*  ",6);
          }
        }
        //  Step 6
        //  Es wird festgehalten, wann die Nachricht gesendet wurde und eventuell
        //  das wievielte mal. (2. Parameter) In dem Beispiel gibt es nur eine
        //  Meldung pro Tag.
        $rc = $funktionen->po_messageControl("Sonnenuntergang",1);
      }
    }
  }


  /****************************************************************************
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  ****************************************************************************/





  /****************************************************************************
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  ****************************************************************************/
  //  Wurde eine Fehlermeldung erzeugt?
  //  Achtung! Dieses Beispiel funktioniert noch nicht. Die Variable
  //  FehlermeldungText ist noch nicht ueberall gefüllt. 
  //  Zur Zeit nur ein Dummy.
  //**************************************************************************
  $rc = $funktionen->po_messageControl("Fehlermeldung",0,$GeraeteNummer);
  //  Wurde die Meldung heute schon gesendet?
  $funktionen->log_schreiben("Eintrag: Fehlermeldung  Datum: ".date("d.m.Y",$rc[0])." Anzahl: ".$rc[1],"*  ",6);

  if ($rc === false or date("YmdH",$rc[0]) <> date("YmdH")) {
    //  Es wird jede Stunde eine Fehlermeldung gesendet, solange sie nicht
    //  beseitigt wurde.

    if (!empty($FehlermeldungText)) {
      //  Es ist eine Fehlermeldung vorhanden
      $TimestampOn = time();
      $Nachricht = "Solaranzeige Gerät [".$GeraeteNummer."] <br>".$FehlermeldungText;
      // Wurde das Ereignis schon mehrfach gespeichert?

      $funktionen->log_schreiben(strip_tags($Nachricht),"*  ",7);
      //  Soll die Nachricht an mehrere Empfänger gesendet werden?
      for ($Ui = 1; $Ui <= count($User_Key); $Ui++) {
        //  Die Nachricht wird an alle Empfänger gesendet, die in der
        //  user.config.php stehen.
        $rc = $funktionen->po_send_message($API_Token,$User_Key[$Ui],$Nachricht);
        if ($rc) {
          $funktionen->log_schreiben("Nachricht wurde versendet an User_Key[".$Ui."]","*  ",6);
        }
      }
      //  Es wird festgehalten, wann die Nachricht gesendet wurde
      $rc = $funktionen->po_messageControl("Fehlermeldung",1,$GeraeteNummer);
    }
  }
  /****************************************************************************
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  ****************************************************************************/




  /****************************************************************************
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  Hier kann Ihre Abfrage stehen. Diese Datei wird bei einem
  //  Update nicht ueberschrieben.
  ****************************************************************************/


  /****************************************************************************
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  ****************************************************************************/



}
else {
  $funktionen->log_schreiben("Die lokale Datenbank ist ausgeschaltet. Die Messengerdienste stehen dadurch nicht zur Verfügung.","*  ",3);
}


return;


?>