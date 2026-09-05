# Knowledge/Contracts

Empty, deliberately, and this file says why so the next person does not wonder.

Nothing outside this module reads articles yet. The two surfaces that will —
the article lookup an agent uses from inside a ticket, and the portal help
centre a customer searches — are Story 8.2, and each will need a contract
declared by ITS consumer, not here:

- The ticket composer lives in Tickets (T3), which is above Knowledge (T2), so
  it may depend on this module directly and the contract belongs on the Tickets
  side only if Tickets wants to stay swappable.
- The portal help centre lives in Portal (T4), likewise above.

Writing either contract now would mean writing an interface with no
implementation and no caller, which is a promise about a design nobody has had
to build against yet — and those are the interfaces that turn out to have the
wrong shape.

`Platform/Contracts` and `Sla/Contracts` are empty for the same reason.
