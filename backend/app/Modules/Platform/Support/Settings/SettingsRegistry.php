<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Support\Audit\AuditLogger;
use InvalidArgumentException;

/**
 * The one way to read or write an administrator-changeable value.
 *
 * Runtime code calls `get('tickets.auto_close_hours')`, never `config()` and
 * never `env()`. The distinction matters: `config()` is boot-time and cached by
 * `config:cache`, so a value read that way cannot change without a
 * redeployment — which is exactly what this story exists to remove.
 */
final class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /**
     * Resolved values for this request, so repeated reads cost nothing.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly SettingsCache $cache,
        private readonly AuditLogger $audit,
    ) {}

    public function register(SettingDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            /*
             * Two modules claiming one key would make "who owns this?"
             * unanswerable and let one module's validation silently govern
             * another's value.
             */
            throw new InvalidArgumentException(
                "Setting [{$definition->key}] is already registered by another module.",
            );
        }

        if ($definition->type === SettingType::Enum && empty($definition->allowedValues)) {
            throw new InvalidArgumentException(
                "Setting [{$definition->key}] is an enum and must declare allowedValues.",
            );
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function definition(string $key): SettingDefinition
    {
        return $this->definitions[$key] ?? throw ProblemException::make(
            'platform.setting_unknown',
            'Unknown setting',
            404,
            "No setting is registered under [{$key}].",
        );
    }

    /**
     * @return list<string>
     */
    public function knownKeys(): array
    {
        $keys = array_keys($this->definitions);
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);

        $stored = $this->resolvedValues();

        if (! array_key_exists($key, $stored)) {
            return $definition->default;
        }

        $value = $stored[$key];

        /*
         * A stored value that no longer satisfies its definition falls back to
         * the default rather than being returned. This happens when a
         * definition tightens after a value was written; returning the stale
         * value would hand callers something the current rules forbid.
         */
        return $definition->validate($value) === true ? $value : $definition->default;
    }

    /**
     * Every setting, resolved, with secrets redacted.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];

        foreach ($this->definitions as $key => $definition) {
            $values[$key] = $definition->redactedValue($this->get($key));
        }

        return $values;
    }

    /**
     * Writes a value and returns what changed.
     *
     * @return array{before: mixed, after: mixed}
     */
    public function set(string $key, mixed $value, ?int $actorUserId): array
    {
        $definition = $this->definition($key);

        $result = $definition->validate($value);

        if ($result !== true) {
            throw ProblemException::make(
                'platform.setting_invalid',
                'Setting value is not allowed',
                422,
                $result,
                ['setting' => $key],
            );
        }

        $before = $this->get($key);

        $this->repository->upsert($key, $definition->type, $value, $actorUserId);

        /*
         * Busted synchronously, and the per-request memo cleared with it, so a
         * read later in THIS request sees the new value. Deferring either would
         * make "takes effect immediately" true only of the next request.
         */
        $this->cache->forget();
        $this->resolved = null;

        $this->audit->write(
            $actorUserId,
            AuditAction::ConfigChanged->value,
            'setting',
            $key,
            ['value' => $definition->redactedValue($before)],
            ['value' => $definition->redactedValue($value)],
        );

        return ['before' => $before, 'after' => $value];
    }

    /** Drops the in-process memo; the next read re-resolves. */
    public function flush(): void
    {
        $this->resolved = null;
        $this->cache->forget();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedValues(): array
    {
        return $this->resolved ??= $this->cache->resolved(fn (): array => $this->repository->all());
    }
}
