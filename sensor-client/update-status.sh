#!/bin/bash
set +e

function hmac() {
	keyfile="$HOME/tuerstatus-key"
	php -r 'echo hash_hmac("sha256","'"$@"'",file_get_contents("'"${keyfile}"'")) . "\n"; '
}

function status() {
#	echo $1 > ~/tuerstatus.txt
#	date > ~/tuerstatus-update.txt
	state="$1"
	time="`date +%s`"
	message="${time}:${state}"
	hmac_php="`hmac $message`"
	# echo $hmac_php
	# unsicher mit OpenSSL (erscheint in Prozessliste)
	# keyfile="$HOME/tuerstatus-key"
	# key="`cat "${keyfile}"`"
	# hmac_openssl="`echo -n "${message}" | openssl dgst -sha256 -hmac "${key}"`"
	# echo $hmac_openssl
	result="`curl -s -S -L "http://fablab.fau.de/doorstatus.php?data=${message}&key=${hmac_php}"`"
	if [ "${result}" != "OK." ]; then
        	echo "Failed: ${result}" >&2
	else
		touch /mnt/ramdisk/tuerstatus.success
	fi
}

# Schauen ob die Türe offen ist
# Türe offen ==  Schalter von RX nach TX an ttyS0 geschlossen
# Ausgabe: Zahl (1=offen, 0=zu)
# Rückgabewert: 1 = Fehler, 0 = okay
function is_open() {
	{
		sleep 1
		# sende "MOEP", lese Antwort
		# read gibt den Prompt auf stderr aus, deshalb weiter unten die Umleitung von stderr und nicht von stdout
		read -t 1 -n4 -p "MOEP" ans || { echo "1"; return 0; }
		if [ "a$ans" == "aMOEP" ]; then
			# Antwort war MOEP -> Schalter  RX-TX  geschlossen
			echo "0"; return 0
		fi;
		#echo "Fehler beim Auslesen: Antwort war $ans" >&2;
		echo "0"
		return 1;
	} < /dev/ttyS0 2> /dev/ttyS0
}

# zehnmal auslesen, bei >7 mal keine Antwort gilt es als offen

n=0
for i in `seq 0 9`; do
	n=$(($n + `is_open`))
done
if [ $n -gt 7 ]; then
	status "open"
#elif [ $n -lt 2 ]; then
else
	status "close"
#else
	#status "unknown"
fi

echo $n

#	sleep 1
#done

