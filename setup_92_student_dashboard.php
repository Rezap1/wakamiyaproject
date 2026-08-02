<?php
$ctrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\ScoreService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StudentDashboardController extends Controller
{
    protected $scoreService, $invoiceService, $paymentService;

    public function __construct(
        ScoreService $scoreService,
        InvoiceService $invoiceService,
        PaymentService $paymentService
    ) {
        $this->scoreService = $scoreService;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $studentId = auth()->user()->id ?? 'STU-001';
        
        $data = Cache::remember('student_dashboard_' . $studentId, 60, function() use ($studentId) {
            // Academic Data
            $allScores = $this->scoreService->getAll();
            $myScores = $allScores->filter(function($s) use ($studentId) {
                return ($s['Student_ID'] ?? '') == $studentId;
            });

            $langProgress = 0;
            $jftScore = $myScores->firstWhere('Assessment_ID', 'JFT-001');
            if ($jftScore) {
                $langProgress = ($jftScore['Score_Value'] ?? 0) >= config('assessment.passing_score', 65) ? 100 : 50;
            }

            $internals = $myScores->map(function($s) {
                return [
                    'name' => $s['Assessment_ID'],
                    'status' => $s['Status'] ?? 'Completed',
                    'score' => $s['Score_Value'],
                    'color' => ($s['Status'] ?? '') == 'PASS' ? 'emerald' : 'red'
                ];
            })->take(4)->toArray();

            // Finance Data
            $allInvoices = $this->invoiceService->getAll();
            $myInvoices = $allInvoices->filter(function($inv) use ($studentId) {
                return ($inv['Student_ID'] ?? '') == $studentId;
            });
            
            $outstandingBills = $myInvoices->where('Status', 'Waiting Payment');
            $totalOutstanding = $outstandingBills->sum('Amount');
            $latestInvoice = $outstandingBills->sortByDesc('Created_At')->first();

            return compact('myScores', 'langProgress', 'internals', 'totalOutstanding', 'latestInvoice', 'outstandingBills');
        });

        return view('dashboard.student', $data);
    }
}
EOT;
file_put_contents('app/Http/Controllers/Core/StudentDashboardController.php', $ctrl);

$bladeContent = file_get_contents('resources/views/dashboard/student.blade.php');
// We need to inject the Finance widget before "Official Examination Center"
$financeWidget = <<<'EOT'
    <!-- Finance & Billing Widget -->
    @if($totalOutstanding > 0)
    <div class="bg-rose-50 border border-rose-200 p-6 rounded-2xl shadow-sm mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-800">Outstanding Bills ({{ $outstandingBills->count() }})</h4>
                <p class="text-2xl font-black text-rose-600">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
                @if($latestInvoice)
                    <p class="text-xs text-rose-500 font-medium mt-1">Due Date: {{ $latestInvoice['Due_Date'] ?? '-' }}</p>
                @endif
            </div>
        </div>
        <a href="{{ route('student.billing.index') }}" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm rounded-xl transition-colors shadow-sm text-center shrink-0">
            Pay Now
        </a>
    </div>
    @endif
EOT;

$searchStr = '<!-- Official Examination Center -->';
if (strpos($bladeContent, '<!-- Finance & Billing Widget -->') === false) {
    $bladeContent = str_replace($searchStr, $financeWidget . "\n\n    " . $searchStr, $bladeContent);
    file_put_contents('resources/views/dashboard/student.blade.php', $bladeContent);
}

echo "Student Dashboard updated.\n";
?>
