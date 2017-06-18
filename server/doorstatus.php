<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
header('Content-Type: text/plain');

// where to store the last open date
define("STATEFILE", "/home/fablab-er/.doorstate");
// a file holding the shared key to protect the integrity of the update
define("KEYFILE", "/home/fablab-er/.doorstate-key");
// maximum duration in seconds since last report before we consider the state outdated
define("CUTOFF", 3 * 60);
// amount of seconds the door must be reported open for the algorithm to consider the door open
define("THRESHOLD", 5);

$hmac = &$_GET['hash'];
$data = &$_GET['data'];

$time = 0;
$newstate = "";
list($time, $newstate) = explode(":", $data);
$time = (int) $time;

if ($time == 0 || $newstate == "") {
	die("Malformed data attribute.");
}

if ($time > time() + 60) {
	// if the clocks are off by more than a minute, assume somebody's trying to trick us
	die("Timestamp is in the future.");
}

$key = file_get_contents(KEYFILE, false);

$local_hmac = hash_hmac("sha256", $data, $key);

if ($local_hmac != $hmac) {
	die("Message integrity could not be verified.");
}

switch ($newstate) {
	case "close":
	case "open":
		break;
	default:
		die("Unknown state.");
}

require("./includes/bootstrap.inc");
require("./sites/fablab.fau.de/settings.php");
require("./includes/database.inc");

db_set_active();
$result = db_query_range("
	SELECT
		  starttime
		, endtime
	FROM
		{fablab_doorstate_current}
	ORDER BY
		starttime DESC
	", 0, 1);
if ($result === false) {
	die("Error communicating with database: 1.");
}
$state = db_fetch_object($result);
if ($state !== false) {
	// the fablab is currently open or the last session wasn't closed
	if ($state->endtime + CUTOFF < time()) {
		// this entry is outdated, mark it as closed
		close_entry($state);
	} else {
		// this is the current entry, update it or close it
		switch ($newstate) {
			case "close":
				close_entry($state);
				break;
			case "open":
				update_entry($state, $time);
				break;
		}
	}
} else {
	// the fablab isn't open
	if ($newstate == "open") {
		// ignore repeated reports of closed state
		$result = db_query("
			INSERT INTO
				{fablab_doorstate_current}
				(starttime, endtime)
			VALUES
				(%d, %d)
		", $time, $time + 1);
		if ($result === false) {
			die("Error communicating with database: 16.");
		}
	}
}

touch(STATEFILE);
print("OK.");

function close_entry(&$entry) {
	if ($entry->endtime - $entry->starttime > THRESHOLD) {
		// ignore bouncing
		$result = db_query("
			INSERT INTO
				{fablab_doorstate}
				(starttime, endtime)
			VALUES
				(%d, %d)
		", $entry->starttime, $entry->endtime);
		if ($result === false) {
			die("Error communicating with database: 2.");
		}
	} else {
		printf("Bouncing detected because the door was only open for %d seconds. Ignoring.", $entry->endtime - $entry->starttime);
	}

	$result = db_query("
		DELETE FROM
			{fablab_doorstate_current}
		WHERE
			starttime = %d
	", $entry->starttime);
	if ($result === false) {
		die("Error communicating with database: 4.");
	}
}

function update_entry(&$entry, $time) {
	$result = db_query("
		UPDATE
			{fablab_doorstate_current}
		SET
			endtime = %d
		WHERE
			starttime = %d
	", $time, $entry->starttime);
	if ($result === false) {
		die("Error communicating with database: 8.");
	}
}
