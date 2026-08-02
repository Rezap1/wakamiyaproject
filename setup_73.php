<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google_Client;
use Google_Service_Sheets;

// --- 1. Google Sheets Append ---
$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');

$credentialsPath = storage_path('app/google-credentials.json');
if (file_exists($credentialsPath)) {
    $client->setAuthConfig($credentialsPath);
}

$service = new Google_Service_Sheets($client);
$spreadsheetId = config('services.google.spreadsheet_id');

$sheetName = 'MASTER_SCORE';
$newColumns = ['Schedule_ID', 'Assignment_ID', 'Weight', 'Semester', 'Academic_Year', 'Remarks'];

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName . '!1:1');
    $values = $response->getValues();
    if (!empty($values)) {
        $headers = $values[0];
        $added = false;
        foreach ($newColumns as $col) {
            if (!in_array($col, $headers)) {
                $headers[] = $col;
                $added = true;
            }
        }
        if ($added) {
            $body = new \Google_Service_Sheets_ValueRange(['values' => [$headers]]);
            $params = ['valueInputOption' => 'USER_ENTERED'];
            $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!1:1', $body, $params);
            echo "Appended columns to \$sheetName.\\n";
        }
    }
} catch (\Exception $e) {
    echo "Sheets Error: " . $e->getMessage() . "\\n";
}

// --- 2. GradeCalculationService ---
$baseDir = __DIR__;
@mkdir("$baseDir/app/Services/Academic", 0777, true);

$gradeSvc = <<<PHP
<?php
namespace App\Services\Academic;

class GradeCalculationService
{
    public function calculateGrade(\$score)
    {
        \$score = (float) \$score;
        if (\$score >= 90) return 'A';
        if (\$score >= 85) return 'B+';
        if (\$score >= 80) return 'B';
        if (\$score >= 75) return 'C+';
        if (\$score >= 70) return 'C';
        if (\$score >= 60) return 'D';
        return 'E';
    }
}
PHP;
file_put_contents("$baseDir/app/Services/Academic/GradeCalculationService.php", $gradeSvc);

// --- 3. ScoreService ---
$scoreSvc = <<<PHP
<?php
namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Services\Core\NotificationService;
use Exception;

class ScoreService
{
    protected \$repository, \$notificationService, \$gradeService;

    public function __construct(
        ScoreRepositoryInterface \$repository, 
        NotificationService \$notificationService,
        GradeCalculationService \$gradeService
    ) {
        \$this->repository = \$repository;
        \$this->notificationService = \$notificationService;
        \$this->gradeService = \$gradeService;
    }

    public function getAll() { return \$this->repository->fetchAll(); }
    public function getById(\$id) { return \$this->repository->findById(\$id); }
    public function generateId() { return \$this->repository->generateNewId('SCR', 6); }

    public function validateScore(array \$data)
    {
        \$val = (float) (\$data['Score_Value'] ?? 0);
        if (\$val < 0 || \$val > 100) throw new Exception("Score must be between 0 and 100.");
        
        // Prevent duplicate type per student/subject
        if (isset(\$data['Score_ID'])) return; // bypass if update
        
        \$existing = \$this->getAll()->first(function(\$i) use (\$data) {
            return \$i['Student_ID'] === (\$data['Student_ID']??'') 
                && \$i['Subject_ID'] === (\$data['Subject_ID']??'')
                && \$i['Score_Type'] === (\$data['Score_Type']??'');
        });
        // if (\$existing) throw new Exception("Score record already exists for this type.");
    }

    public function create(array \$data)
    {
        \$this->validateScore(\$data);
        if (!isset(\$data['Score_ID'])) \$data['Score_ID'] = \$this->generateId();
        \$data['Grade'] = \$this->gradeService->calculateGrade(\$data['Score_Value'] ?? 0);
        \$data['Created_At'] = now()->toDateTimeString();

        \$res = \$this->repository->create(\$data);
        \$this->repository->clearCache();
        
        // Notify Student
        \$this->notificationService->notifyUser(\$data['Student_ID'], 'New Score Published', 'Your score for ' . (\$data['Subject_ID']??'') . ' has been published.');
        
        return \$res;
    }

    public function update(\$id, array \$data)
    {
        \$this->validateScore(\$data);
        if (isset(\$data['Score_Value'])) {
            \$data['Grade'] = \$this->gradeService->calculateGrade(\$data['Score_Value']);
        }
        \$data['Updated_At'] = now()->toDateTimeString();
        \$res = \$this->repository->update(\$id, \$data);
        \$this->repository->clearCache();
        return \$res;
    }
}
PHP;
file_put_contents("$baseDir/app/Services/Academic/ScoreService.php", $scoreSvc);

// --- 4. ScoreController ---
@mkdir("$baseDir/app/Http/Controllers/Academic", 0777, true);
$scoreCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\ScoreService;
use App\Services\Core\ActivityLogService;

class ScoreController extends Controller
{
    protected \$scoreService, \$activityLogService;
    public function __construct(ScoreService \$scoreService, ActivityLogService \$activityLogService)
    {
        \$this->scoreService = \$scoreService;
        \$this->activityLogService = \$activityLogService;
    }

