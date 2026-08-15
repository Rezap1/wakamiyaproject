<?php

namespace App\Helpers;

class TerbilangHelper
{
    private static $units = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'
    ];

    public static function convert($number)
    {
        $number = (float)$number;
        if ($number < 0) {
            return 'Minus ' . trim(self::convert(abs($number)));
        }

        $integerPart = floor($number);

        if ($integerPart == 0) {
            $words = 'Nol';
        } else {
            $words = self::toWords($integerPart);
        }

        return '# ' . preg_replace('/\s+/', ' ', trim($words)) . ' Rupiah #';
    }

    private static function toWords($number)
    {
        $number = (float)$number;
        if ($number < 12) {
            return self::$units[(int)$number];
        } elseif ($number < 20) {
            return self::$units[(int)($number - 10)] . ' Belas';
        } elseif ($number < 100) {
            return self::toWords(floor($number / 10)) . ' Puluh ' . self::toWords($number % 10);
        } elseif ($number < 200) {
            return 'Seratus ' . self::toWords($number - 100);
        } elseif ($number < 1000) {
            return self::toWords(floor($number / 100)) . ' Ratus ' . self::toWords($number % 100);
        } elseif ($number < 2000) {
            return 'Seribu ' . self::toWords($number - 1000);
        } elseif ($number < 1000000) {
            return self::toWords(floor($number / 1000)) . ' Ribu ' . self::toWords($number % 1000);
        } elseif ($number < 1000000000) {
            return self::toWords(floor($number / 1000000)) . ' Juta ' . self::toWords($number % 1000000);
        } elseif ($number < 1000000000000) {
            return self::toWords(floor($number / 1000000000)) . ' Miliar ' . self::toWords(fmod($number, 1000000000));
        } else {
            return self::toWords(floor($number / 1000000000000)) . ' Triliun ' . self::toWords(fmod($number, 1000000000000));
        }
    }
}
