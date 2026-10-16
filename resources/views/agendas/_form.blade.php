@php
    $agenda = $agenda ?? [];
    $picOptions = collect($pics)->mapWithKeys(function ($pic) {
        $name = trim((string) ($pic['Full_Name'] ?? '')) ?: ($pic['Username'] ?? $pic['User_ID']);
        $role = trim((string) ($pic['Role_Name'] ?? 'MASTER'));
        return [$pic['User_ID'] => $name.' · '.$role];
    })->all();
@endphp

<div class="space-y-8">
    <section>
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-bold text-slate-800">Jadwal Agenda</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
            <x-universal.input type="date" name="Agenda_Date" label="Tanggal" :value="$agenda['Agenda_Date'] ?? ''" :required="true" />
            <x-universal.input type="time" name="Start_Time" label="Waktu Mulai" :value="isset($agenda['Start_Time']) ? substr($agenda['Start_Time'], 0, 5) : ''" :required="true" />
            <x-universal.input type="time" name="End_Time" label="Waktu Selesai" :value="isset($agenda['End_Time']) ? substr($agenda['End_Time'], 0, 5) : ''" :required="true" />
            <x-universal.select name="Agenda_Type" label="Jenis Agenda" :options="$types" :value="$agenda['Agenda_Type'] ?? 'MEETING'" :required="true" />
        </div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <x-universal.select name="PIC_User_ID" label="PIC / Penanggung Jawab" :options="$picOptions" :value="$agenda['PIC_User_ID'] ?? auth()->user()->User_ID" :required="true" helper="PIC agenda juga menjadi PIC tindak lanjut." />
            <x-universal.select name="Status" label="Status Agenda" :options="$statuses" :value="$agenda['Status'] ?? 'SCHEDULED'" :required="true" />
        </div>
        <x-universal.textarea name="Related_Parties" label="Pihak Terkait" :value="$agenda['Related_Parties'] ?? ''" :required="true" rows="3" placeholder="Tamu, perusahaan, atau peserta yang terlibat" />
        <x-universal.textarea name="Purpose" label="Tujuan / Pembahasan" :value="$agenda['Purpose'] ?? ''" :required="true" rows="4" />
    </section>

    <section>
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-bold text-slate-800">Hasil Kegiatan</h3>
        <x-universal.textarea name="Activity_Result" label="Hasil Kegiatan" :value="$agenda['Activity_Result'] ?? ''" rows="5" helper="Boleh dikosongkan saat agenda baru dibuat." />
        <x-universal.textarea name="Document_Notes" label="Catatan / Dokumen" :value="$agenda['Document_Notes'] ?? ''" rows="3" placeholder="Tautan notulen, lokasi berkas, atau catatan dokumen pendukung" />
    </section>

    <section>
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-bold text-slate-800">Tindak Lanjut</h3>
        <x-universal.textarea name="Follow_Up" label="Tindak Lanjut" :value="$agenda['Follow_Up'] ?? ''" rows="4" helper="Boleh dikosongkan saat agenda baru dibuat." />
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <x-universal.input type="date" name="Follow_Up_Deadline" label="Deadline Tindak Lanjut" :value="$agenda['Follow_Up_Deadline'] ?? ''" />
            <x-universal.select name="Follow_Up_Status" label="Status Tindak Lanjut" :options="$followUpStatuses" :value="$agenda['Follow_Up_Status'] ?? 'NOT_STARTED'" :required="true" />
        </div>
    </section>
</div>
