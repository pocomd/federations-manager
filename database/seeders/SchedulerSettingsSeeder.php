<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchedulerSetting;
use Illuminate\Database\Seeder;

class SchedulerSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ── Metadata ──────────────────────────────────────────────────────
            [
                'key'         => 'metadata_auto_generate_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'label'       => 'Auto-generate metadata',
                'description' => null,
                'group'       => 'metadata',
            ],
            [
                'key'         => 'metadata_auto_generate_interval',
                'value'       => '15',
                'type'        => 'select',
                'label'       => 'Generation interval (minutes)',
                'description' => 'How often to regenerate signed metadata',
                'group'       => 'metadata',
            ],
            [
                'key'         => 'metadata_valid_until_hours',
                'value'       => '168',
                'type'        => 'integer',
                'label'       => 'Metadata validUntil (hours)',
                'description' => null,
                'group'       => 'metadata',
            ],
            [
                'key'         => 'metadata_cache_duration_hours',
                'value'       => '6',
                'type'        => 'integer',
                'label'       => 'Cache duration (hours)',
                'description' => null,
                'group'       => 'metadata',
            ],

            // ── Validation ────────────────────────────────────────────────────
            [
                'key'         => 'validation_auto_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'label'       => 'Auto-validate entities',
                'description' => null,
                'group'       => 'validation',
            ],
            [
                'key'         => 'validation_schedule_day',
                'value'       => 'sunday',
                'type'        => 'select',
                'label'       => 'Validation day',
                'description' => 'Day of week to run full validation',
                'group'       => 'validation',
            ],
            [
                'key'         => 'validation_schedule_time',
                'value'       => '03:00',
                'type'        => 'string',
                'label'       => 'Validation time (HH:MM)',
                'description' => null,
                'group'       => 'validation',
            ],

            // ── Certificates ──────────────────────────────────────────────────
            [
                'key'         => 'cert_check_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'label'       => 'Auto certificate expiry check',
                'description' => null,
                'group'       => 'certificates',
            ],
            [
                'key'         => 'cert_check_time',
                'value'       => '06:00',
                'type'        => 'string',
                'label'       => 'Daily check time (HH:MM)',
                'description' => null,
                'group'       => 'certificates',
            ],
            [
                'key'         => 'cert_notify_days_critical',
                'value'       => '14',
                'type'        => 'integer',
                'label'       => 'Critical threshold (days)',
                'description' => null,
                'group'       => 'certificates',
            ],
            [
                'key'         => 'cert_notify_days_warning',
                'value'       => '30',
                'type'        => 'integer',
                'label'       => 'Warning threshold (days)',
                'description' => null,
                'group'       => 'certificates',
            ],
            [
                'key'         => 'cert_notify_days_advisory',
                'value'       => '60',
                'type'        => 'integer',
                'label'       => 'Advisory threshold (days)',
                'description' => null,
                'group'       => 'certificates',
            ],
            [
                'key'         => 'cert_notify_days_info',
                'value'       => '90',
                'type'        => 'integer',
                'label'       => 'Info threshold (days)',
                'description' => null,
                'group'       => 'certificates',
            ],

            // ── eduGAIN ───────────────────────────────────────────────────────
            [
                'key'         => 'edugain_sync_enabled',
                'value'       => '0',
                'type'        => 'boolean',
                'label'       => 'Auto eduGAIN sync',
                'description' => null,
                'group'       => 'edugain',
            ],
            [
                'key'         => 'edugain_sync_interval_hours',
                'value'       => '6',
                'type'        => 'select',
                'label'       => 'Sync interval (hours)',
                'description' => null,
                'group'       => 'edugain',
            ],
            [
                'key'         => 'edugain_metadata_url',
                'value'       => 'https://mds.edugain.org/edugain-v2.xml',
                'type'        => 'string',
                'label'       => 'eduGAIN metadata URL',
                'description' => null,
                'group'       => 'edugain',
            ],

            // ── Cleanup ───────────────────────────────────────────────────────
            [
                'key'         => 'cleanup_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'label'       => 'Auto cleanup',
                'description' => null,
                'group'       => 'cleanup',
            ],
            [
                'key'         => 'cleanup_metadata_days',
                'value'       => '7',
                'type'        => 'integer',
                'label'       => 'Delete metadata files older than (days)',
                'description' => null,
                'group'       => 'cleanup',
            ],
            [
                'key'         => 'cleanup_validation_days',
                'value'       => '90',
                'type'        => 'integer',
                'label'       => 'Prune validation results older than (days)',
                'description' => null,
                'group'       => 'cleanup',
            ],
            [
                'key'         => 'cleanup_audit_days',
                'value'       => '365',
                'type'        => 'integer',
                'label'       => 'Archive audit logs older than (days)',
                'description' => null,
                'group'       => 'cleanup',
            ],
        ];

        foreach ($defaults as $row) {
            SchedulerSetting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
