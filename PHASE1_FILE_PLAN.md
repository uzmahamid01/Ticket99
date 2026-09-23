# Phase 1 File Plan — Secure Request Gateway

**Work order:** WO-001 — Define Phase 1 File Guardrails
**Status:** Planning artifact. No application source is modified by this document.

## Purpose

This plan fixes the blast radius of Phase 1 **before** any code-touching story begins.
Phase 1 wraps a Secure Request Gateway around the existing request flow of the Ticket99
HelpDesk monolith. It preserves current routes, the MySQL schema, outbound mail behaviour,
the existing jQuery dependency, and every visible HelpDesk workflow.

A reviewer should be able to compare the final changed-file list of Phase 1 against the
inventory below and reject anything that does not appear here.

## How to use this plan

1. Before starting any Phase 1 story, confirm the files it touches appear in the inventory.
2. If a story needs a file that is not listed, update this plan first (see *Change control*).
3. At review time, diff the actual changed-file list against the inventory.
4. Anything matching the *Deferred scope* section is rejected, not negotiated.

## Phase 1 capabilities

Phase 1 delivers exactly six capabilities. Every in-scope file below is justified by at
least one of them.

| # | Capability | Problem it closes |
|---|---|---|
| 1 | Environment configuration | Deploy-time secrets are committed in PHP source |
| 2 | Session identity | Identity is a browser-controlled `user` cookie |
| 3 | Authorization | Role checks exist on pages but not on the mutation path |
| 4 | CSRF | No mutation carries or validates a token |
| 5 | Escaping | Domain classes echo user-generated values unescaped |
| 6 | AJAX dispatcher | One unguarded `switch` fans out to 22 actions |

## In-scope file inventory

Fourteen files are in scope. Nothing else is authorised for Phase 1 change.

| # | Path | Lines | Primary capability |
|---|---|---|---|
| 1 | `config.php` | 15 | Environment configuration |
| 2 | `init.php` | 41 | Session identity |
| 3 | `core/process.php` | 84 | AJAX dispatcher, CSRF, authorization |
| 4 | `core/users.php` | 371 | Session identity, authorization |
| 5 | `core/tickets.php` | 285 | Authorization, escaping |
| 6 | `core/admin.php` | 450 | Authorization, escaping |
| 7 | `js/ajax.js` | 310 | AJAX dispatcher, CSRF |
| 8 | `ticket/index.php` | 135 | Ticket rendering, escaping |
| 9 | `settings/index.php` | 106 | Settings rendering, escaping |
| 10 | `index1.php` | 105 | Session identity, escaping |
| 11 | `admin/index.php` | 98 | Admin rendering, authorization |
| 12 | `admin/site_management.php` | 121 | Admin rendering, authorization |
| 13 | `admin/user_management.php` | 197 | Admin rendering, authorization |
| 14 | `admin/ticket_management.php` | 37 | Admin rendering, authorization |

### 1. `config.php` — environment configuration

*Current state:* Fifteen lines holding `$host`, `$port`, `$username`, and `$password` as
literals (lines 3–6), plus `$page_title` and `$website_url`. The values are committed to
version control and reach production unchanged.

*Rationale:* This is the single environment configuration surface for the deployment. Phase 1
reads deploy-time values from the process environment so credentials leave source control,
while keeping the same variable names that `init.php` already consumes. Preserving those
names is what keeps the change contained to one file.

### 2. `init.php` — session identity

*Current state:* Bootstraps every request. Sets the timezone, opens an output buffer, enables
`display_errors`, promotes the config variables into `HOST`/`PORT`/`USER`/`PASSWORD`
constants (lines 20–23), requires the five `core/` classes, instantiates `$database`,
`$users`, `$time`, `$tickets`, `$admin`, then calls `signed_in()`, `account_exists()`, and
`is_locked()` (lines 37–40).

*Rationale:* Session identity belongs here: every entry point includes this file, so it is
the only correct place to establish it. Phase 1 starts a configured PHP session with HttpOnly,
SameSite, and HTTPS-aware Secure flags, and settles error display for a served environment.
The existing bootstrap order and object wiring stay as they are.

### 3. `core/process.php` — AJAX dispatcher, CSRF, authorization

