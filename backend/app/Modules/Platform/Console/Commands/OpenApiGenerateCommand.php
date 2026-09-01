<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Support\OpenApiContract;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
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
     * Shared with OpenApiCheckCommand so "generate" and "check" can never
     * disagree about formatting.
     */
    public static function render(Generator $generator): string
    {
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
