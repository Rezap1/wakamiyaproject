@extends('layouts.app')
@section('header', 'Kerjakan Kuis')
@section('content')
<div
    class="mx-auto max-w-3xl pb-32"
    x-data="quizPlayer(
        @js($questions),
        @js($attempt['Deadline_At']),
        @js($savedAnswers ?? []),
        {{ (int) ($answerVersion ?? 0) }},
        @js(route('student.quizzes.autosave', $attempt['Attempt_ID'])),
        @js(route('student.quizzes.submit', $attempt['Attempt_ID'])),
        @js(csrf_token())
    )"
    x-init="start"
>
    <div class="sticky top-16 z-20 mb-4 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur lg:top-4">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-sky-600" x-text="`${index + 1} dari ${questions.length}`"></p>
                <h2 class="line-clamp-1 font-black">{{ $quiz['Title'] }}</h2>
                <p class="mt-1 text-xs font-bold" :class="saveError ? 'text-rose-700' : (dirty || saving ? 'text-amber-700' : 'text-emerald-700')" x-text="saveStatus" aria-live="polite"></p>
            </div>
            <div role="timer" aria-live="polite" class="shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-center text-white">
                <span class="block text-[10px] font-bold uppercase">Sisa waktu</span>
                <strong class="font-mono text-xl" x-text="clock"></strong>
            </div>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-sky-500 transition-all" :style="`width:${((index + 1) / questions.length) * 100}%`"></div></div>
    </div>

    <div x-show="saveError" x-cloak role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">
        <span x-text="saveError"></span>
        <button type="button" class="ml-2 underline" @click="retrySave" x-show="!finalizing && !saveBlocked">Coba simpan lagi</button>
    </div>
    <div x-show="finalizing" x-cloak role="status" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm font-bold text-sky-800" x-text="finalizeStatus"></div>

    <form method="POST" action="{{ route('student.quizzes.submit', $attempt['Attempt_ID']) }}" @submit.prevent="finalize(false)">
        @csrf
        <input type="hidden" name="answer_version" :value="serverVersion">
        <template x-for="(q, i) in questions" :key="q.Question_ID">
            <fieldset x-show="index === i" class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                <legend class="sr-only" x-text="`Soal ${i + 1}`"></legend>
                <p class="text-lg font-black leading-relaxed" x-text="q.Question_Text"></p>
                <div class="mt-6 space-y-3">
                    <template x-for="letter in ['A', 'B', 'C', 'D']" :key="`${q.Question_ID}-${letter}`">
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-2 p-4 transition focus-within:ring-2 focus-within:ring-sky-500" :class="answers[q.Question_ID] === letter ? 'border-sky-500 bg-sky-50' : 'border-slate-200'">
                            <input type="radio" :name="`answers[${q.Question_ID}]`" :value="letter" x-model="answers[q.Question_ID]" @change="answerChanged" :disabled="finalizing" class="h-5 w-5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-black" x-text="letter"></span>
                            <span class="font-semibold" x-text="q[`Option_${letter}`]"></span>
                        </label>
                    </template>
                </div>
            </fieldset>
        </template>

        <div class="mt-4 flex gap-3">
            <button type="button" @click="index--" :disabled="index === 0 || finalizing" class="min-h-12 flex-1 rounded-xl border border-slate-300 bg-white font-black disabled:opacity-40">Sebelumnya</button>
            <button type="button" x-show="index < questions.length - 1" @click="index++" :disabled="finalizing" class="min-h-12 flex-1 rounded-xl bg-sky-600 font-black text-white disabled:opacity-40">Berikutnya</button>
            <button type="submit" x-show="index === questions.length - 1" :disabled="finalizing" class="min-h-12 flex-1 rounded-xl bg-emerald-600 font-black text-white disabled:opacity-50" x-text="finalizing ? 'Memfinalisasi...' : 'Kirim Jawaban'">Kirim Jawaban</button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2" aria-label="Status jawaban">
            <template x-for="(q, i) in questions" :key="`nav-${q.Question_ID}`">
                <button type="button" @click="index = i" :disabled="finalizing" class="h-10 w-10 rounded-lg border font-bold" :class="answers[q.Question_ID] ? 'border-emerald-400 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-500'" :aria-label="`Soal ${i + 1}, ${answers[q.Question_ID] ? 'sudah' : 'belum'} dijawab`" x-text="i + 1"></button>
            </template>
        </div>
    </form>