*Current state:* The mutation path for the whole application. A flat
`switch($_POST['func'])` routes 22 actions. The only gate is `if($users->signed_in())` at
line 4; the `else` branch exposes `auth`. Administrator actions — `add_department`,
`delete_dpt`, `update_dpt`, `admin_update_nickname`, `admin_update_email`, `make_admin`,
`change_block`, `settings`, `close_ticket` — sit in the same branch as ordinary user actions,
so the dispatcher applies no role check of its own. Caller identity is read straight from
`$_COOKIE['user']` and handed to `reply()` and `make_admin()` (lines 28, 61). No action
carries a CSRF token, and no `$_POST` index is guarded by `isset()`.

*Rationale:* This is the AJAX dispatcher and the highest-value file in Phase 1. It gains CSRF
validation on every mutating action, an allowlisted action map in place of the open `switch`,
and per-action authorization so administrator actions require an administrator. The 22 action
names and their response bodies are unchanged, which is what keeps `js/ajax.js` working.

### 4. `core/users.php` — session identity, authorization

*Current state:* Thirteen methods covering account lifecycle. `auth()` (line 7) verifies the
password with `password_verify`, then issues identity as `setcookie('user', $res['id'])` plus
a second cookie holding the raw address. `signed_in()` (line 143) is the whole authentication
test and reads `isset($_COOKIE['user'])` — any value passes, so the cookie is both the
identity and the proof of it. Fifteen further reads of `$_COOKIE` appear across the class.
Queries are already parameterised via PDO, and the class echoes 23 response strings directly.

*Rationale:* Session identity and authorization are rooted here. Phase 1 moves the
authenticated subject into `$_SESSION` at the end of `auth()`, regenerates the session id on
privilege change, and replaces the cookie test in `signed_in()` with a server-side check. A
canonical current-user accessor lands here so callers stop reading the cookie directly. The
reusable authorization helpers that the dispatcher and admin pages call also belong in this
class, next to the existing `user_group` role data.

### 5. `core/tickets.php` — authorization, escaping

*Current state:* Thirteen methods over tickets and replies. Ownership decisions read
`$_COOKIE['user']` directly in thirteen places, including the administrator-read branch at
line 123. Fourteen statements are parameterised and one raw `query()` remains. Eighteen
`echo` sites render ticket subjects, messages, reply bodies, and author names, none through
an escaping helper.

*Rationale:* Escaping and authorization both land here. Ticket data is user-generated and
rendered back to other users, and the ownership decisions in this class are authorization
checks in all but name. Phase 1 switches
ownership checks to the session-backed accessor, routes rendered values through the shared
escaping helper, and converts the remaining raw query. Method signatures and returned markup
structure stay the same so callers and the rendered pages are unaffected.

### 6. `core/admin.php` — authorization, escaping

*Current state:* The largest domain class at 450 lines and 23 methods. Role checks are
present but uneven: `settings()`, `make_admin()`, and `close_ticket()` test
`getuserinfo('user_group') == 1` (lines 353, 371, 388, 441), while `create_department()`,
`delete_department()`, `update_department()`, `update_nickname()`, `update_email()`, and
`change_block()` carry no such test and rely on a caller that does not perform one either.
Twenty raw `query()` calls sit alongside 21 parameterised statements. Seventeen `echo` sites
render user-supplied nicknames, addresses, and department names.

*Rationale:* This class holds the privileged mutations, so consistent authorization is the
point of the work. Phase 1 applies the shared administrator check to every mutating method
rather than the current subset, and routes the rendered values through the escaping helper.
Behaviour for a genuine administrator is unchanged; only the unauthenticated and
under-privileged paths change.

### 7. `js/ajax.js` — AJAX dispatcher, CSRF

*Current state:* The browser half of the dispatcher contract. Thirty-eight jQuery request
calls post 23 distinct `func` values to `core/process.php`, and 18 of them write the response
into the DOM with `.html()`.

*Rationale:* CSRF validation only works if the client sends the token, so this file is the
matching half of the AJAX dispatcher change. Phase 1 attaches the token to each mutating
request, ideally through a single shared setup hook rather than 38 edits. The existing library
and version stay exactly as they are; only the request payload gains a field.

### 8. `ticket/index.php` — ticket rendering, escaping

*Current state:* The busiest rendered page. Seventeen `$_GET` reads — `$_GET['id']` is used
for the lookup at line 5 and then re-read throughout the template — plus seven `$_COOKIE`
reads and role branching at lines 7, 39, 54, 92, and 109. Sixteen `echo` sites emit ticket
content with no escaping.

