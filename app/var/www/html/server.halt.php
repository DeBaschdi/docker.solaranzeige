<?php
/******************************************************************************
//  Den Raspberry stoppen, damit er ausgeschaltet werden kann.
//  
******************************************************************************/
$basedir = dirname(__FILE__,3)."/solaranzeige";
require($basedir."/library/base.inc.php");

$Tracelevel = 8; //  1 bis 10  10 = Debug

Log::write("Der Raspberry Pi wird herunter gefahren.",5);

$fh = fopen("/var/www/pipe/halt.server",'w');
fwrite($fh,"Reboot now\n");
fclose($fh);

echo "<br>";
echo "<br>";
echo "Der Raspberry wird jetzt herunter gefahren.<br>";
echo "Wenn der Bildschirm ausgeht, kann der Raspberry ausgeschaltet werden.";

exit;
?>