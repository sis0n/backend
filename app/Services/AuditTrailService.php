<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditTrailService
{
    /**
     * Reusable method to save an audit log.
     */
    public static function log($userId, $action, $resource, $resourceId = null, $details = null)
    {
        DB::table('audit_logs')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'resource'    => $resource,
            'resource_id' => $resourceId,
            'details'     => $details,
            'created_at'  => now(),
        ]);
    }
}