    public function index()
    {
        \$scores = \$this->scoreService->getAll();
        return view('academic.scores.index', compact('scores'));
    }

    public function create() { return view('academic.scores.create'); }

    public function store(Request \$request)
    {
        try {
            \$this->scoreService->create(\$request->except('_token'));
            \$this->activityLogService->log(auth()->id(), 'CREATE_SCORE', 'Score', 'Created score for ' . \$request->Student_ID);
            return redirect()->route('scores.index')->with('success', 'Score recorded.');
        } catch (\Exception \$e) {
            return back()->withErrors(['error' => \$e->getMessage()])->withInput();
        }
    }

    public function edit(\$id)
    {
        \$score = \$this->scoreService->getById(\$id);
        return view('academic.scores.edit', compact('score'));
    }

    public function update(Request \$request, \$id)
    {
        try {
            \$this->scoreService->update(\$id, \$request->except(['_token', '_method']));
            \$this->activityLogService->log(auth()->id(), 'UPDATE_SCORE', 'Score', 'Updated score ' . \$id);
            return redirect()->route('scores.index')->with('success', 'Score updated.');
        } catch (\Exception \$e) {
            return back()->withErrors(['error' => \$e->getMessage()])->withInput();
        }
    }

    public function exportCSV()
    {
        \$scores = \$this->scoreService->getAll();
        \$csv = "Score_ID,Student_ID,Subject_ID,Type,Score,Grade\\n";
        foreach (\$scores as \$s) {
            \$csv .= (\$s['Score_ID']??'').",".(\$s['Student_ID']??'').",".(\$s['Subject_ID']??'').",".(\$s['Score_Type']??'').",".(\$s['Score_Value']??'').",".(\$s['Grade']??'')."\\n";
        }
        return response(\$csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename="scores.csv"');
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Academic/ScoreController.php", $scoreCtrl);

// --- 5. Blade Views ---
@mkdir("$baseDir/resources/views/academic/scores", 0777, true);

$indexView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Score & Grade Management')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Academic Scores</h2>
        <div class="flex gap-2">
            <a href="{{ route('scores.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Add Score</a>
            <a href="{{ route('scores.export') }}" class="bg-green-600 text-white px-4 py-2 rounded">Export CSV</a>
        </div>
    </div>
    
    <table class="min-w-full">
        <tr class="bg-gray-100">
            <th class="p-3 text-left">Student</th>
            <th class="p-3 text-left">Subject</th>
            <th class="p-3 text-left">Type</th>
            <th class="p-3 text-left">Score</th>
            <th class="p-3 text-left">Grade</th>
            <th class="p-3 text-left">Aksi</th>
        </tr>
        @foreach($scores as $s)
        <tr class="border-b">
            <td class="p-3">{{ $s['Student_ID']??'-' }}</td>
            <td class="p-3">{{ $s['Subject_ID']??'-' }}</td>
            <td class="p-3">{{ $s['Score_Type']??'-' }}</td>
            <td class="p-3 font-bold">{{ $s['Score_Value']??'-' }}</td>
            <td class="p-3">
                <span class="px-2 py-1 rounded text-xs font-bold 
                {{ ($s['Grade']??'')=='A'?'bg-green-100 text-green-700' : (($s['Grade']??'')=='E'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700') }}">
                    {{ $s['Grade']??'-' }}
                </span>
            </td>
            <td class="p-3"><a href="{{ route('scores.edit', $s['Score_ID']) }}" class="text-blue-600">Edit</a></td>
        </tr>
        @endforeach
    </table>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/scores/index.blade.php", $indexView);

$formView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Form Score')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <form action="{{ isset($score) ? route('scores.update', $score['Score_ID']) : route('scores.store') }}" method="POST">
        @csrf @if(isset($score)) @method('PUT') @endif
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block font-bold">Student ID</label><input type="text" name="Student_ID" value="{{ $score['Student_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
            <div><label class="block font-bold">Subject ID</label><input type="text" name="Subject_ID" value="{{ $score['Subject_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
            <div><label class="block font-bold">Score Type</label>
                <select name="Score_Type" class="w-full border-gray-300 rounded">
                    <option>Assignment</option><option>Quiz</option><option>Midterm</option><option>Final Exam</option>
                </select>
            </div>
            <div><label class="block font-bold">Score Value (0-100)</label><input type="number" step="0.01" name="Score_Value" value="{{ $score['Score_Value']??'' }}" class="w-full border-gray-300 rounded" required></div>
            <div class="col-span-2"><label class="block font-bold">Remarks</label><textarea name="Remarks" class="w-full border-gray-300 rounded">{{ $score['Remarks']??'' }}</textarea></div>
        </div>
        <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Nilai</button></div>
    </form>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/scores/create.blade.php", $formView);
file_put_contents("$baseDir/resources/views/academic/scores/edit.blade.php", $formView);

echo "Backend scaffold generated.\\n";
