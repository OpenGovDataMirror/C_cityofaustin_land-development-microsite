<?php

namespace Drupal\apdr_import\Component\Utility;

use Drupal\taxonomy\Entity\Term;

/**
 * @file
 */

class APDRImportHelper {
  /**
   * Returns a formatted date Y-m-d\TH:i:s
   */
  public function getFormattedDate($value) {
    $time = (int) $value/1000;
    // Looks like API data are based on new york timezone
    // we get a timestamp
    // So frst default it to New york then revert to site default.
    date_default_timezone_set("America/New_York");
    $date = date('Y-m-d\TH:i:s', $time);
    $date_config = \Drupal::config('system.date')->get('timezone');
    date_default_timezone_set($date_config['default']);
    return $date;
  }

  /**
   * Returns a formatted date Y-m-d
   */
  public function getFormattedDateSimple($value) {
    $time = (int) $value/1000;
    return date('Y-m-d', $time);
  }
}
