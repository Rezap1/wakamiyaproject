<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AnnouncementService;
use App\Services\Core\ActivityLogService;

class AnnouncementController extends Controller
{
    protected $announcementService;
    protected $activityLogService;

    public function __construct(AnnouncementService $announcementService, ActivityLogService $activityLogService)
    {
        $this->announcementService = $announcementService;
        $this->activityLogService = $activityLogService;
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
            $this->activityLogService->log(auth()->id(), 'CREATED', 'Master Announcement', 'Added new announcement: ' . $request->Title);
            return redirect()->route('announcements.index')->with('success', 'Announcement created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
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
            $this->activityLogService->log(auth()->id(), 'UPDATED', 'Master Announcement', 'Updated announcement ' . $id);
            return redirect()->route('announcements.index')->with('success', 'Announcement updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->announcementService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETED', 'Master Announcement', 'Deleted announcement ' . $id);
            return redirect()->route('announcements.index')->with('success', 'Announcement deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
