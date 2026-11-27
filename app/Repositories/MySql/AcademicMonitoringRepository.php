<?php

namespace App\Repositories\MySql;

use App\Contracts\AcademicMonitoringRepository as AcademicMonitoringRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AcademicMonitoringRepository implements AcademicMonitoringRepositoryContract
{
    public function catalog(): array
    {
        return [
            'batches' => $this->active($this->db()->table('MASTER_BATCH'))
                ->select(['Batch_ID', 'Batch_Code', 'Batch_Name', 'Program_ID', 'Batch_Status', 'Start_Date', 'End_Date'])
                ->orderByDesc('Start_Date')->orderBy('Batch_Name')->get(),
            'classes' => $this->active($this->db()->table('MASTER_CLASS'))
                ->select(['Class_ID', 'Class_Code', 'Class_Name', 'Batch_ID', 'Program_ID', 'Homeroom_Teacher_ID', 'Class_Status'])
                ->orderBy('Class_Name')->get(),
            'students' => $this->active($this->db()->table('MASTER_STUDENT'))
                ->select(['Student_ID', 'Student_Number', 'Full_Name', 'Class_ID', 'Batch_ID', 'Program_ID', 'Enrollment_Status', 'Graduation_Status'])
                ->orderBy('Full_Name')->get(),
            'teachers' => $this->active($this->db()->table('MASTER_TEACHER'))
                ->select(['Teacher_ID', 'Full_Name', 'Teacher_Code', 'Teaching_Status'])->get(),
            'schedules' => $this->active($this->db()->table('MASTER_SCHEDULE'))
                ->select(['Schedule_ID', 'Class_ID', 'Subject_ID', 'Teacher_ID'])->get(),
            'subjects' => $this->active($this->db()->table('MASTER_SUBJECT'))
                ->select(['Subject_ID', 'Subject_Name', 'Subject_Code'])->get(),
            'categories' => $this->active($this->db()->table('MASTER_ASSESSMENT_CONFIG'))
                ->select(['Category_ID', 'Category_Name', 'Aspects_JSON'])->orderBy('Category_Name')->get(),
        ];
    }

    public function observations(array $filters): Collection
    {
        $scores = $this->scoreQuery($filters)->get();
        $quizzes = $this->quizQuery($filters)->get();

        return $scores->concat($quizzes)
            ->sortBy(fn ($row) => ($row->occurred_on ?? '').'|'.($row->observation_id ?? ''))
            ->values();
    }

    public function studentsForClass(string $classId, array $filters): LengthAwarePaginator
    {
        $sorts = [
            'name' => 'Full_Name',
            'number' => 'Student_Number',
            'status' => 'Enrollment_Status',
        ];
        $sort = $sorts[$filters['sort'] ?? 'name'] ?? 'Full_Name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->active($this->db()->table('MASTER_STUDENT'))
            ->where('Class_ID', $classId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('Full_Name', 'like', '%'.$search.'%')
                        ->orWhere('Student_Number', 'like', '%'.$search.'%')
                        ->orWhere('Student_ID', 'like', '%'.$search.'%');
                });
            })
            ->select(['Student_ID', 'Student_Number', 'Full_Name', 'Class_ID', 'Batch_ID', 'Program_ID', 'Enrollment_Status', 'Graduation_Status'])
            ->orderBy($sort, $direction)->orderBy('Student_ID')
            ->paginate(min(50, max(10, (int) ($filters['per_page'] ?? 20))))
            ->withQueryString();
    }

    private function scoreQuery(array $filters): Builder
    {
        $date = $this->scoreDateExpression();
        $classSnapshot = $this->jsonText('score.Evaluation_Details', '$.class_id');

        $query = $this->db()->table('MASTER_SCORE as score')
            ->join('MASTER_STUDENT as student', 'student.Student_ID', '=', 'score.Student_ID')
            ->leftJoin('MASTER_SUBJECT as subject', 'subject.Subject_ID', '=', 'score.Subject_ID')
            ->leftJoin('MASTER_ASSIGNMENT as assignment', 'assignment.Assignment_ID', '=', 'score.Assignment_ID')
            ->leftJoin('MASTER_SCHEDULE as schedule', 'schedule.Schedule_ID', '=', 'score.Schedule_ID')
            ->leftJoin('MASTER_TEACHER as teacher', 'teacher.Teacher_ID', '=', 'schedule.Teacher_ID')
            ->leftJoin('MASTER_USER as creator', 'creator.User_ID', '=', 'score.Created_By')
            ->selectRaw("'MASTER_SCORE' as source")
            ->selectRaw('score.Score_ID as observation_id, score.Student_ID as student_id')
            ->selectRaw("COALESCE(NULLIF(score.Assessment_Category, ''), NULLIF(score.Exam_Type, ''), 'GENERAL') as category")
            ->selectRaw('score.Score as score_value')
            ->selectRaw("{$date} as occurred_on")
            ->selectRaw("COALESCE(NULLIF(assignment.Title, ''), NULLIF(subject.Subject_Name, ''), NULLIF(score.Exam_Type, ''), score.Assessment_Category) as title")
            ->selectRaw('score.Evaluation_Details as details')
            ->selectRaw("NULLIF({$classSnapshot}, '') as class_snapshot_id")
            ->selectRaw('student.Class_ID as current_class_id, student.Batch_ID as current_batch_id')
            ->selectRaw("COALESCE(NULLIF(teacher.Full_Name, ''), NULLIF(creator.Full_Name, ''), score.Created_By) as evaluator")
            ->whereNotNull('score.Score');

        return $this->applyObservationFilters($query, $filters, $date, 'score.Student_ID', 'student.Class_ID', 'student.Batch_ID');
    }

    private function quizQuery(array $filters): Builder
    {
        $date = 'DATE(result.Completed_At)';
        $query = $this->db()->table('QUIZ_RESULT as result')
            ->join('QUIZ as quiz', 'quiz.Quiz_ID', '=', 'result.Quiz_ID')
            ->join('MASTER_STUDENT as student', 'student.Student_ID', '=', 'result.Student_ID')
            ->leftJoin('MASTER_TEACHER as teacher', 'teacher.Teacher_ID', '=', 'result.Teacher_ID')
            ->selectRaw("'QUIZ_RESULT' as source")
            ->selectRaw('result.Result_ID as observation_id, result.Student_ID as student_id')
            ->selectRaw("'KUIS_REGULER' as category")
            ->selectRaw('result.Normalized_Score as score_value')
            ->selectRaw("{$date} as occurred_on")
            ->selectRaw("COALESCE(NULLIF(result.Quiz_Title, ''), quiz.Title) as title")
            ->selectRaw('NULL as details, result.Class_ID as class_snapshot_id')
            ->selectRaw('student.Class_ID as current_class_id, student.Batch_ID as current_batch_id')
            ->selectRaw("COALESCE(NULLIF(teacher.Full_Name, ''), result.Teacher_ID) as evaluator")
            ->whereNotNull('result.Normalized_Score')
            ->whereNotNull('result.Completed_At');

        return $this->applyObservationFilters($query, $filters, $date, 'result.Student_ID', 'student.Class_ID', 'student.Batch_ID');
    }

    private function applyObservationFilters(Builder $query, array $filters, string $date, string $student, string $class, string $batch): Builder
    {
        return $query
            ->when(! empty($filters['from']), fn (Builder $q) => $q->whereRaw("{$date} >= ?", [$filters['from']]))
            ->when(! empty($filters['to']), fn (Builder $q) => $q->whereRaw("{$date} <= ?", [$filters['to']]))
            ->when(! empty($filters['student_id']), fn (Builder $q) => $q->where($student, $filters['student_id']))
            ->when(! empty($filters['class_id']), fn (Builder $q) => $q->where($class, $filters['class_id']))
            ->when(! empty($filters['batch_id']), fn (Builder $q) => $q->where($batch, $filters['batch_id']))
            ->orderByRaw($date)->orderBy('observation_id');
    }

    private function scoreDateExpression(): string
    {
        $assessmentDate = $this->jsonText('score.Evaluation_Details', '$.assessment_date');

        return "COALESCE(NULLIF({$assessmentDate}, ''), DATE(score.Created_At))";
    }

    private function jsonText(string $column, string $path): string
    {
        return $this->db()->getDriverName() === 'sqlite'
            ? "CASE WHEN json_valid({$column}) THEN json_extract({$column}, '{$path}') ELSE NULL END"
            : "CASE WHEN JSON_VALID({$column}) THEN JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')) ELSE NULL END";
    }

    private function active(Builder $query): Builder
    {
        return $query->where(function (Builder $active): void {
            $active->whereNull('Is_Active')->orWhere('Is_Active', 1);
        });
    }

    private function db(): ConnectionInterface
    {
        $connection = DB::connection((string) config('wms_runtime.connection', config('database.default')));
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)
            || ($connection->getDriverName() === 'sqlite' && ! app()->environment('testing'))) {
            throw new RuntimeException('Koneksi Monitoring Akademik tidak diizinkan.');
        }

        return $connection;
    }
}
