@props(['userRole'])

<aside id="sidebar" class="w-64 bg-[#111827] text-slate-300 shadow-xl flex-shrink-0 z-50 flex flex-col transition-transform duration-300 ease-in-out fixed inset-y-0 left-0 transform -translate-x-full lg:relative lg:translate-x-0 border-r border-slate-800">
    <!-- Brand / Logo Area -->
    <div class="pt-8 pb-6 flex flex-col items-center justify-center border-b border-slate-800 relative shrink-0">
        <div class="w-[84px] h-[84px] bg-[#111827] rounded-full flex items-center justify-center border-[3px] border-sky-400 p-1 mb-4 shadow-[0_0_20px_rgba(56,189,248,0.2)]">
            <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo LPK Wakamiya" class="w-full h-full object-cover bg-white rounded-full p-1" onerror="this.src='https://ui-avatars.com/api/?name=W&background=1e293b&color=fff&rounded=true&bold=true'">
        </div>
        <span class="text-[18px] font-black text-white tracking-widest uppercase mt-1">WAKAMIYA</span>
        <span class="text-[9px] font-bold text-sky-400 tracking-[0.2em] mt-1">MANAGEMENT SYSTEM</span>
        <span class="text-[10px] text-slate-600 mt-1 tracking-widest font-semibold">v1.0</span>
    </div>
    
    <div class="flex-1 overflow-y-auto py-6 dark-scrollbar space-y-1.5 pr-4">
        
        <!-- ADMINISTRATOR WORKSPACE -->
        @if($userRole === 'ADMINISTRATOR')
            <x-sidebar.nav-link href="{{ route('dashboard.administrator') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('approvals.index') }}" active="{{ request()->routeIs('approvals.*') }}" icon="inbox">Kotak Persetujuan</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('audit.index') }}" active="{{ request()->routeIs('audit.*') }}" icon="clipboard-list">Jejak Audit</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('settings.index') }}" active="{{ request()->routeIs('settings.*') }}" icon="cog">Pengaturan Sistem</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('users.index') }}" active="{{ request()->routeIs('users.*') }}" icon="users">Pengguna</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('modules.index') }}" active="{{ request()->routeIs('modules.*') }}" icon="puzzle-piece">Modul</x-sidebar.nav-link>
        @endif

        <!-- HR WORKSPACE -->
        @if($userRole === 'HR')
            <x-sidebar.nav-link href="{{ route('dashboard.hr') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('approvals.index') }}" active="{{ request()->routeIs('approvals.*') }}" icon="inbox">Kotak Persetujuan</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('employees.index') }}" active="{{ request()->routeIs('employees.*') }}" icon="identification">Pegawai</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('attendances.index') }}" active="{{ request()->routeIs('attendances.*') }}" icon="clock">Kehadiran</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('payrolls.index') }}" active="{{ request()->routeIs('payrolls.*') }}" icon="cash">Payroll & Gaji</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('departments.index') }}" active="{{ request()->routeIs('departments.*') }}" icon="office-building">Departemen</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('positions.index') }}" active="{{ request()->routeIs('positions.*') }}" icon="badge-check">Jabatan</x-sidebar.nav-link>
        @endif

        <!-- ACADEMIC WORKSPACE -->
        @if($userRole === 'ACADEMIC')
            <x-sidebar.nav-link href="{{ route('dashboard.academic') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('students.index') }}" active="{{ request()->routeIs('students.*') }}" icon="academic-cap">Siswa</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('teachers.index') }}" active="{{ request()->routeIs('teachers.*') }}" icon="user-group">Guru</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('programs.index') }}" active="{{ request()->routeIs('programs.*') }}" icon="book-open">Program</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('batches.index') }}" active="{{ request()->routeIs('batches.*') }}" icon="collection">Batch</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('classes.index') }}" active="{{ request()->routeIs('classes.*') }}" icon="view-boards">Kelas</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('subjects.index') }}" active="{{ request()->routeIs('subjects.*') }}" icon="library">Mata Pelajaran</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('schedules.index') }}" active="{{ request()->routeIs('schedules.*') }}" icon="calendar">Jadwal</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('attendances.index') }}" active="{{ request()->routeIs('attendances.*') }}" icon="clock">Kehadiran</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('assignments.index') }}" active="{{ request()->routeIs('assignments.*') }}" icon="document-text">Tugas Harian</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('submissions.index') }}" active="{{ request()->routeIs('submissions.*') }}" icon="inbox">Pengumpulan Tugas</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('scores.index') }}" active="{{ request()->routeIs('scores.*') }}" icon="chart-bar">Nilai</x-sidebar.nav-link>
        @endif

        <!-- MARKETING WORKSPACE -->
        @if($userRole === 'MARKETING')
            <x-sidebar.nav-link href="{{ route('dashboard.marketing') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('companies.index') }}" active="{{ request()->routeIs('companies.*') }}" icon="office-building">Perusahaan</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('documents.index') }}" active="{{ request()->routeIs('documents.*') }}" icon="folder-open">Arsip Dokumen</x-sidebar.nav-link>
        @endif

        <!-- FINANCE WORKSPACE -->
        @if($userRole === 'FINANCE')
            <x-sidebar.nav-link href="{{ route('dashboard.finance') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('accounts.index') }}" active="{{ request()->routeIs('accounts.*') }}" icon="collection">Master Akun</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('transactions.index') }}" active="{{ request()->routeIs('transactions.*') }}" icon="switch-horizontal">Transaksi</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('invoices.index') }}" active="{{ request()->routeIs('invoices.*') }}" icon="document-duplicate">Tagihan (Invoice)</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('payments.index') }}" active="{{ request()->routeIs('payments.*') }}" icon="credit-card">Pembayaran</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('reports.finance.index') }}" active="{{ request()->routeIs('reports.finance.*') }}" icon="chart-bar">Laporan Finance</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('settings.index') }}" active="{{ request()->routeIs('settings.*') }}" icon="cog">Pengaturan Sistem</x-sidebar.nav-link>
        @endif

        <!-- DIRECTOR WORKSPACE -->
        @if($userRole === 'DIRECTOR')
            <x-sidebar.nav-link href="{{ route('dashboard.director') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('approvals.index') }}" active="{{ request()->routeIs('approvals.*') }}" icon="inbox">Kotak Persetujuan</x-sidebar.nav-link>
        @endif
        
        <!-- TEACHER WORKSPACE -->
        @if($userRole === 'TEACHER')
            <x-sidebar.nav-link href="{{ route('dashboard.teacher') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('teacher.workspace.calendar') }}" active="{{ request()->routeIs('teacher.workspace.calendar') }}" icon="calendar">Kalender</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('attendances.index') }}" active="{{ request()->routeIs('attendances.*') }}" icon="clock">Kehadiran Siswa</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('assignments.index') }}" active="{{ request()->routeIs('assignments.*') }}" icon="document-text">Tugas Harian</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('submissions.index') }}" active="{{ request()->routeIs('submissions.*') }}" icon="inbox">Pengumpulan Tugas</x-sidebar.nav-link>
        @endif
        
        <!-- STUDENT WORKSPACE -->
        @if($userRole === 'STUDENT')
            <x-sidebar.nav-link href="{{ route('dashboard.student') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dashboard</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('student.schedule') }}" active="{{ request()->routeIs('student.schedule') }}" icon="calendar">Jadwal</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('student.portal.assignments') }}" active="{{ request()->routeIs('student.portal.assignments*') }}" icon="document-text">Tugas</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('student.progress') }}" active="{{ request()->routeIs('student.progress') }}" icon="clipboard-check">Nilai</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('student.portal.materials') }}" active="{{ request()->routeIs('student.portal.materials*') }}" icon="book-open">Materi</x-sidebar.nav-link>
            <x-sidebar.nav-link href="{{ route('student.billing.index') }}" active="{{ request()->routeIs('student.billing.*') }}" icon="cash">Tagihan Saya</x-sidebar.nav-link>
        @endif

    </div>
</aside>
