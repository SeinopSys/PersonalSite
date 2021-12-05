<?php

namespace App\Util;

class Time {
  public static $IN_SECONDS = [
    'year' => 31557600,
    'month' => 2592000,
    'week' => 604800,
    'day' => 86400,
    'hour' => 3600,
    'minute' => 60,
    'second' => 1,
  ];

  /**
   * Gets the difference between 2 timestamps
   *
   * @param $now
   * @param $target
   *
   * @return array
   */
  public static function Difference($now, $target) {
    $substract = $now - $target;
    $delta = [
      'past' => $substract > 0,
      'time' => abs($substract),
      'target' => $target,
    ];
    $time = $delta['time'];

    $delta['day'] = floor($time / self::$IN_SECONDS['day']);
    $time -= $delta['day'] * self::$IN_SECONDS['day'];

    $delta['hour'] = floor($time / self::$IN_SECONDS['hour']);
    $time -= $delta['hour'] * self::$IN_SECONDS['hour'];

    $delta['minute'] = floor($time / self::$IN_SECONDS['minute']);
    $time -= $delta['minute'] * self::$IN_SECONDS['minute'];

    $delta['second'] = floor($time);

    if (!empty($delta['day']) && $delta['day'] >= 7){
      $delta['week'] = floor($delta['day'] / 7);
      $delta['day'] -= $delta['week'] * 7;
    }
    if (!empty($delta['week']) && $delta['week'] >= 4){
      $delta['month'] = floor($delta['week'] / 4);
      $delta['week'] -= $delta['month'] * 4;
    }
    if (!empty($delta['month']) && $delta['month'] >= 12){
      $delta['year'] = floor($delta['month'] / 12);
      $delta['month'] -= $delta['year'] * 12;
    }

    return $delta;
  }

  const LOCALE_MAP = [
    'en' => 'en_US',
    'hu' => 'hu_HU',
  ];

  const
    FORMAT_FULL = 'jS M Y, g:i:s a T',
    FORMAT_READABLE = 'readable';

  /**
   * Create an ISO timestamp from the input string
   *
   * @param int    $time
   * @param string $format
   *
   * @return string
   */
  public static function Format($time, $format = 'c') {
    if ($format === self::FORMAT_READABLE){
      $formatter = new \IntlDateFormatter(self::LOCALE_MAP[\App::getLocale()]);

      return datefmt_format($formatter, $time);
    }

    $ts = gmdate($format, $time);
    if ($format === 'c'){
      $ts = str_replace('+00:00', 'Z', $ts);
    }
    if ($format !== 'c' && strpos($format, 'T') === false){
      $ts .= ' ('.date('T').')';
    }

    return $ts;
  }

  const
    TAG_EXTENDED = true,
    TAG_NO_DYNTIME = 'no',
    TAG_STATIC_DYNTIME = 'static';

  /**
   * Create <time datetime></time> tag
   *
   * @param string|int $timestamp
   * @param bool       $extended
   * @param string     $allowDyntime
   *
   * @return string
   */
  public static function Tag($timestamp, $extended = false, $allowDyntime = 'yes') {
    if (\is_string($timestamp)){
      $new_ts = strtotime($timestamp);
      if ($new_ts === false){
        return null;
      }
      $timestamp = $new_ts;
    }
    if ($timestamp === false){
      return null;
    }

    $datetime = self::Format($timestamp);
    $full = self::Format($timestamp, self::FORMAT_FULL);
    $text = self::Format($timestamp, self::FORMAT_READABLE);

    switch ($allowDyntime){
      case self::TAG_NO_DYNTIME:
        $datetime .= "' class='nodt";
      break;
      case self::TAG_STATIC_DYNTIME:
        $datetime .= "' class='no-dynt-el";
      break;
    }

    return
      !$extended
        ? "<time datetime='$datetime' title='$full'>$text</time>"
        : "<time datetime='$datetime'>$full</time>".(
        $allowDyntime === 'yes'
          ? "<span class='dynt-el'>$text</span>"
          : ''
        );
  }
}
