"use client";

import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export interface DestructiveConfirmProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * What will actually happen, in full — "This will delete the category
   * 'Billing'…". REQUIRED, and required to be non-empty.
   *
   * A dialog that only asks "Are you sure?" moves the decision to the reader
   * without giving them anything new to decide with, so they learn to click
   * through it. Naming the consequence is the only thing that makes the pause
   * worth the interruption.
   */
  consequence: ReactNode;
  confirmLabel: string;
  onConfirm: () => void;
  busy?: boolean;
}

/** Returns true when the consequence text carries no information. */
function isEmptyConsequence(consequence: ReactNode): boolean {
  return (
    consequence === null ||
    consequence === undefined ||
    consequence === false ||
    (typeof consequence === "string" && consequence.trim() === "")
  );
}

export function DestructiveConfirm({
  open,
  onOpenChange,
  consequence,
  confirmLabel,
  onConfirm,
  busy = false,
}: DestructiveConfirmProps) {
  const t = useTranslations("admin.confirm");

  /*
   * Refusing to render is deliberate. Falling back to a generic "Are you sure?"
   * would let a caller ship the exact dialog this component exists to prevent,
   * and it would look correct in review.
   */
  if (isEmptyConsequence(consequence)) {
    if (process.env.NODE_ENV !== "production") {
      console.error(
        "DestructiveConfirm was given no consequence text. A confirmation that " +
          "does not say what will happen is worse than none, so nothing rendered.",
      );
    }

    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
          <DialogDescription>{consequence}</DialogDescription>
        </DialogHeader>

        <DialogFooter>
          {/* Cancel first in the DOM: the safe option is the one focus lands on. */}
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={busy}>
            {t("cancel")}
          </Button>
          <Button variant="destructive" onClick={onConfirm} disabled={busy}>
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