</div>
@push('scripts')
<script>
function quizPlayer(questions, deadline, initialAnswers, initialVersion, autosaveUrl, submitUrl, csrfToken) {
    return {
        questions,
        index: 0,
        answers: {...initialAnswers},
        serverVersion: Number(initialVersion) || 0,
        autosaveUrl,
        submitUrl,
        csrfToken,
        clock: '--:--',
        timer: null,
        saveTimer: null,
        retryTimer: null,
        dirty: false,
        saving: false,
        savePromise: null,
        saveError: '',
        saveBlocked: false,
        finalizing: false,
        finalizeStatus: '',
        initialized: false,
        get saveStatus() {
            if (this.saveError) return 'Jawaban belum terkonfirmasi tersimpan';
            if (this.saving) return 'Menyimpan jawaban...';
            if (this.dirty) return 'Perubahan belum tersimpan';
            return 'Jawaban tersimpan di server';
        },
        start() {
            if (this.initialized) return;
            this.initialized = true;
            this.startTimer();
            window.addEventListener('online', () => {
                if (this.dirty && !this.finalizing) this.retrySave();
            });
            window.addEventListener('beforeunload', event => {
                if (this.dirty || this.saving) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },
        answerChanged() {
            if (this.finalizing) return;
            this.dirty = true;
            if (this.saveBlocked) return;
            this.saveError = '';
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.flushSave(), 650);
        },
        retrySave() {
            if (this.saveBlocked) return false;
            this.saveError = '';
            clearTimeout(this.retryTimer);
            return this.flushSave();
        },
        async flushSave() {
            clearTimeout(this.saveTimer);
            if (this.saveBlocked) return false;
            if (!this.dirty) return !this.saveError;
            if (this.saving) {
                await this.savePromise;
                return this.dirty ? this.flushSave() : !this.saveError;
            }

            const snapshot = {...this.answers};
            const version = this.serverVersion + 1;
            this.dirty = false;
            this.saving = true;
            this.savePromise = (async () => {
              try {
                const response = await fetch(this.autosaveUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({version, answers: snapshot}),
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 401 || response.status === 419) {
                    this.dirty = true;
                    this.saveBlocked = true;
                    this.saveError = 'Sesi berakhir. Login kembali untuk memulihkan jawaban yang sudah tersimpan di server.';
                    return false;
                }
                if (response.status === 409) {
                    this.serverVersion = Math.max(this.serverVersion, Number(data.version) || 0);
                    this.dirty = true;
                    this.saveBlocked = true;
                    this.saveError = 'Jawaban berubah dari tab atau perangkat lain. Muat ulang halaman untuk memakai versi server.';
                    return false;
                }
                if (!response.ok || !data.saved) {
                    throw new Error(data.message || 'Server belum mengonfirmasi penyimpanan jawaban.');
                }
                this.serverVersion = Number(data.version);
                this.saveBlocked = false;
                this.saveError = '';
                return true;
              } catch (error) {
                this.dirty = true;
                this.saveError = error.message || 'Jawaban belum tersimpan. Periksa koneksi lalu coba lagi.';
                if (!this.finalizing) {
                    clearTimeout(this.retryTimer);
                    this.retryTimer = setTimeout(() => this.retrySave(), 3000);
                }
                return false;
              } finally {
                this.saving = false;
                if (this.dirty && !this.saveError && !this.finalizing) {
                    this.saveTimer = setTimeout(() => this.flushSave(), 250);
                }
              }
            })();

            return await this.savePromise;
        },
        async finalize(automatic) {
            if (this.finalizing) return;
            if (!automatic && !window.confirm('Kirim jawaban final? Jawaban tidak dapat diubah setelah dikirim.')) return;

            this.finalizing = true;
            this.finalizeStatus = 'Menyimpan perubahan terakhir...';
            clearTimeout(this.saveTimer);
            clearTimeout(this.retryTimer);
            const saved = await this.flushSave();
            if (!saved && !automatic && Date.now() < this.deadlineTimestamp()) {
                this.finalizing = false;
                this.finalizeStatus = '';
                return;
            }

            this.finalizeStatus = saved
                ? 'Memfinalisasi jawaban tersimpan...'
                : 'Waktu habis. Memfinalisasi jawaban terakhir yang telah diterima server...';
            try {
                const response = await fetch(this.submitUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({answer_version: this.serverVersion}),
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 401 || response.status === 419) {
                    throw new Error('Sesi berakhir sebelum finalisasi. Login kembali untuk memulihkan hasil dari jawaban tersimpan.');
                }
                if (!response.ok || !data.finalized || !data.result_url) {
                    throw new Error(data.message || 'Finalisasi belum dikonfirmasi server.');
                }
                window.location.assign(data.result_url);
            } catch (error) {
                this.saveError = error.message || 'Finalisasi gagal sementara. Sistem akan mencoba kembali.';
                this.finalizeStatus = 'Finalisasi belum dikonfirmasi server.';
                this.finalizing = false;
                if (Date.now() >= this.deadlineTimestamp()) {
                    clearTimeout(this.retryTimer);
                    this.retryTimer = setTimeout(() => this.finalize(true), 7000);
                }
            }
        },
        deadlineTimestamp() {
            return new Date(deadline.replace(' ', 'T') + '+07:00').getTime();
        },
        startTimer() {
            const tick = () => {
                const left = Math.max(0, this.deadlineTimestamp() - Date.now());
                this.clock = `${String(Math.floor(left / 60000)).padStart(2, '0')}:${String(Math.floor((left % 60000) / 1000)).padStart(2, '0')}`;
                if (left > 0 && left <= 10000 && this.dirty && !this.saving) this.flushSave();
                if (left === 0) {
                    clearInterval(this.timer);
                    this.finalize(true);
                }
            };
            tick();
            this.timer = setInterval(tick, 1000);
        },
    };
}
</script>
@endpush
@endsection