*Rationale:* This is the ticket rendering surface where another user's ticket subject, message
body, and reply text reach the browser, so it carries the highest escaping value of the
rendered pages. Phase 1 escapes output through the shared helper and moves the role branches
onto the session-backed accessor. Layout, markup, and the `?id=` route are unchanged.

### 9. `settings/index.php` — settings rendering, escaping

*Current state:* Gated by `signed_in()` at line 4, reads `$_COOKIE` twice, and emits nine
values covering the signed-in account's own nickname, address, and avatar selection.

*Rationale:* This is the settings rendering surface, and it is the page backing the
`change_email`, `change_password`, `change_nickname`, and `delete_me` actions, so it must
carry the CSRF token those mutations will require. Phase 1 escapes the rendered account values
and reads the subject from the session instead of the cookie.

### 10. `index1.php` — session identity, escaping

*Current state:* The post-sign-in dashboard. Branches on `signed_in()` at line 4, reads
`$_COOKIE` twice, emits six values, and exposes the admin entry point by testing
`getuserinfo('user_group') == 1` at line 28.

*Rationale:* Session identity is the reason this file is listed. It is the first page a
signed-in user sees, so a regression in the identity change surfaces here first. Phase 1
moves its gate and its role test onto the session-backed accessor and escapes the rendered
values. It is listed for that identity coupling, not for any redesign.

### 11. `admin/index.php` — admin rendering, authorization

*Current state:* Gates the whole page on `getuserinfo('user_group') == 1` at line 4, includes
the shared `admin_menu.php`, and emits twelve summary counts.

*Rationale:* Admin rendering behind an inline role test. Phase 1 replaces that inline test
with the shared administrator helper so the page and the dispatcher enforce authorization
through one code path, and escapes the rendered counts and labels.

### 12. `admin/site_management.php` — admin rendering, authorization

*Current state:* Same inline role gate at line 4. Six `$_GET` reads drive department
management, and five `echo` sites render department names.

*Rationale:* Admin rendering for department and site settings, whose mutations
(`add_department`, `delete_dpt`, `update_dpt`, `settings`) are among those the dispatcher
currently leaves unguarded. Phase 1 applies the shared administrator helper, escapes rendered
department names, and carries the CSRF token for those mutations.

### 13. `admin/user_management.php` — admin rendering, authorization

*Current state:* Inline role gate at line 4, plus a second role test at line 128. Seventeen
`$_GET` reads select and act on the target user, and thirteen `echo` sites render user
records.

*Rationale:* Admin rendering for the most sensitive mutations in the product —
`make_admin`, `change_block`, `admin_update_email`, and `admin_update_nickname` — which is
why its authorization must come from the shared helper rather than two inline tests. Phase 1
also escapes rendered user-supplied nicknames and addresses and carries the CSRF token.

### 14. `admin/ticket_management.php` — admin rendering, authorization

*Current state:* A 37-line shell. Inline role gate at line 4, the shared menu include, and a
single redirect; ticket content is pulled in by the classes it calls.

*Rationale:* Admin rendering for ticket oversight. It is thin, but it is one of the four
concrete admin page paths and must adopt the shared administrator helper so no admin entry
point keeps its own inline role test. Expected change is small.

## Dependent and follow-on surfaces

These are named for awareness. They are **not** authorised for change by this plan; a story
that needs one must add it to the inventory above first.

| Path | State | Relevance |
|------|-------|-----------|
| `core/db.php` | Present, 117 lines | Builds the PDO handle from the config constants (line 11) and hardcodes the database name in `CREATE DATABASE IF NOT EXISTS jKRhAnyNm9` (line 15). Once `config.php` reads the environment, this name is the remaining committed deploy-time value. Schema definitions here stay untouched. |
| `.env.example` | Absent | Template documenting the environment configuration keys once `config.php` reads them. |
| `.gitignore` | Absent | Needed so a real env file cannot be committed. Its absence is why the current credentials are in the repository. |
| `health.php` | Absent | Smoke-check endpoint for verifying a deployment after cutover. |
| `core/includes/head.php`, `core/includes/foot.php` | Present | Shared layout for all rendered pages; the natural home for a shared escaping helper or a CSRF meta tag if one is needed. |
| `admin/admin_menu.php` | Present | Included by all four admin pages. Listed so a reviewer is not surprised to see it referenced, not to authorise editing it. |

