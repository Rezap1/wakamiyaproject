@extends('layouts.app')
@section('header', $quiz ? 'Edit Kuis Latihan' : 'Buat Kuis Latihan')
@section('content')
@php
    $persistedSeed = collect($questions)->map(fn($q) => (array)$q)->values();
    $oldQuestions = old('questions');
    $seed = is_array($oldQuestions)
        ? collect(array_values($oldQuestions))->map(fn($q, $i) => array_merge((array)($persistedSeed->get($i) ?? []), (array)$q))->all()
        : $persistedSeed->all();
    $maxQuestions = (int) config('quiz.max_questions', 100);
    $mediaConfig = [
        'image_max_bytes' => (int) config('quiz.media.image_max_bytes'),
        'image_max_width' => (int) config('quiz.media.image_max_width'),
        'image_max_height' => (int) config('quiz.media.image_max_height'),
        'audio_max_bytes' => (int) config('quiz.media.audio_max_bytes'),
        'audio_max_duration_ms' => (int) config('quiz.media.audio_max_duration_ms'),
        'aggregate_warning_bytes' => (int) config('quiz.media.aggregate_warning_bytes'),
        'aggregate_max_bytes' => (int) config('quiz.media.aggregate_max_bytes'),
    ];
    $formAction = $quiz
        ? route('teacher.practice-quizzes.update', $quiz['Practice_Quiz_ID'])
        : route('teacher.practice-quizzes.store');
