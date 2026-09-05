"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import type { Article } from "@/lib/api/knowledge";

export interface ArticleLifecycleControlsProps {
  article: Article;
  busy?: boolean;
  onPublish: () => void;
  onArchive: () => void;
  onDelete: () => void;
}

/**
 * One primary action, and the honest version of the second.
 *
 * Draft or Archived → Publish. Published → Archive. Never both at once: an
 * article is in one state, and offering the transition it is not in is how
 * somebody archives a draft.
 *
 * Delete is the interesting one. It is shown DISABLED with the reason beside
 * it rather than hidden, because a control that vanishes teaches nothing — the
 * author concludes the feature is missing, or that they lack permission.
 * "This has been published; archive it instead" answers the question they were
 * about to ask, and explains a rule they will meet again.
 *
 * A domain component rather than part of the screen, because the rule it
 * encodes — published means archive-only, forever — is the product's, not this
 * page's. The next surface that offers these actions must not get to decide
 * differently.
 */
export function ArticleLifecycleControls({
  article,
  busy = false,
  onPublish,
  onArchive,
  onDelete,
}: ArticleLifecycleControlsProps) {
  const t = useTranslations("admin.knowledge");
  const [confirmingDelete, setConfirmingDelete] = useState(false);

  const published = article.status === "published";

  return (
    <div className="flex flex-col gap-1" data-slot="article-lifecycle">
      <ActionBar
        actions={[
          {
            id: "delete",
            label: t("delete"),
            destructive: true,
            disabled: busy || !article.can_delete,
            onSelect: () => setConfirmingDelete(true),
          },
          published
            ? {
                id: "archive",
                label: t("archive"),
                primary: true,
                disabled: busy,
                onSelect: onArchive,
              }
            : {
                id: "publish",
                label: t("publish"),
                primary: true,
                disabled: busy,
                onSelect: onPublish,
              },
        ]}
      />

      {!article.can_delete && (
        /*
         * In text, not only in a tooltip. A `title` is invisible to touch and
         * is not reliably announced, which would leave exactly the readers who
         * most need the explanation without one.
         */
        <p className="text-xs text-fg-muted" data-slot="delete-blocked-reason">
          {t("deleteBlocked")}
        </p>
      )}

      <DestructiveConfirm
        open={confirmingDelete}
        onOpenChange={setConfirmingDelete}
        consequence={t("deleteConsequence")}
        confirmLabel={t("delete")}
        busy={busy}
        onConfirm={() => {
          setConfirmingDelete(false);
          onDelete();
        }}
      />
    </div>
  );
}
