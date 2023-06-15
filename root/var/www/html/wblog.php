<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<meta http-equiv="refresh" content="60">
<html>
<head>
  <title>Open Source Projekt Solaranzeige</title>
  </head>
  <body bgcolor="LightGray" style="font-family:courier;font-weight:bold">
  <?php
  // Datei definieren
  $datei = "../log/wallbox.log";
  if (is_file( $datei )) {
    $Start = false;
    // Daten zeilenweise in ein Array einlesen
    $array = file( $datei );
    // Array von hinten nach vorne auslesen und anzeigen
    $i = sizeof( $array );
    for ($k = $i - 40; $k < $i; $k++) {
      if ($Start == true) {
        // echo "<font face=\"Verdana, Sans-Serif\" size=\"-1\">";
        if ($k == $i - 15) {
          echo '<div id="start">'.$array [$k].'</div>';
        }
        else {
          echo nl2br( $array [$k] );
        }
      }
      if (substr( $array [$k], 16, 4 ) == "|-->") {
        $Start = true;
        $Zeile = $k;
      }
    }
  }
  ?>
<script>window.location.hash = "start";</script>
</body>
</html>



