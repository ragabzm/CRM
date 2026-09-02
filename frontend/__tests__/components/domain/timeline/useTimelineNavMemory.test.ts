import { renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { useTimelineNavMemory } from "@/components/domain/CustomerTimeline/useTimelineNavMemory";

const CUSTOMER = "01AAAAAAAAAAAAAAAAAAAAAAAA";

const MEMORY = {
  scrollTop: 420,
  focusedEntryId: "01E7",
  cursors: [null, "Y3Vyc29yLTE="],
};

beforeEach(() => sessionStorage.clear());
afterEach(() => vi.restoreAllMocks());

const hook = (customerId = CUSTOMER) => renderHook(() => useTimelineNavMemory(customerId)).result;

describe("the timeline remembers where the reader was", () => {
  it("returns nothing on a first visit", () => {
    expect(hook().current.recall()).toBeNull();
  });

  it("restores what it stored", () => {
    hook().current.remember(MEMORY);

    // A fresh hook, as after navigating back.
    expect(hook().current.recall()).toEqual(MEMORY);
  });

  it("keeps each customer separate", () => {
    hook().current.remember(MEMORY);

    // Two open tabs on two customers must not fight over one key.
    expect(hook("01BBBBBBBBBBBBBBBBBBBBBBBB").current.recall()).toBeNull();
  });

  it("forgets on request", () => {
    const { current } = hook();
    current.remember(MEMORY);
    current.forget();

    expect(hook().current.recall()).toBeNull();
  });

  it("survives a half-written value", () => {
    sessionStorage.setItem(`timeline:${CUSTOMER}`, "{not json");

    // sessionStorage is the reader's own machine; a corrupt value must not
    // crash the pane it is meant to restore.
    expect(hook().current.recall()).toBeNull();
  });

  it("rejects a value of the wrong shape", () => {
    sessionStorage.setItem(`timeline:${CUSTOMER}`, JSON.stringify({ scrollTop: "lots" }));

    expect(hook().current.recall()).toBeNull();
  });

  it("survives storage being unavailable entirely", () => {
    // A private window, or a browser set to block site data, throws on read as
    // well as write.
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => {
      throw new Error("blocked");
    });
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new Error("blocked");
    });

    const { current } = hook();

    expect(() => current.remember(MEMORY)).not.toThrow();
    expect(current.recall()).toBeNull();
  });

  it("reads once per mount", () => {
    hook().current.remember(MEMORY);

    const { current } = hook();
    const spy = vi.spyOn(Storage.prototype, "getItem");

    current.recall();
    current.recall();

    /*
     * Reading again mid-session would pick up what this component itself just
     * wrote, so the position would drift every time the reader scrolled.
     */
    expect(spy).toHaveBeenCalledTimes(1);
  });

  it("uses session storage, not local", () => {
    hook().current.remember(MEMORY);

    // A position restored a week later is worse than none.
    expect(sessionStorage.getItem(`timeline:${CUSTOMER}`)).not.toBeNull();
    expect(localStorage.getItem(`timeline:${CUSTOMER}`)).toBeNull();
  });
});
