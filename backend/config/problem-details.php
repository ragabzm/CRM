<?php

declare(strict_types=1);

return [

    /*
     * Base URI for RFC 9457 `type` members. The full URI is this value plus the
     * machine code, e.g. https://errors.ragab-crm/customers.not_found.
     *
     * It identifies the problem type; it does not have to resolve, though
     * publishing human-readable docs at these URIs is the intent.
     */
    'type_base_uri' => env('PROBLEM_TYPE_BASE_URI', 'https://errors.ragab-crm'),

];
