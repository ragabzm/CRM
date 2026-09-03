"use client";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export interface SegmentedOption<T extends string> {
  value: T;
  label: string;
}

export interface SegmentedFilterProps<T extends string> {
  /** Names the group for assistive technology, e.g. "State". */
  label: string;
  options: SegmentedOption<T>[];
  value: T;
  onChange: (value: T) => void;
  className?: string;
}

/**
 * A small set of mutually exclusive filters, all visible at once.
 *
 * Buttons in a labelled group rather than a select, because the whole set is
 * worth seeing: "Active / Inactive / All" tells the reader that deactivated
 * records exist and can be asked for, which a collapsed dropdown does not.
 *
 * `aria-pressed` on each, so which one is on is announced rather than only
 * coloured — the state has to survive both greyscale and a screen reader.
 */
export function SegmentedFilter<T extends string>({
  label,
  options,
  value,
  onChange,
  className,
}: SegmentedFilterProps<T>) {
  return (
    <div role="group" aria-label={label} className={cn("flex gap-1", className)}>
      {options.map((option) => (
        <Button
          key={option.value}
          /*
           * `type="button"`, explicitly.
           *
           * A <button> with no type is a SUBMIT button. Every one of these
           * inside a form would submit it — so choosing a language on the
           * registration form sent the form, twice. The default is a browser
           * behaviour nobody expects and nothing else here would have caught.
           */
          type="button"
          variant={option.value === value ? "primary" : "secondary"}
          aria-pressed={option.value === value}
          onClick={() => onChange(option.value)}
        >
          {option.label}
        </Button>
      ))}
    </div>
  );
}
