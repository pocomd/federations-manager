<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R12_RegistrationInfo implements MetadataRule
{
    public function id(): string          { return 'R12'; }
    public function name(): string        { return 'mdrpi:RegistrationInfo with registrationAuthority present'; }
    public function group(): string       { return 'refeds'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://wiki.refeds.org/display/FBP/eduGAIN+Baseline+Profile'; }

    public function description(): string
    {
        return 'mdrpi:RegistrationInfo with a registrationAuthority URI is required for eduGAIN federation aggregation.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if (! empty($entity->registration_authority)) {
            return RuleResult::pass($this->id(), 'mdrpi:RegistrationInfo with registrationAuthority is present.');
        }

        return RuleResult::fail(
            $this->id(),
            'mdrpi:RegistrationInfo with registrationAuthority is required for eduGAIN.',
            'Set FEDERATION_REGISTRATION_AUTHORITY in config/federation.php.',
        );
    }
}
