<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Helpers\UserResolverHelper;
use App\Helpers\TerbilangHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SmartGeneratorController extends Controller
{
    protected $settingService;
    protected $invoiceService;

    public function __construct(SystemSettingService $settingService, InvoiceService $invoiceService)
    {
        $this->settingService = $settingService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $company = $this->settingService->getCompanyProfile();
        $history = $this->getHistory();

        return view('finance.smart_generator', compact('company', 'history'));
    }

    public function searchStudentInvoices(Request $request)
    {
        $query = trim($request->input('q', ''));
        $studentRepo = app(StudentRepositoryInterface::class);
        $userRepo = app(UserRepositoryInterface::class);

        $allInvoices = $this->invoiceService->getAll();
        $students = collect($studentRepo->fetchAll());
        $users = collect($userRepo->fetchAll());

        $results = [];

        foreach ($allInvoices as $inv) {
            $studentId = $inv['Student_ID'] ?? '';
            $invType = strtoupper($inv['Invoice_Type'] ?? 'STUDENT');

            // Only include Student invoices
            if (empty($studentId) && $invType !== 'STUDENT') {
                continue;
            }

            $student = $students->firstWhere('Student_ID', $studentId);
            $studentName = $student['Full_Name'] ?? UserResolverHelper::getName($studentId);
            if ($studentName === '-' || empty($studentName)) {
                $studentName = $studentId ?: 'Siswa';
            }

            // Find email
            $studentEmail = $student['Email'] ?? '';
            if (empty($studentEmail) && !empty($student['User_ID'])) {
                $userRec = $users->firstWhere('User_ID', $student['User_ID']);
                $studentEmail = $userRec['Email'] ?? '';
            }

            $docNumber = $inv['Invoice_ID'] ?? ($inv['Invoice_Number'] ?? '');
            $description = $inv['Description'] ?? ($inv['Category'] ?? 'Tagihan Siswa');
            $grandTotal = (float)($inv['Grand_Total'] ?? ($inv['Amount'] ?? 0));
            $status = strtoupper($inv['Display_Status'] ?? ($inv['Status'] ?? 'UNPAID'));
            $issueDate = $inv['Invoice_Date'] ?? date('Y-m-d');
            $dueDate = $inv['Due_Date'] ?? date('Y-m-d', strtotime('+14 days'));
            $address = $student['Address'] ?? '';

            $parsedItems = $inv['Parsed_Line_Items'] ?? [];
            $items = [];
            if (!empty($parsedItems) && is_array($parsedItems)) {
                foreach ($parsedItems as $pi) {
                    $items[] = [
                        'name' => $pi['description'] ?? $description,
                        'qty' => (float)($pi['qty'] ?? 1),
                        'price' => (float)($pi['unit_price'] ?? 0),
                        'total' => (float)($pi['subtotal'] ?? 0)
                    ];
                }
            }

            if (empty($items)) {
                $items[] = [
                    'name' => $description,
                    'qty' => 1,
                    'price' => $grandTotal,
                    'total' => $grandTotal
                ];
            }

            // Filter by search query if provided
            if (!empty($query)) {
                $searchableText = strtolower("{$docNumber} {$studentId} {$studentName} {$studentEmail} {$description}");
                if (!str_contains($searchableText, strtolower($query))) {
                    continue;
                }
            }

            $results[] = [
                'id' => $docNumber,
                'doc_number' => $docNumber,
                'student_id' => $studentId,
                'student_name' => $studentName,
                'student_email' => $studentEmail,
                'student_address' => $address,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => $status,
                'amount' => $grandTotal,
                'subtotal' => (float)($inv['Subtotal_Amount'] ?? $grandTotal),
                'discount' => (float)($inv['Total_Discount'] ?? 0),
                'ppn_amount' => (float)($inv['Total_Tax'] ?? 0),
                'grand_total' => $grandTotal,
                'description' => $description,
                'items' => $items
            ];
        }

        return response()->json([
            'success' => true,
            'invoices' => array_values($results)
        ]);
    }

    public function exportPdf(Request $request)
    {
        $docType = $request->input('doc_type', 'invoice');
        $data = $this->prepareDocumentData($request);

        $viewName = ($docType === 'kwitansi') 
            ? 'pdf.smart_generator_kwitansi' 
            : 'pdf.smart_generator_invoice';

        $filename = ($docType === 'kwitansi' ? 'Kwitansi_' : 'Invoice_') . ($data['doc_number'] ?? time()) . '.pdf';

        $pdf = Pdf::loadView($viewName, ['data' => $data]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->getDomPDF()->getOptions()->set([
            'defaultFont' => 'Helvetica',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ]);

        return $pdf->download($filename);
    }

    public function saveHistory(Request $request)
    {
        $data = $this->prepareDocumentData($request);
        $data['id'] = $request->input('id', 'HIST-' . date('YmdHis') . '-' . Str::random(4));
        $data['saved_at'] = now()->format('d M Y, H:i');

        $history = $this->getHistory();
        
        // Check if updating existing record
        $existingIndex = null;
        foreach ($history as $index => $item) {
            if (isset($item['doc_number']) && $item['doc_number'] === $data['doc_number']) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $history[$existingIndex] = $data;
        } else {
            array_unshift($history, $data);
        }

        $this->saveHistoryFile($history);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen ' . ($data['doc_number'] ?? '') . ' berhasil disimpan ke riwayat.',
            'history' => $history
        ]);
    }

    public function getHistoryApi()
    {
        return response()->json([
            'success' => true,
            'history' => $this->getHistory()
        ]);
    }

    public function deleteHistory($id)
    {
        $history = $this->getHistory();
        $filtered = array_values(array_filter($history, function($item) use ($id) {
            return ($item['id'] ?? '') !== $id && ($item['doc_number'] ?? '') !== $id;
        }));

        $this->saveHistoryFile($filtered);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat dokumen berhasil dihapus.',
            'history' => $filtered
        ]);
    }

    public function sendEmail(Request $request)
    {
        $clientEmail = trim($request->input('client_email', ''));
        $docNumber = $request->input('doc_number');
        $sourceType = $request->input('source_type', 'manual_invoice');
        $sourceId = $request->input('source_id');
        $studentId = $request->input('student_id');

        // For student invoices, if email is empty, try to resolve from Student/User record
        if ($sourceType === 'student_invoice' && empty($clientEmail) && (!empty($sourceId) || !empty($studentId))) {
            try {
                $studentRepo = app(StudentRepositoryInterface::class);
                $userRepo = app(UserRepositoryInterface::class);
                $sId = $studentId;
                if (empty($sId) && !empty($sourceId)) {
                    $inv = $this->invoiceService->getById($sourceId);
                    $sId = $inv['Student_ID'] ?? '';
                }
                if (!empty($sId)) {
                    $student = $studentRepo->findById($sId);
                    $clientEmail = $student['Email'] ?? '';
                    if (empty($clientEmail) && !empty($student['User_ID'])) {
                        $userRec = $userRepo->findById($student['User_ID']);
                        $clientEmail = $userRec['Email'] ?? '';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore error
            }
        }

        if (empty($clientEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Email siswa tidak tersedia.'
            ], 422);
        }

        $payload = $this->prepareDocumentData($request);
        $payload['client_email'] = $clientEmail;

        $emailDeliveryService = app(\App\Services\Core\EmailDeliveryService::class);
        $result = $emailDeliveryService->sendDocumentEmail($payload);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }

    private function prepareDocumentData(Request $request)
    {
        $items = $request->input('items', []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        $subtotal = 0;
        $formattedItems = [];
        foreach ($items as $item) {
            $qty = (float)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;
            $formattedItems[] = [
                'name' => $item['name'] ?? 'Item Layanan / Produk',
                'qty' => $qty,
                'price' => $price,
                'total' => $total
            ];
        }

        $discount = (float)$request->input('discount', 0);
        $ppnPercent = (float)$request->input('ppn_percent', 0);
        $shipping = (float)$request->input('shipping', 0);

        $afterDiscount = max(0, $subtotal - $discount);
        $ppnAmount = ($afterDiscount * $ppnPercent) / 100;
        $grandTotal = $afterDiscount + $ppnAmount + $shipping;

        // Convert base64 / path images for PDF compatibility
        $logo = $request->input('company_logo');
        $signature = $request->input('signature');
        $stamp = $request->input('stamp');

        $logoBase64 = $this->encodeImageToBase64($logo);
        $signatureBase64 = $this->encodeImageToBase64($signature);
        $stampBase64 = $this->encodeImageToBase64($stamp);

        $kwitansiAmount = (float)$request->input('kwitansi_amount', $grandTotal > 0 ? $grandTotal : $subtotal);

        return [
            'source_type' => $request->input('source_type', 'manual_invoice'),
            'source_id' => $request->input('source_id', null),
            'student_id' => $request->input('student_id', null),

            'doc_type' => $request->input('doc_type', 'invoice'),
            'theme' => $request->input('theme', 'emerald'),
            'doc_number' => $request->input('doc_number', 'INV-WMS-' . rand(1000, 9999)),
            'currency' => $request->input('currency', 'IDR'),
            'issue_date' => $request->input('issue_date', date('Y-m-d')),
            'due_date' => $request->input('due_date', date('Y-m-d', strtotime('+14 days'))),
            'status' => $request->input('status', 'UNPAID'),
            
            // Client info
            'client_name' => $request->input('client_name', 'Rifai Sholikhin'),
            'client_email' => $request->input('client_email', 'rifai@example.com'),
            'client_address' => $request->input('client_address', 'Ds. Sukareja Blok.Karanganyar RT.07/RW 03 Kec.Balongan Kab.Indramayu'),

            // Company Profile Info
            'company_name' => $request->input('company_name', 'PT WAKAMIYA MANDIRI SEJAHTERA'),
            'company_tagline' => $request->input('company_tagline', 'Growing Together With Integrity'),
            'company_address' => $request->input('company_address', 'Perum Graha Samolo Indah Blok B1 No 22 Desa Babakan Caringin, Karang Tengah,Cianjur'),
            'company_phone' => $request->input('company_phone', '0813-1811-5151'),
            'company_email' => $request->input('company_email', 'lpkwakamiya01@gmail.com'),
            'company_web' => $request->input('company_web', 'www.wakamiya.com'),
            'company_npwp' => $request->input('company_npwp', '1000000003150626'),
            'layout_kop' => $request->input('layout_kop', 'left'),

            // Bank Info
            'bank_name' => $request->input('bank_name', 'Bank Syariah Indonesia (BSI)'),
            'bank_account' => $request->input('bank_account', '7343551023'),
            'bank_holder' => $request->input('bank_holder', 'PT WAKAMIYA MANDIRI SEJAHTERA'),

            // Items & Totals
            'items' => $formattedItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'shipping' => $shipping,
            'grand_total' => $grandTotal,

            // Kwitansi specific
            'kwitansi_amount' => $kwitansiAmount,
            'terbilang' => TerbilangHelper::convert($kwitansiAmount),
            'payment_for' => $request->input('payment_for', 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang'),
            'issue_city' => $request->input('issue_city', 'Cianjur'),
            'signer_name' => $request->input('signer_name', 'Helmi Maulana'),

            // Terms
            'notes' => $request->input('notes', 'Pembayaran via Transfer BSI 7343551023 a.n PT Wakamiya Mandiri Sejahtera.'),

            // Images
            'company_logo' => $logoBase64,
            'signature' => $signatureBase64,
            'stamp' => $stampBase64
        ];
    }

    private function encodeImageToBase64($pathOrBase64)
    {
        if (empty($pathOrBase64)) {
            return null;
        }

        if (str_starts_with($pathOrBase64, 'data:image')) {
            return $pathOrBase64;
        }

        $fullPath = public_path($pathOrBase64);
        if (file_exists($fullPath)) {
            $type = pathinfo($fullPath, PATHINFO_EXTENSION);
            $data = file_get_contents($fullPath);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return $pathOrBase64;
    }

    private function getHistory()
    {
        $filePath = storage_path('app/smart_generator_history.json');
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            return json_decode($json, true) ?: [];
        }
        return [];
    }

    private function saveHistoryFile(array $history)
    {
        $filePath = storage_path('app/smart_generator_history.json');
        file_put_contents($filePath, json_encode($history, JSON_PRETTY_PRINT));
    }
}
