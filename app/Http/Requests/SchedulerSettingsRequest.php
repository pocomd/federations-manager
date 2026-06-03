<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SchedulerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('federation.edit');
    }

    public function rules(): array
    {
        return [
            // Metadata group
            'metadata_auto_generate_enabled'  => ['boolean'],
            'metadata_auto_generate_interval' => ['integer', Rule::in([5, 10, 15, 30, 60])],
            'metadata_valid_until_hours'       => ['integer', 'min:1', 'max:168'],
            'metadata_cache_duration_hours'    => ['integer', 'min:1', 'max:72'],

            // Validation group
            'validation_auto_enabled'  => ['boolean'],
            'validation_schedule_day'  => [Rule::in([
                'monday', 'tuesday', 'wednesday', 'thursday',
                'friday', 'saturday', 'sunday', 'daily',
            ])],
            'validation_schedule_time' => ['regex:/^([01]\d|2[0-3]):[0-5]\d$/'],

            // Certificates group
            'cert_check_enabled'        => ['boolean'],
            'cert_check_time'           => ['regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'cert_notify_days_critical' => ['integer', 'min:1', 'max:30'],
            'cert_notify_days_warning'  => ['integer', 'min:1', 'max:60'],
            'cert_notify_days_advisory' => ['integer', 'min:1', 'max:120'],
            'cert_notify_days_info'     => ['integer', 'min:1', 'max:365'],

            // eduGAIN group
            'edugain_sync_enabled'        => ['boolean'],
            'edugain_sync_interval_hours' => ['integer', Rule::in([1, 3, 6, 12, 24])],
            'edugain_metadata_url'        => ['url', 'starts_with:https://', 'max:512'],

            // Cleanup group
            'cleanup_enabled'         => ['boolean'],
            'cleanup_metadata_days'   => ['integer', 'min:1', 'max:365'],
            'cleanup_validation_days' => ['integer', 'min:1', 'max:730'],
            'cleanup_audit_days'      => ['integer', 'min:30', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'validation_schedule_time.regex' => 'Time must be in HH:MM format (e.g. 03:00)',
            'cert_check_time.regex'          => 'Time must be in HH:MM format (e.g. 06:00)',
            'edugain_metadata_url.starts_with' => 'URL must use HTTPS',
            'edugain_metadata_url.url'         => 'Must be a valid URL',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'metadata_auto_generate_enabled' => $this->boolean('metadata_auto_generate_enabled'),
            'validation_auto_enabled'        => $this->boolean('validation_auto_enabled'),
            'cert_check_enabled'             => $this->boolean('cert_check_enabled'),
            'edugain_sync_enabled'           => $this->boolean('edugain_sync_enabled'),
            'cleanup_enabled'                => $this->boolean('cleanup_enabled'),
        ]);
    }
}
