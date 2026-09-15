<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'action' => $request->string('action')->trim()->toString() ?: null,
            'causer_role' => $request->string('causer_role')->trim()->toString() ?: null,
            'date_from' => $request->string('date_from')->trim()->toString() ?: null,
            'date_to' => $request->string('date_to')->trim()->toString() ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
        ];

        $query = ActivityLog::query()
            ->with('causer')
            ->when($filters['action'], fn ($q) => $q->where('action', $filters['action']))
            ->when($filters['causer_role'], fn ($q) => $q->where('causer_role', $filters['causer_role']))
            ->when($filters['date_from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'], function ($q) use ($filters) {
                $s = $filters['search'];
                $q->where(function ($inner) use ($s) {
                    $inner->where('description', 'like', "%{$s}%")
                        ->orWhere('action', 'like', "%{$s}%")
                        ->orWhere('ip_address', 'like', "%{$s}%")
                        ->orWhereHas('causer', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('username', 'like', "%{$s}%"));
                });
            })
            ->latest('created_at')
            ->latest('id');

        $logs = $query->paginate(20)->withQueryString();

        $actionOptions = ActivityAction::cases();
        $roleOptions = ['admin', 'pengawas', 'guru_mapel', 'wali_kelas', 'kepala_sekolah', 'peserta', 'system'];
        $groupedActions = ActivityAction::groupedByRole();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actionOptions' => $actionOptions,
            'roleOptions' => $roleOptions,
            'groupedActions' => $groupedActions,
        ]);
    }
}
