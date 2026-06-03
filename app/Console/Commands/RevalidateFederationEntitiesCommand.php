<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Federation;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Console\Command;

class RevalidateFederationEntitiesCommand extends Command
{
    protected $signature   = 'entities:revalidate {federation : Federation ID}';
    protected $description = 'Re-run compliance rules against all entities in a federation';

    public function handle(EntityMetadataService $metadataService): int
    {
        $federation = Federation::with('entities.certificates')->find($this->argument('federation'));

        if (! $federation) {
            $this->error('Federation not found.');
            return self::FAILURE;
        }

        $entities = $federation->entities;

        if ($entities->isEmpty()) {
            $this->info('No entities in this federation.');
            return self::SUCCESS;
        }

        $this->info("Re-validating {$entities->count()} entity/entities in \"{$federation->name}\"...");

        $passed  = 0;
        $failed  = 0;

        foreach ($entities as $entity) {
            $result = $metadataService->validate($entity);
            $result->passed() ? $passed++ : $failed++;
            $status = $result->passed() ? '<info>✓</info>' : '<comment>✗</comment>';
            $this->line("  {$status} {$entity->entity_id}");
        }

        $this->info("Done. Passed: {$passed}  Failed: {$failed}");

        return self::SUCCESS;
    }
}
