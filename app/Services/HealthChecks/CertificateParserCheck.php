<?php

declare(strict_types=1);

namespace App\Services\HealthChecks;

use App\Services\Entity\CertificateService;
use App\Services\HealthChecks\Contracts\HealthCheck;
use Throwable;

final class CertificateParserCheck implements HealthCheck
{
    // Self-signed RSA-2048 cert valid 2026–2036; generated for health-check round-trip only
    private const TEST_PEM = <<<'PEM'
        -----BEGIN CERTIFICATE-----
        MIIDDTCCAfWgAwIBAgIUDnTkvz1yBh7AK2VBnLTxR0i76cYwDQYJKoZIhvcNAQEL
        BQAwFjEUMBIGA1UEAwwLaGVhbHRoY2hlY2swHhcNMjYwNTA5MTEzNjE3WhcNMzYw
        NTA2MTEzNjE3WjAWMRQwEgYDVQQDDAtoZWFsdGhjaGVjazCCASIwDQYJKoZIhvcN
        AQEBBQADggEPADCCAQoCggEBAKWBO5BkqsXEWBUlY29Du8Ame2pcj23TrzdAkjCx
        GUnI4S9Zv8tJW2Xyh+QI2pAf6lqL8lEHzAuy1XmmK5R5Dp6bCQsvMw2z2KXwIjb/
        +SGrqHQfy9vUPbwFn7Hl9vn9jQnVwmGMwmr6u9I+hotK6x3QUu2gHxInwLZq9J4d
        uDh3+Cv/LVe536bRw+REp7wDleeOmLI4oyRNS0Le/llvmTcx9AdTiN1+rhcHnepk
        KFFZLZn9dtmXc0S4Wiygk79RmtZM8h2MA0L0Q2vatGTe+2ZEnQAZQqtHL3UU16X8
        h5j96KHv5HpZsl0uqrZxz1iScHFuByd/G3MZis4oHUwF82MCAwEAAaNTMFEwHQYD
        VR0OBBYEFN2THybw2p9pfb6cpxj3RHm8CsWdMB8GA1UdIwQYMBaAFN2THybw2p9p
        fb6cpxj3RHm8CsWdMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEB
        ACXfMLZog8SFg0S814ZqYbxINGNUfua/POzsY2jmFbqFYl1R4TWl+qNvF7C7UWkz
        /KeVjJnkMzMmC3ChG9zwFqbMIIQ7qwg8GJ2f3u1ZaBVWYwdvpqr+HuR4MKOT/+xW
        Z4jUiOnKRDTrn26KBrWiv2+bX8CN5LqDupVNbEz7rlHG37n4SB1hZ53+LxdyGkoc
        pdNrHvdT0op/te/hLVoC4QhYsOUCDIRZjNnwN3BdaFGH8LZyWu2w9aJ3H2T00j4c
        IZAXbi2aQqS8ot3h58mWWnq3J5Xgpl02MYmV49zBaFvx8POTCwAhKLnevWQWWdeg
        5f78aM2p+oBBimRnta0JA0k=
        -----END CERTIFICATE-----
        PEM;

    public function __construct(private readonly CertificateService $certificates) {}

    public function run(): HealthCheckResult
    {
        $start = hrtime(true);

        try {
            $result = $this->certificates->parse(self::TEST_PEM);
            $ms     = (int) ((hrtime(true) - $start) / 1_000_000);

            if (empty($result['subject']) || empty($result['not_after'])) {
                return HealthCheckResult::fail('cert_parser', 'Parsed cert missing expected fields', $ms);
            }

            return HealthCheckResult::ok('cert_parser', 'Certificate parsing works', $ms);
        } catch (Throwable $e) {
            $ms = (int) ((hrtime(true) - $start) / 1_000_000);
            return HealthCheckResult::fail('cert_parser', 'Parse error: ' . $e->getMessage(), $ms);
        }
    }
}
