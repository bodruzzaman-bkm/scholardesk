# notes/

Working notes on ScholarDesk: the architecture, the wiring, and a teaching
document that walks all 22 requirements down to the code that implements them.

Nothing here is generated at build time — the HTML pages are self-contained and
open straight in a browser, with no extension, dependency or build step.

## Start here

### 🎓 [`masterclass.html`](masterclass.html) — learn the project

The teaching document. Three parts:

1. **All 22 requirements**, each traced to the code that implements them —
   what it does, where it lives, how it works, a worked example, the wiring,
   and *why it was built that way*. Filterable; 88 file references.
2. **The RAG and AI layer taken apart** — the six classes, the prompt-budget
   arithmetic, why the citation filter is a regex, and the five failure modes.
3. **An interactive query explorer** — pick any of 14 representative queries and
   the page draws every layer it passes through, the gate that could stop it,
   the SQL it emits, and what it writes.

## The visual pages

**Open either in a browser.** 14 hand-drawn diagrams between them. No build
step, no extension, adapts to light and dark.

### 📐 [`visual-architecture.html`](visual-architecture.html) — the system's shape

| Fig | Shows |
|---|---|
| 1 | The whole system — browser, five subsystems, the database it owns, the two outside services it treats as optional |
| 2 | The request lifecycle — nine stages, four exits, three different gates |
| 3 | `ownedBy` vs `accessibleBy` drawn over the same eight papers |
| 4 | How a PDF becomes searchable, including every failure branch |
| 5 | How a question becomes a cited answer, with the three thresholds on one scale |
| 6 | The four tables with exactly one writer, and what each single writer buys |
| 7 | One route traced end to end, and the checks it changes |

### 🔌 [`visual-wiring.html`](visual-wiring.html) — routes, queries, writes

| Fig | Shows |
|---|---|
| 1 | **Four rings** — every URL placed by what gates it |
| 2 | **The filter funnel** — a query string narrowing 240 papers to 4, and the SQL it emits |
| 3 | **Two engines** — keyword vs semantic, and the one real difference |
| 4 | **Who writes what** — writers → tables, single-writer edges picked out |
| 5 | **Comment anchoring** — three valid states, and the one-level rule |
| 6 | **Upload trace** — swimlanes, response boundary as a clock |
| 7 | **Question trace** — swimlanes, access check in red |

## The text companions

| File | What it is |
|---|---|
| [`architecture.md`](architecture.md) | Prose: layers, boot, subsystems, access boundaries, the AI pipeline, cross-cutting concerns, deployment, the seams |
| [`wiring.md`](wiring.md) | Reference: all 84 routes in five columns, every scope's SQL and callers, the reverse index, and three traces written out step by step |
| [`conversation-log.md`](conversation-log.md) | The running Q&A journal |

## The log convention

After every conversation, a new numbered entry goes into
[`conversation-log.md`](conversation-log.md): the question verbatim, the answer,
the visuals produced and where they live, the docs touched, and any open
threads.

Entries are never rewritten. A later conversation that corrects an earlier one
gets its own entry and links back — the log is a history, not a summary.

## Keeping these honest

Every file path and line number in these documents was verified against the
source at the time of writing — 145 references, each resolving to a real file
with the line landing on the symbol it names.

If they drift as the code changes, regenerate rather than patch: they were built
from a full read of the routes, controllers, services, policies and migrations,
and that read is cheap to repeat.
