<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\Entity;
use App\Models\FederationRuleConfig;
use App\Models\EntityRuleConfig;
use App\Models\RuleDefinition;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class RuleEngine
{
    private bool $bypassDbFilter = false;

    public function __construct(
        private readonly RuleRegistry $registry,
    ) {}

    /**
     * Return an engine that treats every rule discovered by the registry as active,
     * skipping the rule_definitions DB query. Intended for unit tests where no
     * rule_definitions rows are seeded.
     */
    public static function withAllActive(RuleRegistry $registry): self
    {
        $instance = new self($registry);
        $instance->bypassDbFilter = true;
        return $instance;
    }

    /**
     * Evaluate all applicable, active rules against the given entity.
     *
     * Config resolution order (highest priority first):
     *   1. Entity-level override (entity_rule_config)
     *   2. Federation-level override (federation_rule_config for the entity's primary federation)
     *   3. Rule default (rule_definitions.active + default_severity)
     *
     * @return RuleResult[]
     */
    public function evaluate(Entity $entity): array
    {
        $activeDefinitions = $this->bypassDbFilter
            ? null
            : RuleDefinition::active()->get()->keyBy('id');
        $entityConfigs    = $entity->exists
            ? EntityRuleConfig::where('entity_id', $entity->id)->get()->keyBy('rule_id')
            : collect();
        $federationConfig = $entity->exists
            ? $this->resolveFederationConfig($entity)
            : collect();

        $results = [];

        foreach ($this->registry->all() as $rule) {
            // Rules absent from active_definitions are globally inactive — report as not_applicable
            if ($activeDefinitions !== null && ! $activeDefinitions->has($rule->id())) {
                $results[] = RuleResult::notApplicable($rule->id(), $rule->name());
                continue;
            }

            $definition = $activeDefinitions?->get($rule->id());

            // Resolve enabled flag
            $enabled = $this->resolveEnabled($rule->id(), $entityConfigs, $federationConfig, $definition);
            if (! $enabled) {
                $results[] = RuleResult::notApplicable($rule->id(), $rule->name());
                continue;
            }

            // Skip rules that don't apply to this entity type
            if (! in_array($entity->type, $rule->appliesTo(), true)) {
                $results[] = RuleResult::notApplicable($rule->id(), $rule->name());
                continue;
            }

            $result = $rule->evaluate($entity);

            // Apply severity override when a failed rule has a configured severity
            if ($result->status === 'fail') {
                $severity = $this->resolveSeverity($rule->id(), $entityConfigs, $federationConfig);
                if ($severity === 'warning') {
                    $result = RuleResult::warning($result->ruleId, $result->message, $result->detail);
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    private function resolveFederationConfig(Entity $entity): \Illuminate\Support\Collection
    {
        $federation = $entity->federations()->first();
        if (! $federation) {
            return collect();
        }

        return FederationRuleConfig::where('federation_id', $federation->id)->get()->keyBy('rule_id');
    }

    private function resolveEnabled(
        string $ruleId,
        \Illuminate\Support\Collection $entityConfigs,
        \Illuminate\Support\Collection $federationConfig,
        ?RuleDefinition $definition,
    ): bool {
        if ($entityConfigs->has($ruleId)) {
            return (bool) $entityConfigs->get($ruleId)->enabled;
        }

        if ($federationConfig->has($ruleId)) {
            return (bool) $federationConfig->get($ruleId)->enabled;
        }

        return $definition ? $definition->active : true;
    }

    private function resolveSeverity(
        string $ruleId,
        \Illuminate\Support\Collection $entityConfigs,
        \Illuminate\Support\Collection $federationConfig,
    ): ?string {
        if ($entityConfigs->has($ruleId) && $entityConfigs->get($ruleId)->severity !== null) {
            return $entityConfigs->get($ruleId)->severity;
        }

        if ($federationConfig->has($ruleId) && $federationConfig->get($ruleId)->severity !== null) {
            return $federationConfig->get($ruleId)->severity;
        }

        return null;
    }
}