## Deferred scope

Everything in this section is **out of scope for Phase 1** and must be rejected in review if
it appears in a Phase 1 diff. Items are recorded here so the boundary is explicit rather than
assumed.

| Deferred item | Why it is deferred |
|---|---|
| PHPMailer migration | Outbound mail behaviour is preserved in Phase 1. The vendored library stays at its current version and its call sites are not in the inventory. |
| jQuery upgrade | `js/ajax.js` gains a token field only. The library and its version are unchanged; an upgrade would touch every page template. |
| Schema redesign | The users, tickets, ticket_replies, departments, registrations, and admin_settings tables are preserved as-is. |
| Database migration | No data is moved, converted, or re-hosted during Phase 1. |
| Frontend redesign | Markup, CSS, and layout stay as they are. Escaping changes the encoding of rendered values, not their presentation. |
| AI | No model-backed features, assisted triage, or classification are part of this work. |
| Redis | Native PHP sessions meet the single-host Phase 1 target; shared session infrastructure is not introduced. |
| Kubernetes | The deployment topology is unchanged. |
| Runtime migration | No PHP version bump or host change is bundled with this hardening. |
| Repository pattern | Domain classes keep their current shape. Restructuring data access would conflict with a focused security diff. |
| CI/CD expansion | Build and deploy automation is not extended by Phase 1. |

A deferred item is not a rejected one. Each may be revisited in a later phase, on its own
work order, once the trust boundary is settled.

## Change control

Two rules govern this plan, corresponding to the edge cases on WO-001.

1. **A later story needing an unlisted file updates this plan first.** The new entry must
   name the file and give a rationale tied to one of the six Phase 1 capabilities. The plan
   change lands before the code change, not alongside it.
2. **A file that appears only because of a broad rewrite is removed or deferred.** If the
   justification for touching a file is a rewrite, a dependency upgrade, a runtime change, or
   a visual redesign rather than one of the six capabilities, it does not enter the inventory;
   it goes to *Deferred scope* with a reason.

## Verification

This story changes no runtime behaviour, so verification is document-level and runs before
any code-touching story starts.

```sh
PLAN=PHASE1_FILE_PLAN.md

# 1. Every in-scope path has an entry.
for f in config.php init.php core/process.php core/users.php core/tickets.php \
         core/admin.php js/ajax.js ticket/index.php settings/index.php index1.php \
         admin/index.php admin/site_management.php admin/user_management.php \
         admin/ticket_management.php; do
  grep -q -- "$f" "$PLAN" || echo "MISSING ENTRY: $f"
done

# 2. Every entry's rationale names a Phase 1 capability.
grep -c '^\*Rationale:\*' "$PLAN"   # expect 14
grep -Ei -- 'environment configuration|session identity|authorization|CSRF|escaping|AJAX dispatcher|health check|ticket rendering|settings rendering|admin rendering' "$PLAN" \
  | grep -c '^\*Rationale:\*'       # expect 14

# 3. Deferred terms appear only inside the Deferred scope section.
#    Terms are read from the table itself so this check cannot drift from the list.
start=$(grep -n '^## Deferred scope'  "$PLAN" | cut -d: -f1)
end=$(  grep -n '^## Change control'  "$PLAN" | cut -d: -f1)
sed -n "${start},${end}p" "$PLAN" \
  | sed -n 's/^| \([A-Za-z][^|]*[^ ]\) *| .*$/\1/p' | grep -v '^Deferred item$' \
  | while IFS= read -r t; do
      grep -nFiw -- "$t" "$PLAN" | cut -d: -f1 | while read -r n; do
        [ "$n" -gt "$start" ] && [ "$n" -lt "$end" ] \
          || echo "UNDEFERRED: '$t' at line $n"
      done
    done
```

Check 3 prints nothing when the boundary holds. It matches whole words (`grep -w`), which is
deliberate: the shortest entry in the deferred table is a two-letter technology name, and a
substring match on it would fire on ordinary words such as *email*, *detail*, and *maintain*
that carry no scope meaning. Read every deferred term as a whole word.

No unit tests, system integration tests, or fixtures are added by this story:
`PHASE1_FILE_PLAN.md` is a planning artifact, no PHP or JavaScript logic changes, and no HTTP
route or database-dependent behaviour is affected.
