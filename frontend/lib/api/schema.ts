// GENERATED FILE — DO NOT EDIT. Regenerate via `pnpm run api:generate`.
// Source of truth: backend/openapi.yaml
export interface paths {
    "/audit": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["audit.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/settings/{key}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put: operations["settings.update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/reassign": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.reassign"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/auth/login": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Sign in */
        post: operations["auth.login"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/auth/session": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Session timing, so the frontend can warn before a session lapses rather
         *     than discovering it on the next request
         */
        get: operations["auth.session"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/auth/logout": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Sign out and destroy the session */
        post: operations["auth.logout"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/auth/me": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The signed-in staff member */
        get: operations["auth.me"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/departments": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["departments.index"];
        put?: never;
        post: operations["departments.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/departments/{department}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /** Rename */
        patch: operations["departments.update"];
        trace?: never;
    };
    "/departments/{department}/deactivate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Deactivate — refused while active tickets remain */
        post: operations["departments.deactivate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
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
    "/auth/password/forgot": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Request a reset link */
        post: operations["auth.password.forgot"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/auth/password/reset": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Redeem a reset token */
        post: operations["auth.password.reset"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/profile": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["profile.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /** Update name and language */
        patch: operations["profile.update"];
        trace?: never;
    };
    "/profile/password": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Change password */
        post: operations["profile.password"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/users": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["users.index"];
        put?: never;
        post: operations["users.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/users/{user}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["users.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch: operations["users.update"];
        trace?: never;
    };
    "/users/{user}/deactivate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Deactivate, never delete */
        post: operations["users.deactivate"];
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
        /** ChangePasswordRequest */
        ChangePasswordRequest: {
            /**
             * @description Proves the person at the keyboard is the account holder, not
             *     someone who found an unlocked screen.
             */
            current_password: string;
            password: string;
            password_confirmation: string;
        };
        /** ForgotPasswordRequest */
        ForgotPasswordRequest: {
            /** Format: email */
            email: string;
        };
        /** LoginRequest */
        LoginRequest: {
            /**
             * Format: email
             * @description Deliberately NOT validated against the password policy. The policy
             *     governs passwords being SET; applying it here would tell an
             *     attacker the shape of a valid password before they ever guess one,
             *     and would lock out any account whose password predates a policy
             *     change.
             */
            email: string;
            password: string;
            remember?: boolean;
        };
        /** ResetPasswordRequest */
        ResetPasswordRequest: {
            token: string;
            /** Format: email */
            email: string;
            password: string;
            password_confirmation: string;
        };
        /** StoreDepartmentRequest */
        StoreDepartmentRequest: {
            /**
             * @description max:120 counts CHARACTERS, not bytes, so an Arabic department
             *     name is not silently shorter than an English one.
             */
            name: string;
        };
        /** StoreUserRequest */
        StoreUserRequest: {
            name: string;
            /** Format: email */
            email: string;
            /** @enum {string} */
            role: "administrator" | "supervisor" | "agent" | "customer";
            department_id?: number | null;
            is_active?: boolean;
            /**
             * @description Optional: omitted means the account is created without a usable
             *     password and the person sets one through the reset flow, which is
             *     better than an administrator inventing and transmitting one.
             */
            password?: string | null;
            password_confirmation?: string | null;
        };
        /** UpdateDepartmentRequest */
        UpdateDepartmentRequest: {
            name: string;
            is_active?: boolean;
        };
        /** UpdateProfileRequest */
        UpdateProfileRequest: {
            name?: string;
            /**
             * @description The two locales the product ships. An unknown value is a 422
             *     rather than a silent fallback, so a typo surfaces at the source.
             * @enum {string}
             */
            preferred_locale?: "en" | "ar";
        };
        /** UpdateUserRequest */
        UpdateUserRequest: {
            name?: string;
            /** Format: email */
            email?: string;
            /** @enum {string} */
            role?: "administrator" | "supervisor" | "agent" | "customer";
            department_id?: number | null;
            is_active?: boolean;
        };
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
    responses: {
        /** @description Validation error */
        ValidationException: {
            headers: {
                [name: string]: unknown;
            };
            content: {
                "application/json": {
                    /** @description Errors overview. */
                    message: string;
                    /** @description A detailed description of each field that failed validation. */
                    errors: {
                        [key: string]: string[];
                    };
                };
            };
        };
        /** @description Unauthenticated */
        AuthenticationException: {
            headers: {
                [name: string]: unknown;
            };
            content: {
                "application/json": {
                    /** @description Error overview. */
                    message: string;
                };
            };
        };
        /** @description Authorization error */
        AuthorizationException: {
            headers: {
                [name: string]: unknown;
            };
            content: {
                "application/json": {
                    /** @description Error overview. */
                    message: string;
                };
            };
        };
        /** @description Not found */
        ModelNotFoundException: {
            headers: {
                [name: string]: unknown;
            };
            content: {
                "application/json": {
                    /** @description Error overview. */
                    message: string;
                };
            };
        };
    };
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type $defs = Record<string, never>;
export interface operations {
    "audit.index": {
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
                        data: string[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "settings.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                key: string;
            };
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
                        key: string;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "tickets.reassign": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                ticket: string;
            };
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
                        ticket: string;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "auth.login": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["LoginRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        email: string;
                        preferred_locale: string;
                        roles: string[];
                    };
                };
            };
            422: components["responses"]["ValidationException"];
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
    "auth.session": {
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
                        inactivity_minutes: number;
                        authenticated: boolean;
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
    "auth.logout": {
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
            401: components["responses"]["AuthenticationException"];
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
    "auth.me": {
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
                        id: number;
                        name: string;
                        email: string;
                        preferred_locale: string;
                        roles: string[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "departments.index": {
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
                        data: {
                            id: number;
                            name: string;
                            is_active: boolean;
                        }[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "departments.store": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["StoreDepartmentRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            /** @description The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict). */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            422: components["responses"]["ValidationException"];
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
    "departments.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The department ID */
                department: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["UpdateDepartmentRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            404: components["responses"]["ModelNotFoundException"];
            /** @description The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict). */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            422: components["responses"]["ValidationException"];
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
    "departments.deactivate": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The department ID */
                department: number;
            };
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
                        id: number;
                        name: string;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            404: components["responses"]["ModelNotFoundException"];
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
    "auth.password.forgot": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ForgotPasswordRequest"];
            };
        };
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
            422: components["responses"]["ValidationException"];
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
    "auth.password.reset": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ResetPasswordRequest"];
            };
        };
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
            422: components["responses"]["ValidationException"];
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
    "profile.show": {
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
                        id: number;
                        name: string;
                        email: string;
                        preferred_locale: string;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "profile.update": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["UpdateProfileRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        email: string;
                        preferred_locale: string;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            403: components["responses"]["AuthorizationException"];
            422: components["responses"]["ValidationException"];
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
    "profile.password": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ChangePasswordRequest"];
            };
        };
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
            401: components["responses"]["AuthenticationException"];
            403: components["responses"]["AuthorizationException"];
            422: components["responses"]["ValidationException"];
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
    "users.index": {
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
                        data: {
                            id: number;
                            name: string;
                            email: string;
                            role: string | null;
                            department_id: number | null;
                            is_active: boolean;
                        }[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "users.store": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["StoreUserRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        email: string;
                        role: string | null;
                        department_id: number | null;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            /** @description The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict). */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            422: components["responses"]["ValidationException"];
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
    "users.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description The user ID */
                user: number;
            };
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
                        id: number;
                        name: string;
                        email: string;
                        role: string | null;
                        department_id: number | null;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            404: components["responses"]["ModelNotFoundException"];
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
    "users.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The user ID */
                user: number;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["UpdateUserRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        name: string;
                        email: string;
                        role: string | null;
                        department_id: number | null;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            404: components["responses"]["ModelNotFoundException"];
            /** @description The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict). */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/problem+json": components["schemas"]["Problem"];
                };
            };
            422: components["responses"]["ValidationException"];
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
    "users.deactivate": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The user ID */
                user: number;
            };
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
                        id: number;
                        name: string;
                        email: string;
                        role: string | null;
                        department_id: number | null;
                        is_active: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
            404: components["responses"]["ModelNotFoundException"];
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
