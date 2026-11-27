<?php

namespace App\Services\Academic;

use App\Contracts\AcademicMonitoringRepository;
use App\Helpers\SheetValue;
use App\Support\Academic\ScoreValueFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AcademicMonitoringService
{
    private const QUIZ_CATEGORY = 'KUIS_REGULER';

    public function __construct(private readonly AcademicMonitoringRepository $repository) {}

    public function build(array $scope, array $filters): array
    {
        $catalog = $this->repository->catalog();
        $batches = $catalog['batches']->map(fn ($row) => (array) $row)->keyBy('Batch_ID');
        $classes = $catalog['classes']->map(fn ($row) => (array) $row)->keyBy('Class_ID');
        $students = $catalog['students']->map(fn ($row) => (array) $row)
            ->filter(fn (array $row) => SheetValue::isOperationalStudent($row))->keyBy('Student_ID');
        $teachers = $catalog['teachers']->map(fn ($row) => (array) $row)->keyBy('Teacher_ID');
        $subjects = $catalog['subjects']->map(fn ($row) => (array) $row)->keyBy('Subject_ID');
        $categories = $this->categoryLabels($catalog['categories']);

        [$batch, $class, $student] = $this->resolveScope($scope, $batches, $classes, $students);
        $scopeStudents = $this->scopeStudents($students, $scope, $batch, $class, $student);
        $scopeClasses = $this->scopeClasses($classes, $scope, $batch, $class);

        $queryFilters = [
            'from' => $filters['comparison_from'] ?? $filters['start_date'],
            'to' => $filters['end_date'],
            'batch_id' => $batch['Batch_ID'] ?? null,
            'class_id' => $class['Class_ID'] ?? null,
            'student_id' => $student['Student_ID'] ?? null,
        ];
        if ($filters['period'] === 'all') {
            $queryFilters['from'] = null;
        }

        $observations = $this->repository->observations($queryFilters)
            ->map(fn ($row) => $this->normalizeObservation((array) $row, $categories))
            ->filter()
            ->filter(fn (array $row) => $students->has($row['student_id']))
            ->values();

        $availableCategoryIds = $observations->pluck('category')->unique()->values();
        $activeCategories = $availableCategoryIds->mapWithKeys(fn (string $id) => [$id => $categories[$id] ?? $this->humanize($id)]);
        if ($filters['category'] !== '') {
            $observations = $observations->where('category', $filters['category'])->values();
        }
        $analyticalCategories = $filters['category'] !== ''
            ? $activeCategories->only([$filters['category']])
            : $activeCategories;

        $current = $filters['period'] === 'all'
            ? $observations
            : $observations->filter(fn (array $row) => $row['occurred_on'] >= $filters['start_date'])->values();
        $previous = ($filters['period'] === 'all' || empty($filters['comparison_from']))
            ? collect()
            : $observations->filter(fn (array $row) => $row['occurred_on'] < $filters['start_date'])->values();

        $stats = $this->categoryStats($current, $analyticalCategories);
        $previousStats = $this->categoryStats($previous, $analyticalCategories);

        $studentPaginator = null;
        if ($class && ! $student) {
            $studentPaginator = $this->repository->studentsForClass($class['Class_ID'], $filters);
            $studentPaginator->setCollection($studentPaginator->getCollection()->map(function ($row) use ($current, $analyticalCategories): array {
                $student = (array) $row;
                $student['monitoring'] = $this->compactStats($this->categoryStats(
                    $current->where('student_id', $student['Student_ID'])->values(),
                    $analyticalCategories
                ));

                return $student;
            }));
        }

        return [
            'scope' => $scope['type'],
            'context' => compact('batch', 'class', 'student'),
            'breadcrumbs' => $this->breadcrumbs($batch, $class, $student),
            'filters' => $filters,
            'summary' => [
                'batches' => $scope['type'] === 'index' ? $batches->count() : ($batch ? 1 : 0),
                'classes' => $scopeClasses->count(),
                'students' => $scopeStudents->count(),
                'with_scores' => $current->pluck('student_id')->unique()->count(),
                'without_scores' => max(0, $scopeStudents->count() - $current->pluck('student_id')->unique()->count()),
                'observations' => $current->count(),
            ],
            'categories' => $activeCategories,
            'category_stats' => $stats,
            'comparison' => $this->comparison($stats, $previousStats, $filters),
            'trend' => $this->trend($current, $analyticalCategories, $filters['period']),
            'chapter_trend' => $this->chapterTrend($current->where('category', 'UJIAN_BAB')->values()),
            'distribution' => $this->distribution($current),
            'entities' => $this->entities($scope['type'], $batches, $classes, $students, $current, $analyticalCategories, $batch),
            'student_paginator' => $studentPaginator,
            'student_history' => $student ? $this->historyPaginator($current, $filters) : null,
            'student_history_export' => $student
                ? $current->sortByDesc(fn (array $row) => $row['occurred_on'].'|'.$row['id'])->take(500)->values()
                : collect(),
            'class_roster_export' => $class && ! $student
                ? $scopeStudents->take(200)->map(function (array $row) use ($current, $analyticalCategories): array {
                    $row['monitoring'] = $this->compactStats($this->categoryStats(
                        $current->where('student_id', $row['Student_ID'])->values(),
                        $analyticalCategories
                    ));

                    return $row;
                })->values()
                : collect(),
            'export_limits' => [
                'class_roster_actual' => $class && ! $student ? $scopeStudents->count() : 0,
                'class_roster_shown' => $class && ! $student ? min(200, $scopeStudents->count()) : 0,
                'student_history_actual' => $student ? $current->count() : 0,
                'student_history_shown' => $student ? min(500, $current->count()) : 0,
            ],
            'teachers' => $class ? $this->classTeachers($class, $catalog['schedules'], $teachers) : collect(),
            'subjects' => $subjects,
            'updated_at' => now(config('app.timezone', 'Asia/Jakarta'))->toIso8601String(),
            'updated_label' => now(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i:s').' WIB',
            'methodology' => 'Rata-rata monitoring dihitung per siswa pada setiap kategori, lalu dirata-ratakan antarsiswa. Kategori tidak digabung menjadi nilai resmi.',
            'history_note' => 'Atribusi kelas menggunakan kelas siswa saat ini. class_id tersimpan hanya dipakai sebagai konteks historis; database tidak menyediakan snapshot keanggotaan kelas immutable.',
            'sources' => ['MASTER_SCORE', 'QUIZ_RESULT'],
        ];
    }

    private function resolveScope(array $scope, Collection $batches, Collection $classes, Collection $students): array
    {
        $batch = isset($scope['batch_id']) ? $batches->get($scope['batch_id']) : null;
        abort_if(isset($scope['batch_id']) && ! $batch, 404);
        $class = isset($scope['class_id']) ? $classes->get($scope['class_id']) : null;
        abort_if(isset($scope['class_id']) && (! $class || ($batch && ($class['Batch_ID'] ?? '') !== $batch['Batch_ID'])), 404);
        $student = isset($scope['student_id']) ? $students->get($scope['student_id']) : null;
        abort_if(isset($scope['student_id']) && (! $student || ($class && ($student['Class_ID'] ?? '') !== $class['Class_ID'])), 404);

        return [$batch, $class, $student];
    }

    private function scopeStudents(Collection $students, array $scope, ?array $batch, ?array $class, ?array $student): Collection
    {
        return match ($scope['type']) {
            'student' => collect($student ? [$student] : []),
            'class' => $students->where('Class_ID', $class['Class_ID'])->values(),
            'batch' => $students->where('Batch_ID', $batch['Batch_ID'])->values(),
            default => $students->values(),
        };
    }

    private function scopeClasses(Collection $classes, array $scope, ?array $batch, ?array $class): Collection
    {
        return match ($scope['type']) {
            'student', 'class' => collect($class ? [$class] : []),
            'batch' => $classes->where('Batch_ID', $batch['Batch_ID'])->values(),
            default => $classes->values(),
        };
    }

    private function normalizeObservation(array $row, Collection $labels): ?array
    {
        if (! is_numeric($row['score_value'] ?? null)) {
            return null;
        }
        $score = (float) $row['score_value'];
        if (! is_finite($score) || $score < 0 || $score > 100) {
            return null;
        }
        $date = trim((string) ($row['occurred_on'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $category = strtoupper(trim((string) ($row['category'] ?? 'GENERAL'))) ?: 'GENERAL';
        $details = json_decode((string) ($row['details'] ?? ''), true);

        return [
            'id' => (string) ($row['observation_id'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'student_id' => (string) ($row['student_id'] ?? ''),
            'category' => $category,
            'category_label' => $labels[$category] ?? $this->humanize($category),
            'score' => $score,
            'score_label' => ScoreValueFormatter::format($score),
            'occurred_on' => $date,
            'title' => trim((string) ($row['title'] ?? '')) ?: ($labels[$category] ?? $this->humanize($category)),
            'details' => is_array($details) ? $details : [],
            'class_snapshot_id' => trim((string) ($row['class_snapshot_id'] ?? '')),
            'current_class_id' => trim((string) ($row['current_class_id'] ?? '')),
            'current_batch_id' => trim((string) ($row['current_batch_id'] ?? '')),
            'evaluator' => trim((string) ($row['evaluator'] ?? '')) ?: '-',
            'status' => 'Final',
        ];
    }

    private function categoryLabels(Collection $configs): Collection
    {
        $labels = $configs->mapWithKeys(function ($row): array {
            $row = (array) $row;
            $id = strtoupper(trim((string) ($row['Category_ID'] ?? '')));

            return $id === '' ? [] : [$id => trim((string) ($row['Category_Name'] ?? '')) ?: $this->humanize($id)];
        });
        $labels['UJIAN_BAB'] = $labels['UJIAN_BAB'] ?? 'Ujian Bab';
        $labels[self::QUIZ_CATEGORY] = 'Kuis Reguler';

        return $labels;
    }

    private function categoryStats(Collection $rows, Collection $categories): Collection
    {
        return $categories->map(function (string $label, string $category) use ($rows): array {
            $categoryRows = $rows->where('category', $category);
            $studentMeans = $categoryRows->groupBy('student_id')->map(fn (Collection $studentRows) => $studentRows->avg('score'));
            $average = $studentMeans->isEmpty() ? null : round((float) $studentMeans->avg(), 2);

            return [
                'category' => $category,
                'label' => $label,
                'average' => $average,
                'average_label' => $average === null ? 'Belum dinilai' : ScoreValueFormatter::format($average),
                'students' => $studentMeans->count(),
                'observations' => $categoryRows->count(),
            ];
        })->values();
    }

    private function compactStats(Collection $stats): array
    {
        return $stats->mapWithKeys(fn (array $row) => [$row['category'] => $row])->all();
    }

    private function comparison(Collection $current, Collection $previous, array $filters): Collection
    {
        $previousByCategory = $previous->keyBy('category');

        return $current->map(function (array $row) use ($previousByCategory, $filters): array {
            $before = $previousByCategory->get($row['category']);
            $delta = ($row['average'] !== null && ($before['average'] ?? null) !== null)
                ? round($row['average'] - $before['average'], 2)
                : null;

            return $row + [
                'previous_average' => $before['average'] ?? null,
                'delta' => $delta,
                'delta_label' => $filters['period'] === 'all' || $delta === null
                    ? 'Belum Ada Pembanding'
                    : (($delta > 0 ? '+' : '').ScoreValueFormatter::format($delta).' poin'),
            ];
        });
    }

    private function trend(Collection $rows, Collection $categories, string $period): array
    {
        $bucketed = $rows->groupBy(fn (array $row) => $this->bucket($row['occurred_on'], $period));
        $limit = $period === 'daily' ? 90 : 60;
        $labels = $bucketed->keys()->sort()->take(-$limit)->values();
        $series = $categories->map(function (string $label, string $category) use ($bucketed, $labels): array {
            return [
                'id' => $category,
                'label' => $label,
                'values' => $labels->map(function (string $bucket) use ($bucketed, $category) {
                    $rows = $bucketed->get($bucket, collect())->where('category', $category);
                    if ($rows->isEmpty()) {
                        return null;
                    }
                    $means = $rows->groupBy('student_id')->map(fn (Collection $studentRows) => $studentRows->avg('score'));

                    return round((float) $means->avg(), 2);
                })->all(),
            ];
        })->values()->all();

        return ['labels' => $labels->map(fn ($label) => $this->bucketLabel($label, $period))->all(), 'series' => $series];
    }

    private function chapterTrend(Collection $rows): array
    {
        $points = $rows->map(function (array $row): array {
            $details = $row['details'];
            $label = trim((string) ($details['chapter'] ?? $details['bab'] ?? $details['chapter_number'] ?? $details['material'] ?? $row['title']));
            preg_match('/\d+/', $label, $match);

            return ['label' => $label ?: $row['title'], 'order' => isset($match[0]) ? (int) $match[0] : PHP_INT_MAX, 'score' => $row['score'], 'date' => $row['occurred_on']];
        })->sortBy([['order', 'asc'], ['date', 'asc']])->values();

        return ['labels' => $points->pluck('label')->all(), 'series' => [['id' => 'UJIAN_BAB', 'label' => 'Ujian Bab', 'values' => $points->pluck('score')->all()]]];
    }

    private function distribution(Collection $rows): array
    {
        $bins = ['0–59' => [0, 59.999], '60–69' => [60, 69.999], '70–79' => [70, 79.999], '80–89' => [80, 89.999], '90–100' => [90, 100]];

        return collect($bins)->map(fn (array $range, string $label) => [
            'label' => $label,
            'value' => $rows->filter(fn (array $row) => $row['score'] >= $range[0] && $row['score'] <= $range[1])->count(),
        ])->values()->all();
    }

    private function entities(string $type, Collection $batches, Collection $classes, Collection $students, Collection $rows, Collection $categories, ?array $batch): Collection
    {
        if ($type === 'index') {
            return $batches->map(function (array $item) use ($classes, $students, $rows, $categories): array {
                $id = $item['Batch_ID'];
                $itemRows = $rows->where('current_batch_id', $id)->values();

                return $item + ['classes' => $classes->where('Batch_ID', $id)->count(), 'students' => $students->where('Batch_ID', $id)->count(), 'monitoring' => $this->compactStats($this->categoryStats($itemRows, $categories))];
            })->values();
        }
        if ($type === 'batch') {
            return $classes->where('Batch_ID', $batch['Batch_ID'])->map(function (array $item) use ($students, $rows, $categories): array {
                $id = $item['Class_ID'];

                return $item + ['students' => $students->where('Class_ID', $id)->count(), 'monitoring' => $this->compactStats($this->categoryStats($rows->where('current_class_id', $id)->values(), $categories))];
            })->values();
        }

        return collect();
    }

    private function historyPaginator(Collection $rows, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? request('page', 1)));
        $perPage = 20;
        $sorted = $rows->sortByDesc(fn (array $row) => $row['occurred_on'].'|'.$row['id'])->values();

        return new LengthAwarePaginator($sorted->forPage($page, $perPage)->values(), $sorted->count(), $perPage, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);
    }

    private function classTeachers(array $class, Collection $schedules, Collection $teachers): Collection
    {
        $ids = $schedules->map(fn ($row) => (array) $row)->where('Class_ID', $class['Class_ID'])->pluck('Teacher_ID')
            ->push($class['Homeroom_Teacher_ID'] ?? null)->filter()->unique();

        return $ids->map(fn ($id) => $teachers->get($id))->filter()->values();
    }

    private function breadcrumbs(?array $batch, ?array $class, ?array $student): array
    {
        $items = [['label' => 'Monitoring Akademik', 'route' => route('director.academic-monitoring.index')]];
        if ($batch) {
            $items[] = ['label' => $batch['Batch_Name'] ?: $batch['Batch_ID'], 'route' => route('director.academic-monitoring.batch', $batch['Batch_ID'])];
        }
        if ($class) {
            $items[] = ['label' => $class['Class_Name'] ?: $class['Class_ID'], 'route' => route('director.academic-monitoring.class', [$batch['Batch_ID'], $class['Class_ID']])];
        }
        if ($student) {
            $items[] = ['label' => $student['Full_Name'], 'route' => null];
        }

        return $items;
    }

    private function bucket(string $date, string $period): string
    {
        $value = CarbonImmutable::parse($date, config('app.timezone'));

        return match ($period) {
            'daily' => $value->format('Y-m-d'),
            'monthly', 'all' => $value->format('Y-m'),
            default => $value->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d'),
        };
    }

    private function bucketLabel(string $bucket, string $period): string
    {
        $value = CarbonImmutable::parse($bucket, config('app.timezone'));

        return match ($period) {
            'daily' => $value->format('d M'),
            'monthly', 'all' => $value->translatedFormat('M Y'),
            default => 'Minggu '.$value->format('d M'),
        };
    }

    private function humanize(string $value): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $value)));
    }
}
