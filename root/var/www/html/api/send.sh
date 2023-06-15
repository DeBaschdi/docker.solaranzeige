#!/bin/sh

curl  -H "Accept: application/xml" -X POST -d @$1 http://solaranzeige.local/api/control.php 
