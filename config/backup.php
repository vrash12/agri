<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backup destination
    |--------------------------------------------------------------------------
    |
    | Dumps are written under storage/app, which the shipped storage/app/.gitignore
    | already excludes from source control. They contain farmer personal data, so
    | keep them out of the repository and off any publicly served path.
    |
    | On a server, point BACKUP_DATABASE_PATH at a directory on separate storage,
    | and copy the dumps off the machine as well. A backup on the same disk as the
    | database does not survive the failure it exists for.
    |
    */

    'path' => env('BACKUP_DATABASE_PATH', storage_path('app/backups/database')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many dumps to keep. The oldest are removed once this many newer ones
    | exist. Set to 0 to keep every dump and prune manually.
    |
    */

    'keep' => (int) env('BACKUP_DATABASE_KEEP', 14),

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | Dumps are gzipped unless this is disabled. Turn it off only when the host
    | lacks the zlib extension.
    |
    */

    'compress' => (bool) env('BACKUP_DATABASE_COMPRESS', true),

    /*
    |--------------------------------------------------------------------------
    | Rows per INSERT statement
    |--------------------------------------------------------------------------
    |
    | Larger batches restore faster and produce smaller files; smaller batches
    | use less memory while writing. 200 suits the size of this database.
    |
    */

    'insert_batch' => (int) env('BACKUP_DATABASE_INSERT_BATCH', 200),

    /*
    |--------------------------------------------------------------------------
    | Daily schedule
    |--------------------------------------------------------------------------
    |
    | The time App\Console\Kernel runs the backup, in the application timezone.
    | This only takes effect where Laravel's scheduler is actually running; see
    | the deployment notes in AGENTS.md.
    |
    */

    'schedule_at' => env('BACKUP_DATABASE_SCHEDULE_AT', '01:30'),

];
