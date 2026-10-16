<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key', 'model_id');

        $now = now();

        $adminId = DB::table($rolesTable)->where('name', 'Admin')->value('id');
        if (!$adminId) {
            $adminId = DB::table($rolesTable)->insertGetId([
                'name' => 'Admin',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $techId = DB::table($rolesTable)->where('name', 'Technicien')->value('id');
        if (!$techId) {
            $techId = DB::table($rolesTable)->insertGetId([
                'name' => 'Technicien',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $oldAdminId = DB::table($rolesTable)->where('name', 'super-admin')->value('id');
        $oldWriterId = DB::table($rolesTable)->where('name', 'writer')->value('id');

        if ($oldAdminId) {
            $adminUserIds = DB::table($modelHasRolesTable)
                ->where('role_id', $oldAdminId)
                ->pluck($modelKey)
                ->all();

            if (!empty($adminUserIds)) {
                DB::table($modelHasRolesTable)
                    ->where('role_id', $adminId)
                    ->whereIn($modelKey, $adminUserIds)
                    ->delete();
            }

            DB::table($modelHasRolesTable)->where('role_id', $oldAdminId)->update(['role_id' => $adminId]);
        }

        if ($oldWriterId) {
            $techUserIds = DB::table($modelHasRolesTable)
                ->where('role_id', $oldWriterId)
                ->pluck($modelKey)
                ->all();

            if (!empty($techUserIds)) {
                DB::table($modelHasRolesTable)
                    ->where('role_id', $techId)
                    ->whereIn($modelKey, $techUserIds)
                    ->delete();
            }

            DB::table($modelHasRolesTable)->where('role_id', $oldWriterId)->update(['role_id' => $techId]);
        }

        DB::table($modelHasRolesTable)
            ->whereNotIn('role_id', [$adminId, $techId])
            ->update(['role_id' => $techId]);

        DB::table('users')
            ->where('role_name', 'like', '%super-admin%')
            ->update(['role_name' => json_encode(['Admin'])]);

        DB::table('users')
            ->where('role_name', 'not like', '%Admin%')
            ->update(['role_name' => json_encode(['Technicien'])]);

        DB::table($rolesTable)->whereIn('name', ['super-admin', 'writer'])->delete();

        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        $allPermissionIds = DB::table($permissionsTable)->pluck('id')->all();
        foreach ($allPermissionIds as $permissionId) {
            $exists = DB::table($roleHasPermissionsTable)
                ->where('role_id', $adminId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table($roleHasPermissionsTable)->insert([
                    'role_id' => $adminId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        // Ensure one role assignment per user.
        $userRoleRows = DB::table($modelHasRolesTable)
            ->select($modelKey)
            ->selectRaw('MIN(role_id) as keep_role_id')
            ->groupBy($modelKey)
            ->get();

        foreach ($userRoleRows as $row) {
            DB::table($modelHasRolesTable)
                ->where($modelKey, $row->{$modelKey})
                ->where('role_id', '!=', $row->keep_role_id)
                ->delete();
        }
    }

    public function down(): void
    {
        // Intentionally left without destructive rollback.
    }
};
