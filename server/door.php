<?php
// vim:sts=2:ts=2:sw=2:et
//ini_set('display_errors', 1);
//error_reporting(E_ALL | E_NOTICE | E_STRICT);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

// where to store the last open date
define("STATEFILE", "/home/fablab-er/.doorstate");
# define("STATEFILE", ".doorstate");
// maximum duration in seconds since last report before we consider the state outdated
define("CUTOFF", 3 * 60);

require("./includes/bootstrap.inc");
require("./sites/fablab.fau.de/settings.php");
require("./includes/database.inc");
require("./includes/common.inc");

function human_time_diff($from, $to = null) {
  if (is_null($to))
    $to = time();

  # $since = t("unbekannter Zeit", array(), "de");

  $diff = (int) abs($to - $from);
  if ($diff <= 3600) {
    $mins = max(1, round($diff / 60));
    $since = format_plural($mins, "einer Minute", "@count Minuten", array(), "de");
  } else if ($diff <= 86400) {
    $hours = max(1, round($diff / 3600));
    $since = format_plural($hours, "einer Stunde", "@count Stunden", array(), "de");
  } else if ($diff <= 604800) {
    $days = max(1, round($diff / 86400));
    $since = format_plural($days, "einem Tag", "@count Tagen", array(), "de");
  } else {
    $weeks = max(1, round($diff / 604800));
    $since = format_plural($weeks, "einer Woche", "@count Wochen", array(), "de");
  }

  return $since;
}

db_set_active();

$current = true;
if (filemtime(STATEFILE) + CUTOFF < time()) {
  $current = false;
}
$open = false;
$last_change = "ERROR";
$timestamp = false;

// try to get the last open time, but only if it's current enough
$result = db_query_range("SELECT starttime FROM {fablab_doorstate_current} WHERE endtime + 180 >= UNIX_TIMESTAMP() ORDER BY starttime DESC", 0, 1);
if ($result !== false) {
  $timestamp = db_result($result);
  if ($timestamp !== false) {
    // found a valid open time
    $open = true;
    $last_change = human_time_diff($timestamp);
  } else {
    // door isn't open
    $last_change = "ERROR";
    // try to find the last close time
    $result = db_query_range("SELECT endtime FROM {fablab_doorstate} ORDER BY endtime DESC", 0, 1);
    if ($result !== false) {
      $timestamp = db_result($result);
      if ($timestamp !== false) {
        // found last close time
        $last_change = human_time_diff($timestamp);
      } else {
        // no last close time, display some weird message
        $last_change = t("den frühen Urzeiten (keine Daten vorhanden)", array(), "de");
      }
    }
  }
}

if ($current) {
  if ($open) {
    // open
    $text = t("Die FabLab-Tür ist seit @duration offen.", array("@duration" => $last_change), "de");
  } else {
    if (($timestamp === false) || ($timestamp < time() - 86400)) {
      // more than a day
      $text = t("Das FabLab war heute noch nicht geöffnet.", array(), "de");
    } else {
      // less than a day
      $text = t("Das FabLab war zuletzt vor @duration geöffnet.", array("@duration" => $last_change), "de");
    }
  }
} else {
  $text = t("Keine aktuellen Informationen über den Türstatus vorhanden.", array(), "de");
}

if ($timestamp === false) {
  $timestamp = 0;
}

$json_options = isset($_GET['pretty']) ? JSON_PRETTY_PRINT : 0;

