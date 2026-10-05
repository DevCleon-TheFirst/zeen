<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Conversations
            ['name' => 'View Conversations', 'slug' => 'conversations.view', 'group' => 'conversations'],
            ['name' => 'Reply to Conversations', 'slug' => 'conversations.reply', 'group' => 'conversations'],
            ['name' => 'Assign Conversations', 'slug' => 'conversations.assign', 'group' => 'conversations'],
            ['name' => 'Resolve Conversations', 'slug' => 'conversations.resolve', 'group' => 'conversations'],

            // Channels
            ['name' => 'View Channels', 'slug' => 'channels.view', 'group' => 'channels'],
            ['name' => 'Manage Channels', 'slug' => 'channels.manage', 'group' => 'channels'],

            // AI Settings
            ['name' => 'View AI Settings', 'slug' => 'ai_settings.view', 'group' => 'ai_settings'],
            ['name' => 'Manage AI Settings', 'slug' => 'ai_settings.manage', 'group' => 'ai_settings'],

            // Automations
            ['name' => 'View Automations', 'slug' => 'automations.view', 'group' => 'automations'],
            ['name' => 'Manage Automations', 'slug' => 'automations.manage', 'group' => 'automations'],

            // Catalog
            ['name' => 'View Catalog', 'slug' => 'catalog.view', 'group' => 'catalog'],
            ['name' => 'Manage Catalog', 'slug' => 'catalog.manage', 'group' => 'catalog'],

            // Appointments
            ['name' => 'View Appointments', 'slug' => 'appointments.view', 'group' => 'appointments'],
            ['name' => 'Manage Appointments', 'slug' => 'appointments.manage', 'group' => 'appointments'],

            // Leads
            ['name' => 'View Leads', 'slug' => 'leads.view', 'group' => 'leads'],
            ['name' => 'Manage Leads', 'slug' => 'leads.manage', 'group' => 'leads'],

            // Staff
            ['name' => 'View Staff', 'slug' => 'staff.view', 'group' => 'staff'],
            ['name' => 'Manage Staff', 'slug' => 'staff.manage', 'group' => 'staff'],

            // Business Settings
            ['name' => 'Manage Business Settings', 'slug' => 'business.manage', 'group' => 'settings'],
        ];

        $permMap = [];
        foreach ($permissions as $p) {
            $perm = Permission::updateOrCreate(['slug' => $p['slug']], $p);
            $permMap[$p['slug']] = $perm->id;
        }

        // Role mappings
        $allPermIds = array_values($permMap);
        $rolePermissions = [
            'owner' => $allPermIds,
            'admin' => $allPermIds,
            'manager' => array_values(array_intersect_key($permMap, array_flip([
                'conversations.view', 'conversations.reply', 'conversations.assign', 'conversations.resolve',
                'channels.view', 'ai_settings.view', 'automations.view', 'automations.manage',
                'catalog.view', 'catalog.manage', 'appointments.view', 'appointments.manage',
                'leads.view', 'leads.manage', 'staff.view',
            ]))),
            'agent' => array_values(array_intersect_key($permMap, array_flip([
                'conversations.view', 'conversations.reply', 'conversations.resolve',
                'catalog.view', 'appointments.view', 'appointments.manage',
                'leads.view', 'leads.manage',
            ]))),
            'support' => array_values(array_intersect_key($permMap, array_flip([
                'conversations.view', 'conversations.reply', 'conversations.resolve',
                'catalog.view', 'appointments.view',
            ]))),
        ];

        foreach ($rolePermissions as $role => $ids) {
            foreach ($ids as $id) {
                DB::table('role_permissions')->updateOrInsert([
                    'role' => $role,
                    'permission_id' => $id,
                ]);
            }
        }
    }
}
