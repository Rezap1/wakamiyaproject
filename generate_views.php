<?php

$base = __DIR__ . '/resources/views/academic';
if (!is_dir($base)) mkdir($base, 0777, true);

$modules = ['subjects', 'schedules', 'announcements'];
foreach ($modules as $mod) {
    if (!is_dir("$base/$mod")) mkdir("$base/$mod", 0777, true);
}

// 1. SUBJECT VIEWS
$subjectIndex = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Master Subject')
@section('content')
<div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
    <div class="p-6 md:p-8 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Daftar Mata Pelajaran</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data mata pelajaran per program.</p>
        </div>
        <a href="{{ route('subjects.create') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl shadow-lg transition-all">
            Tambah Subject
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-800">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Subject Code / Name</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Program</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Credit / Duration</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50">
                @forelse($subjects as $subject)
                <tr class="hover:bg-primary-50/30 transition-colors">
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $subject['Subject_Code'] ?? '-' }}</div>
                        <div class="text-sm text-gray-500">{{ $subject['Subject_Name'] ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $subject['Program_ID'] ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm">{{ $subject['Credit'] ?? '-' }} SKS / {{ $subject['Duration'] ?? '-' }} Menit</td>
                    <td class="px-6 py-4">
                        @if(($subject['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <span class="px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700">Aktif</span>
                        @else
                            <span class="px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                        <a href="{{ route('subjects.edit', $subject['Subject_ID']) }}" class="text-blue-500 hover:text-blue-700 font-medium text-sm">Edit</a>
                        <form action="{{ route('subjects.destroy', $subject['Subject_ID']) }}" method="POST" onsubmit="return confirm('Yakin hapus?');">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 font-medium text-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-gray-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
BLADE;
file_put_contents("$base/subjects/index.blade.php", $subjectIndex);

$subjectCreate = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Tambah Subject')
@section('content')
<div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm p-6">
    <form action="{{ route('subjects.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="block text-sm font-bold mb-2">Subject Code</label><input type="text" name="Subject_Code" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-sm font-bold mb-2">Subject Name</label><input type="text" name="Subject_Name" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-sm font-bold mb-2">Program ID</label><input type="text" name="Program_ID" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-bold mb-2">Credit (SKS)</label><input type="number" name="Credit" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-bold mb-2">Duration (Menit)</label><input type="number" name="Duration" class="w-full rounded-lg border-gray-300"></div>
            <div class="md:col-span-2"><label class="block text-sm font-bold mb-2">Description</label><textarea name="Description" class="w-full rounded-lg border-gray-300"></textarea></div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-6 py-2 bg-primary-600 text-white font-bold rounded-lg hover:bg-primary-700">Simpan</button>
        </div>
    </form>
</div>
@endsection
BLADE;
file_put_contents("$base/subjects/create.blade.php", $subjectCreate);

$subjectEdit = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Edit Subject')
@section('content')
<div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm p-6">
    <form action="{{ route('subjects.update', $subject['Subject_ID']) }}" method="POST">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="block text-sm font-bold mb-2">Subject Code</label><input type="text" name="Subject_Code" value="{{ $subject['Subject_Code'] ?? '' }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-sm font-bold mb-2">Subject Name</label><input type="text" name="Subject_Name" value="{{ $subject['Subject_Name'] ?? '' }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-sm font-bold mb-2">Program ID</label><input type="text" name="Program_ID" value="{{ $subject['Program_ID'] ?? '' }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-bold mb-2">Credit (SKS)</label><input type="number" name="Credit" value="{{ $subject['Credit'] ?? '' }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-bold mb-2">Duration (Menit)</label><input type="number" name="Duration" value="{{ $subject['Duration'] ?? '' }}" class="w-full rounded-lg border-gray-300"></div>
            <div class="md:col-span-2"><label class="block text-sm font-bold mb-2">Description</label><textarea name="Description" class="w-full rounded-lg border-gray-300">{{ $subject['Description'] ?? '' }}</textarea></div>
            <div><label class="block text-sm font-bold mb-2">Status</label>
                <select name="Is_Active" class="w-full rounded-lg border-gray-300">
                    <option value="TRUE" {{ ($subject['Is_Active']??'')=='TRUE'?'selected':'' }}>Aktif</option>
                    <option value="FALSE" {{ ($subject['Is_Active']??'')=='FALSE'?'selected':'' }}>Nonaktif</option>
                </select>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-6 py-2 bg-primary-600 text-white font-bold rounded-lg hover:bg-primary-700">Update</button>
        </div>
    </form>
</div>
@endsection
BLADE;
file_put_contents("$base/subjects/edit.blade.php", $subjectEdit);


// 2. SCHEDULE VIEWS
$scheduleIndex = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Master Schedule')
@section('content')
<div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
        <h2 class="text-xl font-bold">Jadwal Kelas</h2>
        <a href="{{ route('schedules.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Tambah Jadwal</a>
    </div>
    <table class="min-w-full">
        <thead>
            <tr class="bg-gray-50"><th class="px-6 py-3 text-left">Class</th><th class="px-6 py-3 text-left">Subject</th><th class="px-6 py-3 text-left">Teacher</th><th class="px-6 py-3 text-left">Day/Time</th><th class="px-6 py-3 text-right">Aksi</th></tr>
        </thead>
        <tbody>
            @forelse($schedules as $schedule)
            <tr>
                <td class="px-6 py-4">{{ $schedule['Class_ID'] ?? '-' }}</td>
                <td class="px-6 py-4">{{ $schedule['Subject_ID'] ?? '-' }}</td>
                <td class="px-6 py-4">{{ $schedule['Teacher_ID'] ?? '-' }}</td>
                <td class="px-6 py-4">{{ $schedule['Day_Of_Week'] ?? '-' }}, {{ $schedule['Start_Time'] ?? '-' }} - {{ $schedule['End_Time'] ?? '-' }}</td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('schedules.edit', $schedule['Schedule_ID']) }}" class="text-blue-500">Edit</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center py-8">Tidak ada jadwal.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
BLADE;
file_put_contents("$base/schedules/index.blade.php", $scheduleIndex);

$scheduleCreate = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Tambah Jadwal')
@section('content')
<div class="bg-white rounded-2xl p-6 shadow-sm"><form action="{{ route('schedules.store') }}" method="POST">@csrf
    <div class="grid grid-cols-2 gap-4">
        <div><label class="block font-bold">Class ID</label><input type="text" name="Class_ID" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Subject ID</label><input type="text" name="Subject_ID" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Teacher ID</label><input type="text" name="Teacher_ID" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Academic Year ID</label><input type="text" name="Academic_Year_ID" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Day</label><select name="Day_Of_Week" class="w-full border-gray-300 rounded"><option>Senin</option><option>Selasa</option><option>Rabu</option><option>Kamis</option><option>Jumat</option></select></div>
        <div><label class="block font-bold">Room</label><input type="text" name="Room" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Start Time</label><input type="time" name="Start_Time" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">End Time</label><input type="time" name="End_Time" class="w-full border-gray-300 rounded" required></div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$base/schedules/create.blade.php", $scheduleCreate);

$scheduleEdit = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Edit Jadwal')
@section('content')
<div class="bg-white rounded-2xl p-6 shadow-sm"><form action="{{ route('schedules.update', $schedule['Schedule_ID']) }}" method="POST">@csrf @method('PUT')
    <div class="grid grid-cols-2 gap-4">
        <div><label class="block font-bold">Class ID</label><input type="text" name="Class_ID" value="{{ $schedule['Class_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Subject ID</label><input type="text" name="Subject_ID" value="{{ $schedule['Subject_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Teacher ID</label><input type="text" name="Teacher_ID" value="{{ $schedule['Teacher_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Academic Year ID</label><input type="text" name="Academic_Year_ID" value="{{ $schedule['Academic_Year_ID']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Day</label><select name="Day_Of_Week" class="w-full border-gray-300 rounded"><option>Senin</option><option>Selasa</option><option>Rabu</option><option>Kamis</option><option>Jumat</option></select></div>
        <div><label class="block font-bold">Room</label><input type="text" name="Room" value="{{ $schedule['Room']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Start Time</label><input type="time" name="Start_Time" value="{{ $schedule['Start_Time']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">End Time</label><input type="time" name="End_Time" value="{{ $schedule['End_Time']??'' }}" class="w-full border-gray-300 rounded" required></div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$base/schedules/edit.blade.php", $scheduleEdit);

// 3. ANNOUNCEMENT VIEWS
$announcementIndex = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Announcement Center')
@section('content')
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="p-6 border-b flex justify-between items-center">
        <h2 class="text-xl font-bold">Pengumuman</h2>
        @if(in_array($userRole ?? '', ['ADMINISTRATOR', 'ACADEMIC']))
            <a href="{{ route('announcements.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Buat Pengumuman</a>
        @endif
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($announcements as $ann)
        <div class="border rounded-xl p-4 shadow-sm hover:shadow-md transition">
            <h3 class="font-bold text-lg text-primary-700">{{ $ann['Title'] ?? 'No Title' }}</h3>
            <p class="text-sm text-gray-500 mt-1 mb-2">{{ $ann['Target_Role'] ?? 'ALL' }} | {{ $ann['Publish_Date'] ?? '-' }}</p>
            <p class="text-gray-700 line-clamp-3">{{ $ann['Content'] ?? '' }}</p>
            @if(in_array($userRole ?? '', ['ADMINISTRATOR', 'ACADEMIC']))
            <div class="mt-4 pt-4 border-t flex justify-end gap-2">
                <a href="{{ route('announcements.edit', $ann['Announcement_ID']) }}" class="text-sm text-blue-600">Edit</a>
            </div>
            @endif
        </div>
        @empty
        <div class="col-span-2 text-center py-8 text-gray-500">Tidak ada pengumuman.</div>
        @endforelse
    </div>
</div>
@endsection
BLADE;
file_put_contents("$base/announcements/index.blade.php", $announcementIndex);

$announcementCreate = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Buat Pengumuman')
@section('content')
<div class="bg-white rounded-2xl p-6 shadow-sm"><form action="{{ route('announcements.store') }}" method="POST">@csrf
    <div class="grid grid-cols-1 gap-4">
        <div><label class="block font-bold">Title</label><input type="text" name="Title" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Content</label><textarea name="Content" rows="4" class="w-full border-gray-300 rounded" required></textarea></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block font-bold">Target Role</label><select name="Target_Role" class="w-full border-gray-300 rounded"><option value="ALL">ALL</option><option value="TEACHER">TEACHER</option><option value="STUDENT">STUDENT</option></select></div>
            <div><label class="block font-bold">Priority</label><select name="Priority" class="w-full border-gray-300 rounded"><option value="Normal">Normal</option><option value="High">High</option></select></div>
            <div><label class="block font-bold">Publish Date</label><input type="date" name="Publish_Date" class="w-full border-gray-300 rounded"></div>
            <div><label class="block font-bold">Expired Date</label><input type="date" name="Expired_Date" class="w-full border-gray-300 rounded"></div>
        </div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Publikasikan</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$base/announcements/create.blade.php", $announcementCreate);

$announcementEdit = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Edit Pengumuman')
@section('content')
<div class="bg-white rounded-2xl p-6 shadow-sm"><form action="{{ route('announcements.update', $announcement['Announcement_ID']) }}" method="POST">@csrf @method('PUT')
    <div class="grid grid-cols-1 gap-4">
        <div><label class="block font-bold">Title</label><input type="text" name="Title" value="{{ $announcement['Title']??'' }}" class="w-full border-gray-300 rounded" required></div>
        <div><label class="block font-bold">Content</label><textarea name="Content" rows="4" class="w-full border-gray-300 rounded" required>{{ $announcement['Content']??'' }}</textarea></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block font-bold">Target Role</label><select name="Target_Role" class="w-full border-gray-300 rounded"><option value="ALL">ALL</option><option value="TEACHER">TEACHER</option><option value="STUDENT">STUDENT</option></select></div>
            <div><label class="block font-bold">Status</label><select name="Status" class="w-full border-gray-300 rounded"><option value="PUBLISHED">Published</option><option value="DRAFT">Draft</option></select></div>
            <div><label class="block font-bold">Publish Date</label><input type="date" name="Publish_Date" value="{{ $announcement['Publish_Date']??'' }}" class="w-full border-gray-300 rounded"></div>
            <div><label class="block font-bold">Expired Date</label><input type="date" name="Expired_Date" value="{{ $announcement['Expired_Date']??'' }}" class="w-full border-gray-300 rounded"></div>
        </div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$base/announcements/edit.blade.php", $announcementEdit);

echo "Views generated.\n";