if (!defined('_FABLAB_SPACEAPI')) {
  $door_state = new stdClass();
  $door_state->open = $open;
  $door_state->current = $current;
  $door_state->text = check_plain($text);
  $door_state->{'last-change'} = (int) $timestamp;
  echo json_encode( $door_state, $json_options );
} else {

  # <editor-fold desc="get api versions, the output will be compatible with">
  # api versions defined by spaceapi.net (ascending)
  $existing_apis = array('0.8', '0.9', '0.11', '0.12', '0.13');
  # api versions, the output will be compatible with (ascending)
  $compatible_apis = array();

  if ( isset($_GET["api"]) ) {
    if (is_numeric(str_replace('.', '', $_GET["api"]))) {
      $compatible_apis[] = $_GET["api"];
    }
    else {
      $api_op = str_replace('!', '!=', substr($_GET["api"], 0, 1));
      in_array($api_op, array('>', '<', '!=')) or
      die("Unsupported API operator '$api_op'");
      $api = substr($_GET["api"], 1);

      foreach ($existing_apis as $e_api) {
        if (version_compare($e_api, $api, $api_op)) {
          $compatible_apis[] = $e_api;
        }
      }

    }
  }
  else {
    $compatible_apis = $existing_apis;
  }
  # </editor-fold>

  # <editor-fold desc="check if only valid api version">
  foreach ($compatible_apis as $c_api) {
    in_array($c_api, $existing_apis) or die("The API version is not supported!");
  }
  sizeof($compatible_apis) or die("The API version is not supported!");
  # </editor-fold>


  # <editor-fold desc="generate space_api">

  # <editor-fold desc="common">

  $space_api = new stdClass();
  $space_api->api = end( $compatible_apis ); # *0.8 *0.9 *0.11 *0.12 *0.13
  $space_api->space = "FAU FabLab"; # *0.8 *0.9 *0.11 *0.12 *0.13
  $space_api->logo = "https://fablab.fau.de/spaceapi/logo_transparentbg.png"; # *0.8 *0.9 *0.11 *0.12 *0.13
  $space_api->url = "https://fablab.fau.de/"; # *0.8 *0.9 *0.11 *0.12 *0.13

  # <editor-fold desc="stream">
//  $space_api->stream = new stdClass(); # 0.8 0.9 0.11 0.12 0.13
//  $space_api->stream->mp4 = ""; # 0.8 0.9 0.11 0.12 0.13
//  $space_api->stream->mjpeg = ""; # 0.8 0.9 0.11 0.12 0.13
//  $space_api->stream->ustream = ""; # 0.8 0.9 0.11 0.12 0.13
  # $space_api->stream->ext_bla = ""; # 0.8 0.9 0.11 0.12 0.13
  # $space_api->stream->bla = ""; # 0.8 0.9 0.11 0.12 ~
  # </editor-fold>

  # <editor-fold desc="events">
//  $ev1 = new stdClass(); # 0.8 0.9 0.11 0.12 0.13
//  $ev1->name = ""; # *0.8 *0.9 *0.11 *0.12 *0.13
//  $ev1->type = ""; # *0.8 *0.9 *0.11 *0.12 *0.13
//
//  if ( in_array('0.13', $compatible_apis) ) {
//    $ev1->timestamp = 0; # *0.13
//  }
//  if (in_array('0.8', $compatible_apis) ||
//    in_array('0.9', $compatible_apis) ||
//    in_array('0.11', $compatible_apis) ||
//    in_array('0.12', $compatible_apis)
//  ) {
//    $ev1->t = 0; # *0.8 *0.9 *0.11 *0.12
//  }
//  $ev1->extra = ""; # 0.8 0.9 0.11 0.12 0.13
//  $space_api->events = array($ev1); # 0.8 0.9 0.11 0.12 0.13
  # </editor-fold>

  # </editor-fold>

  if (in_array('0.8', $compatible_apis) ||
    in_array('0.9', $compatible_apis) ||
    in_array('0.11', $compatible_apis) ||
    in_array('0.12', $compatible_apis)
  ) {

    $space_api->address = "Raum U1.239\nErwin-Rommel-Straße 60\n91058 Erlangen\nGermany"; # 0.8 0.9 0.11 0.12
    $space_api->lat = 49.574; # 0.8 0.9 0.11 0.12
    $space_api->lon = 11.030; # 0.8 0.9 0.11 0.12
//    $space_api->cam = array(""); # (min 1) 0.8 0.9 0.11 0.12

    $space_api->open = $current && $open; # *0.8 *0.9 *0.11 *0.12
    $space_api->status = ($space_api->open ? "open for public" : "you can call us, maybe someone is here");  # 0.8 0.9 0.11 0.12
    $space_api->lastchange = (int) $timestamp; # 0.8 0.9 0.11 0.12

  }

  if (in_array('0.8', $compatible_apis)) {
    $space_api->phone = "+49 9131 85 28013"; # 0.8
  }

  if (in_array('0.13', $compatible_apis)) {

    # <editor-fold desc="space location object">
    $space_api->location = new stdClass(); # *0.13
    $space_api->location->address = "Raum U1.239\nErwin-Rommel-Straße 60\n91058 Erlangen\nGermany"; #0.13
    $space_api->location->lat = 49.574; # *0.13
    $space_api->location->lon = 11.030; # *0.13
    # </editor-fold>

    # <editor-fold desc="spacefed">
    $space_api->spacefed = new stdClass(); # 0.13
    $space_api->spacefed->spacenet = FALSE; # *0.13
    $space_api->spacefed->spacesaml = FALSE; # *0.13
    $space_api->spacefed->spacephone = FALSE; # *0.13
    # </editor-fold>

    # <editor-fold desc="state as tri-state for API 0.13">
    $space_api->state = new stdClass(); # *0.13
    $space_api->state->lastchange = (int) $timestamp; # 0.13
    $space_api->state->open = $current && $open; # *0.13 (true false null) array?!?
//    $space_api->state->trigger_person = ""; # 0.13
    $space_api->state->message = ($space_api->state->open ? "open for public" : "you can call us, maybe someone is here"); # 0.13
    $space_api->state->icon = new stdClass(); # 0.13
    $space_api->state->icon->open = "https://fablab.fau.de/spaceapi/logo_open.png"; # *0.13
    $space_api->state->icon->closed = "https://fablab.fau.de/spaceapi/logo_closed.png"; # *0.13
    # </editor-fold>

    # <editor-fold desc="cache up to 5 minutes">
    $space_api->cache = new stdClass(); # 0.13
    $space_api->cache->schedule = "m.05"; # 0.13
    # </editor-fold>

    // announce our projects page and github site
    $space_api->projects = array(
      "https://fablab.fau.de/project",
      "https://github.com/fau-fablab/"
    ); # 0.13

    # <editor-fold desc="radio show">
//    $rs1 = new stdClass(); # 0.13
//    $rs1->name = ""; # *0.13
//    $rs1->url = ""; # *0.13
//    $rs1->type = ""; # *0.13
//    $rs1->start = ""; # *0.13
//    $rs1->end = ""; # *0.13
//    $space_api->radio_show = array(); # 0.13
    # </editor-fold>

    // where to report issues with the space API json
    $space_api->issue_report_channels = array(
      "twitter",
      "ml"
    ); # *0.13 (email, issue_mail, twitter, ml)

  }

  if (in_array('0.9', $compatible_apis) ||
    in_array('0.11', $compatible_apis) ||
    in_array('0.12', $compatible_apis) ||
    in_array('0.13', $compatible_apis) ) {

    # <editor-fold desc="space contact info object">
    $space_api->contact = new stdClass(); # 0.9 0.11 0.12 *0.13
    $space_api->contact->phone = "+49 9131 85 28013"; # 0.9 0.11 0.12 0.13
//    $space_api->contact->sip = ""; # 0.9 0.11 0.12 0.13
    # <editor-fold desc="keymaster">
//    if ( !( in_array('0.9', $compatible_apis) ||
//            in_array('0.11', $compatible_apis) ||
//            in_array('0.12', $compatible_apis) ) &&
//          in_array('0.13', $compatible_apis) ) {
//      $km1 = new stdClass(); # 0.13
//      $km1->name = ""; # 0.13
//      $km1->irc_nick = ""; # 0.13
//      $km1->phone = ""; # 0.13
//      $km1->email = ""; # 0.13
//      $km1->twitter = ""; # 0.13
//      $space_api->contact->keymaster = array($km1); #  # 0.13
//
//    }
//    if (!in_array('0.13', $compatible_apis) && (
//      in_array('0.9', $compatible_apis) ||
//      in_array('0.11', $compatible_apis) ||
//      in_array('0.12', $compatible_apis) ) ) {
//
//      $space_api->contact->keymaster = array(""); # 0.9 0.11 0.12
//    }
    # </editor-fold>
    $space_api->contact->irc = "irc://irc.fau.de/#faufablab"; # 0.9 0.11 0.12 0.13
    $space_api->contact->twitter = "@FAUFabLab"; # 0.9 0.11 0.12 0.13
//    $space_api->contact->email = ""; # 0.9 0.11 0.12 0.13
    $space_api->contact->ml = "fablab-aktive@fablab.fau.de"; # 0.9 0.11 0.12 0.13
//    $space_api->contact->jabber = ""; # 0.9 0.11 0.12 0.13
    if (in_array('0.13', $compatible_apis)) {
      $space_api->contact->facebook = "FAUFabLab"; # 0.13
      $space_api->contact->google = new stdClass(); # 0.13
      $space_api->contact->google->plus = "+FAUFabLabErlangen"; # 0.13
//      $space_api->contact->identica = ""; # 0.13
//      $space_api->contact->foursquare = ""; # 0.13
//      $space_api->contact->issue_email = ""; # 0.13
    }
    # </editor-fold>

  }

  if (in_array('0.11', $compatible_apis) ||
    in_array('0.12', $compatible_apis) ) {

    # <editor-fold desc="icon">
    $space_api->icon = new stdClass(); # *0.11 *0.12
    $space_api->icon->open = "https://fablab.fau.de/spaceapi/logo_open.png"; # *0.11 *0.12
    $space_api->icon->closed = "https://fablab.fau.de/spaceapi/logo_closed.png"; # *0.11 *0.12
    # </editor-fold>

  }

  # <editor-fold desc="feeds and sensors: conflict: 012 <-> 0.13"

  if ( in_array('0.13', $compatible_apis) and !in_array('0.12', $compatible_apis) ) {

    # <editor-fold desc="sensors">
    $space_api->sensors = new stdClass(); # 0.13 (and others but only predefined)
    $active_members = new stdClass(); # 0.13
    $active_members->value = 30; # 0.13
    $active_members->name = "active members"; # 0.13
    $active_members->description = "approximated amount of all active members"; # 0.13
    $space_api->sensors->total_member_count = array($active_members); # 0.13
    # </editor-fold>

    # <editor-fold desc="feeds">
    $space_api->feeds = new stdClass(); # 0.13 (and flickr)

    $space_api->feeds->blog = new stdClass(); # 0.13
    $space_api->feeds->blog->type = "rss"; # 0.13
    $space_api->feeds->blog->url = "https://fablab.fau.de/rss.xml"; # *0.13

    $space_api->feeds->wiki = new stdClass(); # 0.13
    $space_api->feeds->wiki->type = "rss"; # 0.13
    $space_api->feeds->wiki->url = "https://fablab.fau.de/wiki/feed.php"; # *0.13

    $space_api->feeds->calendar = new stdClass(); # 0.13
    $space_api->feeds->calendar->type = "ical"; # 0.13
    $space_api->feeds->calendar->url = "https://fablab.fau.de/termine/ical"; # *0.13
    # </editor-fold>

  }

  if ( in_array('0.12', $compatible_apis) and !in_array('0.13', $compatible_apis) ) {

    # <editor-fold desc="sensors">
//    $temp = new stdClass(); # 0.12
//    $temp->kitchen = '48F'; # 0.12
//    $space_api->sensors = array($temp); # 0.12
    $active_members = new stdClass(); # 0.12
    $active_members->active_members = '30'; # 0.12
    $space_api->sensors = array($active_members); # 0.12
    # </editor-fold>

    # <editor-fold desc="feeds">
    $blog = new stdClass(); # 0.12
    $blog->name = "blog"; # 0.12
    $blog->type = "rss"; # 0.12
    $blog->url = "https://fablab.fau.de/rss.xml"; # 0.12

    $calendar = new stdClass(); # 0.12
    $calendar->name = "calendar"; # 0.12
    $calendar->type = "ical"; # 0.12
    $calendar->url = "https://fablab.fau.de/termine/ical"; # 0.12

    $space_api->feeds = array($blog, $calendar); # 0.12
    # </editor-fold>

  }

  # </editor-fold>

  # </editor-fold>

  echo json_encode($space_api, $json_options);
}
