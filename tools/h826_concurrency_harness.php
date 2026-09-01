<?php

/**
 * H8.26 local multi-process harness.  It exercises the actual PaymentService
 * with file-backed test repositories (never Google Sheets) so no production
 * data is mutated.  The result is evidence for the application lock/idempotency
 * workflow only; it cannot prove Google Sheets transactional semantics.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

$args = $argv;
$isWorker = (($args[1] ?? '') === '--worker');
if (!$isWorker) {

$root = storage_path('app');
$state = $root . DIRECTORY_SEPARATOR . 'h826-race-' . bin2hex(random_bytes(8)) . '.json';
file_put_contents($state, json_encode(['payments' => [], 'transactions' => []], JSON_THROW_ON_ERROR));
$php = PHP_BINARY;
$script = __FILE__;
$commands = [
    [$php, $script, '--worker', $state, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'submit'],
    [$php, $script, '--worker', $state, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'submit'],
];
$outputs = h826Spawn($commands);
$stateData = json_decode((string) file_get_contents($state), true, 512, JSON_THROW_ON_ERROR);
$rows = $stateData['payments'] ?? [];
$uniqueIds = array_values(array_unique(array_map(fn ($r) => (string) ($r['Payment_ID'] ?? ''), $rows)));
echo "=== H8.26 LOCAL MULTI-PROCESS PAYMENT HARNESS ===\n";
echo "Production mutation: NONE (file-backed isolated state)\n";
echo "Workers: 2 same idempotency key + same payload\n";
echo "Worker exit codes: " . implode(', ', array_column($outputs, 'exit')) . "\n";
foreach ($outputs as $index => $output) {
    if (trim($output['stdout']) !== '') echo "worker {$index} stdout: " . trim($output['stdout']) . "\n";
    if (trim($output['stderr']) !== '') echo "worker {$index} stderr: " . trim($output['stderr']) . "\n";
}
echo "Persisted payment rows: " . count($rows) . "\n";
echo "Unique Payment_ID values: " . count(array_filter($uniqueIds)) . "\n";
echo count($rows) === 1 && count(array_filter($uniqueIds)) === 1
    ? "RESULT: PASS — one logical payment persisted\n"
    : "RESULT: FAIL — duplicate or missing payment persisted\n";
if ($rows !== []) {
    $paymentId = (string) ($rows[0]['Payment_ID'] ?? '');
    $verifyCommands = [
        [$php, $script, '--worker', $state, $paymentId, 'verify'],
        [$php, $script, '--worker', $state, $paymentId, 'verify'],
    ];
    $verifyOutputs = h826Spawn($verifyCommands);
    $stateData = json_decode((string) file_get_contents($state), true, 512, JSON_THROW_ON_ERROR);
    $verified = collect($stateData['payments'] ?? [])->firstWhere('Payment_ID', $paymentId);
    $ledger = array_values(array_filter($stateData['transactions'] ?? [], fn ($r) => ($r['Reference_ID'] ?? '') === $paymentId));
    echo "Verify worker exit codes: " . implode(', ', array_column($verifyOutputs, 'exit')) . "\n";
    foreach ($verifyOutputs as $index => $output) {
        if (trim($output['stdout']) !== '') echo "verify worker {$index} stdout: " . trim($output['stdout']) . "\n";
        if (trim($output['stderr']) !== '') echo "verify worker {$index} stderr: " . trim($output['stderr']) . "\n";
    }
    echo "Verified payment status: " . ($verified['Status'] ?? 'MISSING') . "\n";
    echo "Payment ledger rows after two verify requests: " . count($ledger) . "\n";
    echo (($verified['Status'] ?? '') === 'Verified' && count($ledger) === 1)
        ? "VERIFY RESULT: PASS — one deterministic ledger persisted\n"
        : "VERIFY RESULT: FAIL — verification race produced missing/duplicate ledger\n";
}
@unlink($state);
echo "LIMITATION: this proves application/file-lock behavior only; Google Sheets transactional concurrency remains NOT PROVEN.\n";
}

function h826Worker(string $state, string $key, string $mode): void
{
    $repo = new H826FilePaymentRepository($state);
    $student = new H826StudentRepository();
    $events = Mockery::mock(EnterpriseEventService::class);
    $events->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturnTrue();
    Auth::login(new GenericUser([
        'id' => $mode === 'verify' ? 'USR-FIN' : 'USR-STU',
        'User_ID' => $mode === 'verify' ? 'USR-FIN' : 'USR-STU',
        'Role' => $mode === 'verify' ? 'FINANCE' : 'STUDENT',
    ]));
    $transactionService = Mockery::mock(TransactionService::class);
    if ($mode === 'verify') {
        $transactionService->shouldReceive('create')->zeroOrMoreTimes()->andReturnUsing(function (array $data) use ($state) {
            h826StateUpdate($state, function (&$snapshot) use ($data) {
                $identity = (string) ($data['Transaction_ID'] ?? '');
                foreach ($snapshot['transactions'] as $row) {
                    if (($row['Transaction_ID'] ?? '') === $identity) return;
                }
                $snapshot['transactions'][] = $data;
            });
            return $data;
        });
    }
    $service = new PaymentService(
        $repo, new H826InvoiceRepository(), $student, new H826CompanyRepository(),
        new H826AccountRepository(), new H826TransactionRepository($state), $events,
        $transactionService
    );
    try {
        if ($mode === 'verify') {
            $service->verifyPayment($key, 'spoofed-verifier', 'Verified');
            echo "worker verify: ok\n";
            return;
        }
        $service->submitPayment([
            'Self_Service' => true,
            'Amount_Paid' => 250,
            'Payment_Method' => 'CASH',
            'Sender_Name' => 'Student A',
            'Transfer_Date' => '2026-09-01',
            'Idempotency_Key' => $key,
        ]);
        echo "worker submit: ok\n";
    } catch (Throwable $e) {
        echo "worker {$mode}: " . get_class($e) . ': ' . $e->getMessage() . "\n";
        exit(1);
    }
}

function h826Spawn(array $commands): array
{
    $processes = [];
    foreach ($commands as $command) {
        $escaped = implode(' ', array_map('escapeshellarg', $command));
        $pipes = [];
        $processes[] = proc_open($escaped, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $processes[array_key_last($processes) . '_pipes'] = $pipes;
    }
    $outputs = [];
    foreach ($processes as $key => $process) {
        if (!is_int($key)) continue;
        $pipes = $processes[$key . '_pipes'];
        $outputs[] = ['stdout' => stream_get_contents($pipes[1]), 'stderr' => stream_get_contents($pipes[2]), 'exit' => proc_close($process)];
    }
    return $outputs;
}

function h826StateRead(string $path): array
{
    $handle = fopen($path, 'c+');
    flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $contents !== '' ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : ['payments' => [], 'transactions' => []];
}

function h826StateUpdate(string $path, callable $mutator): void
{
    $handle = fopen($path, 'c+');
    flock($handle, LOCK_EX);
    rewind($handle);
    $contents = stream_get_contents($handle);
    $state = $contents !== '' ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : ['payments' => [], 'transactions' => []];
    $mutator($state);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

class H826FilePaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(private string $path) {}
    public function getAll() { return collect(h826StateRead($this->path)['payments'] ?? []); }
    public function getAllFresh() { return $this->getAll(); }
    public function getById($id) { return $this->getAll()->firstWhere('Payment_ID', $id); }
    public function getByIdFresh($id) { return $this->getById($id); }
    public function create(array $data) {
        $duplicate = false;
        h826StateUpdate($this->path, function (&$state) use ($data, &$duplicate) {
            foreach ($state['payments'] as $row) if (($row['Payment_ID'] ?? '') === ($data['Payment_ID'] ?? '')) $duplicate = true;
            if (!$duplicate) $state['payments'][] = $data;
        });
        if ($duplicate) throw new DuplicatePrimaryKeyException('duplicate payment id');
        return $data;
    }
    public function update($id, array $data) { h826StateUpdate($this->path, function (&$state) use ($id, $data) { foreach ($state['payments'] as &$row) if (($row['Payment_ID'] ?? '') === $id) $row = array_merge($row, $data); }); return true; }
    public function delete($id) { return false; }
    public function clearCache() {}
}

class H826StudentRepository implements StudentRepositoryInterface
{
    public function fetchAll() { return collect([['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU', 'Is_Active' => 'TRUE']]); }
    public function findById(string $id) { return $id === 'STU-1' ? $this->fetchAll()->first() : null; }
    public function findByStudentNumber(string $number) { return null; }
    public function findByNationalId(string $nationalId) { return null; }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix . '-1'; }
    public function create(array $data) { return true; }
    public function update(string $id, array $data) { return true; }
    public function softDelete(string $id) { return true; }
    public function clearCache() {}
}
class H826InvoiceRepository implements InvoiceRepositoryInterface { public function getAll(){return collect();} public function getById($id){return null;} public function create(array $data){return true;} public function update($id,array $data){return true;} public function delete($id){return true;} }
class H826CompanyRepository implements CompanyRepositoryInterface { public function fetchAll(){return collect();} public function findById(string $id){return null;} public function findByCode(string $code){return null;} public function generateNewId(string $prefix,int $padding=6): string{return $prefix.'-1';} public function create(array $data){return true;} public function update(string $id,array $data){return true;} public function softDelete(string $id){return true;} }
class H826AccountRepository implements AccountRepositoryInterface { public function fetchAll(){return collect([['Account_ID'=>'ACC-1','Account_Code'=>'101','Account_Name'=>'Kas','Account_Category'=>'ASSET','Is_Active'=>'TRUE']]);} public function findById(string $id){return $id==='ACC-1'?$this->fetchAll()->first():null;} public function create(array $data){return true;} public function update(string $id,array $data){return true;} public function delete(string $id){return true;} public function generateNewId(string $prefix='ACC',int $padding=6): string{return $prefix.'-1';} }
class H826TransactionRepository implements TransactionRepositoryInterface { public function __construct(private string $path){} public function fetchAll(){return collect(h826StateRead($this->path)['transactions']??[]);} public function findById(string $id){return $this->fetchAll()->firstWhere('Transaction_ID',$id);} public function create(array $data){h826StateUpdate($this->path,function(&$s)use($data){$s['transactions'][]=$data;});return $data;} public function update(string $id,array $data){return true;} public function delete(string $id){return true;} public function generateNewId(string $prefix,int $padding=6): string{return $prefix.'-1';} }

if ($isWorker) {
    h826Worker($args[2] ?? '', $args[3] ?? '', $args[4] ?? 'submit');
    exit(0);
}
