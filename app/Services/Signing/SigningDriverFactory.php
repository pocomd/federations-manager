<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\Federation;
use App\Services\Signing\Contracts\SigningDriver;
use InvalidArgumentException;

class SigningDriverFactory
{
    /** @var array<string, class-string<SigningDriver>> */
    private array $drivers = [
        'file'    => FileSigningDriver::class,
        'softhsm' => SoftHsmSigningDriver::class,
    ];

    public function make(Federation $federation): SigningDriver
    {
        $name = $federation->signing_driver ?? 'file';

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Unknown signing driver: \"{$name}\"");
        }

        return app($this->drivers[$name]);
    }

    /**
     * Return name => label for all active drivers (controlled by ENV flags).
     *
     * @return array<string, string>
     */
    public function activeDrivers(): array
    {
        $map = [
            'file'    => ['label' => 'Local file (PEM on disk)',   'key' => 'FILE_SIGNING_IS_ACTIVE'],
            'softhsm' => ['label' => 'SoftHSM2 (PKCS#11 token)',  'key' => 'SOFTHSM_SIGNING_IS_ACTIVE'],
        ];

        return collect($map)
            ->filter(fn($d) => config('federation.signing_drivers.' . $d['key'], false))
            ->mapWithKeys(fn($d, $key) => [$key => $d['label']])
            ->all();
    }
}
