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
    "/api-clients": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["api-clients.index"];
        put?: never;
        post: operations["api-clients.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api-clients/{client}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete: operations["api-clients.destroy"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/categories": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.knowledge.categories.index"];
        put?: never;
        post: operations["admin.knowledge.categories.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/categories/{category}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        /**
         * Delete — refused while articles still sit in it
         * @description Every article belongs to exactly one category, so deleting an occupied
         *     one has no correct outcome: the articles cannot be left pointing at
         *     nothing, and moving them somewhere the administrator did not choose is a
         *     decision this endpoint has no business making.
         */
        delete: operations["admin.knowledge.categories.destroy"];
        options?: never;
        head?: never;
        patch: operations["admin.knowledge.categories.update"];
        trace?: never;
    };
    "/admin/knowledge/articles/{article}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Draft or Archived → Published */
        post: operations["admin.knowledge.articles.publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/articles/{article}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Published → Archived */
        post: operations["admin.knowledge.articles.archive"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/knowledge/search": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["knowledge.search"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/articles/{article}/translations/{locale}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put: operations["admin.knowledge.articles.translations.upsert"];
        post?: never;
        /**
         * Removes one language version
         * @description Refused for the article's default language while others exist, because
         *     the default is the fallback every reader lands on when theirs is
         *     missing. Removing it turns "we do not have your language" into a blank
         *     page.
         */
        delete: operations["admin.knowledge.articles.translations.destroy"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/articles": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.knowledge.articles.index"];
        put?: never;
        post: operations["admin.knowledge.articles.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/knowledge/articles/{article}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One article, in the reader's language where it exists */
        get: operations["admin.knowledge.articles.show"];
        put?: never;
        post?: never;
        /** Delete — only for an article nobody has ever seen */
        delete: operations["admin.knowledge.articles.destroy"];
        options?: never;
        head?: never;
        patch: operations["admin.knowledge.articles.update"];
        trace?: never;
    };
    "/admin/assignment-mappings": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.assignment-mappings.index"];
        put?: never;
        post: operations["admin.assignment-mappings.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/assignment-mappings/{mapping}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete: operations["admin.assignment-mappings.destroy"];
        options?: never;
        head?: never;
        patch: operations["admin.assignment-mappings.update"];
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
    "/branches": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["branches.index"];
        put?: never;
        post: operations["branches.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/branches/{branch}": {
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
        patch: operations["branches.update"];
        trace?: never;
    };
    "/branding": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["branding.show"];
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
    "/admin/channels": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.channels.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/channels/{id}": {
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
        patch: operations["admin.channels.update"];
        trace?: never;
    };
    "/chat-desk/conversations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Who is waiting, oldest first, plus what this agent already holds
         * @description Both in one response: an agent's chat pane needs "is anybody waiting?"
         *     and "what am I in the middle of?" on the same interval, and asking twice
         *     would let the two disagree.
         */
        get: operations["chat.desk.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat-desk/conversations/{conversation}/take": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["chat.desk.take"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat-desk/conversations/{conversation}/close": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["chat.desk.close"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat/embed-origins": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * The origins allowed to embed the widget
         * @description Public, and it gives nothing away: it is the list of sites that may
         *     frame a page they can already load. The frontend reads it to build the
         *     `frame-ancestors` policy on the frame document, which is the half of
         *     this rule a browser actually enforces.
         */
        get: operations["chat.origins"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat/conversations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["chat.open"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat/conversations/current": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Rejoins the conversation the cookie names, if it is still open
         * @description This is what makes a reload continue rather than restart. It answers
         *     404 rather than an error when there is nothing to rejoin, because "you
         *          * have no conversation" is the ordinary case for anybody opening the
         *     widget for the first time.
         */
        get: operations["chat.current"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat/conversations/current/messages": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Everything said so far, newest last
         * @description The whole transcript every time rather than a cursor. A chat is a
         *     handful of short messages, the widget is redrawing a list it already
         *     holds, and a `since=` parameter is a synchronisation layer with an
         *     off-by-one that shows up as a message nobody ever sees.
         */
        get: operations["chat.messages"];
        put?: never;
        post: operations["chat.send"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/chat/conversations/current/close": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["chat.close"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
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
    "/feedback/{ticket}/{verdict}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["tickets.feedback.invitation"];
        put?: never;
        post?: never;
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
    "/help/articles": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Search first, categories second */
        get: operations["help.articles.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/help/articles/{article}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One article, if a customer may read it */
        get: operations["help.articles.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/inbound/email": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["inbound.email"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/integrations/log": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The log, newest first */
        get: operations["integrations.log"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/integrations/erp/test": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Tries the configuration, and says exactly what happened
         * @description The endpoint reached, the status, the timing, and the error where there
         *     is one. "Failed" on its own is a result an administrator can do nothing
         *     with — and the whole point of this button is that they can.
         */
        post: operations["integrations.test"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/email/log": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.email.log"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/email/quarantine": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.email.quarantine.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/email/quarantine/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One message, with its bytes */
        get: operations["admin.email.quarantine.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/admin/email/quarantine/{id}/replay": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Feeds the message back through intake */
        post: operations["admin.email.quarantine.replay"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/notifications": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["notifications.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/notifications/{id}/read": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["notifications.read"];
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
    "/me/tasks": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * My tasks, open ones first and the soonest at the top
         * @description Completed tasks are included but sorted below: they are the evidence the
         *     work happened, and hiding them makes an agent wonder whether the tick
         *     registered.
         */
        get: operations["me.tasks.index"];
        put?: never;
        post: operations["me.tasks.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/me/tasks/{task}": {
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
        patch: operations["me.tasks.update"];
        trace?: never;
    };
    "/me/reminders": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["me.reminders.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/me/mentions": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * The notes that named me, newest first
         * @description Each carries enough to open the ticket AT the note — the reference, the
         *     subject and the message id — because the whole content of a mention is
         *     "come and read this sentence", and landing at the top of a long thread
         *     asks somebody to search for it.
         */
        get: operations["me.mentions.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/me/mentions/{mention}/read": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["me.mentions.read"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/inbound/{channel}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["inbound.phone"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/inbound/{channel}/receipts": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * A provider telling us what happened to something we sent
         * @description Separate from the inbound route because it is a different fact about a
         *     different message. `delivered` and `read` are set HERE and nowhere else
         *     — never inferred from a successful send, because "we handed it over" and
         *     "it arrived" are different things and an agent reading "delivered" is
         *     entitled to believe the second.
         */
        post: operations["inbound.phone.receipts"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/register": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.auth.register"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/login": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.auth.login"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/logout": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.auth.logout"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/me": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["portal.auth.me"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/password/forgot": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.auth.password.forgot"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/auth/password/reset": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.auth.password.reset"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/requests": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["portal.requests.index"];
        put?: never;
        post: operations["portal.requests.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/requests/{id}/rating": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * How it went, in one tap
         * @description `positive` is a boolean and there is no third value on the wire. A
         *     client cannot send a 3, a 0 or a star count, because the validation
         *     refuses anything that is not a boolean — which is the cheapest place to
         *     hold a decision the whole product depends on.
         */
        post: operations["portal.requests.rate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/requests/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["portal.requests.show"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/requests/{id}/replies": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.requests.reply"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/portal/requests/{id}/reopen": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["portal.requests.reopen"];
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
    "/reports": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["reports.show"];
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
    "/admin/sla/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["admin.sla.preview"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/assist/summary": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["assist.summary"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/assist/reply": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["assist.reply"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/assist/category": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["assist.category"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/assist/articles": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["assist.articles"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/{ticket}/escalate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["tickets.escalate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
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
    "/ticket-categories": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get: operations["ticket-categories.index"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/assignees": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Who can be handed a ticket */
        get: operations["assignees.index"];
        put?: never;
        post?: never;
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
        /**
         * The ticket list
         * @description Read-only, so no Idempotency-Key. Scoped by TicketVisibility before any
         *     caller-supplied filter is applied — an agent who puts a colleague's id in
         *     the URL gets their own tickets back rather than a refusal, because the
         *     filter narrows what they may see and never widens it.
         */
        get: operations["tickets.index"];
        put?: never;
        post: operations["tickets.store"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/tickets/counts": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * The five numbers on the agent's home screen
         * @description One aggregate query. Five round trips would be five times the load for a
         *     strip of numbers, taken at five slightly different moments — so they
         *     could disagree with each other and with the list they link to.
         */
        get: operations["tickets.counts"];
        put?: never;
        post?: never;
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
    "/inbound/web-form/attachments": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["channels.web_form.attachments"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/inbound/web-form/session": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * A token anonymous uploads hang off, issued when the page is drawn
         * @description The form needs somewhere to put a file before the ticket that will own it
         *     exists. Without a token the alternative is an upload endpoint that
         *     accepts anything from anyone, which is a public file store.
         *
         *     The categories come back with it, because the form needs them and the
         *     authenticated `/ticket-categories` endpoint is gated on `ticket.read` —
         *     which a stranger does not have. One public call rather than a second
         *     public endpoint duplicating the same list.
         */
        get: operations["channels.web_form.session"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/inbound/web-form": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post: operations["channels.web_form.store"];
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
        /**
         * RegisterPortalAccountRequest
         * @description What a customer has to give to open an account.
         *
         *     Four fields, and no more. Every extra one is a reason somebody abandons a
         *     form they only opened because something had already gone wrong.
         */
        RegisterPortalAccountRequest: {
            name: string;
            /** Format: email */
            email: string;
            password: string;
            /**
             * @description `nullable` as well as `sometimes`: an explicit null means "I have
             *     no preference", which is a legitimate thing for a client to say
             *     and different from sending nothing. Both fall back to English.
             * @enum {string|null}
             */
            preferred_locale?: "en" | "ar" | null;
            password_confirmation: string;
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
        /**
         * StoreArticleRequest
         * @description The attributes of a new article. Not its words, and not its state.
         *
         *     `status`, `has_been_published` and the lifecycle stamps are absent by
         *     construction: they belong to `ArticleLifecycle`, and a request that could
         *     name them could publish an article without going through the rule that makes
         *     publishing permanent.
         */
        StoreArticleRequest: {
            /** @enum {string} */
            type: "faq" | "help" | "solution" | "guide";
            category_id: number;
            /** @enum {string} */
            default_locale: "en" | "ar";
            internal_only?: boolean;
        };
        /** StoreAttachmentRequest */
        StoreAttachmentRequest: {
            /** @enum {string} */
            owner_type: "customer" | "ticket" | "message" | "web_form_draft" | "article";
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
            preferred_channel?: "email" | "phone" | "whatsapp" | "chat" | null;
            /**
             * @description At least one way to reach them. A customer record with no contact
             *     details cannot be replied to, which makes it a name in a list
             *     rather than a customer.
             */
            identifiers: {
                /** @enum {string} */
                kind: "email" | "phone" | "whatsapp" | "chat";
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
        /** StoreTicketRequest */
        StoreTicketRequest: {
            subject: string;
            description: string;
            customer_id: string;
            /** @enum {string} */
            channel: "agent" | "portal" | "email" | "web_form" | "whatsapp" | "sms" | "chat" | "system";
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
         * SubmitWebFormRequest
         * @description The six fields, and nothing else that reaches the pipeline.
         *
         *     The set is fixed by the story and is not configurable: name, contact,
         *     subject, category, message, attachment. Two more arrive with the request and
         *     neither is a field a person fills in — `hp_company` is the honeypot and
         *     `rendered_at` is when the page was drawn — so both are validated here and
         *     dropped before anything downstream sees them.
         */
        SubmitWebFormRequest: {
            name: string;
            contact: string;
            subject: string;
            category_id: number;
            message: string;
            attachment_ids?: string[];
            session_token?: string | null;
            /**
             * @description Must arrive; its CONTENTS are not validated here. A 422 saying "this field must be empty" tells whoever wrote the
             *     robot exactly which field to stop filling in. The controller
             *     answers a filled honeypot with 202 and a normal-looking body
             *     instead, and that only works if validation lets it through.
             */
            hp_company: string | null;
            /** Format: date-time */
            rendered_at: string;
        };
        /**
         * UpdateArticleRequest
         * @description Editing an article's attributes.
         *
         *     Refuses the lifecycle fields OUT LOUD rather than ignoring them. A request
         *     that sends `status: published` and gets a 200 back has been told its edit
         *     worked; discovering later that the article is still a draft is worse than
         *     being refused, and it is the kind of thing a client keeps doing.
         */
        UpdateArticleRequest: {
            /** @enum {string} */
            type?: "faq" | "help" | "solution" | "guide";
            category_id?: number;
            /** @enum {string} */
            default_locale?: "en" | "ar";
            internal_only?: boolean;
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
            preferred_channel?: "email" | "phone" | "whatsapp" | "chat" | null;
            identifiers?: {
                /** @enum {string} */
                kind?: "email" | "phone" | "whatsapp" | "chat";
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
         * UpsertArticleTranslationRequest
         * @description One article in one language.
         *
         *     The body is validated as a string and nothing more. Trying to validate the
         *     SHAPE of HTML here would be a second, weaker copy of the sanitiser — and the
         *     two would disagree, which means either a body the validator accepts and the
         *     sanitiser empties, or one the validator rejects that was perfectly safe.
         *     `HtmlSanitiser` is the single authority, and it runs on write.
         */
        UpsertArticleTranslationRequest: {
            title: string;
            body: string;
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
    "api-clients.index": {
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
                            abilities: null | Record<string, never> | string[];
                            owner: string | null;
                            /**
                             * @description When it was last used, which is the only question anybody
                             *     asks of this list: a client nothing has called for six months
                             *     is a credential somebody should revoke.
                             */
                            last_used_at: string | null;
                            created_at: string;
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
    "api-clients.store": {
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
                    name: string;
                    owner_id: number;
                    abilities: string[];
                };
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        data: {
                            id: number;
                            name: string;
                            abilities: unknown[];
                            /**
                             * @description The ONLY time this value exists outside the client's own
                             *     configuration. Copy it now; it cannot be recovered.
                             */
                            token: string;
                        };
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
    "api-clients.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                client: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description No content */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
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
    "admin.knowledge.categories.index": {
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
                            article_count: number;
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
    "admin.knowledge.categories.store": {
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
                         * @description No `parent` rule, because there is no parent. Flat by
                         *     construction — see the migration.
                         */
                        en: string;
                        ar: string;
                    };
                };
            };
        };
        responses: {
            201: {
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
                        article_count: number;
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
    "admin.knowledge.categories.destroy": {
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
    "admin.knowledge.categories.update": {
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
                         * @description No `parent` rule, because there is no parent. Flat by
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
                        article_count: number;
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
    "admin.knowledge.articles.publish": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
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
    "admin.knowledge.articles.archive": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
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
    "knowledge.search": {
        parameters: {
            query: {
                q: string;
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
    "admin.knowledge.articles.translations.upsert": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
                locale: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["UpsertArticleTranslationRequest"];
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
    "admin.knowledge.articles.translations.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
                locale: string;
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
                        type: string;
                        category_id: number;
                        internal_only: boolean;
                        status: string;
                        default_locale: string;
                        /**
                         * @description Which languages exist, so a list can show an availability chip
                         *     without fetching every body. An article that exists only in
                         *     Arabic is complete, not half-finished, and the chip is what says
                         *     so.
                         */
                        available_locales: unknown[];
                        /**
                         * @description The title, in whichever language the reader would be served. On the LIST too, not only on the record. An article list showing
                         *     identifiers is a list nobody can find anything in — and the
                         *     identifier is the one thing about an article that means nothing
                         *     to the person reading it.
                         */
                        title: string | null;
                        /**
                         * @description Never inferred by the caller from `status` and `internal_only`.
                         *     Both conditions, in one place — see `Article::isCustomerVisible`.
                         */
                        is_customer_visible: boolean;
                        /**
                         * @description The interface offers Delete or Archive based on this, and the
                         *     server refuses on the same rule. Two answers computed once.
                         */
                        can_delete: boolean;
                        has_been_published: boolean;
                        published_at: string | null;
                        published_by: string | null;
                        archived_at: string | null;
                        archived_by: string | null;
                        created_at: string | null;
                        updated_at: string | null;
                        ""?: string[] | {
                            served_locale: null;
                            title: string | null;
                            body: string | null;
                            translations: unknown[];
                        };
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
    "admin.knowledge.articles.index": {
        parameters: {
            query?: {
                type?: "faq" | "help" | "solution" | "guide";
                status?: "draft" | "published" | "archived";
                category_id?: number;
                internal_only?: boolean;
                q?: string;
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
                            [key: string]: unknown;
                        }[];
                        meta: {
                            [key: string]: number;
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
    "admin.knowledge.articles.store": {
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
                "application/json": components["schemas"]["StoreArticleRequest"];
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: string;
                        type: string;
                        category_id: number;
                        internal_only: boolean;
                        status: string;
                        default_locale: string;
                        /**
                         * @description Which languages exist, so a list can show an availability chip
                         *     without fetching every body. An article that exists only in
                         *     Arabic is complete, not half-finished, and the chip is what says
                         *     so.
                         */
                        available_locales: unknown[];
                        /**
                         * @description The title, in whichever language the reader would be served. On the LIST too, not only on the record. An article list showing
                         *     identifiers is a list nobody can find anything in — and the
                         *     identifier is the one thing about an article that means nothing
                         *     to the person reading it.
                         */
                        title: string | null;
                        /**
                         * @description Never inferred by the caller from `status` and `internal_only`.
                         *     Both conditions, in one place — see `Article::isCustomerVisible`.
                         */
                        is_customer_visible: boolean;
                        /**
                         * @description The interface offers Delete or Archive based on this, and the
                         *     server refuses on the same rule. Two answers computed once.
                         */
                        can_delete: boolean;
                        has_been_published: boolean;
                        published_at: string | null;
                        published_by: string | null;
                        archived_at: string | null;
                        archived_by: string | null;
                        created_at: string | null;
                        updated_at: string | null;
                        ""?: string[] | {
                            served_locale: null;
                            title: string | null;
                            body: string | null;
                            translations: unknown[];
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
    "admin.knowledge.articles.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description The article ID */
                article: string;
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
    "admin.knowledge.articles.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
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
    "admin.knowledge.articles.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The article ID */
                article: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["UpdateArticleRequest"];
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
                        type: string;
                        category_id: number;
                        internal_only: boolean;
                        status: string;
                        default_locale: string;
                        /**
                         * @description Which languages exist, so a list can show an availability chip
                         *     without fetching every body. An article that exists only in
                         *     Arabic is complete, not half-finished, and the chip is what says
                         *     so.
                         */
                        available_locales: unknown[];
                        /**
                         * @description The title, in whichever language the reader would be served. On the LIST too, not only on the record. An article list showing
                         *     identifiers is a list nobody can find anything in — and the
                         *     identifier is the one thing about an article that means nothing
                         *     to the person reading it.
                         */
                        title: string | null;
                        /**
                         * @description Never inferred by the caller from `status` and `internal_only`.
                         *     Both conditions, in one place — see `Article::isCustomerVisible`.
                         */
                        is_customer_visible: boolean;
                        /**
                         * @description The interface offers Delete or Archive based on this, and the
                         *     server refuses on the same rule. Two answers computed once.
                         */
                        can_delete: boolean;
                        has_been_published: boolean;
                        published_at: string | null;
                        published_by: string | null;
                        archived_at: string | null;
                        archived_by: string | null;
                        created_at: string | null;
                        updated_at: string | null;
                        ""?: string[] | {
                            served_locale: null;
                            title: string | null;
                            body: string | null;
                            translations: unknown[];
                        };
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
    "admin.assignment-mappings.index": {
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
                        precedence: string[];
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
    "admin.assignment-mappings.store": {
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
                    /** @enum {string} */
                    source_type: "category" | "department";
                    source_id: number;
                    /** @enum {string} */
                    target_type: "agent" | "department";
                    target_id: number;
                };
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        id: number;
                        source_type: string;
                        source_id: number;
                        target_type: string;
                        target_id: number;
                        /**
                         * @description Whether this row can actually fire, and why not. A mapping pointing at somebody who left is worse than no mapping
                         *     at all: it looks like the queue is being sorted while every
                         *     matching ticket quietly stays unassigned. Saying so on the row
                         *     is the difference between a rule and a mystery.
                         */
                        active: boolean;
                        /** @enum {string|null} */
                        inactive_reason: "That department no longer exists." | "That department has been deactivated." | "That account no longer exists." | "That account has been deactivated." | null;
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
    "admin.assignment-mappings.destroy": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The mapping ID */
                mapping: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /**
             * @description Existing tickets are untouched. A mapping decides where NEW work
             *     lands; rewriting history because a rule changed would move tickets
             *     out from under the people already working them.
             */
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
    "admin.assignment-mappings.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                /** @description The mapping ID */
                mapping: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @enum {string} */
                    target_type: "agent" | "department";
                    target_id: number;
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
                        source_type: string;
                        source_id: number;
                        target_type: string;
                        target_id: number;
                        /**
                         * @description Whether this row can actually fire, and why not. A mapping pointing at somebody who left is worse than no mapping
                         *     at all: it looks like the queue is being sorted while every
                         *     matching ticket quietly stays unassigned. Saying so on the row
                         *     is the difference between a rule and a mystery.
                         */
                        active: boolean;
                        /** @enum {string|null} */
                        inactive_reason: "That department no longer exists." | "That department has been deactivated." | "That account no longer exists." | "That account has been deactivated." | null;
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
                action?: "auth.sign_in.succeeded" | "auth.sign_in.failed" | "user.created" | "user.updated" | "user.deactivated" | "user.reactivated" | "department.created" | "department.updated" | "department.deleted" | "branch.created" | "branch.updated" | "api_client.issued" | "api_client.revoked" | "config.changed" | "article.published" | "article.archived" | "article.deleted" | "ticket.field_changed" | "customer.field_changed";
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
    "branches.index": {
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
    "branches.store": {
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
                    name: string;
                    code: string;
                };
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        data: {
                            id: number;
                            name: string;
                            code: string;
                            is_active: boolean;
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
    "branches.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                branch: number;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    name?: string;
                    is_active?: boolean;
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
                            id: number;
                            name: string;
                            code: string;
                            is_active: boolean;
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
    "branding.show": {
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
                            logo_url: string | null;
                            primary_colour: string | null;
                            header: string | null;
                        };
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
    "admin.channels.index": {
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
    "admin.channels.update": {
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
                "application/json": {
                    is_active?: boolean;
                    department_id?: number | null;
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
    "chat.desk.index": {
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
                            poll_seconds: number;
                            waiting: unknown[];
                            mine: unknown[];
                        };
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
    "chat.desk.take": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                conversation: string;
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
                        data: unknown;
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
    "chat.desk.close": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                conversation: string;
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
                        data: unknown;
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
    "chat.origins": {
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
                            origins: unknown[];
                        };
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
    "chat.open": {
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
                    visitor_name?: string | null;
                    visitor_identifier?: string | null;
                };
            };
        };
        responses: {
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": Record<string, never>;
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
    "chat.current": {
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
                            poll_seconds: number;
                            expires_at: string;
                            /** @enum {string} */
                            state: "abandoned" | "ended" | "taken" | "waiting";
                        };
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
    "chat.messages": {
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
                            /** @enum {string} */
                            state: "abandoned" | "ended" | "taken" | "waiting";
                            poll_seconds: number;
                            taken: boolean;
                            messages: {
                                [key: string]: {
                                    id: string;
                                    /** @enum {string} */
                                    from: "visitor" | "assistant" | "agent";
                                    body: string;
                                    /**
                                     * @description No name for the machine: it never presents itself as a
                                     *     person, and a name is the first thing that would.
                                     */
                                    author_name: string | null;
                                    sent_at: string;
                                };
                            };
                        };
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
    "chat.send": {
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
                        data: {
                            /** @enum {string} */
                            state: "abandoned" | "ended" | "taken" | "waiting";
                            poll_seconds: number;
                            taken: boolean;
                            messages: {
                                [key: string]: {
                                    id: string;
                                    /** @enum {string} */
                                    from: "visitor" | "assistant" | "agent";
                                    body: string;
                                    /**
                                     * @description No name for the machine: it never presents itself as a
                                     *     person, and a name is the first thing that would.
                                     */
                                    author_name: string | null;
                                    sent_at: string;
                                };
                            };
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
    "chat.close": {
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
                        data: {
                            /** @enum {string} */
                            state: "abandoned" | "ended" | "taken" | "waiting";
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
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        status: string;
                        provider: string;
                        sent_to: string;
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
    "tickets.feedback.invitation": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                ticket: string;
                verdict: string;
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
    "help.articles.index": {
        parameters: {
            query?: {
                q?: string | null;
                category_id?: number;
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
                        categories: {
                            [key: string]: unknown;
                        }[];
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
    "help.articles.show": {
        parameters: {
            query?: never;
            header?: never;
            path: {
                article: string;
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
    "inbound.email": {
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
    "integrations.log": {
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
                            direction: string;
                            integration: string;
                            target: string;
                            status: string;
                            attempt: number;
                            response_status: number | null;
                            duration_ms: number | null;
                            error: string | null;
                            occurred_at: string;
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
    "integrations.test": {
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
                        data: {
                            succeeded: boolean;
                            /**
                             * @description Where it went, so a typo in the endpoint is visible in the
                             *     answer rather than inferred from a timeout.
                             */
                            endpoint: string;
                            status: number | null;
                            duration_ms: number;
                            error: string | null;
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
    "admin.email.log": {
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
                        meta: {
                            [key: string]: number;
                        };
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
    "admin.email.quarantine.index": {
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
                        meta: {
                            [key: string]: number;
                        };
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
    "admin.email.quarantine.show": {
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
    "admin.email.quarantine.replay": {
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
    "notifications.index": {
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
                        unread_count: number;
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
    "notifications.read": {
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
                        status: string;
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
    "me.tasks.index": {
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
                        data: unknown[];
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
    "me.tasks.store": {
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
                    title: string;
                    ticket_id?: string | null;
                    /** Format: date-time */
                    due_at?: string | null;
                };
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        data: unknown;
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
    "me.tasks.update": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                task: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /**
                     * @description The ONLY editable field. A task has no description, no assignee
                     *     and no sub-tasks to edit, and its title is what it is.
                     */
                    completed: boolean;
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
                        data: unknown;
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
    "me.reminders.store": {
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
                    /** Format: date-time */
                    remind_at: string;
                    ticket_id?: string | null;
                    task_id?: string | null;
                };
            };
        };
        responses: {
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        data: {
                            id: string;
                            remind_at: string;
                            ticket_id: string | null;
                            task_id: string | null;
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
    "me.mentions.index": {
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
                            message_id: string;
                            ticket_id: string;
                            reference: string;
                            subject: string;
                            author_name: string;
                            /**
                             * @description A quoted extract, not the whole note. Home is a queue, and a
                             *     long internal note pasted into it pushes every other row off
                             *     the screen — the row is an invitation to open the ticket.
                             */
                            excerpt: string;
                            read_at: string | null;
                            mentioned_at: string;
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
    "me.mentions.read": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                mention: string;
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
                            read_at: string | null;
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
    "inbound.phone": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                channel: string;
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
                        status: string;
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
    "inbound.phone.receipts": {
        parameters: {
            query?: never;
            header: {
                /** @description A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for 24 hours. */
                "Idempotency-Key": string;
            };
            path: {
                channel: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    provider_message_id: string;
                    state: string;
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
                        status: string;
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
    "portal.auth.register": {
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
                "application/json": components["schemas"]["RegisterPortalAccountRequest"];
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
    "portal.auth.login": {
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
                    /** Format: email */
                    email: string;
                    password: string;
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
    "portal.auth.logout": {
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
    "portal.auth.me": {
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
    "portal.auth.password.forgot": {
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
                    /** Format: email */
                    email: string;
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
                        status: string;
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
    "portal.auth.password.reset": {
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
                    token: string;
                    /** Format: email */
                    email: string;
                    password: string;
                    password_confirmation: string;
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
                        status: string;
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
    "portal.requests.index": {
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
    "portal.requests.store": {
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
                    subject: string;
                    description: string;
                    /**
                     * @description Optional. A customer who does not know which category their
                     *     problem belongs to should still be able to ask — sorting it is
                     *     the desk's job, and a required dropdown is where people give up.
                     */
                    category_id?: number | null;
                    attachment_ids?: string[];
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
    "portal.requests.rate": {
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
                    positive: boolean;
                    /** @description Optional, always, and never asked for before the rating. */
                    comment?: string | null;
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
    "portal.requests.show": {
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
    "portal.requests.reply": {
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
                    body: string;
                    attachment_ids?: string[];
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
    "portal.requests.reopen": {
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
                "application/json": {
                    subject: string;
                    description: string;
                    /**
                     * @description Optional. A customer who does not know which category their
                     *     problem belongs to should still be able to ask — sorting it is
                     *     the desk's job, and a required dropdown is where people give up.
                     */
                    category_id?: number | null;
                    attachment_ids?: string[];
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
    "reports.show": {
        parameters: {
            query: {
                from: string;
                to: string;
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
                            configured: boolean;
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
    "admin.sla.preview": {
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
                        minutes: number;
                        from: string;
                        due_at: string;
                        timezone: string;
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
    "assist.summary": {
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
                            summary: string | null;
                        };
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
    "assist.reply": {
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
                            suggestions: string[];
                        };
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
    "assist.category": {
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
                            proposal: {
                                category_id: number;
                                name: string;
                            } | null;
                        };
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
    "assist.articles": {
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
                            articles: {
                                [key: string]: unknown;
                            }[];
                        };
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
    "tickets.escalate": {
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
                "application/json": {
                    reason: string;
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
    "ticket-categories.index": {
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
    "assignees.index": {
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
    "tickets.index": {
        parameters: {
            query?: {
                "status[]"?: "open" | "pending" | "resolved" | "closed";
                "priority[]"?: "low" | "normal" | "high" | "urgent";
                "category_id[]"?: number[];
                /** @description Either a user id or the "nobody has picked this up" sentinel. */
                "assignee_id[]"?: string[];
                "department_id[]"?: number[];
                /**
                 * @description Named states only. This is answered by computing the reading for
                 *     the live queue, so an arbitrary string would be a walk over
                 *     every open ticket that could never match anything.
                 */
                sla_state?: "on_track" | "at_risk" | "breached" | "met" | "paused";
                /**
                 * @description A filter, never a status. Escalation is a property an open,
                 *     pending, resolved or closed ticket can carry — `?escalated=1`
                 *     narrows within the lifecycle rather than replacing it.
                 */
                escalated?: boolean;
                "branch_id[]"?: number[];
                satisfaction?: "positive" | "negative" | "rated";
                created_from?: string;
                created_to?: string;
                q?: string | null;
                sort?: "updated_at" | "created_at" | "priority" | "reference" | "status";
                direction?: "asc" | "desc";
                /**
                 * @description Capped rather than unbounded: a caller asking for ten thousand
                 *     rows gets a page, not an outage.
                 */
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
                            [key: string]: unknown;
                        }[];
                        meta: {
                            [key: string]: number;
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
    "tickets.counts": {
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
                        [key: string]: number | null;
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
    "channels.web_form.attachments": {
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
                "multipart/form-data": {
                    session_token: string;
                    /** Format: binary */
                    file: string;
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
    "channels.web_form.session": {
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
                        session_token: string;
                        expires_at: string;
                        categories: {
                            id: number;
                            name: string;
                        }[];
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
    "channels.web_form.store": {
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
                "application/json": components["schemas"]["SubmitWebFormRequest"];
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
                        reference?: string;
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
}
