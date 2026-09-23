<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RegionOneSampleData;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

final class PopulateRegionOneSamples extends Command
{
    protected $signature = 'demo:region1 {--owner= : Existing active System Owner ID} {--municipality=* : Exactly three municipality IDs in distinct Region I provinces} {--apply : Insert the reviewed synthetic cohort; otherwise preview only}';

    protected $description = 'Preview or explicitly populate 12 synthetic farmers, 36 hypothetical plots and 12 sample rice releases in Region I.';

    public function handle(RegionOneSampleData $samples): int
    {
        $ids = $this->option('municipality');
        if (! ctype_digit((string) $this->option('owner')) || count($ids) !== 3
            || collect($ids)->contains(fn ($id) => ! ctype_digit((string) $id))) {
            $this->error('Provide a System Owner ID and three --municipality IDs. Without --apply, this command only previews.');

            return self::FAILURE;
        }
        try {
            $owner = User::query()->find($this->option('owner'));
            if (! $owner) {
                throw new DomainException('The authorizing System Owner account does not exist.');
            }
            $result = $samples->populate($owner, array_map('intval', $ids), (bool) $this->option('apply'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable $exception) {
            // SQL exceptions can contain protected row values. Never print/log their query here.
            $this->error('Sample import failed; no partial transaction was committed. Check schema, connection and lock availability privately.');
        }

        return self::FAILURE;
    }
}
