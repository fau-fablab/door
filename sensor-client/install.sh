#!/bin/bash

if [ "$USER" = "tuerstatus" ]; then
	cd "$(dirname $0)"
	cp update-status.sh ~
	touch ~/tuerstatus-key
	chmod 600 ~/tuerstatus-key
	echo "Install erfolgreich"
	echo "evtl muss nach" ~/tuerstatus-key "noch der Key gelegt werden."
else
	echo "Bitte dieses Skript als Nutzer tuerstatus ausführen!"
#	adduser --system tuerstatus
#	adduser tuerstatus dialout

# Ram-Disk (empfohlen, wenn Status irgendwo gespeichert werden soll):
# addgroup ramdisk --system
# adduser tuerstatus ramdisk

fi
