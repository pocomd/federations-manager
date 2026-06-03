<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Certificate;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class C03_NotExpired implements MetadataRule
{
    public function id(): string          { return 'C03'; }
    public function name(): string        { return 'Certificate is not expired'; }
    public function group(): string       { return 'certificate'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-metadata-2.0-os.pdf'; }

    public function description(): string
    {
        return 'No certificate may be expired. A warning is raised when any certificate expires within 30 days.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        if ($entity->certificates->isEmpty()) {
            return RuleResult::notApplicable($this->id());
        }

        $expired  = [];
        $expiring = [];

        foreach ($entity->certificates as $cert) {
            $days = now()->diffInDays($cert->not_after, false);

            if ($days < 0) {
                $expired[] = "Subject: {$cert->subject} expired {$cert->not_after}";
            } elseif ($days < 30) {
                $expiring[] = "Subject: {$cert->subject} expires in {$days} days ({$cert->not_after})";
            }
        }

        if (! empty($expired)) {
            return RuleResult::fail(
                $this->id(),
                'One or more certificates are expired.',
                implode('; ', $expired),
            );
        }

        if (! empty($expiring)) {
            return RuleResult::warning(
                $this->id(),
                'One or more certificates expire within 30 days — rotate before expiry.',
                implode('; ', $expiring),
            );
        }

        return RuleResult::pass($this->id(), 'All certificates are valid and not expiring soon.');
    }
}
