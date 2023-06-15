#!/bin/bash
if [ -f /var/www/pipe/reboot.server ]; then
  rm -f /var/www/pipe/reboot.server
  /sbin/shutdown --reboot now
fi

if [ -f /var/www/pipe/halt.server ]; then
  rm -f /var/www/pipe/halt.server
  /sbin/shutdown -hP now
fi

if [ -f /var/www/pipe/ReglerRestart ]; then
  rm -f /var/www/pipe/ReglerRestart
fi
