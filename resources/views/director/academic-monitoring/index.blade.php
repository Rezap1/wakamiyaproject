@extends('layouts.app')

@section('header', 'Monitoring Akademik')

@php
    $context = $monitoring['context'];
    $routeArgs = array_values(array_filter([
        $context['batch']['Batch_ID'] ?? null,
        $context['class']['Class_ID'] ?? null,
        $context['student']['Student_ID'] ?? null,
    ]));
    $dataRoute = match($monitoring['scope']) {
        'batch' => route('director.academic-monitoring.batch.data', $routeArgs),
        'class' => route('director.academic-monitoring.class.data', $routeArgs),
        'student' => route('director.academic-monitoring.student.data', $routeArgs),
        default => route('director.academic-monitoring.data'),
    };
    $pdfRoute = match($monitoring['scope']) {
        'batch' => route('director.academic-monitoring.batch.pdf', $routeArgs),
        'class' => route('director.academic-monitoring.class.pdf', $routeArgs),
        'student' => route('director.academic-monitoring.student.pdf', $routeArgs),
        default => route('director.academic-monitoring.pdf'),
    };
@endphp

@section('content')
<div id="academic-monitoring-root" data-endpoint="{{ $dataRoute }}" class="space-y-5">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-sky-950 p-5 text-white shadow-xl sm:p-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-2 text-xs font-semibold text-sky-200">
                    @foreach($monitoring['breadcrumbs'] as $crumb)
                        @if(!$loop->first)<span aria-hidden="true">/</span>@endif
                        @if($crumb['route'] && !$loop->last)<a class="hover:text-white" href="{{ $crumb['route'] }}">{{ $crumb['label'] }}</a>
                        @else<span class="text-white">{{ $crumb['label'] }}</span>@endif
                    @endforeach
                </nav>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-sky-300">Direktur · Read-only</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                    @if($monitoring['scope'] === 'student') {{ $context['student']['Full_Name'] }}
                    @elseif($monitoring['scope'] === 'class') {{ $context['class']['Class_Name'] }}
                    @elseif($monitoring['scope'] === 'batch') {{ $context['batch']['Batch_Name'] }}
                    @else Perkembangan Akademik WMS @endif
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Statistik monitoring dari nilai akademik final dan Kuis Reguler. Kuis Latihan tidak termasuk.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button id="monitoring-refresh" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-sky-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.5 15A7 7 0 0018 18.5M18.5 9A7 7 0 006 5.5"/></svg>
                    Perbarui
                </button>
                <a href="{{ $pdfRoute }}?{{ http_build_query(request()->query()) }}" class="inline-flex min-h-11 items-center rounded-xl bg-white px-4 py-2 text-sm font-black text-slate-900 hover:bg-sky-50">Export PDF</a>
            </div>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-white/10 pt-4 text-xs text-slate-300">
            <span id="monitoring-status" class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Pembaruan otomatis setiap 60 detik saat tab aktif</span>
            <span>Terakhir diperbarui: <strong id="monitoring-updated" class="text-white">{{ $monitoring['updated_label'] }}</strong></span>
        </div>
    </section>

    <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter monitoring akademik">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="text-xs font-bold text-slate-600">Agregasi
                <select name="period" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm">
                    @foreach(['daily'=>'Harian','weekly'=>'Mingguan','monthly'=>'Bulanan','all'=>'Keseluruhan'] as $value=>$label)
                        <option value="{{ $value }}" @selected($monitoring['filters']['period']===$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Dari
                <input type="date" name="start_date" value="{{ $monitoring['filters']['start_date'] }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm">
            </label>
            <label class="text-xs font-bold text-slate-600">Sampai
                <input type="date" name="end_date" value="{{ $monitoring['filters']['end_date'] }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm">
            </label>
            <label class="text-xs font-bold text-slate-600">Jenis penilaian
                <select name="category" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Semua kategori</option>
                    @foreach($monitoring['categories'] as $id=>$label)<option value="{{ $id }}" @selected($monitoring['filters']['category']===$id)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <div class="flex items-end"><button class="min-h-11 w-full rounded-lg bg-sky-600 px-4 text-sm font-black text-white hover:bg-sky-700">Terapkan</button></div>
            <div class="flex items-end"><a href="{{ url()->current() }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Reset</a></div>
        </div>
    </form>

    <div id="monitoring-error" class="hidden rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900" role="alert"></div>
    <div id="monitoring-dynamic">@include('director.academic-monitoring._content', ['monitoring' => $monitoring])</div>
</div>

<script type="application/json" id="monitoring-chart-data">@json(['trend'=>$monitoring['trend'],'chapter_trend'=>$monitoring['chapter_trend'],'distribution'=>$monitoring['distribution']])</script>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('academic-monitoring-root');
    if (!root) return;
    const state = { busy: false, timer: null, lastSuccess: Date.now(), controller: null, data: JSON.parse(document.getElementById('monitoring-chart-data').textContent) };
    const palette = ['#0284c7','#7c3aed','#059669','#ea580c','#db2777','#475569'];
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    function lineChart(target, chart) {
        const el = document.querySelector(target); if (!el) return;
        const labels = chart?.labels || [], series = chart?.series || [];
        if (!labels.length || !series.some(s => (s.values || []).some(v => v !== null))) {
            el.innerHTML = '<div class="flex h-64 items-center justify-center text-sm font-semibold text-slate-400">Belum ada data pada periode ini.</div>'; return;
        }
        const w=760,h=280,p={l:42,r:16,t:18,b:50}, iw=w-p.l-p.r, ih=h-p.t-p.b;
        const x=i => p.l + (labels.length===1 ? iw/2 : i*iw/(labels.length-1)); const y=v => p.t + (100-Number(v))*ih/100;
        let svg=`<svg viewBox="0 0 ${w} ${h}" class="h-auto w-full" role="img" aria-label="Grafik perkembangan nilai">`;
        [0,25,50,75,100].forEach(v=>{svg+=`<line x1="${p.l}" x2="${w-p.r}" y1="${y(v)}" y2="${y(v)}" stroke="#e2e8f0"/><text x="${p.l-8}" y="${y(v)+4}" text-anchor="end" font-size="10" fill="#64748b">${v}</text>`});
        series.forEach((s,si)=>{let path='',open=false;(s.values||[]).forEach((v,i)=>{if(v===null){open=false;return;} path+=`${open?'L':'M'}${x(i)},${y(v)} `;open=true;});svg+=`<path d="${path}" fill="none" stroke="${palette[si%palette.length]}" stroke-width="3" stroke-linecap="round"/>`; (s.values||[]).forEach((v,i)=>{if(v!==null)svg+=`<circle cx="${x(i)}" cy="${y(v)}" r="4" fill="${palette[si%palette.length]}"><title>${esc(s.label)} · ${esc(labels[i])}: ${esc(v)}</title></circle>`})});
        labels.forEach((label,i)=>{if(labels.length<=12||i%Math.ceil(labels.length/10)===0)svg+=`<text x="${x(i)}" y="${h-24}" text-anchor="middle" font-size="9" fill="#64748b">${esc(String(label).slice(0,14))}</text>`});
        svg+='</svg><div class="mt-2 flex flex-wrap gap-3">'+series.map((s,i)=>`<span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600"><span class="h-2.5 w-2.5 rounded-full" style="background:${palette[i%palette.length]}"></span>${esc(s.label)}</span>`).join('')+'</div>'; el.innerHTML=svg;
    }
    function bars(target, data) {
        const el=document.querySelector(target); if(!el)return; const max=Math.max(1,...(data||[]).map(x=>x.value));
        el.innerHTML=(data||[]).map(row=>`<div class="flex items-center gap-3"><span class="w-14 text-xs font-bold text-slate-500">${esc(row.label)}</span><div class="h-7 flex-1 overflow-hidden rounded-md bg-slate-100"><div class="flex h-full items-center justify-end rounded-md bg-gradient-to-r from-sky-500 to-indigo-500 px-2 text-xs font-black text-white" style="width:${row.value?Math.max(8,row.value/max*100):0}%">${row.value||''}</div></div></div>`).join('');
    }
    function revoke(message) {
        clearInterval(state.timer);
        document.getElementById('monitoring-dynamic').innerHTML='';
        state.data={trend:{labels:[],series:[]},chapter_trend:{labels:[],series:[]},distribution:[]};
        throw new Error(message);
    }
    function draw(){lineChart('[data-chart="trend"]',state.data.trend);lineChart('[data-chart="chapter"]',state.data.chapter_trend);bars('[data-chart="distribution"]',state.data.distribution)}
    async function refresh(manual=false){
        if(state.busy||document.hidden)return; state.busy=true; const status=document.getElementById('monitoring-status'); const error=document.getElementById('monitoring-error');
        status.innerHTML='<span class="h-2 w-2 animate-pulse rounded-full bg-sky-300"></span>Memperbarui data…'; state.controller=new AbortController();
        try{const url=new URL(root.dataset.endpoint,location.origin); new URLSearchParams(location.search).forEach((v,k)=>url.searchParams.set(k,v)); const res=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',signal:state.controller.signal,cache:'no-store'});
            if(res.status===401||res.status===403||res.status===419||res.redirected)revoke('Sesi atau otorisasi berakhir. Data terlindungi telah disembunyikan; silakan login kembali.');
            if(!res.ok)throw new Error('Pembaruan gagal. Data terakhir yang berhasil dimuat tetap ditampilkan.');
            if(!(res.headers.get('content-type')||'').includes('application/json'))throw new Error('Pembaruan gagal. Data terakhir yang berhasil dimuat tetap ditampilkan.');
            const payload=await res.json(); document.getElementById('monitoring-dynamic').innerHTML=payload.html; state.data={trend:payload.trend,chapter_trend:payload.chapter_trend,distribution:payload.distribution}; document.getElementById('monitoring-updated').textContent=payload.updated_label; state.lastSuccess=Date.now(); error.classList.add('hidden'); draw(); status.innerHTML='<span class="h-2 w-2 rounded-full bg-emerald-400"></span>Pembaruan otomatis setiap 60 detik saat tab aktif';
        }catch(e){if(e.name!=='AbortError'){error.textContent=e.message;error.classList.remove('hidden');status.innerHTML='<span class="h-2 w-2 rounded-full bg-amber-400"></span>Data belum diperbarui';}}
        finally{state.busy=false;state.controller=null;}
    }
    document.getElementById('monitoring-refresh').addEventListener('click',()=>refresh(true));
    document.addEventListener('visibilitychange',()=>{if(!document.hidden&&Date.now()-state.lastSuccess>60000)refresh();});
    window.addEventListener('pagehide',()=>{clearInterval(state.timer);state.controller?.abort();});
    state.timer=setInterval(()=>refresh(),60000); draw();
})();
</script>
@endpush
