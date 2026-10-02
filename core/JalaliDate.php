<?php
/**
 * Connectix Panel - Jalali (Shamsi) Date Converter
 * Converts Gregorian dates to Persian Jalali with beautiful formatting
 * All dates in bot will be shown in Shamsi
 */

class JalaliDate
{
    private static array $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    private static array $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    private static array $jMonthNames = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند'
    ];

    private static array $jMonthNamesShort = [
        1 => 'فرو',
        2 => 'ارد',
        3 => 'خرد',
        4 => 'تیر',
        5 => 'مرد',
        6 => 'شهر',
        7 => 'مهر',
        8 => 'آبا',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهم',
        12 => 'اسف'
    ];

    private static array $weekDays = [
        0 => 'شنبه',
        1 => 'یکشنبه',
        2 => 'دوشنبه',
        3 => 'سه‌شنبه',
        4 => 'چهارشنبه',
        5 => 'پنجشنبه',
        6 => 'جمعه'
    ];

    /**
     * Gregorian to Jalali conversion
     * Returns [jy, jm, jd]
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gy -= 1600;
        $gm -= 1;
        $gd -= 1;

        $gDayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        for ($i = 0; $i < $gm; ++$i) {
            $gDayNo += self::$gDaysInMonth[$i];
        }
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
            $gDayNo++;
        }
        $gDayNo += $gd;

        $jDayNo = $gDayNo - 79;
        $jNp = intdiv($jDayNo, 12053);
        $jDayNo %= 12053;

        $jy = 979 + 33 * $jNp + 4 * intdiv($jDayNo, 1461);
        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jy += intdiv($jDayNo - 1, 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }

        for ($i = 0; $i < 11 && $jDayNo >= self::$jDaysInMonth[$i]; ++$i) {
            $jDayNo -= self::$jDaysInMonth[$i];
        }
        $jm = $i + 1;
        $jd = $jDayNo + 1;

        return [$jy, $jm, $jd];
    }

    /**
     * Convert timestamp or date string to Jalali array
     */
    public static function toJalali($time = null): array
    {
        if ($time === null) $time = time();
        if (!is_numeric($time)) $time = strtotime($time) ?: time();
        $gy = (int)date('Y', $time);
        $gm = (int)date('n', $time);
        $gd = (int)date('j', $time);
        [$jy, $jm, $jd] = self::gregorianToJalali($gy, $gm, $gd);
        $hour = (int)date('H', $time);
        $minute = (int)date('i', $time);
        $second = (int)date('s', $time);
        $weekDay = (int)date('w', $time); // 0 Sunday, convert to Jalali week
        // Convert to Persian week: Saturday = 0
        $jWeekDay = ($weekDay + 1) % 7;
        return [
            'jy' => $jy,
            'jm' => $jm,
            'jd' => $jd,
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
            'weekday' => $jWeekDay,
            'timestamp' => $time
        ];
    }

    /**
     * Format Jalali date with Persian
     * @param mixed $time timestamp or date string
     * @param string $format Type: full, date, datetime, time, beautiful
     */
    public static function format($time = null, string $format = 'beautiful'): string
    {
        $j = self::toJalali($time);
        $jy = $j['jy'];
        $jm = $j['jm'];
        $jd = $j['jd'];
        $hour = str_pad($j['hour'], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($j['minute'], 2, '0', STR_PAD_LEFT);
        $second = str_pad($j['second'], 2, '0', STR_PAD_LEFT);
        $monthName = self::$jMonthNames[$jm] ?? $jm;
        $weekDayName = self::$weekDays[$j['weekday']] ?? '';

        switch ($format) {
            case 'full':
                // شنبه 15 مهر 1403 - 14:30:25
                return "{$weekDayName} {$jd} {$monthName} {$jy} - {$hour}:{$minute}:{$second}";
            case 'date':
                // 15 مهر 1403
                return "{$jd} {$monthName} {$jy}";
            case 'datetime':
                // 15 مهر 1403 ساعت 14:30
                return "{$jd} {$monthName} {$jy} ساعت {$hour}:{$minute}";
            case 'time':
                return "{$hour}:{$minute}:{$second}";
            case 'short':
                // 1403/07/15
                return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
            case 'short_datetime':
                // 1403/07/15 14:30
                return sprintf("%04d/%02d/%02d %02d:%02d", $jy, $jm, $jd, $j['hour'], $j['minute']);
            case 'beautiful':
            default:
                // 📅 شنبه 15 مهر 1403 ⏰ 14:30
                return "{$weekDayName} {$jd} {$monthName} {$jy} ⏰ {$hour}:{$minute}";
        }
    }

    /**
     * Beautiful invoice date with emoji
     */
    public static function invoiceDate($time = null): string
    {
        $j = self::toJalali($time);
        $monthName = self::$jMonthNames[$j['jm']] ?? $j['jm'];
        $weekDay = self::$weekDays[$j['weekday']] ?? '';
        $hour = str_pad($j['hour'], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($j['minute'], 2, '0', STR_PAD_LEFT);
        return "📅 {$weekDay} {$j['jd']} {$monthName} {$j['jy']} ⏰ {$hour}:{$minute}";
    }

    /**
     * For expire dates - show remaining + Jalali
     */
    public static function expireDate($expireAt): string
    {
        if (empty($expireAt)) return '♾️ نامحدود';
        $ts = is_numeric($expireAt) ? (int)$expireAt : strtotime($expireAt);
        if ($ts <= time()) return '❌ منقضی شده';
        
        $j = self::toJalali($ts);
        $monthName = self::$jMonthNames[$j['jm']] ?? $j['jm'];
        $diff = $ts - time();
        $days = ceil($diff / 86400);
        
        $persianDays = self::toPersianNumber($days);
        $dateStr = "{$j['jd']} {$monthName} {$j['jy']}";
        
        if ($days <= 3) {
            return "⚠️ {$dateStr} ({$persianDays} روز - فوری تمدید کنید!)";
        } elseif ($days <= 7) {
            return "⏳ {$dateStr} ({$persianDays} روز باقی‌مانده)";
        } else {
            return "✅ {$dateStr} ({$persianDays} روز باقی‌مانده)";
        }
    }

    /**
     * Convert English numbers to Persian
     */
    public static function toPersianNumber($number): string
    {
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        return str_replace($en, $fa, (string)$number);
    }

    /**
     * Convert Persian numbers to English
     */
    public static function toEnglishNumber($number): string
    {
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $en = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
        return str_replace($fa, $en, (string)$number);
    }

    /**
     * Format with Persian numbers
     */
    public static function formatPersian($time = null, string $format = 'beautiful'): string
    {
        $str = self::format($time, $format);
        return self::toPersianNumber($str);
    }

    /**
     * Time ago in Persian with Jalali
     */
    public static function timeAgo($datetime): string
    {
        if (empty($datetime)) return 'هرگز';
        $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return 'لحظاتی پیش';
        if ($diff < 3600) return self::toPersianNumber(floor($diff / 60)) . ' دقیقه پیش';
        if ($diff < 86400) return self::toPersianNumber(floor($diff / 3600)) . ' ساعت پیش';
        if ($diff < 604800) return self::toPersianNumber(floor($diff / 86400)) . ' روز پیش';
        
        // More than a week, show Jalali date
        return self::format($time, 'date');
    }

    /**
     * Beautiful invoice header
     */
    public static function invoiceHeader(): string
    {
        return self::invoiceDate(time());
    }
}
