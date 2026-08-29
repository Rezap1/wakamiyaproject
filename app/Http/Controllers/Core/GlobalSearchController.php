<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\GlobalSearchService;
use App\Services\Core\RoleService;

class GlobalSearchController extends Controller
{
    protected $searchService;
    protected $roleService;

    public function __construct(GlobalSearchService $searchService, RoleService $roleService)
    {
        $this->searchService = $searchService;
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $keyword = $request->get('q', '');
        $user = auth()->user();
        $roleName = $this->resolveRoleName($user);
        $userId = $this->resolveUserId($user);

        $results = [];
        if ($keyword) {
            $results = $this->searchService->search($keyword, $roleName, $userId);
            $this->searchService->saveHistory($userId, $keyword);
        }

        return view('search.index', compact('keyword', 'results'));
    }

    public function overlay(Request $request)
    {
        $keyword = $request->get('q', '');
        $user = auth()->user();
        $userId = $this->resolveUserId($user);
        $roleName = $this->resolveRoleName($user);

        if ($keyword) {
            $results = $this->searchService->search($keyword, $roleName, $userId);
            $this->searchService->saveHistory($userId, $keyword);
            return response()->json(['status' => 'success', 'data' => $results]);
        } else {
            // Return history
            $history = $this->searchService->getHistory($userId);
            return response()->json(['status' => 'history', 'data' => $history]);
        }
    }

    public function clearHistory()
    {
        $user = auth()->user();
        $this->searchService->clearHistory($this->resolveUserId($user));

        if (request()->expectsJson()) {
            return response()->json(['status' => 'success']);
        }

        return back()->with('success', 'Search history cleared.');
    }

    private function resolveUserId($user): string
    {
        if (!$user) {
            abort(403, 'Sesi pengguna tidak valid.');
        }

        $identifier = $user->User_ID ?? $user->Employee_ID ?? $user->email ?? auth()->id();
        if (!$identifier) {
            abort(403, 'Identitas pengguna tidak dapat ditentukan.');
        }

        return (string) $identifier;
    }

    private function resolveRoleName($user): string
    {
        $role = strtoupper(trim((string) ($user->Role ?? session('role') ?? '')));
        if ($role !== '') {
            return $role;
        }

        $roleData = $this->roleService->getRoleById($user->Role_ID ?? '');
        return strtoupper(trim($roleData['Role_Name'] ?? 'GUEST'));
    }
}
