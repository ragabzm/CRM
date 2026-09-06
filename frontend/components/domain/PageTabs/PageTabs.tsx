"use client";

import type { ReactNode } from "react";

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export interface PageTab<T extends string> {
  value: T;
  label: string;
  content: ReactNode;
}

export interface PageTabsProps<T extends string> {
  /** Names the set for assistive technology, e.g. "What is on your desk". */
  label: string;
  tabs: Array<PageTab<T>>;
  value: T;
  onChange: (value: T) => void;
}

/**
 * Panels of one screen, switched in place.
 *
 * A domain component rather than the tabs primitive used directly, because a
 * screen assembling its own tab chrome is how two screens end up with tabs
 * that look and behave slightly differently.
 *
 * THE COUNT BELONGS IN THE LABEL. A badge in its own element is announced as a
 * bare number after the tab name — "Mentions, one" reads as a list of two
 * things. Callers pass "Mentions (1)" as one string, so what is seen and what
 * is heard are the same sentence.
 */
export function PageTabs<T extends string>({ label, tabs, value, onChange }: PageTabsProps<T>) {
  return (
    <Tabs value={value} onValueChange={(next) => onChange(next as T)} data-slot="page-tabs">
      <TabsList aria-label={label}>
        {tabs.map((tab) => (
          <TabsTrigger key={tab.value} value={tab.value}>
            {tab.label}
          </TabsTrigger>
        ))}
      </TabsList>

      {tabs.map((tab) => (
        /*
         * Every panel is mounted in the DOM but only the selected one is
         * shown, which is what the primitive does by default — and it is the
         * behaviour a screen reader's rotor and browser find-in-page both
         * expect from tabs.
         */
        <TabsContent key={tab.value} value={tab.value} className="flex flex-col gap-3">
          {tab.content}
        </TabsContent>
      ))}
    </Tabs>
  );
}
