@props(['user' => null, 'class' => 'w-9 h-9', 'textSize' => 'text-xs'])

@php
    $u = $user ?? auth()->user();
    $name = $u->Username ?? $u->Name ?? $u->Full_Name ?? $u->name ?? 'User';
    $userId = $u->User_ID ?? $u->id ?? null;

    // Resolve Profile Photo URL
    $photoUrl = null;

    if (isset($u->Profile_Photo) && !empty($u->Profile_Photo)) {
        $photoUrl = str_starts_with($u->Profile_Photo, 'http') 
            ? $u->Profile_Photo 
            : asset($u->Profile_Photo);
    } elseif ($userId) {
        $cacheKey = 'user_photo_' . $userId;
        $photoUrl = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function() use ($userId, $u) {
            // 1. Try finding Employee_ID by User_ID in EmployeeRepository
            try {
                $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
                $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $userId);
                if ($employee && !empty($employee['Employee_ID'])) {
                    $empService = app(\App\Services\Core\EmployeeService::class);
                    $path = $empService->getProfilePhotoPath($employee['Employee_ID']);
                    if ($path && file_exists(public_path($path))) {
                        return asset($path);
                    }
                }
            } catch (\Throwable $e) {}

            // 2. Try finding Student_ID by User_ID in StudentRepository
            try {
                $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                $students = $studentRepo->fetchAll();
                $student = collect($students)->firstWhere('User_ID', $userId);
                
                if (!$student) {
                    $studentName = $u->Full_Name ?? $u->Username ?? $u->Name ?? null;
                    if ($studentName) {
                        $student = collect($students)->firstWhere('Full_Name', $studentName);
                    }
                }

                if ($student && !empty($student['Student_ID'])) {
                    $files = glob(storage_path('app/public/profiles/student_' . $student['Student_ID'] . '.*'));
                    if (!empty($files) && file_exists(public_path('storage/profiles/' . basename($files[0])))) {
                        return asset('storage/profiles/' . basename($files[0]));
                    }
                }
            } catch (\Throwable $e) {}

            // 3. Fallback direct file glob for User_ID
            $userFiles = glob(storage_path('app/public/profiles/user_' . $userId . '.*'));
            if (!empty($userFiles) && file_exists(public_path('storage/profiles/' . basename($userFiles[0])))) {
                return asset('storage/profiles/' . basename($userFiles[0]));
            }

            return '';
        });

        $photoUrl = $photoUrl ?: null;
    }

    // Dynamic Initials Fallback (Never Hardcoded DE)
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    $words = array_values(array_filter(explode(' ', $cleanName)));
    if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
    } elseif (count($words) === 1) {
        $initials = strtoupper(substr($words[0], 0, min(2, strlen($words[0]))));
    } else {
        $initials = 'US';
    }
@endphp

@if($photoUrl)
    <div class="relative shrink-0 flex items-center justify-center">
        <img src="{{ $photoUrl }}" alt="{{ $name }}" class="{{ $class }} rounded-full object-cover border-2 border-sky-400/80 shadow-sm shrink-0" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');">
        <div class="hidden {{ $class }} rounded-full bg-gradient-to-tr from-[#111827] to-sky-600 text-white font-extrabold items-center justify-center shrink-0 border-2 border-sky-400/80 shadow-sm {{ $textSize }}">
            <span>{{ $initials }}</span>
        </div>
    </div>
@else
    <div class="{{ $class }} rounded-full bg-gradient-to-tr from-[#111827] to-sky-600 text-white font-extrabold flex items-center justify-center shrink-0 border-2 border-sky-400/80 shadow-sm {{ $textSize }}">
        <span>{{ $initials }}</span>
    </div>
@endif
