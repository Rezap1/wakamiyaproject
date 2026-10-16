<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgendaRequest;
use App\Services\Core\AgendaService;
use Illuminate\Http\Request;

final class AgendaController extends Controller
{
    public function __construct(private AgendaService $agendas) {}

    public function index(Request $request)
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
            'type' => ['nullable', 'in:'.implode(',', array_keys(AgendaService::TYPES))],
            'pic' => ['nullable', 'string', 'max:191'],
            'agenda_status' => ['nullable', 'in:'.implode(',', array_keys(AgendaService::STATUSES))],
            'follow_up_status' => ['nullable', 'in:'.implode(',', array_keys(AgendaService::FOLLOW_UP_STATUSES))],
        ]);

        return view('agendas.index', [
            'agendas' => $this->agendas->paginate($filters),
            'pics' => $this->agendas->pics(),
            'types' => AgendaService::TYPES,
            'statuses' => AgendaService::STATUSES,
            'followUpStatuses' => AgendaService::FOLLOW_UP_STATUSES,
        ]);
    }

    public function create()
    {
        return view('agendas.create', $this->formOptions());
    }

    public function store(AgendaRequest $request)
    {
        $agenda = $this->agendas->create($request->validated());

        return redirect()->route('agendas.show', $agenda['Agenda_ID'])
            ->with('success', 'Agenda berhasil dibuat.');
    }

    public function show(string $id)
    {
        return view('agendas.show', ['agenda' => $this->findOrFail($id)] + $this->formOptions());
    }

    public function edit(string $id)
    {
        return view('agendas.edit', ['agenda' => $this->findOrFail($id)] + $this->formOptions());
    }

    public function update(AgendaRequest $request, string $id)
    {
        $this->findOrFail($id);
        $this->agendas->update($id, $request->validated());

        return redirect()->route('agendas.show', $id)->with('success', 'Agenda berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $this->findOrFail($id);
        $this->agendas->delete($id);

        return redirect()->route('agendas.index')->with('success', 'Agenda berhasil dihapus.');
    }

    private function findOrFail(string $id): array
    {
        $agenda = $this->agendas->find($id);
        abort_if($agenda === null, 404, 'Agenda tidak ditemukan.');

        return $agenda;
    }

    private function formOptions(): array
    {
        return [
            'pics' => $this->agendas->pics(),
            'types' => AgendaService::TYPES,
            'statuses' => AgendaService::STATUSES,
            'followUpStatuses' => AgendaService::FOLLOW_UP_STATUSES,
        ];
    }
}
