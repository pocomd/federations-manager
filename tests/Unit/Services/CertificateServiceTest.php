<?php

declare(strict_types=1);

use App\Models\EntityCertificate;
use App\Services\Entity\CertificateService;
use Carbon\CarbonImmutable;

/** Alias — delegates to the shared helper defined in tests/Pest.php. */
function makeTestPem(): string
{
    return generateSelfSignedPem();
}

describe('CertificateService', function () {
    beforeEach(function () {
        $this->service = new CertificateService();
    });

    // ── parse() ────────────────────────────────────────────────────────

    describe('parse()', function () {
        it('returns all expected fields for a valid PEM', function () {
            $result = $this->service->parse(makeTestPem());

            expect($result)->toHaveKeys([
                'subject', 'issuer', 'serial', 'not_before', 'not_after',
                'key_bits', 'key_algorithm', 'fingerprint', 'signature_algorithm',
            ]);
            expect($result['not_before'])->toBeInstanceOf(CarbonImmutable::class);
            expect($result['not_after'])->toBeInstanceOf(CarbonImmutable::class);
            expect($result['key_algorithm'])->toBe('RSA');
            expect($result['key_bits'])->toBe(2048);
        });

        it('formats fingerprint as colon-separated uppercase hex pairs', function () {
            $fingerprint = $this->service->parse(makeTestPem())['fingerprint'];

            expect($fingerprint)->toMatch('/^[0-9A-F]{2}(:[0-9A-F]{2})*$/');
        });

        it('throws InvalidArgumentException for invalid PEM input', function () {
            $this->service->parse('this-is-not-a-certificate');
        })->throws(InvalidArgumentException::class);
    });

    // ── isExpired() ────────────────────────────────────────────────────

    describe('isExpired()', function () {
        it('returns true when not_after is in the past', function () {
            $cert            = new EntityCertificate();
            $cert->not_after = CarbonImmutable::now()->subDay();

            expect($this->service->isExpired($cert))->toBeTrue();
        });

        it('returns false when not_after is in the future', function () {
            $cert            = new EntityCertificate();
            $cert->not_after = CarbonImmutable::now()->addYear();

            expect($this->service->isExpired($cert))->toBeFalse();
        });
    });

    // ── daysUntilExpiry() ─────────────────────────────────────────────

    describe('daysUntilExpiry()', function () {
        it('returns 0 for an already-expired cert', function () {
            $cert            = new EntityCertificate();
            $cert->not_after = CarbonImmutable::now()->subDay();

            expect($this->service->daysUntilExpiry($cert))->toBe(0);
        });

        it('returns a positive number of days for a future cert', function () {
            $cert            = new EntityCertificate();
            $cert->not_after = CarbonImmutable::now()->addDays(30);

            expect($this->service->daysUntilExpiry($cert))->toBeGreaterThanOrEqual(29);
        });

        it('returns the exact remaining day count for a known expiry', function () {
            $cert            = new EntityCertificate();
            // Add a few extra minutes so the assertion never flaps on the boundary
            $cert->not_after = CarbonImmutable::now()->addDays(45)->addMinutes(5);

            expect($this->service->daysUntilExpiry($cert))->toBe(45);
        });
    });

    // ── isDebianWeak() ────────────────────────────────────────────────

    describe('isDebianWeak()', function () {
        it('returns false when no blacklist file exists', function () {
            $path = storage_path('app/debian-blacklist.txt');
            if (file_exists($path)) {
                unlink($path);
            }

            expect($this->service->isDebianWeak('aabbccdd'))->toBeFalse();
        });

        it('returns true when the normalised fingerprint is in the blacklist', function () {
            $path = storage_path('app/debian-blacklist.txt');
            file_put_contents($path, "aabbccdd\n");

            try {
                // Colon-formatted input should match lowercase no-colon list entry
                expect($this->service->isDebianWeak('AA:BB:CC:DD'))->toBeTrue();
            } finally {
                unlink($path);
            }
        });

        it('returns false when the fingerprint is not in the blacklist', function () {
            $path = storage_path('app/debian-blacklist.txt');
            file_put_contents($path, "aabbccdd\n");

            try {
                expect($this->service->isDebianWeak('11:22:33:44'))->toBeFalse();
            } finally {
                unlink($path);
            }
        });
    });
});
