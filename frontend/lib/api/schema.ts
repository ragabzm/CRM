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
    "/admin/email/test": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["admin.email.test"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/attachments": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Everything attached to one record */
        get: operations["attachments.index"];
        put?: never;
        post: operations["attachments.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/attachments/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["attachments.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/attachments/{id}/download": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * A short-lived link, or a refusal
         * @description Never streams the bytes through this application in production: the
         *     redirect points at storage, which validates the signature itself. That
         *     keeps large files off the web process entirely.
         */
        get: operations["attachments.download"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/audit-entries": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["audit-entries.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/audit-entries/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["audit-entries.show"];
        put?: never;
        post?: never;
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
    "/admin/categories": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.categories.index"];
        put?: never;
        post: operations["admin.categories.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/categories/{category}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        /** Delete — refused while tickets still use it */
        delete: operations["admin.categories.destroy"];
        options?: never;
        head?: never;
        patch: operations["admin.categories.update"];
        trace?: never;
    };
    "/tickets/{ticket}/customer-context": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["tickets.customer-context"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/customers/duplicates/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["customers.duplicates.preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/customers/{customerId}/notes": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["customers.notes.index"];
        put?: never;
        post: operations["customers.notes.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/notes/{noteId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete: operations["customers.notes.destroy"];
        options?: never;
        head?: never;
        patch: operations["customers.notes.update"];
        trace?: never;
    };
    "/customers/{customer}/timeline": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["customers.timeline"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/customers": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["customers.index"];
        put?: never;
        post: operations["customers.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/customers/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * A single customer, whatever its state
         * @description Deactivated records resolve here on purpose. They are absent from search
         *     so they do not clutter today's work, but a link in an old ticket must
         *     still open the person it refers to — a 404 there would look like data
         *     loss.
         */
        get: operations["customers.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch: operations["customers.update"];
        trace?: never;
    };
    "/customers/{id}/deactivate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Deactivate. The row survives, and so does everything attached to it */
        post: operations["customers.deactivate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/customers/{id}/reactivate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["customers.reactivate"];
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
    "/portal/tickets": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The signed-in customer's own tickets */
        get: operations["portal.tickets.index"];
        put?: never;
        post: operations["portal.tickets.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/priorities": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.priorities.index"];
        put?: never;
        post?: never;
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
    "/admin/quick-replies": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.quick-replies.index"];
        put?: never;
        post: operations["admin.quick-replies.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/quick-replies/reorder": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Reorder by supplying the complete list of ids */
        post: operations["admin.quick-replies.reorder"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/quick-replies/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete: operations["admin.quick-replies.destroy"];
        options?: never;
        head?: never;
        patch: operations["admin.quick-replies.update"];
        trace?: never;
    };
    "/quick-replies": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["quick-replies.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/settings": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Every setting with its type, current value, default and rule */
        get: operations["admin.settings.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/settings/{key}": {
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
        /** Update one setting. Takes effect on the next read, in this request */
        patch: operations["admin.settings.update"];
        trace?: never;
    };
    "/tickets/{ticket}/events": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["tickets.events.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/messages": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["tickets.messages.index"];
        put?: never;
        post: operations["tickets.messages.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/messages/{message}/retry": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Puts a failed send back in the queue
         * @description Retry rather than "send again": the message already exists and already
         *     says who wrote it and when. Creating a second one would put the agent's
         *     words in the thread twice for a failure that was never theirs.
         */
        post: operations["tickets.messages.retry"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["tickets.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch: operations["tickets.update"];
        trace?: never;
    };
    "/tickets/{ticket}/assign": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.assign"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/resolve": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.resolve"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/department": {
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
        /** Moves a ticket to another department */
        patch: operations["tickets.department"];
        trace?: never;
    };
    "/tickets/{ticket}/reopen": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.reopen"];
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
        /**
         * AppendMessageRequest
         * @description No `version` rule, deliberately — see AppendMessage.
         */
        AppendMessageRequest: {
            body: string;
            /** @enum {string} */
            direction?: "inbound" | "outbound" | "internal";
            /**
             * @description Ids of files already uploaded against this ticket. Sent as a list
             *     rather than as the files themselves so a slow or refused upload
             *     never costs the agent the reply they typed.
             */
            attachment_ids?: string[];
        };
        /** AssignTicketRequest */
        AssignTicketRequest: {
            version: number;
            /**
             * @description Present but null returns the ticket to the pool, which is a real
             *     instruction and not the same as omitting the field.
             */
            assignee_id: number | null;
        };
        /** ChangeDepartmentRequest */
        ChangeDepartmentRequest: {
            version: number;
            /**
             * @description Existence is checked by the command, not by an `exists` rule, so
             *     the refusal is `tickets.department_invalid` with the id echoed
             *     back rather than a generic validation error the UI cannot act on.
             */
            department_id: number;
            reason?: string | null;
        };
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
        /** PreviewDuplicatesRequest */
        PreviewDuplicatesRequest: {
            emails?: string[];
            phones?: string[];
            /**
             * @description Set when checking an EXISTING customer's edits, so they are not
             *     offered as their own duplicate.
             */
            exclude_customer_id?: string;
        };
        /** ReopenTicketRequest */
        ReopenTicketRequest: {
            version: number;
            reason?: string | null;
        };
        /** ResetPasswordRequest */
        ResetPasswordRequest: {
            token: string;
            /** Format: email */
            email: string;
            password: string;
            password_confirmation: string;
        };
        /** ResolveTicketRequest */
        ResolveTicketRequest: {
            version: number;
            resolution_note: string;
        };
        /** StoreAttachmentRequest */
        StoreAttachmentRequest: {
            /** @enum {string} */
            owner_type: "customer" | "ticket" | "message";
            /**
             * @description ULID-shaped, and that is as far as the check goes. Platform is T0 and cannot look inside Customers or Tickets to
             *     confirm the owner exists — a query from here would invert the
             *     dependency graph. The owning module checks existence when it
             *     builds the upload, and an attachment pointing at nothing is
             *     invisible rather than dangerous: it is only ever read through
             *     "everything attached to THIS record".
             */
            owner_id: string;
            /**
             * Format: binary
             * @description No `max` and no `mimes` here on purpose. The size cap and the
             *     allow-list are runtime settings an administrator controls, and
             *     baking them into a validation rule would freeze them at deploy
             *     time. AttachmentUploader applies both, against the current values.
             */
            file: string;
        };
        /** StoreCustomerRequest */
        StoreCustomerRequest: {
            full_name: string;
            department_id: number;
            /** @enum {string|null} */
            preferred_channel?: "email" | "phone" | null;
            notes?: string | null;
            /**
             * @description At least one way to reach them. A customer record with no contact
             *     details cannot be replied to, which makes it a name in a list
             *     rather than a customer.
             */
            identifiers: {
                /** @enum {string} */
                kind: "email" | "phone";
                value: string;
                is_primary?: boolean;
            }[];
            /** @description The client's acknowledgement of an offered duplicate. */
            confirm_create_duplicate?: boolean;
        };
        /** StoreDepartmentRequest */
        StoreDepartmentRequest: {
            /**
             * @description max:120 counts CHARACTERS, not bytes, so an Arabic department
             *     name is not silently shorter than an English one.
             */
            name: string;
        };
        /**
         * StorePortalTicketRequest
         * @description What a customer may say when opening a ticket.
         *
         *     Deliberately short. No `customer_id` (taken from the account), no `channel`
         *     (fixed to portal), no `priority`, `assignee_id` or `department_id` — a
         *     customer marking their own ticket Urgent and assigning it to a named agent
         *     would make those fields mean nothing.
         */
        StorePortalTicketRequest: {
            subject: string;
            description: string;
        };
        /** StoreTicketRequest */
        StoreTicketRequest: {
            subject: string;
            description: string;
            customer_id: string;
            /** @enum {string} */
            channel: "agent" | "portal" | "email" | "system";
            category_id?: number | null;
            /** @enum {string|null} */
            priority?: "low" | "normal" | "high" | "urgent" | null;
            department_id?: number | null;
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
        /**
         * UpdateCustomerRequest
         * @description Every field optional; supplying `identifiers` replaces the whole set.
         *
         *     Replacement rather than merge, because the form the agent is looking at shows
         *     the complete list — the rows they deleted are gone from what they submit, and
         *     a merge would silently resurrect them.
         */
        UpdateCustomerRequest: {
            full_name?: string;
            department_id?: number;
            /** @enum {string|null} */
            preferred_channel?: "email" | "phone" | null;
            notes?: string | null;
            identifiers?: {
                /** @enum {string} */
                kind?: "email" | "phone";
                value?: string;
                is_primary?: boolean;
            }[];
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
        /**
         * UpdateTicketAttributesRequest
         * @description A partial change. Every attribute optional, the version required.
         *
         *     The version is required even though the guard would tolerate null, because a
         *     caller who omitted it would be silently opting out of the protection this
         *     whole mechanism exists for.
         *
         *     It may arrive in the body as `version` or in an `If-Match` header — the same
         *     number, spelled the way the caller finds natural. There is still ONE guard
         *     reading ONE value; a second mechanism would be a second thing to get wrong,
         *     and the one that is wrong is the one nobody tested.
         */
        UpdateTicketAttributesRequest: {
            /** @description Required only when the header did not carry it. */
            version?: number;
            /** @enum {string} */
            status?: "open" | "pending" | "resolved" | "closed";
            /** @enum {string} */
            priority?: "low" | "normal" | "high" | "urgent";
            category_id?: number | null;
            assignee_id?: number | null;
            department_id?: number | null;
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
    "admin.email.test": {
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
            202: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        /** @constant */
                        status: "accepted";
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
    "attachments.index": {
        parameters: {
            query: {
                owner_type: string;
                owner_id: string;
            };
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
                            [key: string]: unknown;
                        }[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "attachments.store": {
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
                "multipart/form-data": components["schemas"]["StoreAttachmentRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "attachments.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
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
                        [key: string]: unknown;
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
    "attachments.download": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
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
                    "application/json": Record<string, never>;
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
    "audit-entries.index": {
        parameters: {
            query?: {
                actor_id?: string;
                actor_search?: string;
                action?: "auth.sign_in.succeeded" | "auth.sign_in.failed" | "user.created" | "user.updated" | "user.deactivated" | "user.reactivated" | "department.created" | "department.updated" | "department.deleted" | "config.changed" | "ticket.field_changed" | "customer.field_changed";
                from?: string;
                to?: string;
                per_page?: number;
            };
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
                            id: string;
                            occurred_at: string;
                            actor: {
                                id: string | null;
                                type: string;
                                label: string;
                            };
                            action: string;
                            target: {
                                type: string | null;
                                id: string | null;
                            };
                            source_ip: string | null;
                            request_id: string | null;
                        }[];
                        meta: {
                            page: number;
                            per_page: number;
                            total: number;
                            last_page: number;
                        };
                        actions: string[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "audit-entries.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
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
                        id: string;
                        occurred_at: string;
                        actor: {
                            id: string | null;
                            type: string;
                            label: string;
                        };
                        action: string;
                        target: {
                            type: string | null;
                            id: string | null;
                        };
                        before: {
                            [key: string]: unknown;
                        } | null;
                        after: {
                            [key: string]: unknown;
                        } | null;
                        source_ip: string | null;
                        request_id: string | null;
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
    "admin.categories.index": {
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
                            name: {
                                en: string;
                                ar: string;
                            };
                            sort_order: number;
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
    "admin.categories.store": {
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
                "application/json": {
                    name: {
                        /**
                         * @description No `parent` rule, because there is no parent. The list is flat by
                         *     construction — see the migration.
                         */
                        en: string;
                        ar: string;
                    };
                };
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
                        name: {
                            en: string;
                            ar: string;
                        };
                        sort_order: number;
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
    "admin.categories.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The category ID */
                category: number;
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
                        deleted: number;
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
    "admin.categories.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The category ID */
                category: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    name: {
                        /**
                         * @description No `parent` rule, because there is no parent. The list is flat by
                         *     construction — see the migration.
                         */
                        en: string;
                        ar: string;
                    };
                };
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
                        name: {
                            en: string;
                            ar: string;
                        };
                        sort_order: number;
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
    "tickets.customer-context": {
        parameters: {
            query?: never;
            header?: never;
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
                        [key: string]: unknown;
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
    "customers.duplicates.preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["PreviewDuplicatesRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        matches: {
                            customer_id: string;
                            reference: string;
                            full_name: string;
                            state: string;
                            matched_value: string;
                            matched_kind: string;
                        }[];
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "customers.notes.index": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                customerId: string;
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
                        data: {
                            [key: string]: unknown;
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
    "customers.notes.store": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                customerId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    body: string;
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "customers.notes.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                noteId: string;
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
                        deleted: string;
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
    "customers.notes.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                noteId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    body: string;
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "customers.timeline": {
        parameters: {
            query?: {
                cursor?: string | null;
                limit?: number;
            };
            header?: never;
            path: {
                customer: string;
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
                        data: {
                            id: string;
                            kind: string;
                            ticket_id: string;
                            ticket_ref: string;
                            occurred_at: string;
                            preview: string | null;
                        }[];
                        next_cursor: string | null;
                        has_more: boolean;
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "customers.index": {
        parameters: {
            query?: {
                q?: string;
                state?: "active" | "inactive" | "all";
                department_id?: number;
                limit?: number;
            };
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
                            [key: string]: unknown;
                        }[];
                        meta: {
                            page: number;
                            per_page: number;
                            total: number;
                            last_page: number;
                        };
                    };
                };
            };
            401: components["responses"]["AuthenticationException"];
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
    "customers.store": {
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
                "application/json": components["schemas"]["StoreCustomerRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "customers.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                id: string;
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
                        [key: string]: unknown;
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
    "customers.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["UpdateCustomerRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "customers.deactivate": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                id: string;
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
                        [key: string]: unknown;
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
    "customers.reactivate": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                id: string;
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
                        [key: string]: unknown;
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
    "portal.tickets.index": {
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
                            [key: string]: unknown;
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
    "portal.tickets.store": {
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
                "application/json": components["schemas"]["StorePortalTicketRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "admin.priorities.index": {
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
                            value: string;
                        }[];
                        editable: boolean;
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
                        roles: string[];
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
    "admin.quick-replies.index": {
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
                            id: string;
                            label: {
                                en: string;
                                ar: string;
                            };
                            body: {
                                en: string;
                                ar: string;
                            };
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
    "admin.quick-replies.store": {
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
                "application/json": {
                    label: {
                        /**
                         * @description Both languages, always. A reply that exists in one language is a
                         *     gap an agent discovers mid-conversation with a customer waiting.
                         */
                        en: string;
                        ar: string;
                    };
                    body: {
                        en: string;
                        ar: string;
                    };
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: string;
                        label: {
                            en: string;
                            ar: string;
                        };
                        body: {
                            en: string;
                            ar: string;
                        };
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
    "admin.quick-replies.reorder": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path?: never;
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    order?: string;
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        data: {
                            id: string;
                            label: {
                                en: string;
                                ar: string;
                            };
                            body: {
                                en: string;
                                ar: string;
                            };
                        }[];
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
    "admin.quick-replies.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                id: string;
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
                        data: {
                            id: string;
                            label: {
                                en: string;
                                ar: string;
                            };
                            body: {
                                en: string;
                                ar: string;
                            };
                        }[];
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
    "admin.quick-replies.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                id: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    label: {
                        /**
                         * @description Both languages, always. A reply that exists in one language is a
                         *     gap an agent discovers mid-conversation with a customer waiting.
                         */
                        en: string;
                        ar: string;
                    };
                    body: {
                        en: string;
                        ar: string;
                    };
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: string;
                        label: {
                            en: string;
                            ar: string;
                        };
                        body: {
                            en: string;
                            ar: string;
                        };
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
    "quick-replies.index": {
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
                            id: string;
                            label: {
                                en: string;
                                ar: string;
                            };
                            body: {
                                en: string;
                                ar: string;
                            };
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
    "admin.settings.index": {
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
                            key: string;
                            type: string;
                            value: unknown;
                            default: unknown;
                            secret: boolean;
                            summary: string;
                            allowed_values: string[] | null;
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
    "admin.settings.update": {
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
        requestBody?: {
            content: {
                "application/json": {
                    value?: string;
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        key: string;
                        value: unknown;
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
    "tickets.events.index": {
        parameters: {
            query?: {
                cursor?: string;
            };
            header?: never;
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
                        data: {
                            [key: string]: unknown;
                        }[];
                        next_cursor: string | null;
                        has_more: boolean;
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
    "tickets.messages.index": {
        parameters: {
            query?: never;
            header?: never;
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
                        data: {
                            [key: string]: unknown;
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
    "tickets.messages.store": {
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
        requestBody: {
            content: {
                "application/json": components["schemas"]["AppendMessageRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.messages.retry": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                ticket: string;
                message: string;
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
                        [key: string]: unknown;
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
    "tickets.store": {
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
                "application/json": components["schemas"]["StoreTicketRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.show": {
        parameters: {
            query?: never;
            header?: never;
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
                        [key: string]: unknown;
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
    "tickets.update": {
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
        requestBody?: {
            content: {
                "application/json": components["schemas"]["UpdateTicketAttributesRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.assign": {
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
        requestBody: {
            content: {
                "application/json": components["schemas"]["AssignTicketRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.resolve": {
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
        requestBody: {
            content: {
                "application/json": components["schemas"]["ResolveTicketRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.department": {
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
        requestBody: {
            content: {
                "application/json": components["schemas"]["ChangeDepartmentRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
    "tickets.reopen": {
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
        requestBody: {
            content: {
                "application/json": components["schemas"]["ReopenTicketRequest"];
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        [key: string]: unknown;
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
