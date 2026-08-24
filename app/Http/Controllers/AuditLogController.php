<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $query = $this->filteredQuery($request);
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $stats = [
            'total' => (clone $query)->count(),
            'today' => (clone $query)
                ->where('created_at', '>=', LocalTime::utcStartOfLocalDay())
                ->count(),
            'seven_days' => (clone $query)
                ->where('created_at', '>=', LocalTime::utcStartOfLocalDay(LocalTime::now()->subDays(6)))
                ->count(),
            'alerts' => (clone $query)
                ->whereIn('event', ['deleted', 'login_failed', 'login_blocked'])
                ->count(),
        ];

        $eventCounts = (clone $query)
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        $moduleCounts = (clone $query)
            ->selectRaw('module, COUNT(*) as total')
            ->groupBy('module')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $records = (clone $query)
            ->with(['actor:id,name,email,role', 'municipality:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('audit_logs.index', [
            'records' => $records,
            'stats' => $stats,
            'eventCounts' => $eventCounts,
            'moduleCounts' => $moduleCounts,
            'eventLabels' => AuditLog::EVENT_LABELS,
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'municipalities' => Municipality::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                'event' => (string) $request->query('event', ''),
                'module' => (string) $request->query('module', ''),
                'municipality_id' => (string) $request->query('municipality_id', ''),
                'user_id' => (string) $request->query('user_id', ''),
                'date_from' => (string) $request->query('date_from', ''),
                'date_to' => (string) $request->query('date_to', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): View
    {
        $this->authorizeAccess($request);

        $auditLog->load(['actor:id,name,email,role', 'municipality:id,name']);

        return view('audit_logs.show', [
            'auditLog' => $auditLog,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAccess($request);

        $query = $this->filteredQuery($request);
        $maxId = (clone $query)->max('id');
        $filterSnapshot = $request->only([
            'q',
            'event',
            'module',
            'municipality_id',
            'user_id',
            'date_from',
            'date_to',
        ]);

        AuditTrail::record(
            'exported',
            'Audit trail',
            $request->user()->name.' exported the audit trail.',
            [
                'metadata' => [
                    'filters' => array_filter($filterSnapshot, fn ($value) => filled($value)),
                    'exported_through_id' => $maxId,
                ],
            ]
        );

        $fileName = 'audit-trail-'.LocalTime::now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $maxId): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Audit ID',
                'Date and Time',
                'Event',
                'Module',
                'Description',
                'Actor',
                'Actor Email',
                'Role',
                'Municipality',
                'IP Address',
                'Request',
                'Record Type',
                'Record ID',
                'Before',
                'After',
                'Context',
            ]);

            if ($maxId !== null) {
                $query->where('id', '<=', $maxId)
                    ->with(['municipality:id,name'])
                    ->orderBy('id')
                    ->chunkById(500, function ($logs) use ($output): void {
                        foreach ($logs as $log) {
                            fputcsv($output, array_map([$this, 'csvValue'], [
                                $log->id,
                                LocalTime::fromUtc($log->created_at)?->format('Y-m-d H:i:s P'),
                                $log->event_label,
                                $log->module,
                                $log->description,
                                $log->actor_name ?: 'System / unknown',
                                $log->actor_email,
                                $log->actor_role,
                                $log->municipality?->name,
                                $log->ip_address,
                                trim(($log->request_method ?? '').' '.($log->request_url ?? '')),
                                $log->auditable_type,
                                $log->auditable_id,
                                $this->jsonForCsv($log->old_values),
                                $this->jsonForCsv($log->new_values),
                                $this->jsonForCsv($log->metadata),
                            ]));
                        }
                    });
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->canViewAuditTrail(), 403);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query();
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('description', 'like', '%'.$search.'%')
                    ->orWhere('actor_name', 'like', '%'.$search.'%')
                    ->orWhere('actor_email', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('auditable_id', $search);
            });
        }

        $event = (string) $request->query('event', '');
        if (array_key_exists($event, AuditLog::EVENT_LABELS)) {
            $query->where('event', $event);
        }

        $module = trim((string) $request->query('module', ''));
        if ($module !== '') {
            $query->where('module', $module);
        }

        foreach (['municipality_id', 'user_id'] as $field) {
            $value = (string) $request->query($field, '');

            if ($value !== '' && ctype_digit($value)) {
                $query->where($field, (int) $value);
            }
        }

        if ($date = $this->parseDate($request->query('date_from'))) {
            $query->where('created_at', '>=', LocalTime::utcStartOfLocalDay($date));
        }

        if ($date = $this->parseDate($request->query('date_to'))) {
            $query->where('created_at', '<=', LocalTime::utcEndOfLocalDay($date));
        }

        return $query;
    }

    /** @param mixed $value */
    private function parseDate($value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, LocalTime::timezone());
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /** @param mixed $values */
    private function jsonForCsv($values): string
    {
        return $values
            ? (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';
    }

    /** @param mixed $value */
    private function csvValue($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
