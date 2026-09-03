<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and write the settings registry.
 *
 * Thin by design: validation lives in the definition, so every writer — this
 * controller, a console command, a future importer — is judged by the same
 * rule rather than by whichever checks its own author remembered.
 */
final class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * Every setting with its type, current value, default and rule.
     *
     * @response array{data: array<int, array{key:string,type:string,value:mixed,default:mixed,secret:bool,configured:bool,summary:string,allowed_values:array<int,string>|null}>}
     */
    public function index(): JsonResponse
    {
        $data = [];

        foreach ($this->registry->definitions() as $key => $definition) {
            $data[] = [
                'key' => $key,
                'type' => $definition->type->value,
                'value' => $definition->redactedValue($this->registry->get($key)),
                // The default travels with the value so the console can offer
                // "reset to default" without a second round trip.
                'default' => $definition->redactedValue($definition->default),
                'secret' => $definition->secret,
                /*
                 * Whether a credential is set — the one fact about it a reader
                 * legitimately needs. Without it an unset password and a set
                 * one look identical, and the only way to tell them apart is
                 * to break something.
                 */
                'configured' => $definition->isConfigured($this->registry->get($key)),
                'summary' => $definition->summary,
                'allowed_values' => $definition->allowedValues,
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * Update one setting. Takes effect on the next read, in this request.
     *
     * @response array{key:string,value:mixed}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        // `has` rather than `input`, so writing an explicit null is possible
        // and distinguishable from omitting the field.
        if (! $request->has('value')) {
            throw \App\Modules\Platform\Exceptions\ProblemException::make(
                'platform.setting_invalid',
                'Setting value is required',
                422,
                'Provide a `value` for this setting.',
                ['setting' => $key],
            );
        }

        $definition = $this->registry->definition($key);

        $this->registry->set($key, $request->input('value'), $request->user()?->getAuthIdentifier());

        return new JsonResponse([
            'key' => $key,
            'value' => $definition->redactedValue($this->registry->get($key)),
        ]);
    }
}
