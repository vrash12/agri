<?php

namespace App\Http\Controllers;

use App\Models\BackupFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * File extensions allowed for in-site editing.
     * - Text-like: edited as plain text in the browser
     * - Excel: edited in browser then uploaded back as XLSX blob
     */
    private array $editableTextExts  = ['txt','log','sql','csv','json','xml','md'];
    private array $editableExcelExts = ['xlsx'];

    public function index(Request $request)
    {
        $q = BackupFile::query()
            ->with(['uploader:id,name'])
            ->select([
                'id','disk','folder','original_name','stored_name','path','size','mime','sha256','notes','uploaded_by','created_at'
            ]);

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
        $folders = BackupFile::query()
            ->select('folder')
            ->whereNotNull('folder')
            ->where('folder', '<>', '')
            ->distinct()
            ->orderBy('folder', 'desc')
            ->limit(300)
            ->pluck('folder');

        $uploaders = User::query()
            ->select('id','name')
            ->whereIn('id', BackupFile::query()->select('uploaded_by')->whereNotNull('uploaded_by'))
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
        ];

        return view('backups.index', compact('files','folders','uploaders','extPresets','active'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'files' => ['required','array','min:1'],
            'files.*' => ['file','max:51200'], // 50MB each
            'folder' => ['nullable','string','max:120'],
            'notes' => ['nullable','string','max:2000'],
        ]);

        $disk = 'local';
        $folder = trim($data['folder'] ?? '');
        if ($folder === '') $folder = now()->format('Y/m');

        foreach ($request->file('files') as $file) {
            $original = $file->getClientOriginalName();
            $mime = $file->getClientMimeType();
            $size = $file->getSize();

            $dir = 'backups/' . $folder;
            $storedName = uniqid('bkp_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
            $path = $file->storeAs($dir, $storedName, $disk);

            $sha256 = $this->computeSha256($disk, $path);

            BackupFile::create([
                'disk' => $disk,
                'folder' => $folder,
                'original_name' => $original,
                'stored_name' => $storedName,
                'path' => $path,
                'size' => (int) $size,
                'mime' => $mime,
                'sha256' => $sha256,
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => Auth::id(),
            ]);
        }

        return redirect()->route('backups.index')->with('success', 'Backup file(s) uploaded.');
    }

    public function download(BackupFile $backup)
    {
        $disk = $backup->disk;
        if (!Storage::disk($disk)->exists($backup->path)) {
            return redirect()->route('backups.index')->with('error', 'File not found in storage.');
        }

        return Storage::disk($disk)->download($backup->path, $backup->original_name);
    }

    public function destroy(BackupFile $backup)
    {
        $disk = $backup->disk;
        if (Storage::disk($disk)->exists($backup->path)) {
            Storage::disk($disk)->delete($backup->path);
        }
        $backup->delete();

        return redirect()->route('backups.index')->with('success', 'Backup deleted.');
    }

    // =========================================================
    // PREVIEW + STREAM + SAVE (EDIT INSIDE THE SITE)
    // =========================================================

    /**
     * Shows the preview/editor UI (resources/views/backups/preview.blade.php)
     */
    public function preview(BackupFile $backup)
    {
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

            Storage::disk($disk)->put($backup->path, $data['content']);

            // update meta
            $backup->size = (int) Storage::disk($disk)->size($backup->path);
            $backup->mime = $backup->mime ?: 'text/plain';
            $backup->sha256 = $this->computeSha256($disk, $backup->path);
            $backup->save();

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

            // overwrite same storage path
            $stream = fopen($uploaded->getRealPath(), 'rb');
            Storage::disk($disk)->put($backup->path, $stream);
            if (is_resource($stream)) fclose($stream);

            // update meta
            $backup->size = (int) Storage::disk($disk)->size($backup->path);
            $backup->mime = $uploaded->getClientMimeType()
                ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $backup->sha256 = $this->computeSha256($disk, $backup->path);
            $backup->save();

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