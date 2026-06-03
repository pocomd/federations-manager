<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Rules\Contracts\MetadataRule;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Finder;

final class RuleRegistry
{
    private const CACHE_KEY = 'metadata_rule_registry';
    private const CACHE_TTL = 3600; // 1 hour

    /** @var MetadataRule[]|null */
    private ?array $rules = null;

    public function __construct(
        private readonly string $rulesPath,
        private readonly string $rulesNamespace,
    ) {}

    /** @return MetadataRule[] */
    public function all(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $classes = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => $this->discover());

        $this->rules = array_map(
            fn(string $class) => app($class),
            $classes,
        );

        return $this->rules;
    }

    public function find(string $id): ?MetadataRule
    {
        foreach ($this->all() as $rule) {
            if ($rule->id() === $id) {
                return $rule;
            }
        }

        return null;
    }

    /** @return string[] Fully-qualified class names */
    public function classNames(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => $this->discover());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->rules = null;
    }

    /** @return string[] */
    private function discover(): array
    {
        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->in($this->rulesPath)
            ->exclude('Contracts')
            ->sortByName();

        $classes = [];

        foreach ($finder as $file) {
            $relativePath = str_replace(
                [DIRECTORY_SEPARATOR, '/'],
                '\\',
                $file->getRelativePathname(),
            );

            $class = $this->rulesNamespace . '\\' . substr($relativePath, 0, -4); // strip .php

            if (
                class_exists($class)
                && is_subclass_of($class, MetadataRule::class)
                && (new \ReflectionClass($class))->isFinal()
            ) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
