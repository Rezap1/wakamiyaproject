<?php
namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;

class GlobalSearchService
{
    // Inject repositories as needed, for now we will simulate them or inject later
    // using app() helper to avoid massive constructor dependencies

    public function search($keyword, $role, $userId)
    {
        if (empty(trim($keyword))) return [];
        
        $keyword = strtolower(trim($keyword));
        $cacheKey = "wms_search_{$role}_{$userId}_" . md5($keyword);
        
        return Cache::remember($cacheKey, 60, function () use ($keyword, $role, $userId) {
            $results = [
                'Students' => [],
                'Teachers' => [],
                'Subjects' => [],
                'Announcements' => [],
            ];
            
            // Progressive Search Logic based on Role
            
            // 1. Search Announcements (All roles can see announcements, usually)
            try {
                $announcementRepo = app(\App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface::class);
                $announcements = collect($announcementRepo->fetchAll())->filter(function($a) use ($keyword) {
                    return str_contains(strtolower($a['Title'] ?? ''), $keyword) || str_contains(strtolower($a['Content'] ?? ''), $keyword);
                })->take(5);
                foreach ($announcements as $a) {
                    $results['Announcements'][] = [
                        'title' => $a['Title'] ?? 'Announcement',
                        'desc' => substr($a['Content'] ?? '', 0, 50) . '...',
                        'url' => route('announcements.index')
                    ];
                }
            } catch (\Exception $e) {
                // Ignore if not setup
            }

            // 2. Search Students (Admin, HR, Academic, Teacher)
            if (in_array($role, ['ADMINISTRATOR', 'ACADEMIC', 'TEACHER'])) {
                try {
                    $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                    $students = collect($studentRepo->fetchAll())->filter(function($s) use ($keyword) {
                        return str_contains(strtolower($s['Name'] ?? ''), $keyword) || str_contains(strtolower($s['Student_ID'] ?? ''), $keyword);
                    })->take(5);
                    foreach ($students as $s) {
                        $results['Students'][] = [
                            'title' => $s['Name'] ?? 'Unknown',
                            'desc' => $s['Student_ID'] ?? '',
                            'url' => route('students.index')
                        ];
                    }
                } catch (\Exception $e) {}
            }

            // 3. Search Teachers (Admin, HR, Academic)
            if (in_array($role, ['ADMINISTRATOR', 'ACADEMIC', 'HR'])) {
                try {
                    $teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
                    $teachers = collect($teacherRepo->fetchAll())->filter(function($t) use ($keyword) {
                        return str_contains(strtolower($t['Name'] ?? ''), $keyword) || str_contains(strtolower($t['Teacher_ID'] ?? ''), $keyword);
                    })->take(5);
                    foreach ($teachers as $t) {
                        $results['Teachers'][] = [
                            'title' => $t['Name'] ?? 'Unknown',
                            'desc' => $t['Teacher_ID'] ?? '',
                            'url' => route('teachers.index')
                        ];
                    }
                } catch (\Exception $e) {}
            }

            // Filter out empty categories
            return array_filter($results, function($group) { return count($group) > 0; });
        });
    }

    public function getHistory($userId)
    {
        return Cache::get("wms_search_history_{$userId}", []);
    }

    public function saveHistory($userId, $keyword)
    {
        if (empty(trim($keyword))) return;
        
        $history = $this->getHistory($userId);
        
        // Remove if exists to put it at the top
        $history = array_filter($history, function($k) use ($keyword) { return strtolower($k) !== strtolower($keyword); });
        
        array_unshift($history, $keyword);
        $history = array_slice($history, 0, 10);
        
        Cache::put("wms_search_history_{$userId}", $history, 86400 * 30); // Save for 30 days
    }

    public function clearHistory($userId)
    {
        Cache::forget("wms_search_history_{$userId}");
    }
}