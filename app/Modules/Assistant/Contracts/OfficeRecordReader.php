<?php

namespace App\Modules\Assistant\Contracts;

use App\Models\User;

/** Authorized local reports; implementations must never call an AI provider. */
interface OfficeRecordReader
{
    public function answerTask(User $user, string $topic, int $year, string $search = '', ?int $farmerId = null): array;
}
