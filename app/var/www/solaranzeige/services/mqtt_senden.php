<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2019]  [Ulrich Kunz]
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
//  Es dient dem übertragen der Daten an einen MQTT-Broker.
//  Es werden alle Daten die ausgelesen werden in eine Pipe geschrieben.
//  Ein weiterer Prozess sendet dann die Daten als MQTT Protokoll
//
//  Diese Funktion ist nur eingeschaltet, wenn in der user.config.php
//  $MQTT = true  eingetragen ist.
//  
*****************************************************************************/
//
//
//

$fifoPath = "/var/www/pipe/pipe";

if (! file_exists($fifoPath)) {
  Log::write("Pipe wird neu erstellt.","   ",5);
  posix_mkfifo($fifoPath, 0644);
} 
$fifo = fopen($fifoPath, "w+"); 
if (is_resource($fifo)) {
  if (isset($aktuelleDaten["Info"]) and is_array($aktuelleDaten["Info"])) {
    foreach ($aktuelleDaten as $key => $value) {
      if (is_array($aktuelleDaten[$key])) {
        foreach ($aktuelleDaten[$key] as $key1 => $value1) {
          //  Bei der Multi-Regler-Version wird zusaetzlich das Geraet mit gesendet.
          $rc = fwrite($fifo, $MQTTGeraet."/".$key."/".$key1." ".$value1."\r\n");
          Log::write($MQTTGeraet."/".$key."/".$key1." ".$value1." rc: ".$rc,"   ",10);
        }
      }
    }
  }
  else  {
    foreach($aktuelleDaten as $key=>$wert) {
      if (!is_array($wert)) {
        //  Bei der Multi-Regler-Version wird zusaetzlich das Geraet mit gesendet.
        $rc = fwrite($fifo, $MQTTGeraet."/".$key." ".$wert."\r\n");
        Log::write($MQTTGeraet."/".$key." ".$wert." rc: ".$rc,"   ",10);
      }
    }
  }

  $rc = fwrite($fifo,"|");
  fclose($fifo);
}

?>