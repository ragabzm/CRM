// GENERATED FILE — DO NOT EDIT. Regenerate via `pnpm run api:generate`.
// Source of truth: backend/openapi.yaml
export interface paths {
    "/healthz": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Liveness probe
         * @description Returns 200 as long as the web process can serve a request. It does not
         *     touch the database on purpose: this answers "is this process up", not
         *     "is the whole stack healthy".
         */
        get: operations["platform.healthz"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/healthz-echo": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Echoes the posted payload back
         * @description TEMPORARY (Story 1.1 only): the acceptance criteria require a write
         *     endpoint to prove Idempotency-Key replay against, and no real one exists
         *     yet. Remove this once Story 1.2+ introduces genuine writes.
         */
        post: operations["platform.healthz-echo"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
}
export type webhooks = Record<string, never>;
export interface components {
    schemas: {
        /**
         * Problem
         * @description RFC 9457 problem details. Every 4xx and 5xx response in this API has this shape.
         */
        Problem: {
            /**
             * Format: uri
             * @description A URI identifying the problem type. Always the base URI plus `code`.
             */
            type: string;
            /** @description A short, human-readable summary. */
            title: string;
            status: number;
            /** @description A human-readable explanation specific to this occurrence. */
            detail?: string | null;
            /** @description The request URI this problem occurred on. */
            instance: string;
            /**
             * @description Stable machine identifier shaped `module.condition`. Branch on this, never on `title`.
             * @example platform.internal_error
             * @example platform.validation_failed
             * @example platform.unauthorized
             * @example platform.forbidden
             * @example platform.not_found
             * @example platform.method_not_allowed
             * @example platform.conflict
             * @example platform.too_many_requests
             * @example platform.request_failed
             * @example platform.idempotency_conflict
             * @example platform.idempotency_in_flight
             */
            code: string;
            /** @description Correlation id for this request; matches the X-Request-Id response header and the request_id in the logs. */
            trace_id: string;
            /** @description Present on validation failures: field name to list of messages. */
            errors?: {
                [key: string]: string[];
            };
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type $defs = Record<string, never>;
export interface operations {
    "platform.healthz": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        status: string;
                    };
                };
            };
            /** @description An RFC 9457 problem document. */
            default: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
        };
    };
    "platform.healthz-echo": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        status: string;
                        echo: {
                            [key: string]: unknown;
                        };
                    };
                };
            };
            /** @description The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict). */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            /** @description A concurrent request with the same Idempotency-Key is still in flight (code: platform.idempotency_in_flight). */
            425: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            /** @description An RFC 9457 problem document. */
            default: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
        };
    };
}
