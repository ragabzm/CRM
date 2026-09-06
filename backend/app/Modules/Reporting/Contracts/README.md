# Reporting has no contracts, and should not grow any.

A contract exists so that a LOWER-tier module can be handed something a higher
one implements — `Tickets\Contracts\SlaReader` is the shape Tickets needs and
Sla provides, declared by the consumer so the dependency points downward.

Nothing depends on Reporting. It sits at the top of the tier list, reads
Tickets and Sla, and is read by nobody: no module asks it a question, and it
asks none of them for anything it could not get from a query. So there is no
inversion to express and nothing to declare.

If a contract ever appears here, it means something below has started depending
on the reports — and a figure being load-bearing for behaviour is exactly the
coupling that turns "a number a supervisor reads" into a number that cannot be
changed without breaking something.
