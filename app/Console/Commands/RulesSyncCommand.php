<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RuleDefinition;
use App\Services\Metadata\RuleRegistry;
use Illuminate\Console\Command;

class RulesSyncCommand extends Command
{
    protected $signature   = 'rules:sync';
    protected $description = 'Sync metadata rule definitions from discovered rule classes into the database';

    public function handle(RuleRegistry $registry): int
    {
        $registry->flush();

        $rules = $registry->all();

        if (empty($rules)) {
            $this->error('No rule classes discovered. Check app/Services/Metadata/Rules/.');
            return self::FAILURE;
        }

        $this->info('Discovered ' . count($rules) . ' rule class(es).');

        $discoveredIds = [];

        foreach ($rules as $rule) {
            $discoveredIds[] = $rule->id();

            RuleDefinition::upsert(
                [
                    'id'               => $rule->id(),
                    'name'             => $rule->name(),
                    'group'            => $rule->group(),
                    'applies_to'       => $this->serializeAppliesTo($rule->appliesTo()),
                    'default_severity' => $rule->defaultSeverity(),
                    'description'      => $rule->description(),
                    'spec_url'         => $rule->specUrl(),
                    'active'           => true,
                ],
                uniqueBy: ['id'],
                update:   ['name', 'group', 'applies_to', 'default_severity', 'description', 'spec_url', 'active'],
            );

            $this->line("  <info>✓</info> {$rule->id()} — {$rule->name()}");
        }

        // Deactivate rules that are no longer present (never delete — preserve config history)
        $deactivated = RuleDefinition::whereNotIn('id', $discoveredIds)
            ->where('active', true)
            ->update(['active' => false]);

        if ($deactivated > 0) {
            $this->warn("Deactivated {$deactivated} rule(s) no longer present in code.");
        }

        $registry->flush();

        $this->info('Rule definitions synced successfully.');

        return self::SUCCESS;
    }

    /** @param string[] $types */
    private function serializeAppliesTo(array $types): string
    {
        sort($types);

        if ($types === ['idp', 'sp']) {
            return 'both';
        }

        return implode(',', $types);
    }
}
