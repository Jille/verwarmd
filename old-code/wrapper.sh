#!/bin/sh

FAILS=0
FIRSTFAIL=0

while :; do
	"$@"
	NOW=`date +%s`
	if [ "$(($NOW-3600))" -gt "$FIRSTFAIL" ]; then
		FAILS=0
	fi
	if [ "$FAILS" = "0" ]; then
		FIRSTFAIL=$NOW
	fi
	if [ "$FAILS" -ge 3 ]; then
		sudo reboot
	fi
	FAILS=$(($FAILS+1))
	gpio mode 0 out
	gpio mode 2 out
	gpio write 0 1
	sleep 0.5
	gpio write 0 1
	if [ "$FAILS" -gt "1" ]; then
		sleep 10
	fi
done
