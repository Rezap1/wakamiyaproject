<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AnnouncementService;
use App\Services\Core\ClassService;
use App\Services\Core\ActivityLogService;

class AnnouncementController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {
        $announcements = $this->announcementService->getAll();
        
        return [
            'moduleName' => 'Pengumuman Akademik',
            'data' => collect(array_values($announcements->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Judul', 'Target Penerima', 'Prioritas', 'Status', 'Dibuat Pada'],
            'mapRow' => function($row) {
                return [
                    $row['Title'] ?? '-',
                    $this->announcementService->audienceLabel((array) $row),
                    $this->announcementService->priorityLabel($row['Priority'] ?? 'NORMAL'),
                    $this->announcementService->presentationStatus((array) $row),
                    $row['Created_At'] ?? '-'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Pengumuman</td><td>: '.$announcements->count().'</td></tr>'
        ];
    }

    protected $announcementService;
    protected $classService;

    public function __construct(AnnouncementService $announcementService, ?ClassService $classService = null)
    {
        $this->announcementService = $announcementService;
        $this->classService = $classService;
    }

    public function index(Request $request)
    {
        $announcements = $this->announcementService->getAll()->map(function ($announcement) {
            $announcement['Audience_Label'] = $this->announcementService->audienceLabel((array) $announcement);
            $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
            $announcement['Status_Label'] = $this->announcementService->presentationStatus((array) $announcement);
            $announcement['Start_Label'] = $this->formatDate($this->announcementService->startAt((array) $announcement));
            $announcement['Expiry_Label'] = $this->formatDate($this->announcementService->expiresAt((array) $announcement));
            return $announcement;
        });
        return view('academic.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('academic.announcements.create', ['classes' => $this->classes()]);
    }

    public function store(\App\Http\Requests\StoreAnnouncementRequest $request)
    {
        try {
            $data = $request->validated();
            $this->announcementService->create($data);
            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (!$announcement) return redirect()->route('announcements.index')->withErrors(['error' => 'Pengumuman tidak ditemukan.']);
        $announcement['Audience_Label'] = $this->announcementService->audienceLabel((array) $announcement);
        $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
        $announcement['Status_Label'] = $this->announcementService->presentationStatus((array) $announcement);
        $announcement['Start_Label'] = $this->formatDate($this->announcementService->startAt((array) $announcement));
        $announcement['Expiry_Label'] = $this->formatDate($this->announcementService->expiresAt((array) $announcement));
        return view('academic.announcements.show', compact('announcement'));
    }

    public function edit($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (!$announcement) return redirect()->route('announcements.index')->withErrors(['error' => 'Pengumuman tidak ditemukan.']);
        return view('academic.announcements.edit', ['announcement' => $announcement, 'classes' => $this->classes()]);
    }

    public function update(\App\Http\Requests\UpdateAnnouncementRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $this->announcementService->update($id, $data);
            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->announcementService->delete($id);
            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function deactivate($id)
    {
        try {
            $this->announcementService->delete($id);
            return redirect()->route('announcements.index')->with('success', 'Pengumuman dinonaktifkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    protected function classes()
    {
        try {
            if ($this->classService) return collect($this->classService->getAllClasses())->filter(fn ($class) => strtoupper((string) ($class['Is_Active'] ?? 'TRUE')) !== 'FALSE')->values();
        } catch (\Throwable) {
        }
        return collect();
    }

    protected function formatDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i') . ' WIB' : '-';
    }
}
