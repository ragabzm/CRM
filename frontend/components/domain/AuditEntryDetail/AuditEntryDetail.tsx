"use client";

import { useTranslations } from "next-intl";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { AuditEntryDetail as Entry } from "@/lib/api/admin";

export interface AuditEntryDetailProps {
  entry: Entry | null;
  onOpenChange: (open: boolean) => void;
}

function Payload({ title, value, empty }: { title: string; value: unknown; empty: string }) {
  return (
    <section className="flex min-w-0 flex-col gap-2">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-fg-muted">{title}</h3>
      {value === null || value === undefined ? (
        <p className="text-sm text-fg-subtle">{empty}</p>
      ) : (
        // Scrolls inside its own box: an entry with a large payload must not
        // make the page itself scroll sideways.
        <pre className="num max-h-80 overflow-auto rounded-md border border-border-default bg-surface-sunken p-3 text-xs text-fg-default">
          <code dir="ltr">{JSON.stringify(value, null, 2)}</code>
        </pre>
      )}
    </section>
  );
}

/**
 * One entry, read-only, with its before and after side by side.
 *
 * There is no edit control and no delete control anywhere in this dialog — not
 * disabled ones, none at all. A greyed-out Save would invite the question of
 * who is allowed to press it, and the answer is nobody.
 */
export function AuditEntryDetail({ entry, onOpenChange }: AuditEntryDetailProps) {
  const t = useTranslations("audit.detail");
  const tColumns = useTranslations("audit.columns");

  return (
    <Dialog open={entry !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
          <DialogDescription>{t("redacted")}</DialogDescription>
        </DialogHeader>

        {entry && (
          <div className="flex flex-col gap-4">
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
              <dt className="text-fg-muted">{tColumns("actor")}</dt>
              <dd className="text-fg-default">{entry.actor.label}</dd>

              <dt className="text-fg-muted">{t("actorType")}</dt>
              <dd className="text-fg-default">{entry.actor.type}</dd>

              <dt className="text-fg-muted">{t("entryId")}</dt>
              {/* Isolated: a ULID inside Arabic prose reverses without it. */}
              <dd>
                <BidiValue>{entry.id}</BidiValue>
              </dd>
            </dl>

            <div className="grid gap-4 tablet:grid-cols-2">
              <Payload title={t("before")} value={entry.before} empty={t("noPayload")} />
              <Payload title={t("after")} value={entry.after} empty={t("noPayload")} />
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
