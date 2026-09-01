# Layer D — layout shell

`AppShell`, `Sidebar`, `TopBar` — the frame every screen renders inside.

## Contract

- May import from Layer A and Layer B.
- May **not** import from `@/components/screens/**` — the shell hosts screens,
  it does not know them.
- Same token and direction rules as every other layer.

Scaffold only in Story 1.2; the shell is built out in Story 1.4 alongside the
locale selector, because its chrome is where the direction flip is most visible.

Established by Story 1.2 (work item 493).
