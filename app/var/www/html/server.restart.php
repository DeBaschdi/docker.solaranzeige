<?php
/******************************************************************************
//  Den Raspberry neu starten.
//  
******************************************************************************/
require("phpinc/funktionen.inc.php");

$Tracelevel = 8; //  1 bis 10  10 = Debug

$funktionen = new funktionen();


$funktionen->log_schreiben("Der Raspberry Pi wird neu gestartet.",5);

$fh = fopen("/var/www/pipe/reboot.server",'w');
fwrite($fh,"Reboot now\n");
fclose($fh);

echo "<br>";
echo "<br>";
echo "Der Raspberry wird jetzt neu gestartet.<br>";
echo "Bitte 1 - 2 Minuten Geduld.";

exit;
?>