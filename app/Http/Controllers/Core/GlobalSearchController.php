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
        $roleData = $this->roleService->getRoleById($user->Role_ID);
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));
        
        $results = [];
        if ($keyword) {
            $results = $this->searchService->search($keyword, $roleName, $user->Employee_ID ?? $user->User_ID);
            $this->searchService->saveHistory($user->Employee_ID ?? $user->User_ID, $keyword);
        }

        return view('search.index', compact('keyword', 'results'));
    }

    public function overlay(Request $request)
    {
        $keyword = $request->get('q', '');
        $user = auth()->user();
        $userId = $user->Employee_ID ?? $user->User_ID;
        $roleData = $this->roleService->getRoleById($user->Role_ID);
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));

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
        $this->searchService->clearHistory($user->Employee_ID ?? $user->User_ID);
        return back()->with('success', 'Search history cleared.');
    }
}
