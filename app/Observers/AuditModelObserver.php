<?php

namespace App\Observers;

use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class AuditModelObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $rawChanges = $model->getChanges();
        $newValues = AuditTrail::cleanValues($rawChanges);
        $protectedFields = array_values(array_intersect(
            array_keys($rawChanges),
            ['password', 'remember_token', 'public_map_token', 'profile_photo_path']
        ));

        if (! $newValues && $protectedFields === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($newValues ?? []) as $key) {
            $oldValues[$key] = $model->getRawOriginal($key);
        }

        $this->record(
            'updated',
            $model,
            $oldValues,
            $newValues,
            $protectedFields === []
                ? []
                : ['protected_fields_changed' => $protectedFields]
        );
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $model->getAttributes(), null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        string $event,
        Model $model,
        ?array $oldValues,
        ?array $newValues,
        array $metadata = []
    ): void {
        $module = $this->moduleName($model);
        $action = match ($event) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => $event,
        };

        AuditTrail::record(
            $event,
            $module,
            sprintf('%s %s “%s”.', auth()->user()?->name ?? 'System', $action, $this->recordLabel($model)),
            [
                'auditable' => $model,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => $metadata,
            ]
        );
    }

    private function moduleName(Model $model): string
    {
        return match (true) {
            $model instanceof Farmer => 'Farmers',
            $model instanceof FarmPlot => 'Farm plots',
            $model instanceof RiceSeedDistribution => 'Rice distributions',
            $model instanceof AntiRabiesVaccination => 'Vaccinations',
            $model instanceof FarmersCooperative => 'Cooperatives',
            $model instanceof BackupFile => 'Backup files',
            $model instanceof User => 'User management',
            $model instanceof Municipality => 'Municipalities',
            default => class_basename($model),
        };
    }

    private function recordLabel(Model $model): string
    {
        if ($model instanceof Farmer) {
            return trim(collect([
                $model->first_name,
                $model->middle_name,
                $model->last_name,
                $model->ext_name,
            ])->filter()->implode(' ')) ?: 'Farmer #'.$model->getKey();
        }

        if ($model instanceof RiceSeedDistribution) {
            $name = trim(collect([
                $model->first_name,
                $model->middle_name,
                $model->last_name,
            ])->filter()->implode(' '));

            return $name ?: 'Distribution #'.$model->getKey();
        }

        if ($model instanceof AntiRabiesVaccination) {
            return $model->pet_name
                ? $model->pet_name.' — '.$model->owner_name
                : ($model->owner_name ?: 'Vaccination #'.$model->getKey());
        }

        if ($model instanceof BackupFile) {
            return $model->original_name ?: 'Backup #'.$model->getKey();
        }

        if ($model instanceof User) {
            return $model->email ?: $model->name;
        }

        return $model->name
            ?? $model->code
            ?? class_basename($model).' #'.$model->getKey();
    }
}
