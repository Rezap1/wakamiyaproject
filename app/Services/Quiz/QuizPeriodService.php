<?php

namespace App\Services\Quiz;

use Carbon\CarbonImmutable;

class QuizPeriodService
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function periodFor(CarbonImmutable|string|null $moment = null): array
    {
        $at = $moment instanceof CarbonImmutable
            ? $moment->setTimezone($this->timezone())
            : CarbonImmutable::parse($moment ?? $this->now(), $this->timezone());
        $anchor = CarbonImmutable::parse(config('quiz.period_anchor'), $this->timezone());
        $days = (int) config('quiz.period_days', 14);
        $seconds = $days * 86400;
        $index = (int) floor(($at->getTimestamp() - $anchor->getTimestamp()) / $seconds);
        $start = $anchor->addDays($index * $days);
        $end = $start->addDays($days);

        return [
            'index' => $index,
            'start' => $start,
            'end' => $end,
            'label' => $this->label($start, $end),
        ];
    }

    public function contains(array $period, CarbonImmutable|string $moment): bool
    {
        $at = $moment instanceof CarbonImmutable ? $moment : CarbonImmutable::parse($moment, $this->timezone());

        return $at->greaterThanOrEqualTo($period['start']) && $at->lessThan($period['end']);
    }

    public function isClosed(array $period, CarbonImmutable|string|null $now = null): bool
    {
        $at = $now instanceof CarbonImmutable ? $now : CarbonImmutable::parse($now ?? $this->now(), $this->timezone());

        return $at->greaterThanOrEqualTo($period['end']);
    }

    public function timezone(): string
    {
        return (string) config('quiz.timezone', 'Asia/Jakarta');
    }

    private function label(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $last = $end->subDay();
        if ($start->month === $last->month && $start->year === $last->year) {
            return $start->day.'–'.$last->locale('id')->translatedFormat('j F Y');
        }
        if ($start->year === $last->year) {
            return $start->locale('id')->translatedFormat('j F').'–'.$last->locale('id')->translatedFormat('j F Y');
        }

        return $start->locale('id')->translatedFormat('j F Y').'–'.$last->locale('id')->translatedFormat('j F Y');
    }
}
