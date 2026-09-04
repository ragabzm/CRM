import { cn } from "@/lib/utils";

export interface AvatarChipProps {
  /** The person's name. Null renders the unassigned state. */
  name: string | null;
  /** Shown beside the circle. Off inside a dense table cell. */
  showName?: boolean;
  unassignedLabel: string;
  className?: string;
}

/**
 * A person, as a circle of initials.
 *
 * The assignee column was a bare name, which made "nobody has this" — the one
 * value an agent is scanning for — look exactly like everybody else. The
 * unassigned state is a dashed empty circle: different in shape, not only in
 * wording, so it reads at a glance and in greyscale.
 *
 * Initials are taken from the FIRST and LAST word. A middle name is not what
 * anybody uses to recognise a colleague, and "OAN" is harder to read than
 * "ON".
 */
export function AvatarChip({
  name,
  showName = false,
  unassignedLabel,
  className,
}: AvatarChipProps) {
  const assigned = name !== null && name.trim() !== "";
  const label = assigned ? name : unassignedLabel;

  return (
    <span
      data-slot="avatar-chip"
      data-assigned={assigned}
      className={cn("inline-flex items-center gap-2 whitespace-nowrap", className)}
    >
      <span
        aria-hidden="true"
        className={cn(
          "grid size-[26px] shrink-0 place-items-center rounded-full text-[13px] font-semibold",
          assigned
            ? "bg-surface-sunken text-fg-muted"
            : // Dashed and empty: an absence, drawn as one.
              "border border-dashed border-border-strong text-fg-placeholder",
        )}
      >
        {assigned ? initialsOf(name) : "+"}
      </span>

      {/*
        The name is always in the accessibility tree even when the circle is
        the only thing on screen — initials alone tell a screen reader nothing,
        and "ON" is not a person.
      */}
      <span className={cn(!showName && "sr-only")} dir="auto">
        {label}
      </span>
    </span>
  );
}

function initialsOf(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);

  if (words.length === 0) return "?";

  const first = words[0] ?? "";
  const last = words.length > 1 ? (words[words.length - 1] ?? "") : "";

  return (first.charAt(0) + last.charAt(0)).toLocaleUpperCase();
}
