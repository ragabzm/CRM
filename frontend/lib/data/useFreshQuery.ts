"use client";

import { useCallback, useEffect, useRef, useState } from "react";

export interface FreshQueryState<T> {
  /** The last result that actually arrived. Never cleared by a failure. */
  data: T | null;
  /** True only until the FIRST result arrives; a refetch does not set it. */
  loading: boolean;
  /** Set when the most recent attempt failed, while `data` stays put. */
  stale: boolean;
  status: number | null;
  refetch: () => void;
}

export interface FreshQueryOptions {
  /** Milliseconds between background refetches. Omit to fetch once. */
  refetchInterval?: number;
  /** Refetch when the tab regains focus. Defaults to true. */
  refetchOnWindowFocus?: boolean;
}

/**
 * A query that keeps itself current, and keeps its last good answer.
 *
 * Deliberately small, and deliberately NOT React Query or SWR. Neither is in
 * this project, and adding one to get an interval would put a second data layer
 * beside the plain `request()` every other screen uses — two ways to fetch, two
 * caching stories, and a bundle cost for a timer.
 *
 * The behaviour that matters:
 *
 * **A failed refetch never clears the table.** An agent working a queue over a
 * flaky connection would otherwise watch their rows vanish every thirty
 * seconds. The last good data stays on screen, `stale` goes true so the screen
 * can say so quietly, and the next interval tries again.
 *
 * **A result that arrives after the inputs changed is dropped.** Changing a
 * filter starts a new request; if the old one lands second it would repaint the
 * table with rows the agent is no longer asking for.
 */
export function useFreshQuery<T>(
  key: string,
  fetcher: () => Promise<T>,
  options: FreshQueryOptions = {},
): FreshQueryState<T> {
  const { refetchInterval, refetchOnWindowFocus = true } = options;

  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [stale, setStale] = useState(false);
  const [status, setStatus] = useState<number | null>(null);

  /*
   * The key of the request currently in flight. A response whose key no longer
   * matches is discarded — this is what stops a slow answer to an old filter
   * from repainting the table after the agent has moved on.
   */
  const inFlight = useRef(key);
  const latest = useRef(fetcher);

  // In an effect, not during render: a ref written while rendering is a write
  // React is entitled to discard or replay.
  useEffect(() => {
    latest.current = fetcher;
  });

  const run = useCallback(() => {
    inFlight.current = key;

    latest
      .current()
      .then((result) => {
        if (inFlight.current !== key) return;

        setData(result);
        setStale(false);
        setStatus(null);
      })
      .catch((caught: unknown) => {
        if (inFlight.current !== key) return;

        // `data` is untouched on purpose.
        setStale(true);
        setStatus(
          typeof caught === "object" && caught !== null && "status" in caught
            ? Number((caught as { status: unknown }).status)
            : null,
        );
      })
      .finally(() => {
        if (inFlight.current === key) setLoading(false);
      });
  }, [key]);

  useEffect(() => {
    // Deferred: setting state straight from an effect body cascades renders.
    void Promise.resolve().then(() => {
      setLoading((current) => (data === null ? true : current));
      run();
    });
    // `data` is deliberately not a dependency; including it would refetch on
    // every result.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [run]);

  useEffect(() => {
    if (refetchInterval === undefined) return;

    const timer = setInterval(run, refetchInterval);

    return () => clearInterval(timer);
  }, [run, refetchInterval]);

  useEffect(() => {
    if (!refetchOnWindowFocus) return;

    /*
     * Coming back to the tab is the moment an agent most needs current data —
     * they have been elsewhere, and the queue has moved. Cheaper and more
     * useful than shortening the interval for everyone.
     */
    window.addEventListener("focus", run);

    return () => window.removeEventListener("focus", run);
  }, [run, refetchOnWindowFocus]);

  return { data, loading, stale, status, refetch: run };
}
