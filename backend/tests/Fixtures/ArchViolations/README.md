# Architecture violation fixtures

Deliberately broken code. Nothing here is autoloaded into the application or
analysed by the real `deptrac.yaml`; the architecture tests point a copy of the
production rules at this directory and assert that the rules **reject** it.

Without these, a misconfigured ruleset that silently passes everything would
look exactly like a healthy codebase.