@endphp
<form method="POST" action="{{ $formAction }}" class="mx-auto max-w-5xl space-y-6 pb-24" x-data="quizBuilder(@js($seed), {{ $maxQuestions }}, @js(route('teacher.practice-quizzes.media.upload')), @js(csrf_token()), @js($mediaConfig), @js($errors->toArray()), [], [])" x-init="init()" @submit="if (uploadingCount > 0 || recordingCount > 0) { $event.preventDefault(); countError = 'Hentikan rekaman dan tunggu seluruh upload media selesai.'; }">
    @csrf @if($quiz) @method('PUT') @endif
    <input type="hidden" name="Idempotency_Key" value="{{ old('Idempotency_Key', (string) \Illuminate\Support\Str::uuid()) }}">
    @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7"><h2 class="text-xl font-black">Informasi Kuis Latihan</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="sm:col-span-2 text-sm font-bold">Judul Kuis Latihan<input name="Title" value="{{ old('Title', $revision['Title'] ?? $quiz['Title'] ?? '') }}" required maxlength="160" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></label>
            <label class="sm:col-span-2 text-sm font-bold">Deskripsi<textarea name="Description" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border-slate-300">{{ old('Description', $revision['Description'] ?? $quiz['Description'] ?? '') }}</textarea></label>
            <label class="sm:col-span-2 text-sm font-bold">Instruksi<textarea name="Instructions" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border-slate-300">{{ old('Instructions', $revision['Instructions'] ?? $quiz['Instructions'] ?? '') }}</textarea></label>
            <label class="text-sm font-bold">Durasi (menit)<input type="number" min="1" max="480" name="Duration_Minutes" value="{{ old('Duration_Minutes', $revision['Duration_Minutes'] ?? $quiz['Duration_Minutes'] ?? 30) }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
            <p class="self-end rounded-xl bg-sky-50 p-3 text-sm text-sky-800">Tersedia untuk semua siswa aktif, tanpa jadwal berakhir dan tanpa batas pengerjaan.</p>
        </div>
    </section>
    <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><h2 class="text-xl font-black">Soal A–D</h2><p class="text-sm text-slate-500">Skor maksimum: <strong x-text="maximum"></strong></p></div>
            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-[minmax(0,12rem)_auto] sm:items-end lg:w-auto">
                <label class="text-sm font-bold text-slate-700">Jumlah Soal
                    <input type="number" min="1" max="{{ $maxQuestions }}" step="1" x-model.number="requestedCount" @keydown.enter.prevent="applyQuestionCount" class="mt-2 min-h-11 w-full rounded-xl border-slate-300" aria-describedby="question-count-help question-count-error">
                </label>
                <button type="button" @click="applyQuestionCount" class="min-h-11 rounded-xl bg-sky-50 px-4 py-2 font-bold text-sky-700" x-text="questionActionLabel">Buat Soal</button>
                <input type="hidden" name="question_count" :value="questions.length">
                <p id="question-count-help" class="text-xs text-slate-500 sm:col-span-2">Minimal 1, maksimal {{ $maxQuestions }} soal. Menambah jumlah tidak menghapus jawaban yang sudah diisi.</p>
                <p id="question-count-error" x-cloak x-show="countError" x-text="countError" role="alert" class="text-sm font-bold text-rose-700 sm:col-span-2"></p>
            </div>
        </div>
        <div class="mt-5 space-y-5">
            <template x-for="(q, i) in questions" :key="q.key">
                <fieldset class="min-w-0 rounded-2xl border border-slate-200 p-4 sm:p-5" data-question-card>
                    <legend class="px-2 text-lg font-black" x-text="`Soal ${i + 1}`"></legend>
                    <p x-show="questionError(i)" x-text="questionError(i)" class="mb-3 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"></p>
                    <label class="block text-sm font-bold text-slate-700">
                        Pertanyaan
                        <textarea :name="`questions[${i}][Question_Text]`" x-model="q.Question_Text" rows="3" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Tulis pertanyaan..."></textarea>
                    </label>
                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-black text-slate-800">Gambar soal</h4>
                            <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG, atau WebP · maksimal 7 MB · maksimal 4096×4096 piksel.</p>
                            <input type="hidden" :name="`questions[${i}][Image_Token]`" :value="q.Image_Token || ''">
                            <input type="hidden" :name="`questions[${i}][Remove_Image]`" :value="q.Remove_Image ? 1 : 0">
                            <div class="mt-3 flex min-w-0 flex-wrap gap-2">
                                <button type="button" @click="$event.currentTarget.nextElementSibling.click()" :disabled="q.Image_Uploading" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50"><x-sidebar.icon name="photo" class="h-5 w-5" /><span x-text="q.Image_URL ? 'Ganti Gambar' : 'Pilih Gambar'"></span></button>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" @change="uploadMedia(q, 'image', $event)" :disabled="q.Image_Uploading" class="sr-only" aria-label="Pilih gambar soal">
                                <span x-show="q.Image_FileName" x-text="q.Image_FileName" class="min-w-0 self-center break-all text-xs text-slate-600"></span>
                            </div>
                            <div x-show="q.Image_Uploading" class="mt-3"><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-sky-500" :style="`width:${q.Image_Progress}%`"></div></div><p class="mt-1 text-xs text-sky-700">Mengunggah <span x-text="q.Image_Progress"></span>%</p></div>
                            <p x-show="q.Image_Warning" x-text="q.Image_Warning" class="mt-2 text-xs font-semibold text-amber-700"></p>
                            <p x-show="q.Image_Error" class="mt-2 text-xs font-bold text-rose-700"><span x-text="q.Image_Error"></span><span x-show="q.Image_Error_Code" x-text="` (Kode: ${q.Image_Error_Code})`"></span></p>
                            <div x-show="q.Image_URL" class="mt-3 space-y-2">
                                <img :src="q.Image_URL" x-on:error="previewFailed(q, 'image')" alt="Preview gambar soal" class="max-h-64 w-full rounded-xl border border-slate-200 object-contain">
                                <div class="flex items-center justify-between gap-2 text-xs"><span x-text="formatBytes(q.Image_Size_Bytes)"></span><button type="button" @click="removeMedia(q, 'image')" class="min-h-11 px-2 font-bold text-rose-700">Hapus gambar</button></div>
                            </div>
                        </section>
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-black text-slate-800">Audio listening</h4>
                            <p class="mt-1 text-xs text-slate-500">MP3 atau M4A · maksimal 01:30 · maksimal 5 MB. Siswa dapat memutar ulang tanpa batas.</p>
                            <input type="hidden" :name="`questions[${i}][Audio_Token]`" :value="q.Audio_Token || ''">
                            <input type="hidden" :name="`questions[${i}][Remove_Audio]`" :value="q.Remove_Audio ? 1 : 0">
                            <div class="mt-3 flex min-w-0 flex-wrap gap-2">
                                <button type="button" @click="$event.currentTarget.nextElementSibling.click()" :disabled="q.Audio_Uploading || q.Recording_Active" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50"><x-sidebar.icon name="musical-note" class="h-5 w-5" /><span x-text="q.Audio_URL ? 'Ganti Audio' : 'Pilih Audio'"></span></button>
                                <input type="file" accept=".mp3,.m4a,audio/mpeg,audio/mp4,audio/x-m4a" @change="uploadMedia(q, 'audio', $event)" :disabled="q.Audio_Uploading || q.Recording_Active" class="sr-only" aria-label="Pilih audio soal">
                                <button type="button" x-show="!q.Recording_Active" @click="startRecording(q)" :disabled="q.Audio_Uploading" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-black text-rose-700 disabled:opacity-50"><x-sidebar.icon name="microphone" class="h-5 w-5" /><span x-text="q.Audio_URL ? 'Rekam Ulang' : 'Rekam Suara'"></span></button>
                                <button type="button" x-show="q.Recording_Active" @click="stopRecording(q)" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-sm font-black text-white"><span class="h-3 w-3 animate-pulse rounded-full bg-white"></span>Berhenti Merekam</button>
                            </div>
                            <div x-show="q.Recording_Active" role="status" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-800">Merekam <span x-text="formatDuration(q.Recording_Elapsed_Milliseconds)"></span> / 01:30</div>
                            <p x-show="!recordingSupported" class="mt-2 text-xs text-slate-500">Browser ini tidak mendukung perekaman suara. Gunakan tombol Pilih Audio.</p>
                            <p x-show="q.Audio_FileName" class="mt-2 min-w-0 break-all text-xs text-slate-600">File: <span x-text="q.Audio_FileName"></span></p>
                            <div x-show="q.Audio_Uploading" class="mt-3"><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-sky-500" :style="`width:${q.Audio_Progress}%`"></div></div><p class="mt-1 text-xs text-sky-700"><span x-text="q.Audio_Processing ? 'Memproses rekaman' : 'Mengunggah audio'"></span> <span x-text="q.Audio_Progress"></span>%</p></div>
                            <p x-show="q.Audio_Warning" x-text="q.Audio_Warning" class="mt-2 text-xs font-semibold text-amber-700"></p>
                            <p x-show="q.Audio_Error" class="mt-2 text-xs font-bold text-rose-700"><span x-text="q.Audio_Error"></span><span x-show="q.Audio_Error_Code" x-text="` (Kode: ${q.Audio_Error_Code})`"></span></p>
                            <div x-show="q.Audio_URL" class="mt-3 space-y-2">
                                <audio :src="q.Audio_URL" x-on:error="previewFailed(q, 'audio')" controls preload="metadata" class="w-full"></audio>
                                <div class="flex items-center justify-between gap-2 text-xs"><span><span x-text="formatDuration(q.Audio_Duration_Milliseconds)"></span> · <span x-text="formatBytes(q.Audio_Size_Bytes)"></span></span><button type="button" @click="removeMedia(q, 'audio')" class="min-h-11 px-2 font-bold text-rose-700">Hapus audio</button></div>
                            </div>
                        </section>
                    </div>
                    <div class="mt-5">
                        <p class="text-sm font-black text-slate-800">Pilihan Jawaban</p>
                        <div class="mt-3 space-y-3">
                            <template x-for="letter in ['A', 'B', 'C', 'D']" :key="`${q.key}-${letter}`">
                                <label class="grid min-w-0 grid-cols-[2.75rem_minmax(0,1fr)] items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 font-black text-sky-800" x-text="letter"></span>
                                    <span class="sr-only" x-text="`Pilihan ${letter}`"></span>
                                    <input :name="`questions[${i}][Option_${letter}]`" x-model="q[`Option_${letter}`]" :placeholder="`Masukkan pilihan ${letter}...`" class="min-h-11 min-w-0 w-full rounded-xl border-slate-300" data-option-input>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700">Jawaban Benar
                            <select :name="`questions[${i}][Correct_Option]`" x-model="q.Correct_Option" class="mt-2 min-h-11 w-full rounded-xl border-slate-300"><option>A</option><option>B</option><option>C</option><option>D</option></select>
                        </label>
                        <label class="text-sm font-bold text-slate-700">Poin
                            <input type="number" min="0.01" step="0.01" :name="`questions[${i}][Point]`" x-model.number="q.Point" class="mt-2 min-h-11 w-full rounded-xl border-slate-300">
                        </label>
                    </div>
                    <label class="mt-5 block text-sm font-bold text-slate-700">Pembahasan (opsional)
                        <textarea :name="`questions[${i}][Explanation]`" x-model="q.Explanation" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Pembahasan ditampilkan setelah attempt selesai."></textarea>
                    </label>
                </fieldset>
            </template>
        </div>
    </section>
    <div class="rounded-xl border p-4 text-sm" :class="totalMediaBytes > Number(mediaConfig.aggregate_warning_bytes) ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-700'"><strong>Total media: <span x-text="formatBytes(totalMediaBytes)"></span></strong><span> / <span x-text="formatBytes(Number(mediaConfig.aggregate_max_bytes))"></span></span><p x-show="totalMediaBytes > Number(mediaConfig.aggregate_warning_bytes)" class="mt-1 text-xs font-bold">Ukuran media sudah melewati 50 MB. Perhatikan waktu muat siswa.</p></div>
    <div class="sticky bottom-20 z-20 flex gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur md:bottom-4"><button name="intent" value="draft" :disabled="uploadingCount > 0" class="min-h-12 flex-1 rounded-xl border border-slate-300 font-black disabled:opacity-50">Simpan Draft</button><button name="intent" value="publish" :disabled="uploadingCount > 0" class="min-h-12 flex-1 rounded-xl bg-sky-600 font-black text-white disabled:opacity-50">Terbitkan</button></div>
