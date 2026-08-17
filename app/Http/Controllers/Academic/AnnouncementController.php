<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AnnouncementService;
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
            'headers' => ['Title', 'Category', 'Target Audience', 'Status', 'Created At'],
            'mapRow' => function($row) {
                return [
                    $row['Title'] ?? '-',
                    $row['Category'] ?? '-',
                    $row['Target_Audience'] ?? 'ALL',
                    $row['Status'] ?? 'PUBLISHED',
                    $row['Created_At'] ?? '-'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Pengumuman</td><td>: '.$announcements->count().'</td></tr>'
        ];
    }

    protected $announcementService;

    public function __construct(AnnouncementService $announcementService)
    {
        $this->announcementService = $announcementService;
    }

    public function index(Request $request)
    {
        $announcements = $this->announcementService->getAll();
        return view('academic.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('academic.announcements.create');
    }

    public function store(\App\Http\Requests\StoreAnnouncementRequest $request)
    {
        try {
            $data = $request->except('_token');
            $this->announcementService->create($data);
            return redirect()->route('announcements.index')->with('success', 'Announcement created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (!$announcement) return redirect()->route('announcements.index')->withErrors(['error' => 'Not found']);
        return view('academic.announcements.edit', compact('announcement'));
    }

    public function edit($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (!$announcement) return redirect()->route('announcements.index')->withErrors(['error' => 'Not found']);
        return view('academic.announcements.edit', compact('announcement'));
    }

    public function update(\App\Http\Requests\UpdateAnnouncementRequest $request, $id)
    {
        try {
            $data = $request->except(['_token', '_method']);
            $this->announcementService->update($id, $data);
            return redirect()->route('announcements.index')->with('success', 'Announcement updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->announcementService->delete($id);
            return redirect()->route('announcements.index')->with('success', 'Announcement deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
