<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Support\OpenApiContract;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

/**
 * Renders the OpenAPI document to YAML.
 *
 * Scramble's own scramble:export writes JSON whatever extension you give it,
 * and the contract this repo publishes is backend/openapi.yaml, so the spec
 * array is re-serialised here instead.
 */
final class OpenApiGenerateCommand extends Command
{
    protected $signature = 'openapi:generate {--path=openapi.yaml : Where to write the document}';

    protected $description = 'Generate backend/openapi.yaml from the registered routes.';

    public function handle(Generator $generator): int
    {
        $path = base_path((string) $this->option('path'));

        file_put_contents($path, self::render($generator));

        $this->info("OpenAPI document written to {$path}.");

        return self::SUCCESS;
    }

    /**
     * Points the app at a throwaway in-memory schema for the duration of
     * generation.
     *
     * Scramble reads a model's columns to describe a response, which needs a
     * reachable database. Without this, generating the contract would depend on
     * whichever database the developer or the CI runner happens to have — and
     * the document is supposed to be a function of the ROUTES, not of anyone's
     * environment. An in-memory SQLite built from this repository's own
     * migrations gives every machine the same answer and needs no service.
     */
    private static function withEphemeralSchema(): void
    {
        Config::set('database.default', 'openapi_schema');
        Config::set('database.connections.openapi_schema', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        /*
         * The cache store too. Its default driver is `database`, and the
         * permission registrar flushes its cache during boot — which would send
         * generation back to the very database this method exists to avoid.
         */
        Config::set('cache.default', 'array');
        Config::set('session.driver', 'array');
        Config::set('queue.default', 'sync');

        DB::purge('openapi_schema');

        Artisan::call('migrate', ['--force' => true, '--database' => 'openapi_schema']);
    }

    /**
     * Shared with OpenApiCheckCommand so "generate" and "check" can never
     * disagree about formatting.
     */
    public static function render(Generator $generator): string
    {
        self::withEphemeralSchema();

        $spec = $generator->generate(Scramble::getGeneratorConfig('default'))->spec();

        return Yaml::dump(
            OpenApiContract::decorate($spec),
            20,
            2,
            // Response codes are strings in OpenAPI; PHP coerces "200" to an int
            // array key, so the dumper has to be told to quote them back.
            Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NUMERIC_KEY_AS_STRING,
        );
    }
}
