<?php

namespace App\Observers;

use App\Events\CommercialDataChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Broadcast only after persisted changes; never include record details. */
class CommercialChangeObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(object $model): void
    {
        $this->notify($model);
    }

    public function deleted(object $model): void
    {
        $this->notify($model);
    }

    public function restored(object $model): void
    {
        $this->notify($model);
    }

    private function notify(object $model): void
    {
        $tenantId = $model->getAttribute('tenant_id');
        if (is_string($tenantId) && $tenantId !== '') {
            CommercialDataChanged::dispatch($tenantId);
        }
    }
}
