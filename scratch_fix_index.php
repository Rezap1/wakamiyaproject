<?php
$file = __DIR__ . '/resources/views/academic/attendances/index.blade.php';
$content = file_get_contents($file);

$mobileCode = <<<'HTML'
</x-universal.data-table>
    </div>

    <!-- MOBILE CARD VIEW -->
    <div class="md:hidden space-y-4 mt-6">
        @forelse($attendances ?? [] as $item)
            @php
                $studentId = $item['Student_ID'] ?? 'Unknown';
                $studentName = isset($students[$studentId]) ? ($students[$studentId]['Full_Name'] ?? $studentId) : $studentId;
                $classId = $item['Class_ID'] ?? $item['Schedule_ID'] ?? '-';
                $className = isset($classOptions[$classId]) ? $classOptions[$classId] : $classId;
                
                $status = strtolower($item['Status'] ?? 'hadir');
                $badgeColor = match($status) {
                    'hadir', 'present' => 'green',
                    'terlambat', 'late' => 'yellow',
                    'sakit', 'izin', 'sick', 'leave', 'permission' => 'blue',
                    'alpha', 'absent' => 'red',
                    default => 'slate',
                };
                
                $displayStatus = match($status) {
                    'present' => 'Hadir',
                    'late' => 'Terlambat',
                    'sick' => 'Sakit',
                    'leave', 'permission' => 'Izin',
                    'absent' => 'Alpha',
                    default => ucfirst($item['Status'] ?? 'Hadir'),
                };
                
                $dateFormatted = !empty($item['Attendance_Date']) ? \Carbon\Carbon::parse($item['Attendance_Date'])->format('d M Y') : '-';
                $timeFormatted = !empty($item['Created_At']) ? \Carbon\Carbon::parse($item['Created_At'])->format('H:i') : '-';
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase">{{ $dateFormatted }}</p>
                        <h4 class="text-base font-bold text-slate-800 mt-1">{{ $studentName }}</h4>
                        <p class="text-xs text-slate-500">{{ $studentId }} &bull; {{ $className }}</p>
                    </div>
                    <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                </div>
                
                <div class="bg-slate-50 rounded-lg p-3 grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Jam Masuk</p>
                        <p class="text-sm font-bold text-slate-800">{{ $timeFormatted }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Status</p>
                        <p class="text-sm font-bold text-{{ $badgeColor }}-600 flex items-center">
                            @if(in_array($badgeColor, ['green', 'yellow']))
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            @endif
                            {{ $displayStatus }}
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <x-universal.empty-state title="Data Kehadiran Kosong" description="Belum ada catatan kehadiran untuk tanggal yang dipilih." />
        @endforelse
        
        @if(is_object($attendances) && method_exists($attendances, 'links'))
            <div class="pt-4 pb-2">
                <x-universal.pagination :paginator="$attendances" />
            </div>
        @endif
    </div>
HTML;

$content = str_replace('</x-universal.data-table>', $mobileCode, $content);
$content = str_replace('<x-universal.data-table', '<div class="hidden md:block">' . "\n" . '        <x-universal.data-table', $content);

file_put_contents($file, $content);
echo "Done.";
