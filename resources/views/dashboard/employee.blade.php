@extends('layouts.app')

@section('header', 'Dashboard Pegawai')

@section('content')
    <div class="mx-auto w-full max-w-5xl space-y-5">
        <x-mobile-dashboard-hero user-role="EMPLOYEE" />

        <section class="hidden lg:block" aria-labelledby="employee-dashboard-title">
            <x-dashboard-header />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700" aria-hidden="true">
                        <x-sidebar.icon name="camera" class="h-5 w-5" />
                    </span>
                    <h2 id="employee-dashboard-title" class="mt-4 text-lg font-extrabold text-slate-900">Presensi Pegawai</h2>
                    <p class="mt-1 text-sm text-slate-500">Pindai QR untuk mencatat kehadiran kerja.</p>
                </a>

                <a href="{{ route('dashboard.personal-payroll') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700" aria-hidden="true">
                        <x-sidebar.icon name="cash" class="h-5 w-5" />
                    </span>
                    <h2 class="mt-4 text-lg font-extrabold text-slate-900">Slip Gaji Saya</h2>
                    <p class="mt-1 text-sm text-slate-500">Lihat riwayat penggajian pribadi Anda.</p>
                </a>
            </div>
        </section>
    </div>
@endsection
