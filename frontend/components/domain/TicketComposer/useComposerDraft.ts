"use client";

import { useCallback, useRef } from "react";

export interface ComposerDraft {
  body: string;
  type: "reply" | "note";
  attachmentIds: string[];
}

const EMPTY: ComposerDraft = { body: "", type: "reply", attachmentIds: [] };

function keyFor(ticketId: string): string {
  return `ticket.draft.${ticketId}`;
}

/**
 * Keeps what the agent has typed but not sent.
 *
 * A half-written reply is work. A session that lapses, a tab closed by
 * accident, a colleague's change forcing a reload — none of those are the
 * agent's doing, and none should cost them the paragraph they were three
 * sentences into.
 *
 * `localStorage`, not `sessionStorage`: this survives the browser closing,
 * which is exactly the case worth surviving. It holds no credential — cookie
 * mode keeps the session out of reach of JavaScript entirely — so there is
 * nothing here to protect beyond the agent's own words.
 *
 * Every access is guarded. A private window, a browser set to block site data,
 * or a full quota all THROW rather than returning null, and a composer that
 * crashes because it could not save a draft has destroyed the very thing it was
 * trying to protect.
 */
export function useComposerDraft(ticketId: string) {
  const readOnce = useRef<ComposerDraft | null | undefined>(undefined);

  const recall = useCallback((): ComposerDraft | null => {
    /*
     * Read once per mount. Reading again mid-session would pick up what this
     * component itself just wrote, and the composer would fight the agent's
     * cursor.
     */
    if (readOnce.current !== undefined) return readOnce.current;

    readOnce.current = null;

    try {
      const raw = localStorage.getItem(keyFor(ticketId));

      if (raw === null) return null;

      const parsed: unknown = JSON.parse(raw);

      if (typeof parsed !== "object" || parsed === null) return null;

      const draft = parsed as Partial<ComposerDraft>;

      // Validated, not trusted. This is the agent's own machine, and a value
      // left by an older version of this component must not crash the pane it
      // is meant to restore.
      if (typeof draft.body !== "string") return null;

      readOnce.current = {
        body: draft.body,
        type: draft.type === "note" ? "note" : "reply",
        attachmentIds: Array.isArray(draft.attachmentIds)
          ? draft.attachmentIds.filter((id): id is string => typeof id === "string")
          : [],
      };
    } catch {
      readOnce.current = null;
    }

    return readOnce.current;
  }, [ticketId]);

  const remember = useCallback(
    (draft: ComposerDraft) => {
      try {
        // Nothing worth keeping: clear rather than store an empty draft, so a
        // stale one does not reappear after the agent has emptied the box.
        if (draft.body.trim() === "" && draft.attachmentIds.length === 0) {
          localStorage.removeItem(keyFor(ticketId));

          return;
        }

        localStorage.setItem(keyFor(ticketId), JSON.stringify(draft));
      } catch {
        // In-memory only from here. The composer still works; the draft simply
        // will not outlive the tab.
      }
    },
    [ticketId],
  );

  const forget = useCallback(() => {
    try {
      localStorage.removeItem(keyFor(ticketId));
    } catch {
      // Nothing to do, and nothing worth telling the agent about.
    }
  }, [ticketId]);

  return { recall, remember, forget, empty: EMPTY };
}
