@props(['filters' => []])

<x-card class="mb-6 p-1 border border-slate-200" x-data="{ expanded: false }">
    <div class="flex justify-between items-center cursor-pointer p-3 lg:p-4 hover:bg-slate-50 transition-colors rounded-xl" @click="expanded = !expanded">
        <h3 class="font-bold text-[13px] text-slate-800 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            </div>
            Global Filter
        </h3>
        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>

    <form method="GET" x-show="expanded" x-transition.opacity.duration.200ms class="px-4 pb-4 pt-2 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-1" style="display: none;">
        
        @if(in_array('keyword', $filters))
        <div class="xl:col-span-4">
            <x-input name="keyword" label="Keyword" value="{{ request('keyword') }}" placeholder="Cari...y keyword..." />
        </div>
        @endif

        @if(in_array('program', $filters))
        <div>
            <x-select name="program" label="Program">
                <option value="">All Programs</option>
            </x-select>
        </div>
        @endif
        
        @if(in_array('batch', $filters))
        <div>
            <x-select name="batch" label="Batch">
                <option value="">All Batches</option>
            </x-select>
        </div>
        @endif

        @if(in_array('class', $filters))
        <div>
            <x-select name="class" label="Class">
                <option value="">All Classes</option>
            </x-select>
        </div>
        @endif

        @if(in_array('teacher', $filters))
        <div>
            <x-select name="teacher" label="Teacher">
                <option value="">All Teachers</option>
            </x-select>
        </div>
        @endif

        @if(in_array('student', $filters))
        <div>
            <x-select name="student" label="Student">
                <option value="">All Students</option>
            </x-select>
        </div>
        @endif

        @if(in_array('status', $filters))
        <div>
            <x-select name="status" label="Status">
                <option value="">All Statuses</option>
            </x-select>
        </div>
        @endif

        @if(in_array('date', $filters))
        <div>
            <x-input type="date" name="date" label="Date" value="{{ request('date') }}" />
        </div>
        @endif

        @if(in_array('academic_year', $filters))
        <div>
            <x-select name="academic_year" label="Academic Year">
                <option value="">All Years</option>
            </x-select>
        </div>
        @endif

        @if(in_array('semester', $filters))
        <div>
            <x-select name="semester" label="Semester">
                <option value="">All Semesters</option>
            </x-select>
        </div>
        @endif

        <div class="xl:col-span-4 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-2">
            <a href="{{ url()->current() }}" class="text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors">Reset</a>
            <x-button type="submit" variant="primary" size="sm">Apply Filter</x-button>
        </div>
    </form>
</x-card>