</form>
@push('scripts')
<script>
function quizBuilder(seed, maxQuestions, uploadUrl, csrfToken, mediaConfig, validationErrors, selectedClassIds, availableClassIds) {
    const fresh = () => ({
        key: `${Date.now()}-${Math.random()}`,
        Question_Text: '',
        Option_A: '',
        Option_B: '',
        Option_C: '',
        Option_D: '',
        Correct_Option: 'A',
        Point: 10,
        Explanation: '',
        Image_Token: '', Image_URL: null, Image_Size_Bytes: 0, Image_FileName: '', Remove_Image: false, Image_Uploading: false, Image_Progress: 0, Image_Warning: '', Image_Error: '', Image_Error_Code: '', Image_Request: 0, Image_Xhr: null,
        Audio_Token: '', Audio_URL: null, Audio_Size_Bytes: 0, Audio_FileName: '', Audio_Duration_Milliseconds: 0, Remove_Audio: false, Audio_Uploading: false, Audio_Processing: false, Audio_Progress: 0, Audio_Warning: '', Audio_Error: '', Audio_Error_Code: '', Audio_Request: 0, Audio_Xhr: null,
        Recording_Active: false, Recording_Discard: false, Recording_Elapsed_Milliseconds: 0, Recording_Recorder: null, Recording_Stream: null, Recording_Timer: null, Recording_Chunks: [],
    });
    const normalize = (question, index) => {
        const normalized = {
            ...fresh(),
            ...question,
            key: question.Question_ID || `${Date.now()}-${index}-${Math.random()}`,
        };
        if (normalized.Image_Token) normalized.Image_URL = `${uploadUrl}/${encodeURIComponent(normalized.Image_Token)}`;
        if (normalized.Audio_Token) normalized.Audio_URL = `${uploadUrl}/${encodeURIComponent(normalized.Audio_Token)}`;
        if (normalized.Remove_Image) { normalized.Image_URL = null; normalized.Image_Size_Bytes = 0; }
        if (normalized.Remove_Audio) { normalized.Audio_URL = null; normalized.Audio_Size_Bytes = 0; normalized.Audio_Duration_Milliseconds = 0; }
        return normalized;
    };

    const initialQuestions = seed.length ? seed.map(normalize) : [fresh()];

    return {
        questions: initialQuestions,
        requestedCount: initialQuestions.length,
        maxQuestions,
        uploadUrl,
        csrfToken,
        mediaConfig,
        validationErrors,
        selectedClassIds,
        availableClassIds,
        countError: '',
        recordingSupported: Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.MediaRecorder),
        get uploadingCount() { return this.questions.filter(q => q.Image_Uploading || q.Audio_Uploading).length; },
        get recordingCount() { return this.questions.filter(q => q.Recording_Active).length; },
        get totalMediaBytes() { return this.questions.reduce((total, q) => total + Number(q.Image_Size_Bytes || 0) + Number(q.Audio_Size_Bytes || 0), 0); },
        get maximum() {
            return this.questions.reduce((total, question) => total + (Number(question.Point) || 0), 0);
        },
        get questionActionLabel() {
            const count = Number(this.requestedCount);
            return Number.isInteger(count) ? `Buat ${count} Soal` : 'Buat Soal';
        },
        init() {
            window.addEventListener('beforeunload', () => this.questions.forEach(q => this.releaseRecording(q)));
        },
        applyQuestionCount() {
            const target = Number(this.requestedCount);
            if (!Number.isInteger(target) || target < 1 || target > this.maxQuestions) {
                this.countError = `Jumlah soal harus berupa bilangan bulat antara 1 dan ${this.maxQuestions}.`;
                return;
            }

            this.countError = '';
            if (target < this.questions.length) {
                const removed = this.questions.length - target;
                const confirmed = window.confirm(`Kurangi jumlah soal menjadi ${target}? Data pada ${removed} soal terakhir akan dihapus dari formulir.`);
                if (!confirmed) {
                    this.requestedCount = this.questions.length;
                    return;
                }
                const removedQuestions = this.questions.splice(target);
                removedQuestions.forEach(q => {
                    ['Image', 'Audio'].forEach(prefix => {
                        q[`${prefix}_Request`] = Number(q[`${prefix}_Request`] || 0) + 1;
                        q[`${prefix}_Xhr`]?.abort();
                    });
                    this.cancelRecording(q);
                });
            } else {
                while (this.questions.length < target) {
                    this.questions.push(fresh());
                }
            }
            this.requestedCount = this.questions.length;
        },
        formatBytes(bytes) {
            const value = Number(bytes) || 0;
            if (value < 1024) return `${value} B`;
            if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
            return `${(value / 1024 / 1024).toFixed(2)} MB`;
        },
        questionError(index) {
            const prefix = `questions.${index}.`;
            const key = Object.keys(this.validationErrors || {}).find(key => key.startsWith(prefix));
            return key ? (this.validationErrors[key][0] || '') : '';
        },
        formatDuration(ms) {
            const seconds = Math.ceil((Number(ms) || 0) / 1000);
            return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        },
        removeMedia(q, type) {
            const prefix = type === 'image' ? 'Image' : 'Audio';
            q[`${prefix}_Request`] = Number(q[`${prefix}_Request`] || 0) + 1;
            q[`${prefix}_Xhr`]?.abort();
            q[`${prefix}_Xhr`] = null;
            q[`${prefix}_Uploading`] = false;
            if (type === 'audio') {
                this.cancelRecording(q);
                q.Audio_Processing = false;
            }
            q[`${prefix}_Token`] = '';
            q[`${prefix}_URL`] = null;
            q[`${prefix}_Size_Bytes`] = 0;
            q[`${prefix}_FileName`] = '';
            q[`Remove_${prefix}`] = true;
            q[`${prefix}_Error`] = '';
            q[`${prefix}_Error_Code`] = '';
            q[`${prefix}_Warning`] = '';
            if (type === 'audio') { q.Audio_Duration_Milliseconds = 0; q.Audio_Warning = ''; }
        },
        async browserValidate(file, type) {
            const max = Number(type === 'image' ? this.mediaConfig.image_max_bytes : this.mediaConfig.audio_max_bytes);
            if (file.size > max) throw new Error(type === 'image' ? 'Ukuran file gambar maksimal 7 MB.' : 'Ukuran file audio maksimal 5 MB.');
            let url = '';
            try {
                url = URL.createObjectURL(file);
                if (type === 'image') {
                    const dimensions = await new Promise((resolve, reject) => { const img = new Image(); img.onload = () => resolve([img.naturalWidth, img.naturalHeight]); img.onerror = reject; img.src = url; });
                    if (dimensions[0] > Number(this.mediaConfig.image_max_width) || dimensions[1] > Number(this.mediaConfig.image_max_height)) throw new Error('Dimensi gambar maksimal 4096×4096 piksel.');
                } else {
                    const duration = await new Promise((resolve, reject) => { const audio = new Audio(); audio.preload = 'metadata'; audio.onloadedmetadata = () => resolve(audio.duration); audio.onerror = reject; audio.src = url; });
                    if (!Number.isFinite(duration) || duration <= 0) return 'Durasi audio tidak dapat dibaca browser. File tetap diperiksa oleh server.';
                    if (duration * 1000 > Number(this.mediaConfig.audio_max_duration_ms)) return 'Metadata browser menunjukkan durasi di atas 01:30. File tetap diperiksa oleh server.';
                }
            } catch (error) {
                if (type === 'audio') return 'Durasi audio tidak dapat dibaca browser. File tetap diperiksa oleh server.';
                throw error;
            } finally { if (url) URL.revokeObjectURL(url); }
            return '';
        },
        async uploadMedia(q, type, event) {
            const input = event.target;
            const file = input.files && input.files[0];
            if (!file) return;
            try {
                await this.uploadFile(q, type, file, 'upload');
            } finally { input.value = ''; }
        },
        errorMessage(status, payload) {
            const validation = payload?.errors?.file;
            if (Array.isArray(validation) && validation[0]) return validation[0];
            if (typeof validation === 'string' && validation) return validation;
            if (payload?.message && status === 422) return payload.message;
            const messages = {
                401: 'Sesi login berakhir. Silakan login kembali.',
                403: 'Anda tidak memiliki akses untuk mengunggah media ini.',
                409: 'Media berubah karena permintaan lain. Silakan pilih file kembali.',
                410: 'Media staging sudah kedaluwarsa. Silakan unggah kembali.',
                413: 'Ukuran request terlalu besar untuk server.',
                419: 'Sesi atau token keamanan berakhir. Muat ulang halaman lalu coba lagi.',
                423: 'Media sedang diproses oleh permintaan lain. Silakan coba kembali.',
                429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
                500: 'Terjadi kesalahan internal saat memproses media.',
                503: 'Layanan pemrosesan audio sementara tidak tersedia.',
                504: 'Pemrosesan media terlalu lama. Silakan coba kembali.',
            };
            return messages[status] || payload?.message || `Upload media gagal (HTTP ${status}).`;
        },
        uploadFailure(reasonCode, message, status = 0, requestId = '') {
            const error = new Error(message);
            error.reasonCode = reasonCode;
            error.status = status;
            error.requestId = requestId;
            return error;
        },
        requestId() {
            return window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        },
        async snapshotAudioFile(file) {
            const size = Number(file?.size);
            if (!Number.isFinite(size) || size <= 0) {
                throw this.uploadFailure('QMU-FILE-EMPTY', 'File audio kosong. Silakan pilih file lain.');
            }
            if (size > Number(this.mediaConfig.audio_max_bytes)) {
                throw new Error('Ukuran file audio maksimal 5 MB.');
            }

            let bytes;
            try {
                if (typeof file.arrayBuffer === 'function') {
                    bytes = await file.arrayBuffer();
                } else {
                    bytes = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => reject(reader.error || new Error('FileReader gagal membaca audio.'));
                        reader.onabort = () => reject(new DOMException('Pembacaan file dibatalkan.', 'AbortError'));
                        reader.readAsArrayBuffer(file);
                    });
                }
            } catch (error) {
                const accessFailure = ['NotAllowedError', 'NotReadableError', 'SecurityError'].includes(error?.name);
                throw this.uploadFailure(
                    accessFailure ? 'QMU-FILE-ACCESS' : 'QMU-FILE-READ',
                    accessFailure
                        ? 'Browser tidak dapat mengakses file audio. Pilih ulang file dari penyimpanan perangkat.'
                        : 'File audio tidak dapat dibaca. Silakan pilih ulang atau gunakan file lain.',
                );
            }

            if (!(bytes instanceof ArrayBuffer) || bytes.byteLength === 0) {
                throw this.uploadFailure('QMU-FILE-EMPTY', 'File audio kosong. Silakan pilih file lain.');
            }
            if (bytes.byteLength !== size) {
                throw this.uploadFailure('QMU-FILE-READ', 'File audio tidak terbaca lengkap. Silakan pilih ulang file.');
            }

            try {
                return new File([bytes], file.name || 'audio', {
                    type: file.type || '',
                    lastModified: Number(file.lastModified) || Date.now(),
                });
            } catch (_) {
                throw this.uploadFailure('QMU-FILE-READ', 'File audio tidak dapat disiapkan untuk upload. Silakan pilih ulang file.');
            }
        },
        validateStagingResponse(payload, type, requestId) {
            if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
                throw this.uploadFailure('QMU-SCHEMA', 'Server mengirim respons upload yang tidak lengkap.', 201, requestId);
            }
            const token = typeof payload.token === 'string' ? payload.token.trim() : '';
            const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
            const size = Number(payload.size);
            const duration = Number(payload.duration_ms);
            let preview;
            try { preview = new URL(payload.preview_url, window.location.href); } catch (_) {}
            const validDuration = type !== 'audio' || (Number.isFinite(duration) && duration > 0);
            if (!uuid.test(token) || payload.type !== type || !Number.isFinite(size) || size <= 0
                || !preview || preview.origin !== window.location.origin || !validDuration) {
                throw this.uploadFailure('QMU-SCHEMA', 'Server mengirim respons upload yang tidak lengkap.', 201, requestId);
            }

            return {...payload, token, size, duration_ms: type === 'audio' ? duration : null, preview_url: preview.href};
        },
        requestUpload(q, prefix, body, type, requestId) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.uploadUrl);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('X-Request-ID', requestId);
                xhr.timeout = 45000;
                q[`${prefix}_Xhr`] = xhr;
                xhr.upload.onprogress = event => {
                    if (event.lengthComputable) q[`${prefix}_Progress`] = Math.round((event.loaded / event.total) * 100);
                };
                xhr.onload = () => {
                    let payload;
                    try {
                        payload = JSON.parse(xhr.responseText);
                    } catch (_) {
                        reject(this.uploadFailure('QMU-JSON', 'Respons upload dari server tidak dapat dibaca.', xhr.status, requestId));
                        return;
                    }
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(this.uploadFailure(`QMU-HTTP-${xhr.status || 0}`, this.errorMessage(xhr.status, payload), xhr.status, requestId));
                        return;
                    }
                    try {
                        resolve(this.validateStagingResponse(payload, type, requestId));
                    } catch (error) {
                        reject(error);
                    }
                };
                xhr.onerror = () => reject(this.uploadFailure('QMU-NETWORK', 'Koneksi terputus. Silakan coba kembali.', 0, requestId));
                xhr.ontimeout = () => reject(this.uploadFailure('QMU-TIMEOUT', 'Pemrosesan media terlalu lama. Silakan coba kembali.', 0, requestId));
                xhr.onabort = () => reject(this.uploadFailure('QMU-ABORTED', 'Upload digantikan oleh file yang lebih baru.', 0, requestId));
                try {
                    xhr.send(body);
                } catch (_) {
                    reject(this.uploadFailure('QMU-REQUEST-SEND', 'Browser gagal memulai upload media. Silakan pilih ulang file.', 0, requestId));
                }
            });
        },
        previewFailed(q, type) {
            const prefix = type === 'image' ? 'Image' : 'Audio';
            if (!q[`${prefix}_Token`]) return;
            q[`${prefix}_Warning`] = 'Upload berhasil, tetapi preview media belum dapat dimuat. Media tetap tersimpan untuk penerbitan.';
            console.warn('quiz_media_preview_failure', {reason_code: 'QMU-PREVIEW', media_type: type});
        },
        async uploadFile(q, type, file, source = 'upload') {
            const prefix = type === 'image' ? 'Image' : 'Audio';
            const request = Number(q[`${prefix}_Request`] || 0) + 1;
            q[`${prefix}_Request`] = request;
            if (q[`${prefix}_Xhr`]) q[`${prefix}_Xhr`].abort();
            q[`${prefix}_Error`] = '';
            q[`${prefix}_Error_Code`] = '';
            q[`${prefix}_Warning`] = '';
            q[`${prefix}_Uploading`] = true;
            if (type === 'audio') q.Audio_Processing = source === 'recording';
            q[`${prefix}_Progress`] = 0;
            try {
                const uploadableFile = type === 'audio' && source === 'upload'
                    ? await this.snapshotAudioFile(file)
                    : file;
                const browserWarning = await this.browserValidate(uploadableFile, type);
                if (type === 'audio') q.Audio_Warning = browserWarning;
                const body = new FormData();
                body.append('media_type', type);
                body.append('source', source);
                body.append('file', uploadableFile, uploadableFile.name || (source === 'recording' ? 'rekaman-suara.m4a' : 'media'));
                const requestId = this.requestId();
                const data = await this.requestUpload(q, prefix, body, type, requestId);
                if (!this.questions.includes(q) || q[`${prefix}_Request`] !== request) return;
                q[`${prefix}_Token`] = data.token;
                q[`${prefix}_Size_Bytes`] = data.size;
                q[`${prefix}_FileName`] = file.name || (source === 'recording' ? 'rekaman-suara.m4a' : '');
                q[`Remove_${prefix}`] = false;
                if (type === 'audio') q.Audio_Duration_Milliseconds = data.duration_ms;
                try {
                    q[`${prefix}_URL`] = data.preview_url;
                } catch (_) {
                    q[`${prefix}_Warning`] = 'Upload berhasil, tetapi preview media belum dapat ditampilkan. Media tetap tersimpan untuk penerbitan.';
                    console.warn('quiz_media_ui_failure', {reason_code: 'QMU-UI', media_type: type, source});
                }
            } catch (error) {
                if (error?.reasonCode !== 'QMU-ABORTED' && q[`${prefix}_Request`] === request) {
                    q[`${prefix}_Error`] = error?.message || 'Upload media gagal.';
                    q[`${prefix}_Error_Code`] = error?.reasonCode || 'QMU-UI';
                    console.warn('quiz_media_upload_failure', {
                        reason_code: q[`${prefix}_Error_Code`],
                        media_type: type,
                        source,
                        status: Number(error?.status || 0),
                        request_id: error?.requestId || '',
                    });
                }
            } finally {
                if (q[`${prefix}_Request`] === request) {
                    q[`${prefix}_Uploading`] = false;
                    q[`${prefix}_Xhr`] = null;
                    if (type === 'audio') q.Audio_Processing = false;
                }
            }
        },
        recordingMime() {
            return ['audio/mp4;codecs=mp4a.40.2', 'audio/webm;codecs=opus', 'audio/ogg;codecs=opus']
                .find(type => MediaRecorder.isTypeSupported(type)) || '';
        },
        recordingExtension(mime) {
            if (mime.includes('mp4')) return 'm4a';
            if (mime.includes('ogg')) return 'ogg';
            return 'webm';
        },
        async startRecording(q) {
            if (!this.recordingSupported) { q.Audio_Error = 'Browser ini tidak mendukung perekaman suara.'; return; }
            if (q.Recording_Active || q.Audio_Uploading) return;
            q.Audio_Error = ''; q.Audio_Warning = ''; q.Recording_Discard = false;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({audio: true, video: false});
                const requestedMime = this.recordingMime();
                const recorder = requestedMime ? new MediaRecorder(stream, {mimeType: requestedMime}) : new MediaRecorder(stream);
                q.Recording_Stream = stream; q.Recording_Recorder = recorder; q.Recording_Chunks = [];
                q.Recording_Elapsed_Milliseconds = 0; q.Recording_Active = true;
                const startedAt = Date.now();
                recorder.addEventListener('dataavailable', event => { if (event.data?.size) q.Recording_Chunks.push(event.data); });
                recorder.addEventListener('error', () => {
                    q.Recording_Discard = true;
                    q.Audio_Error = 'Rekaman suara gagal. Silakan rekam ulang.';
                    this.releaseRecording(q);
                });
                recorder.addEventListener('stop', async () => {
                    clearInterval(q.Recording_Timer); q.Recording_Timer = null; q.Recording_Active = false;
                    const actualMime = recorder.mimeType || q.Recording_Chunks[0]?.type || requestedMime;
                    const blob = new Blob(q.Recording_Chunks, {type: actualMime});
                    const discard = q.Recording_Discard;
                    this.releaseRecording(q); q.Recording_Chunks = [];
                    if (discard) return;
                    if (!blob.size) { q.Audio_Error = 'Rekaman kosong. Silakan rekam ulang.'; return; }
                    const extension = this.recordingExtension(actualMime);
                    await this.uploadFile(q, 'audio', new File([blob], `rekaman-${Date.now()}.${extension}`, {type: actualMime}), 'recording');
                }, {once: true});
                recorder.start(250);
                q.Recording_Timer = setInterval(() => {
                    q.Recording_Elapsed_Milliseconds = Date.now() - startedAt;
                    if (q.Recording_Elapsed_Milliseconds >= 89500) this.stopRecording(q);
                }, 250);
            } catch (error) {
                this.releaseRecording(q);
                q.Audio_Error = error?.name === 'NotAllowedError'
                    ? 'Izin mikrofon belum diberikan.'
                    : error?.name === 'NotFoundError'
                        ? 'Mikrofon tidak tersedia pada perangkat ini.'
                        : 'Mikrofon tidak dapat digunakan. Tutup aplikasi lain yang sedang memakainya lalu coba lagi.';
            }
        },
        stopRecording(q) {
            if (q.Recording_Recorder?.state === 'recording') q.Recording_Recorder.stop();
        },
        cancelRecording(q) {
            q.Recording_Discard = true;
            const recorder = q.Recording_Recorder;
            if (recorder?.state === 'recording') recorder.stop();
            this.releaseRecording(q);
            q.Recording_Chunks = [];
        },
        releaseRecording(q) {
            clearInterval(q.Recording_Timer); q.Recording_Timer = null;
            q.Recording_Stream?.getTracks().forEach(track => track.stop());
            q.Recording_Stream = null; q.Recording_Recorder = null; q.Recording_Active = false;
        },
    };
}
</script>
@endpush
@endsection
