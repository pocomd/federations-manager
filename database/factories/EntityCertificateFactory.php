<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EntityCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityCertificate>
 */
class EntityCertificateFactory extends Factory
{
    protected $model = EntityCertificate::class;

    public function definition(): array
    {
        $org = fake()->company();

        return [
            // entity_id must be set by the caller via ->for($entity) or ->create(['entity_id' => ...])
            'use'                 => 'signing',
            'pem'                 => $this->fakePem(),
            'subject'             => "CN={$org}, O={$org}, C=" . fake()->countryCode(),
            'issuer'              => "CN={$org}, O={$org}, C=" . fake()->countryCode(),
            'serial'              => strtoupper(bin2hex(random_bytes(8))),
            'not_before'          => now()->subYear(),
            'not_after'           => now()->addYears(3),
            'key_bits'            => 2048,
            'key_algorithm'       => 'RSA',
            'fingerprint'         => $this->fakeFingerprint(),
            'signature_algorithm' => 'sha256WithRSAEncryption',
            'debian_weak'         => false,
        ];
    }

    /** Certificate expiring within $days days from now. */
    public function expiringSoon(int $days = 10): static
    {
        return $this->state(fn () => [
            'not_after' => now()->addDays($days),
        ]);
    }

    /** Already expired certificate. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'not_after' => now()->subDay(),
        ]);
    }

    /** Encryption-use certificate. */
    public function encryption(): static
    {
        return $this->state(fn () => ['use' => 'encryption']);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function fakePem(): string
    {
        $data    = base64_encode(random_bytes(800));
        $wrapped = chunk_split($data, 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n{$wrapped}-----END CERTIFICATE-----\n";
    }

    private function fakeFingerprint(): string
    {
        return implode(':', str_split(strtoupper(bin2hex(random_bytes(32))), 2));
    }
}
