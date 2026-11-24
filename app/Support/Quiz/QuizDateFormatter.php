<?php

namespace App\Support\Quiz;

use Carbon\CarbonImmutable;

class QuizDateFormatter
{
    private const MONTHS = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public function range(string $start, string $end): string
    {
        $startAt = $this->parse($start);
        $endAt = $this->parse($end);
        if ($startAt->isSameDay($endAt)) {
            return $this->date($startAt).', '.$startAt->format('H:i').' – '.$endAt->format('H:i').' WIB';
        }

        return $this->date($startAt).', '.$startAt->format('H:i').' – '.$this->date($endAt).', '.$endAt->format('H:i').' WIB';
    }

    public function dateTime(?string $value): string
    {
        if (trim((string) $value) === '') {
            return '-';
        }
        $at = $this->parse((string) $value);

        return $this->date($at).', '.$at->format('H:i').' WIB';
    }

    private function date(CarbonImmutable $value): string
    {
        return $value->day.' '.self::MONTHS[$value->month].' '.$value->year;
    }

    private function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, (string) config('quiz.timezone', 'Asia/Jakarta'))
            ->setTimezone((string) config('quiz.timezone', 'Asia/Jakarta'));
    }
}
