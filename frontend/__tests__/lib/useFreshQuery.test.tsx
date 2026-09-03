import { renderHook, waitFor, act } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useFreshQuery } from "@/lib/data/useFreshQuery";

/**
 * A list that keeps itself current, and keeps its last good answer.
 *
 * The behaviour that matters is what happens when a refetch FAILS. An agent
 * working a queue over a flaky connection must not watch their rows vanish
 * every thirty seconds — so a failure leaves the data exactly where it was and
 * only flips a quiet flag.
 */

let answers: Array<() => Promise<unknown>> = [];
let call = 0;

const fetcher = () => {
  const next = answers[Math.min(call, answers.length - 1)]!;
  call++;

  return next() as Promise<{ n: number }>;
};

const ok = (n: number) => () => Promise.resolve({ n });
const fails = (status?: number) => () =>
  Promise.reject(
    status === undefined ? new Error("network") : Object.assign(new Error("no"), { status }),
  );

beforeEach(() => {
  answers = [ok(1)];
  call = 0;
  vi.useRealTimers();
});

afterEach(() => vi.useRealTimers());

describe("a query that keeps itself fresh", () => {
  it("delivers the first result", async () => {
    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));
    expect(result.current.loading).toBe(false);
  });

  it("keeps the last good data when a refetch fails", async () => {
    answers = [ok(1), fails()];

    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));

    act(() => result.current.refetch());

    await waitFor(() => expect(result.current.stale).toBe(true));

    // The load-bearing assertion of the whole hook.
    expect(result.current.data).toEqual({ n: 1 });
  });

  it("clears the stale flag once a later attempt succeeds", async () => {
    answers = [ok(1), fails(), ok(2)];

    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));

    act(() => result.current.refetch());
    await waitFor(() => expect(result.current.stale).toBe(true));

    act(() => result.current.refetch());
    await waitFor(() => expect(result.current.data).toEqual({ n: 2 }));

    expect(result.current.stale).toBe(false);
  });

  it("reports the status so a screen can tell refused from broken", async () => {
    answers = [fails(403)];

    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    // "No access" and "the network blipped" call for different screens.
    await waitFor(() => expect(result.current.status).toBe(403));
  });

  it("stops showing the first-load spinner once anything has arrived", async () => {
    answers = [ok(1), fails()];

    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    await waitFor(() => expect(result.current.loading).toBe(false));

    act(() => result.current.refetch());

    // A refetch is background work; flipping `loading` would blank the table
    // the hook exists to preserve.
    expect(result.current.loading).toBe(false);
  });

  it("refetches on an interval", async () => {
    answers = [ok(1), ok(2)];

    // A real short interval rather than fake timers: the hook defers its first
    // fetch by a microtask (setting state straight from an effect body cascades
    // renders), and fake timers do not advance those.
    const { result } = renderHook(() => useFreshQuery("k", fetcher, { refetchInterval: 150 }));

    // Long enough that the first result is observable before the interval
    // fires; a shorter one made the assertion race the timer it was testing.
    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));
    await waitFor(() => expect(result.current.data).toEqual({ n: 2 }), { timeout: 2000 });
  });

  it("refetches when the tab regains focus", async () => {
    answers = [ok(1), ok(2)];

    const { result } = renderHook(() => useFreshQuery("k", fetcher));

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));

    /*
     * Coming back to the tab is the moment an agent most needs current data —
     * they have been elsewhere and the queue has moved. Cheaper and more useful
     * than shortening the interval for everyone.
     */
    act(() => {
      window.dispatchEvent(new Event("focus"));
    });

    await waitFor(() => expect(result.current.data).toEqual({ n: 2 }));
  });

  it("does not listen for focus when asked not to", async () => {
    answers = [ok(1), ok(2)];

    const { result } = renderHook(() =>
      useFreshQuery("k", fetcher, { refetchOnWindowFocus: false }),
    );

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));

    act(() => {
      window.dispatchEvent(new Event("focus"));
    });

    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(result.current.data).toEqual({ n: 1 });
  });

  it("stops its interval when unmounted", async () => {
    answers = [ok(1)];

    const { result, unmount } = renderHook(() =>
      useFreshQuery("k", fetcher, { refetchInterval: 30 }),
    );

    await waitFor(() => expect(result.current.data).toEqual({ n: 1 }));

    unmount();
    const before = call;

    await new Promise((resolve) => setTimeout(resolve, 120));

    // A timer left running after a screen is gone is a request nobody reads.
    expect(call).toBe(before);
  });

  it("discards a result that arrives after the inputs changed", async () => {
    let releaseSlow: (value: { n: number }) => void = () => undefined;

    const slow = () => new Promise<{ n: number }>((resolve) => (releaseSlow = resolve));

    answers = [slow, ok(2)];

    const { result, rerender } = renderHook(({ key }) => useFreshQuery(key, fetcher), {
      initialProps: { key: "old" },
    });

    // The filter changes before the first answer lands.
    rerender({ key: "new" });

    await waitFor(() => expect(result.current.data).toEqual({ n: 2 }));

    act(() => releaseSlow({ n: 1 }));
    await new Promise((resolve) => setTimeout(resolve, 20));

    /*
     * The stale answer must not repaint the table. Otherwise an agent who
     * changed a filter watches the old rows come back a second later, and has
     * no way to tell which set is real.
     */
    expect(result.current.data).toEqual({ n: 2 });
  });
});
