"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import {
  createCategory,
  createQuickReply,
  deleteCategory,
  deleteQuickReply,
  listCategories,
  listPriorities,
  listQuickReplies,
  reorderQuickReplies,
  updateCategory,
  updateQuickReply,
  type Category,
  type Priority,
  type QuickReply,
} from "@/lib/api/admin";
import { ApiError } from "@/lib/api/request";
import type { Problem } from "@/lib/api/client";

import { CategoryEditor } from "@/components/domain/CategoryEditor/CategoryEditor";
import { CategoryList } from "@/components/domain/CategoryList/CategoryList";
import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import { QuickReplyEditor } from "@/components/domain/QuickReplyEditor/QuickReplyEditor";
import { QuickReplyList } from "@/components/domain/QuickReplyList/QuickReplyList";
import { RuleBlockedRefusal } from "@/components/domain/RuleBlockedRefusal/RuleBlockedRefusal";

import { Panel } from "./Panel";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

export function TicketingSection() {
  const t = useTranslations("admin.ticketing");
  const tConfirm = useTranslations("admin.confirm");

  const { settings, save } = useSettings();

  const [categories, setCategories] = useState<Category[]>([]);
  const [replies, setReplies] = useState<QuickReply[]>([]);
  const [priorities, setPriorities] = useState<Priority[]>([]);

  /** `null` = closed, "new" = creating, a Category = renaming that one. */
  const [editingCategory, setEditingCategory] = useState<Category | "new" | null>(null);
  const [editingReply, setEditingReply] = useState<QuickReply | null>(null);
  const [composingReply, setComposingReply] = useState(false);

  const [pendingCategory, setPendingCategory] = useState<Category | null>(null);
  const [pendingReply, setPendingReply] = useState<QuickReply | null>(null);
  const [blocked, setBlocked] = useState<Problem | null>(null);

  useEffect(() => {
    void listCategories()
      .then(setCategories)
      .catch(() => undefined);
    void listQuickReplies()
      .then(setReplies)
      .catch(() => undefined);
    void listPriorities()
      .then((result) => setPriorities(result.data))
      .catch(() => undefined);
  }, []);

  async function confirmCategoryDelete() {
    if (!pendingCategory) return;

    try {
      await deleteCategory(pendingCategory.id);
      setCategories(await listCategories());
      setBlocked(null);
    } catch (caught) {
      // A 409 is a RULE, not a failure: it is rendered as a refusal with the
      // count and a route to the tickets holding it up.
      setBlocked(caught instanceof ApiError ? caught.problem : null);
    } finally {
      setPendingCategory(null);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("categories")} hint={t("categoriesHint")}>
        {blocked && <RuleBlockedRefusal problem={blocked} />}

        <CategoryList
          categories={categories}
          onAdd={() => setEditingCategory("new")}
          onRename={setEditingCategory}
          onDelete={setPendingCategory}
        />
      </Panel>

      <Panel title={t("closing")}>
        <SettingsGroup
          keys={["tickets.auto_close_hours", "tickets.reopen_window_hours"]}
          settings={settings}
          save={save}
        />
      </Panel>

      <Panel title={t("quickReplies")} hint={t("quickRepliesHint")}>
        {editingReply || composingReply ? (
          <QuickReplyEditor
            {...(editingReply ? { reply: editingReply } : {})}
            onCancel={() => {
              setEditingReply(null);
              setComposingReply(false);
            }}
            onSubmit={async (input) => {
              if (editingReply) {
                await updateQuickReply(editingReply.id, input);
              } else {
                await createQuickReply(input);
              }
              setReplies(await listQuickReplies());
              setEditingReply(null);
              setComposingReply(false);
            }}
          />
        ) : null}

        <QuickReplyList
          replies={replies}
          {...(editingReply || composingReply ? {} : { onAdd: () => setComposingReply(true) })}
          onReorder={async (order) => {
            // Optimistic: the move is the reader's own action and reverting it
            // on the next paint reads as a bug rather than as a refusal.
            const byId = new Map(replies.map((reply) => [reply.id, reply]));
            setReplies(order.map((id) => byId.get(id)!).filter(Boolean));
            setReplies(await reorderQuickReplies(order));
          }}
          onEdit={setEditingReply}
          onDelete={setPendingReply}
        />
      </Panel>

      <Panel title={t("priorities")}>
        {/* The exact copy the story specifies: the reader is told the set is
            fixed, not left to discover that nothing here is clickable. */}
        <p className="text-sm text-fg-muted">{t("prioritiesFixed")}</p>
        <ul className="flex flex-wrap gap-2">
          {priorities.map((priority) => (
            <li
              key={priority.value}
              className="rounded-full border border-border-default px-3 py-1 text-xs text-fg-default"
            >
              {priority.value}
            </li>
          ))}
        </ul>
      </Panel>

      <CategoryEditor
        open={editingCategory !== null}
        onOpenChange={(open) => !open && setEditingCategory(null)}
        {...(editingCategory && editingCategory !== "new" ? { category: editingCategory } : {})}
        onSubmit={async (name) => {
          if (editingCategory && editingCategory !== "new") {
            await updateCategory(editingCategory.id, name);
          } else {
            await createCategory(name);
          }
          setCategories(await listCategories());
        }}
      />

      <DestructiveConfirm
        open={pendingCategory !== null}
        onOpenChange={(open) => !open && setPendingCategory(null)}
        consequence={tConfirm("deleteCategory", { name: pendingCategory?.name.en ?? "" })}
        confirmLabel={tConfirm("confirmDelete")}
        onConfirm={() => void confirmCategoryDelete()}
      />

      <DestructiveConfirm
        open={pendingReply !== null}
        onOpenChange={(open) => !open && setPendingReply(null)}
        consequence={tConfirm("deleteQuickReply", { name: pendingReply?.label.en ?? "" })}
        confirmLabel={tConfirm("confirmDelete")}
        onConfirm={async () => {
          if (!pendingReply) return;
          setReplies(await deleteQuickReply(pendingReply.id));
          setPendingReply(null);
        }}
      />
    </div>
  );
}
