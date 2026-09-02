"use client";

import { useCallback, useRef } from "react";

export interface TimelineMemory {
  /** How far down the page the reader had scrolled. */
  scrollTop: number;
  /** The row they were on, so focus lands back where it left. */
  focusedEntryId: string | null;
  /** Every cursor already fetched, so the same slice can be rebuilt. */
  cursors: Array<string | null>;
}

/**
 * Remembers where the reader was before they opened a ticket.
 *
 * An agent scrolls three pages into a year of history, opens a ticket, comes
 * back — and without this lands at the top of page one with nothing focused.
 * They then have to find their place again, which is the moment people stop
 * using a timeline and start searching instead.
 *
 * `sessionStorage`, not `localStorage`: this is about one visit, and a position
 * restored a week later would be worse than none. Keyed per customer so two
 * open tabs do not fight.
 *
 * Every access is wrapped — a private window, or a browser set to block site
 * data, throws on read as well as write, and a timeline must still render.
 */
export function useTimelineNavMemory(customerId: string) {
  const key = `timeline:${customerId}`;
  // Read once per mount. Reading again mid-session would pick up what this
  // component itself just wrote.
  const restored = useRef<TimelineMemory | null | undefined>(undefined);

  const remember = useCallback(
    (memory: TimelineMemory) => {
      try {
        sessionStorage.setItem(key, JSON.stringify(memory));
      } catch {
        // Losing the position is a small cost; failing to navigate is not.
      }
    },
    [key],
  );

  const recall = useCallback((): TimelineMemory | null => {
    if (restored.current !== undefined) return restored.current;

    try {
      const raw = sessionStorage.getItem(key);

      if (raw === null) {
        restored.current = null;

        return null;
      }

      const parsed: unknown = JSON.parse(raw);

      // Validated rather than trusted: sessionStorage is the reader's own
      // machine, and a half-written or hand-edited value must not crash the
      // pane it is meant to restore.
      if (
        parsed === null ||
        typeof parsed !== "object" ||
        typeof (parsed as TimelineMemory).scrollTop !== "number" ||
        !Array.isArray((parsed as TimelineMemory).cursors)
      ) {
        restored.current = null;

        return null;
      }

      restored.current = parsed as TimelineMemory;

      return restored.current;
    } catch {
      restored.current = null;

      return null;
    }
  }, [key]);

  const forget = useCallback(() => {
    try {
      sessionStorage.removeItem(key);
    } catch {
      // Nothing to do; the entry expires with the session anyway.
    }
  }, [key]);

  return { remember, recall, forget };
}
