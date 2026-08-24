<?php

namespace App\Http\Controllers;

use App\Models\BackupFile;
use App\Models\User;
use App\Support\ConcurrentWrite;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite
    ) {
        $this->middleware('auth');
    }

    private array $editableTextExts  = ['txt','log','sql','csv','json','xml','md'];
    private array $editableExcelExts = ['xlsx'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', BackupFile::class);

        $q = BackupFile::query()
            ->with(['uploader:id,name', 'municipality:id,name'])
            ->select([
                'id','municipality_id','disk','folder','original_name','stored_name','path','size','mime','sha256','notes','uploaded_by','created_at'
            ]);
        $this->municipalityAccess->applyOptionalFilter(
            $q,
            $request->user(),
            $request->query('municipality_id')
        );

        // --------------------------
        // BASIC SEARCH (field + mode)
        // --------------------------
        $search = trim((string) $request->get('search', ''));
        $field  = (string) $request->get('search_field', 'all');      // all|name|folder|notes|sha256
        $mode   = (string) $request->get('search_mode', 'contains');  // contains|starts|ends|exact

        if ($search !== '') {
            $like = $this->buildLike($search, $mode);

            $apply = function ($builder, $col) use ($search, $like, $mode) {
                if ($mode === 'exact') {
                    $builder->where($col, $search);
                } else {
                    $builder->where($col, 'like', $like);
                }
            };

            $q->where(function ($w) use ($field, $apply) {
                switch ($field) {
                    case 'name':   $apply($w, 'original_name'); break;
                    case 'folder': $apply($w, 'folder'); break;
                    case 'notes':  $apply($w, 'notes'); break;
                    case 'sha256': $apply($w, 'sha256'); break;
                    default:
                        $w->where(function ($ww) use ($apply) { $apply($ww, 'original_name'); })
                          ->orWhere(function ($ww) use ($apply) { $apply($ww, 'folder'); })
                          ->orWhere(function ($ww) use ($apply) { $apply($ww, 'notes'); })
                          ->orWhere(function ($ww) use ($apply) { $apply($ww, 'sha256'); });
                        break;
                }
            });
        }

        // --------------------------
        // ADVANCED FILTERS
        // --------------------------

        // Folder (exact)
        $folder = trim((string) $request->get('folder', ''));
        if ($folder !== '') {
            $q->where('folder', $folder);
        }

        // Uploader
        $uploadedBy = $request->get('uploaded_by');
        if ($uploadedBy !== null && $uploadedBy !== '') {
            $q->where('uploaded_by', (int) $uploadedBy);
        }

        // Multi extensions: exts[]=zip&exts[]=sql
        $exts = $request->input('exts', []);
        if (!is_array($exts)) $exts = [];
        $exts = array_values(array_filter(array_map(function ($e) {
            $e = strtolower(trim((string)$e));
            $e = ltrim($e, '.');
            return preg_match('/^[a-z0-9]{1,12}$/', $e) ? $e : null;
        }, $exts)));

        if (count($exts) > 0) {
            $q->where(function ($w) use ($exts) {
                foreach ($exts as $e) {
                    $w->orWhere('original_name', 'like', '%.' . $e);
                }
            });
        }

        // Date preset OR custom range
        $datePreset = (string) $request->get('date_preset', '');
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            $datePreset,
            (string)$request->get('date_from', ''),
            (string)$request->get('date_to', '')
        );
        if ($dateFrom) $q->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $q->whereDate('created_at', '<=', $dateTo);

        // Size preset OR custom
        $sizePreset = (string) $request->get('size_preset', '');
        [$minMb, $maxMb] = $this->resolveSizeRange(
            $sizePreset,
            $request->get('min_mb'),
            $request->get('max_mb')
        );

        if ($minMb !== null) {
            $minBytes = (int) round(((float) $minMb) * 1024 * 1024);
            $q->where('size', '>=', max(0, $minBytes));
        }
        if ($maxMb !== null) {
            $maxBytes = (int) round(((float) $maxMb) * 1024 * 1024);
            $q->where('size', '<=', max(0, $maxBytes));
        }

        // Summary cards reflect the active filter set, before pagination.
        $filteredFileCount = (int) (clone $q)->reorder()->count();
        $filteredBytes = (int) (clone $q)->reorder()->sum('size');
        $hashedFileCount = (int) (clone $q)
            ->reorder()
            ->whereNotNull('sha256')
            ->where('sha256', '<>', '')
            ->count();
        $filteredFolderCount = (int) (clone $q)
            ->reorder()
            ->whereNotNull('folder')
            ->where('folder', '<>', '')
            ->distinct()
            ->count('folder');
        $latestUploadAt = (clone $q)->reorder()->max('created_at');

        // --------------------------
        // SORTING (whitelist)
        // --------------------------
        $sort = (string) $request->get('sort', 'newest');
        switch ($sort) {
            case 'oldest':     $q->orderBy('created_at', 'asc')->orderBy('id', 'asc'); break;
            case 'name_asc':   $q->orderBy('original_name', 'asc')->orderBy('id', 'desc'); break;
            case 'name_desc':  $q->orderBy('original_name', 'desc')->orderBy('id', 'desc'); break;
            case 'size_asc':   $q->orderBy('size', 'asc')->orderBy('id', 'desc'); break;
            case 'size_desc':  $q->orderBy('size', 'desc')->orderBy('id', 'desc'); break;
            default:           $q->orderBy('created_at', 'desc')->orderBy('id', 'desc'); break;
        }

        // Pagination
        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(10, min(100, $perPage));
        $files = $q->paginate($perPage)->withQueryString();

        // Dropdown data
        $folderQuery = BackupFile::query();
        $this->municipalityAccess->applyOptionalFilter(
            $folderQuery,
            $request->user(),
            $request->query('municipality_id')
        );
        $folders = $folderQuery
            ->select('folder')
            ->whereNotNull('folder')
            ->where('folder', '<>', '')
            ->distinct()
            ->orderBy('folder', 'desc')
            ->limit(300)
            ->pluck('folder');

        $visibleUploaderIds = BackupFile::query()
            ->select('uploaded_by')
            ->whereNotNull('uploaded_by');
        $this->municipalityAccess->applyOptionalFilter(
            $visibleUploaderIds,
            $request->user(),
            $request->query('municipality_id')
        );
        $uploaders = User::query()
            ->select('id','name')
            ->whereIn('id', $visibleUploaderIds)
            ->orderBy('name', 'asc')
            ->get();

        $extPresets = ['zip','sql','pdf','xlsx','csv','png','jpg','jpeg','txt','log','json','xml','md'];

        // Active filters summary for chips
        $active = [
            'search' => $search,
            'search_field' => $field,
            'search_mode' => $mode,
            'folder' => $folder,
            'uploaded_by' => $uploadedBy,
            'exts' => $exts,
            'date_preset' => $datePreset,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'size_preset' => $sizePreset,
            'min_mb' => $minMb,
            'max_mb' => $maxMb,
            'sort' => $sort,
            'per_page' => $perPage,
            'municipality_id' => $request->query('municipality_id'),
        ];

        return view('backups.index', [
            'files' => $files,
            'folders' => $folders,
            'uploaders' => $uploaders,
            'extPresets' => $extPresets,
            'active' => $active,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id'),
            'filteredFileCount' => $filteredFileCount,
            'filteredBytes' => $filteredBytes,
            'hashedFileCount' => $hashedFileCount,
            'filteredFolderCount' => $filteredFolderCount,
            'latestUploadAt' => $latestUploadAt,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', BackupFile::class);
        $data = $request->validate([
            'municipality_id' => ['nullable','integer'],
            'files' => ['required','array','min:1'],
            'files.*' => ['file','max:51200'], // 50MB each
            'folder' => ['nullable','string','max:120'],
            'notes' => ['nullable','string','max:2000'],
        ]);
        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $data['municipality_id'] ?? null
        );

        $disk = 'local';
        $folder = trim($data['folder'] ?? '');
        if ($folder === '') $folder = LocalTime::now()->format('Y/m');

        $storedPaths = [];
        $records = [];

        try {
            foreach ($request->file('files') as $file) {
                $original = $file->getClientOriginalName();
                $storedName = uniqid('bkp_', true).'_'
                    .preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
                $path = $file->storeAs(
                    'backups/'.$folder,
                    $storedName,
                    $disk
                );
                if (! $path) {
                    throw new \RuntimeException(
                        'One of the backup files could not be stored.'
                    );
                }
                $storedPaths[] = $path;
                $records[] = [
                    'municipality_id' => $municipalityId,
                    'disk' => $disk,
                    'folder' => $folder,
                    'original_name' => $original,
                    'stored_name' => $storedName,
                    'path' => $path,
                    'size' => (int) $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                    'sha256' => $this->computeSha256($disk, $path),
                    'notes' => $data['notes'] ?? null,
                    'uploaded_by' => Auth::id(),
                ];
            }

            $this->concurrentWrite->transaction(
                function () use ($records): void {
                    foreach ($records as $record) {
                        BackupFile::create($record);
                    }
                }
            );
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk($disk)->delete($storedPath);
            }

            throw $exception;
        }

        return redirect()->route('backups.index')->with('success', 'Backup file(s) uploaded.');
    }

    public function download(BackupFile $backup)
    {
        $this->authorize('view', $backup);
        $disk = $backup->disk;
        if (!Storage::disk($disk)->exists($backup->path)) {
            return redirect()->route('backups.index')->with('error', 'File not found in storage.');
        }

        return Storage::disk($disk)->download($backup->path, $backup->original_name);
    }

    public function destroy(BackupFile $backup)
    {
        $this->authorize('delete', $backup);

        $this->concurrentWrite->locked(
            $backup,
            function (BackupFile $current): void {
                $disk = $current->disk;
                $path = $current->path;

                $current->delete();
                $current->getConnection()->afterCommit(
                    fn () => Storage::disk($disk)->delete($path)
                );
            }
        );

        return redirect()->route('backups.index')->with('success', 'Backup deleted.');
    }

    // =========================================================
    // PREVIEW + STREAM + SAVE (EDIT INSIDE THE SITE)
    // =========================================================

    /**
     * Shows the preview/editor UI (resources/views/backups/preview.blade.php)
     */
    public function preview(Request $request, BackupFile $backup)
    {
        $this->authorize('view', $backup);

        if ($request->query('mode') === 'edit') {
            $this->authorize('update', $backup);
        }

        $disk = $backup->disk;
        if (!Storage::disk($disk)->exists($backup->path)) {
            return redirect()->route('backups.index')->with('error', 'File not found in storage.');
        }

        return view('backups.preview', ['file' => $backup]);
    }

    /**
     * Streams file inline so browser can show PDF/images and JS can fetch bytes.
     */
    public function stream(BackupFile $backup)
    {
        $this->authorize('view', $backup);
        $disk = $backup->disk;
        if (!Storage::disk($disk)->exists($backup->path)) {
            abort(404);
        }

        $name = $backup->original_name ?: basename($backup->path);
        $mime = $backup->mime ?: (Storage::disk($disk)->mimeType($backup->path) ?: 'application/octet-stream');

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return Storage::disk($disk)->response($backup->path, $name, $headers);
    }

    /**
     * Saves edits from the in-site editor.
     *
     * Expected payload (AJAX):
     *  - kind=text  + content=...
     *  - kind=xlsx  + file=<blob>
     *
     * Returns JSON.
     */
    public function save(Request $request, BackupFile $backup)
    {
        $this->authorize('update', $backup);
        $disk = $backup->disk;

        if (!Storage::disk($disk)->exists($backup->path)) {
            return response()->json(['ok' => false, 'message' => 'File not found'], 404);
        }

        $kind = (string) $request->input('kind', '');
        $ext  = strtolower(pathinfo((string)$backup->original_name, PATHINFO_EXTENSION));

        // ---- TEXT SAVE ----
        if ($kind === 'text') {
            if (!$this->isEditableText($ext)) {
                return response()->json(['ok' => false, 'message' => 'This file type is not editable'], 422);
            }

            $data = $request->validate([
                // up to ~10MB of text (adjust if you want bigger)
                'content' => ['required', 'string', 'max:10000000'],
            ]);

            $this->concurrentWrite->execute(
                $backup,
                $request->input('_record_version'),
                function (BackupFile $current) use ($data, $disk): void {
                    $stored = Storage::disk($disk)->put(
                        $current->path,
                        $data['content']
                    );
                    if (! $stored) {
                        throw new \RuntimeException(
                            'The edited backup file could not be stored.'
                        );
                    }

                    $current->size = (int) Storage::disk($disk)->size(
                        $current->path
                    );
                    $current->mime = $current->mime ?: 'text/plain';
                    $current->sha256 = $this->computeSha256(
                        $disk,
                        $current->path
                    );
                    $current->save();
                }
            );

            return response()->json(['ok' => true]);
        }

        // ---- XLSX SAVE ----
        if ($kind === 'xlsx') {
            if (!$this->isEditableExcel($ext)) {
                return response()->json(['ok' => false, 'message' => 'This file type is not editable'], 422);
            }

            $data = $request->validate([
                'file' => ['required', 'file', 'max:51200'], // 50MB
            ]);

            $uploaded = $request->file('file');

            $this->concurrentWrite->execute(
                $backup,
                $request->input('_record_version'),
                function (BackupFile $current) use ($uploaded, $disk): void {
                    $stream = fopen($uploaded->getRealPath(), 'rb');
                    if ($stream === false) {
                        throw new \RuntimeException(
                            'The uploaded workbook could not be opened.'
                        );
                    }

                    try {
                        $stored = Storage::disk($disk)->put(
                            $current->path,
                            $stream
                        );
                        if (! $stored) {
                            throw new \RuntimeException(
                                'The edited workbook could not be stored.'
                            );
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }

                    $current->size = (int) Storage::disk($disk)->size(
                        $current->path
                    );
                    $current->mime = $uploaded->getClientMimeType()
                        ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    $current->sha256 = $this->computeSha256(
                        $disk,
                        $current->path
                    );
                    $current->save();
                }
            );

            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'message' => 'Unsupported edit kind'], 422);
    }

    // --------------------------
    // Helpers
    // --------------------------

    private function buildLike(string $s, string $mode): string
    {
        $s = addcslashes($s, "%_"); // escape wildcards
        return match ($mode) {
            'starts' => $s . '%',
            'ends'   => '%' . $s,
            'exact'  => $s,
            default  => '%' . $s . '%', // contains
        };
    }

    private function resolveDateRange(string $preset, string $from, string $to): array
    {
        $preset = strtolower(trim($preset));
        $today = now()->toDateString();

        if ($preset === 'today') return [$today, $today];
        if ($preset === '7d')    return [now()->subDays(6)->toDateString(), $today];
        if ($preset === '30d')   return [now()->subDays(29)->toDateString(), $today];

        $from = trim($from);
        $to = trim($to);
        return [$from !== '' ? $from : null, $to !== '' ? $to : null];
    }

    private function resolveSizeRange(string $preset, $minMb, $maxMb): array
    {
        $preset = strtolower(trim($preset));
        if ($preset === 'small')  return [0, 5];      // <= 5 MB
        if ($preset === 'medium') return [5, 50];     // 5–50 MB
        if ($preset === 'large')  return [50, null];  // >= 50 MB

        $min = ($minMb !== null && $minMb !== '') ? (float)$minMb : null;
        $max = ($maxMb !== null && $maxMb !== '') ? (float)$maxMb : null;
        return [$min, $max];
    }

    private function isEditableText(string $ext): bool
    {
        $ext = strtolower($ext);
        return in_array($ext, $this->editableTextExts, true);
    }

    private function isEditableExcel(string $ext): bool
    {
        $ext = strtolower($ext);
        return in_array($ext, $this->editableExcelExts, true);
    }

    /**
     * Compute SHA256 without loading the whole file in memory.
     */
    private function computeSha256(string $disk, string $path): ?string
    {
        try {
            $stream = Storage::disk($disk)->readStream($path);
            if (!$stream) return null;

            $hash = hash_init('sha256');
            while (!feof($stream)) {
                $buf = fread($stream, 1024 * 1024); // 1MB chunks
                if ($buf === false) break;
                hash_update($hash, $buf);
            }

            if (is_resource($stream)) fclose($stream);
            return hash_final($hash);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeFilename(string $name): string
    {
        // simple safe header filename
        $name = str_replace(["\r","\n",'"'], ['','',''], $name);
        return $name;
    }
}
