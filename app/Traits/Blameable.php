<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Blameable
{
    /**
     * Boot the blameable trait for a model.
     */
    public static function bootBlameable(): void
    {
        static::creating(function ($model) {
            if (empty($model->created_by)) {
                $model->created_by = Auth::id() ?? null;
            }
            if (empty($model->updated_by)) {
                $model->updated_by = Auth::id() ?? null;
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty()) {
                $model->updated_by = Auth::id() ?? null;
            }
        });

        static::deleting(function ($model) {
            // We use method_exists because this will typically be used with SoftDeletes
            if (method_exists($model, 'runSoftDelete')) {
                $model->deleted_by = Auth::id() ?? null;
                $model->saveQuietly();
            }
        });
    }
}
