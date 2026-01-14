<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait AuditTrail
{
    /**
     * Boot the trait and register model event hooks.
     */
    public static function bootAuditTrail(): void
    {
        static::created(function ($model) {
            $model->recordAudit('created');
        });

        static::updated(function ($model) {
            // Only record if something actually changed
            if ($model->getDirty()) {
                $model->recordAudit('updated');
            }
        });

        static::deleted(function ($model) {
            $model->recordAudit('deleted');
        });
    }

    /**
     * Record the audit log for the current model event.
     */
    protected function recordAudit(string $event): void
    {
        try {
            $user = Auth::user();
            
            // Define company context
            $companyId = $this->company_id ?? ($user ? $user->company_id : null);
            
            if (!$companyId) {
                return; // Cannot log without company context
            }

            $modelName = strtolower(class_basename($this));
            $eventType = "{$modelName}_{$event}";

            // Prepare event data
            $eventData = [
                'actor_name' => $user ? $user->name : 'System',
                'target_name' => $this->name ?? $this->display_name ?? $this->canonical_name ?? null,
            ];
            
            if ($event === 'updated') {
                $eventData['old'] = array_intersect_key($this->getOriginal(), $this->getDirty());
                $eventData['new'] = $this->getDirty();
                
                // Don't log sensitive fields
                unset($eventData['old']['password'], $eventData['new']['password']);
                unset($eventData['old']['remember_token'], $eventData['new']['remember_token']);
                unset($eventData['old']['credentials'], $eventData['new']['credentials']);
                unset($eventData['old']['api_key'], $eventData['new']['api_key']);
            } elseif ($event === 'created' || $event === 'deleted') {
                $eventData['data'] = $this->attributesToArray();
                unset(
                    $eventData['data']['password'], 
                    $eventData['data']['remember_token'],
                    $eventData['data']['credentials'],
                    $eventData['data']['api_key']
                );
            }

            AuditLog::create([
                'company_id'  => $companyId,
                'event_type'  => $eventType,
                'actor_type'  => $user ? 'User' : 'System',
                'actor_id'    => $user ? $user->user_id : null,
                'target_type' => class_basename($this),
                'target_id'   => $this->getKey(),
                'event_data'  => $eventData,
                'ip_address'  => Request::ip(),
                'user_agent'  => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail logging to prevent breaking the main operation
            \Log::error("Failed to record audit log: " . $e->getMessage());
        }
    }
}
