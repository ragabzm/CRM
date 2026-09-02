# Layer D — layout shell

`AppShell` is the frame every route renders inside. It is mounted once, in
`app/layout.tsx`, so a screen never has to remember to wrap itself in chrome.

## The direction contract

`dir` and `lang` are set on `<html>` in the root layout and **nowhere else**.
There is no second Arabic stylesheet, no per-component `dir` override and no
mirroring code in this folder — the sidebar is grid column 1 in both writing
modes, and the browser places it on the left in LTR and on the right in RTL.

That is why `AppShell.test.tsx` asserts the rendered markup is _byte-identical_
in both directions: if any component ever branches on direction, that test fails.

## Files

| File                   | What it does                                                                               |
| ---------------------- | ------------------------------------------------------------------------------------------ |
| `AppShell.tsx`         | Server Component. The grid: sidebar column + `TopBar` + `<main>`.                          |
| `Sidebar.tsx`          | The four destinations. Administration is **absent** without the `administrator` role.      |
| `TopBar.tsx`           | Brand, mobile nav sheet, language toggle, bell, user menu. Hosts the locale-failure toast. |
| `LanguageToggle.tsx`   | EN ⇄ AR. Persists server-side, then refreshes.                                             |
| `NotificationBell.tsx` | A plain `<ul>` with an empty state. No unread badge.                                       |
| `UserMenu.tsx`         | Profile and sign-out.                                                                      |

## Decisions worth knowing

- **The toggle names the language you switch _to_** (`العربية` while English is
  active). A control named after the current state reads as a status, not an
  action.
- **The language change is a server round trip**, not client state: `dir`/`lang`
  live on `<html>`, and only the server can change them coherently.
- **Administration is hidden, not disabled**, for non-administrators — unlike
  the DataTable's locked identity column, there is nothing here for the reader
  to learn, and advertising an unreachable destination invites the question.
- **No unread badge on the bell.** Story 1.3 has no real notification data, and
  a fabricated count is a number the product cannot honour.
- **Below `md` the sidebar is a sheet**, rendering the same `<Sidebar />`, so
  there is only one list of destinations to keep correct.

Established by Story 1.2 (work item 493); built out by Story 1.3 (work item 494).
