<?php

if (!function_exists('filterTimeSQLCondition')) {
  function filterTimeSQLCondition($value)
  {
    $ts = "CONVERT_TZ(FROM_UNIXTIME(jt.ts), '+00:00', '+07:00')";

    return match ($value) {
      'today' => "DATE($ts) = CURDATE()",
      'morning' => "TIME($ts) BETWEEN '06:00:00' AND '09:59:59'",
      'afternoon' => "TIME($ts) BETWEEN '10:00:00' AND '14:59:59'",
      'evening' => "TIME($ts) BETWEEN '15:00:00' AND '17:59:59'",
      'night' => "(TIME($ts) >= '18:00:00' OR TIME($ts) < '06:00:00')",
      default => "1 = 1",
    };
  }
}


if (!function_exists('format_unix_timestamp')) {
  function format_unix_timestamp($timestamp, $format = 'Y-m-d H:i:s', $timezone = 'Asia/Jakarta')
  {
    if (!is_numeric($timestamp)) {
      return null;
    }

    try {
      return (new DateTime("@$timestamp"))
        ->setTimezone(new DateTimeZone($timezone))
        ->format($format);
    } catch (Exception $e) {
      return null;
    }
  }
}

function match_time(\Carbon\Carbon $carbon, $value)
{
  switch ($value) {
    case 'today':
      return $carbon->isToday();
    case 'morning':
      return $carbon->between($carbon->copy()->setTime(6, 0), $carbon->copy()->setTime(9, 59));
    case 'afternoon':
      return $carbon->between($carbon->copy()->setTime(10, 0), $carbon->copy()->setTime(14, 59));
    case 'evening':
      return $carbon->between($carbon->copy()->setTime(15, 0), $carbon->copy()->setTime(17, 59));
    case 'night':
      return $carbon->hour >= 18 || $carbon->hour < 6;
    default:
      return true;
  }
}
