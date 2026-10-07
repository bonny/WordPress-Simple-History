# parallel-dev.sh `--multisite` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `scripts/parallel-dev.sh up <slug> --multisite` starts a Playground instance that is a WordPress subdirectory multisite with two sites, on the usual `.test:94xx` URL, with Simple History (and Premium when mounted) network-activated.

**Architecture:** Playground's own `enableMultisite` blueprint step refuses any URL with a port, but WordPress itself has allowed ports in multisite since 6.6. The step's body after that guard is nothing more than: set `siteurl`/`home`, run `wp core multisite-convert` through Playground's bundled WP-CLI, then force `$_SERVER['HTTP_HOST']` in `wp-config.php` so Playground's internal requests (which arrive as `127.0.0.1:<port>`) resolve to the network. `parallel-dev.sh` replays exactly that sequence as ordinary blueprint steps, port included, prepended before the template's `login`/`activatePlugin` steps, and appends network activation plus a second site after them. No fork of Playground, no proxy on port 80.

**Tech Stack:** bash + jq (the script), WordPress Playground CLI 3.x blueprints (`defineWpConfigConsts`, `setSiteOptions`, `wp-cli`, `runPHP`, `extraLibraries`), WP-CLI `core multisite-convert`, `site create`, `plugin activate --network`.

**Spec:** Issue `142 - Multisite information for super admin`, section "Probe result" (2026-09-06), item "Blocked: multisite browser smoke test", option 2. Design discussion is in this session; the relevant facts are restated in Global Constraints.

## Global Constraints

-   Playground's `enableMultisite` step must not be used: it throws `WordPress multisites do not support custom ports` whenever the instance URL has a port, and every parallel-dev URL has one (`http://<slug>.test:<port>` or `http://localhost:<port>`).
-   Playground activation steps (`activatePlugin`) and its auto-login only work on a multisite after `wp-config.php` sets `$_SERVER['HTTP_HOST']` to the network domain. Without it every internal request is 302-redirected to the network home and `activatePlugin` reports "could not be activated". This is what Playground's own step does; we must do the same.
-   The `wp-cli` blueprint step requires `"extraLibraries": ["wp-cli"]` at the blueprint root, or it throws `wp-cli.phar not found`.
-   WP-CLI's `multisite_convert_` builds `DOMAIN_CURRENT_SITE` from `get_option('siteurl')` with the port kept, so `siteurl` must be the final instance URL before the convert step runs. It refuses `localhost` only for subdomain installs; we always use subdirectories (`--base=/`), so the `localhost` fallback URL keeps working.
-   Subdirectory install, never subdomains: `.test` wildcard DNS exists on this machine but subdomain multisites need a wildcard per slug and would not work on the `localhost` fallback.
-   Steps injected by the script must be stripped again on re-run (the script supports `--blueprint` pointing at its own generated file) and must be identifiable by content only. Blueprint steps are schema-validated, so no custom marker keys on step objects.
-   Follow the script's existing style: tabs, `local` variables, comments above code explaining why, `err` for fatal messages, jq for all JSON edits with the `> "$blueprint.tmp" && mv` pattern.
-   Do not commit until the user says so (project rule). Each task ends at a ready-to-commit state with the suggested message; run the commit only with the user's go-ahead.
-   `scripts/parallel-dev.sh` currently has an unrelated uncommitted hunk in `issue_file_for()` (archive lookup via `find`). Leave it in place; do not revert it. When the user approves committing Task 1, ask whether that hunk should go in first as its own commit (`Look in the issue archive too when resolving a slug's issue file`).

---

## File Structure

| File                                       | Responsibility                                                                                                                                                                                                                                              |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `scripts/parallel-dev.sh`                  | Modify. `cmd_up` gains `--multisite`; a new `blueprint_add_multisite_steps()` helper builds and prepends/appends the multisite steps; the strip filter learns to remove them; `.playground.json` records `multisite`; the Ready block and `status` show it. |
| `scripts/playground-multisite-host.php`    | Create. The PHP for the `runPHP` step that patches `wp-config.php` with the forced `HTTP_HOST`. Kept in a file like `playground-provision-app-password.php` so the PHP is readable and phpcs-checkable, loaded with `jq --rawfile`.                         |
| `.claude/skills/worktree/SKILL.md`         | Modify. Replace the "Multisite" section's broken `enableMultisite` recipe with the flag.                                                                                                                                                                    |
| `.claude/skills/wp-playground/SKILL.md`    | Modify, only if it mentions `enableMultisite` (check with grep); point it at the flag.                                                                                                                                                                      |
| `todos/playground-multisite-port-guard.md` | Create. Draft of the upstream Playground issue for the user to post.                                                                                                                                                                                        |

No automated test harness exists for the script. Each task's verification is a real `up`/`down` cycle on a throwaway slug with curl assertions; the commands are given in full.

---

### Task 1: `--multisite` flag, multisite blueprint steps, wp-config host patch

**Files:**

-   Create: `scripts/playground-multisite-host.php`
-   Modify: `scripts/parallel-dev.sh` (usage header lines 6-15, `cmd_up` argument loop ~line 274, strip filter ~line 378, step assembly ~lines 388-447, `.playground.json` writer ~line 486, Ready block ~line 520)

**Interfaces:**

-   Consumes: existing script variables `$blueprint`, `$site_url`, `$slug`, `$premium`, `$SCRIPT_DIR`; existing `err()`.
-   Produces: `MULTISITE_HOST_PHP_FILE` constant; shell function `blueprint_add_multisite_steps <blueprint> <site_url> <slug> <premium>`; `.playground.json` field `multisite` (`true`/`false`). Task 2 relies on the field name `multisite` and the `--multisite` flag spelling.

-   [ ] **Step 1: Write the wp-config host patch PHP**

Create `scripts/playground-multisite-host.php`:

```php
<?php
/**
 * Blueprint runPHP step: pin $_SERVER['HTTP_HOST'] in wp-config.php.
 *
 * Injected by scripts/parallel-dev.sh when `up` runs with --multisite.
 * Playground's internal requests (blueprint steps, auto-login, wp-cli)
 * arrive as 127.0.0.1:<port>, which is not the network's domain, so a
 * multisite WordPress redirects every one of them to the network home
 * and later steps fail. Playground's own enableMultisite step solves
 * this by hardcoding HTTP_HOST at the top of wp-config.php; this does
 * the same, with the port kept.
 *
 * Marker "sh-parallel-dev-multisite" lets parallel-dev.sh find and strip
 * this step when re-generating a blueprint.
 *
 * The host is passed through the SH_DEV_MULTISITE_HOST constant, defined
 * by the defineWpConfigConsts step that precedes this one.
 *
 * @package SimpleHistoryDev
 */

$sh_dev_config_path = '/wordpress/wp-config.php';
$sh_dev_host        = defined( 'SH_DEV_MULTISITE_HOST' ) ? SH_DEV_MULTISITE_HOST : '';

if ( $sh_dev_host === '' ) {
	echo "sh-parallel-dev-multisite: SH_DEV_MULTISITE_HOST is not defined\n";
	exit( 1 );
}

$sh_dev_config = file_get_contents( $sh_dev_config_path );

if ( $sh_dev_config === false ) {
	echo "sh-parallel-dev-multisite: cannot read {$sh_dev_config_path}\n";
	exit( 1 );
}

// Idempotent: a re-run of the blueprint must not stack a second assignment.
if ( strpos( $sh_dev_config, "\$_SERVER['HTTP_HOST']" ) === false ) {
	$sh_dev_line   = "\$_SERVER['HTTP_HOST'] = '" . addslashes( $sh_dev_host ) . "';\n";
	$sh_dev_config = preg_replace( '/^<\?php\s*/i', "<?php\n" . $sh_dev_line, $sh_dev_config, 1 );

	if ( file_put_contents( $sh_dev_config_path, $sh_dev_config ) === false ) {
		echo "sh-parallel-dev-multisite: cannot write {$sh_dev_config_path}\n";
		exit( 1 );
	}
}
```

Note: this file runs inside Playground's `runPHP` step, which does not bootstrap WordPress. It must stay free of WordPress functions.

-   [ ] **Step 2: Lint the PHP file**

Run: `./vendor/bin/phpcs scripts/playground-multisite-host.php && php -l scripts/playground-multisite-host.php`
Expected: no phpcs errors, `No syntax errors detected`. If phpcs flags the `file_get_contents`/`file_put_contents` calls (WordPress.WP.AlternativeFunctions), add the same `// phpcs:disable WordPress.WP.AlternativeFunctions -- runs before WordPress loads.` line that `scripts/playground-provision-app-password.php` uses for its own non-WP calls, or a matching one if it has none.

-   [ ] **Step 3: Add the flag, the file constant, and the usage text**

In `scripts/parallel-dev.sh`:

Near line 46 (`PROVISION_PHP_FILE=`), add:

```bash
MULTISITE_HOST_PHP_FILE="$SCRIPT_DIR/playground-multisite-host.php"
```

In the header comment (line 8) and the `usage` text (line 54), change the `up` line to:

```
scripts/parallel-dev.sh up <slug> [--premium=<path>] [--no-premium] [--blueprint=<path>] [--multisite]
```

and add under the existing explanation of `--premium`:

```
    --multisite converts the instance into a subdirectory multisite with a
    second site at /site2/, and network-activates Simple History (and
    Premium when mounted). Pass it on every `up`; Playground is rebuilt
    from the blueprint each start.
```

In `cmd_up`, change the `local` line and the argument loop:

```bash
	local slug="" premium="" premium_explicit=0 no_premium=0 blueprint_override="" multisite=0

	for arg in "$@"; do
		case "$arg" in
			--premium) premium="$PREMIUM_DEFAULT_DIR"; premium_explicit=1 ;;
			--premium=*) premium="${arg#--premium=}"; premium_explicit=1 ;;
			--no-premium) no_premium=1 ;;
			--blueprint=*) blueprint_override="${arg#--blueprint=}" ;;
			--multisite) multisite=1 ;;
			-*) err "unknown flag: $arg" ;;
			*) slug="$arg" ;;
		esac
	done
```

-   [ ] **Step 4: Extend the strip filter**

Replace the existing strip `jq` (the one whose comment starts "Strip any steps a previous run injected") with:

```bash
	# Strip any steps a previous run injected (supports --blueprint pointing
	# at the generated file itself) — otherwise the appends below accumulate
	# on every restart, and stale copies carry old ports/tokens/passwords.
	# Multisite steps are matched by content: blueprint steps are
	# schema-validated, so there is no room for a marker key on them.
	jq '.steps |= map(select(
			((.step == "defineWpConfigConsts") and ((.consts // {}) | has("SH_DEV_WORKTREE_PATH")))
			or ((.step == "defineWpConfigConsts") and ((.consts // {}) | has("SH_DEV_MULTISITE_HOST")))
			or ((.step == "runPHP") and ((.code // "") | contains("sh-parallel-dev-provision")))
			or ((.step == "runPHP") and ((.code // "") | contains("sh-parallel-dev-multisite")))
			or ((.step == "activatePlugin") and (.pluginPath == "simple-history-premium/simple-history-premium.php"))
			or ((.step == "setSiteOptions") and ((.options // {}) | has("siteurl")))
			or ((.step == "wp-cli") and ((.command // "") | test("^wp (core multisite-convert|site create|plugin activate .*--network)")))
			| not))' \
		"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
```

-   [ ] **Step 5: Add the multisite step builder**

Add this function above `cmd_up` (after `site_url_for`):

```bash
# Turn the generated blueprint into a subdirectory multisite.
#
# Playground's own enableMultisite step refuses any instance URL with a
# port, although WordPress itself has allowed ports in multisite since
# 6.6. Its body is what we replay here, port included:
#   1. WP_ALLOW_MULTISITE, so wp-cli lets us convert.
#   2. siteurl/home set to the final URL — multisite-convert derives
#      DOMAIN_CURRENT_SITE from siteurl, port and all.
#   3. `wp core multisite-convert` (writes the MULTISITE constants).
#   4. $_SERVER['HTTP_HOST'] pinned in wp-config.php, because
#      Playground's internal requests arrive as 127.0.0.1:<port> and a
#      multisite redirects unknown hosts to the network home — which is
#      what breaks every later activatePlugin/login step without it.
# Steps 1-4 go BEFORE the template's login/activatePlugin steps. After
# everything else: network-activate our plugins and add a second site so
# the network actually has something to show.
#
# Subdirectory install only. Subdomains would need wildcard DNS per slug
# and do not work on the localhost fallback URL.
blueprint_add_multisite_steps() {
	local blueprint="$1" site_url="$2" slug="$3" premium="$4"

	local host
	host="${site_url#http://}"
	host="${host%%/*}"

	local pre_steps
	pre_steps="$(jq -n \
		--arg url "$site_url" \
		--arg host "$host" \
		--arg title "$slug network" \
		--rawfile code "$MULTISITE_HOST_PHP_FILE" \
		'[
			{step: "defineWpConfigConsts", consts: {WP_ALLOW_MULTISITE: true, SH_DEV_MULTISITE_HOST: $host}},
			{step: "setSiteOptions", options: {siteurl: $url, home: $url}},
			{step: "wp-cli", command: ("wp core multisite-convert --base=/ --title=" + ($title | @json))},
			{step: "runPHP", code: $code}
		]')"

	local plugins="simple-history"

	if [ -n "$premium" ]; then
		plugins="$plugins simple-history-premium"
	fi

	local post_steps
	post_steps="$(jq -n \
		--arg plugins "$plugins" \
		--arg title "$slug site 2" \
		'[
			{step: "wp-cli", command: ("wp plugin activate " + $plugins + " --network")},
			{step: "wp-cli", command: ("wp site create --slug=site2 --title=" + ($title | @json))}
		]')"

	jq --argjson pre "$pre_steps" --argjson post "$post_steps" \
		'.extraLibraries = (((.extraLibraries // []) + ["wp-cli"]) | unique)
		 | .steps = $pre + .steps + $post' \
		"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
}
```

-   [ ] **Step 6: Call it from `cmd_up` at the right point**

The multisite steps must wrap the template steps and the premium `activatePlugin`, but sit before the script's final `defineWpConfigConsts` (WP_HOME etc.) and the app-password `runPHP`. Insert the call immediately after the premium `activatePlugin` append and before the comment "One consts step for everything the instance needs":

```bash
	if [ -n "$premium" ]; then
		jq '.steps += [{"step": "activatePlugin", "pluginPath": "simple-history-premium/simple-history-premium.php"}]' \
			"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
	fi

	if [ "$multisite" = 1 ]; then
		blueprint_add_multisite_steps "$blueprint" "$site_url" "$slug" "$premium"
	fi
```

Order check on the resulting `.steps`, which the verification step asserts: `defineWpConfigConsts(WP_ALLOW_MULTISITE)`, `setSiteOptions(siteurl)`, `wp-cli multisite-convert`, `runPHP(host patch)`, then the template's steps, then premium `activatePlugin` (if any), then `wp-cli plugin activate --network`, `wp-cli site create`, then `defineWpConfigConsts(SH_DEV_WORKTREE_PATH…)`, then `runPHP(provision)`.

-   [ ] **Step 7: Record and show it**

In the `.playground.json` writer, add `--argjson multisite "$( [ "$multisite" = 1 ] && echo true || echo false )"` to the `jq -n` arguments and `multisite: $multisite` to the object literal (after `premium: $premium`).

In the Ready block (after the `premium:` line, mirror its formatting), add:

```bash
	if [ "$multisite" = 1 ]; then
		echo "  multisite: subdirectory network — sites: $site_url/ and $site_url/site2/ — Network Admin: $site_url/wp-admin/network/"
	fi
```

-   [ ] **Step 8: Shell syntax check**

Run: `bash -n scripts/parallel-dev.sh && shellcheck scripts/parallel-dev.sh 2>/dev/null | head -20 || true`
Expected: `bash -n` silent. shellcheck may not be installed; if it is, no new warnings compared to `git stash`-free baseline (compare against `git show HEAD:scripts/parallel-dev.sh | shellcheck -` output).

-   [ ] **Step 9: Verify with a throwaway multisite instance, no premium**

```bash
scripts/parallel-dev.sh up ms-smoke --multisite --no-premium
D=.claude/worktrees/ms-smoke
jq -r '.multisite' "$D/.playground.json"                                   # expect: true
jq -c '.extraLibraries, [.steps[].step]' "$D/.playground-blueprint.json"
# expect: ["wp-cli"] and the order listed in Step 6
URL=$(jq -r .url "$D/.playground.json")
jar=$(mktemp); curl -s -c "$jar" -L -o /dev/null "$URL/wp-admin/"
curl -s -b "$jar" -o /dev/null -w 'network admin %{http_code}\n' "$URL/wp-admin/network/"       # expect 200
curl -s -o /dev/null -w 'site2 %{http_code}\n' -L "$URL/site2/"                                    # expect 200
curl -s -b "$jar" "$URL/wp-admin/network/plugins.php" | grep -c 'Network Deactivate'              # expect >= 1
AUTH=$(jq -r '.app_user + ":" + .app_password' "$D/.playground.json")
curl -s -u "$AUTH" "$URL/wp-json/simple-history/v1/events?per_page=2" | head -c 200; echo         # expect JSON array, not rest_not_logged_in
grep -i -E 'fatal|error' "$D/.playground.log" | head                                               # expect nothing
```

If `wp-admin/network/` is not 200: read `.playground.log`. A 302 to the home URL means the HTTP_HOST patch did not land; check `wp-config.php` inside the instance via a REST-less probe: add a temporary `runPHP` step printing `file_get_contents('/wordpress/wp-config.php')` is not possible after start, so instead run `up` again with `--blueprint=$D/.playground-blueprint.json` after inserting `{"step":"runPHP","code":"<?php echo file_get_contents('/wordpress/wp-config.php');"}` as the last step; Playground prints runPHP output to `.playground.log`.

-   [ ] **Step 10: Verify with premium mounted, then tear down**

```bash
scripts/parallel-dev.sh down ms-smoke
scripts/parallel-dev.sh up ms-smoke --multisite
D=.claude/worktrees/ms-smoke; URL=$(jq -r .url "$D/.playground.json")
jar=$(mktemp); curl -s -c "$jar" -L -o /dev/null "$URL/wp-admin/"
curl -s -b "$jar" "$URL/wp-admin/network/plugins.php" | grep -o -E 'simple-history(-premium)?[^"]*Network Deactivate' | sort -u
# expect both plugins listed as network-active
scripts/parallel-dev.sh down ms-smoke --remove
git worktree list | grep -c ms-smoke                                                               # expect 0
```

-   [ ] **Step 11: Re-run idempotency check**

`up` must be re-runnable with the generated blueprint as input without duplicating steps:

```bash
scripts/parallel-dev.sh up ms-smoke --multisite --no-premium
D=.claude/worktrees/ms-smoke
scripts/parallel-dev.sh down ms-smoke
scripts/parallel-dev.sh up ms-smoke --multisite --no-premium --blueprint="$D/.playground-blueprint.json"
jq '[.steps[] | select(.step=="wp-cli")] | length' "$D/.playground-blueprint.json"                # expect 3
scripts/parallel-dev.sh down ms-smoke --remove
```

-   [ ] **Step 12: Ready to commit (ask the user first)**

```bash
git add scripts/parallel-dev.sh scripts/playground-multisite-host.php
git commit -m "parallel-dev: --multisite flag that converts the Playground instance to a subdirectory network

Playground's enableMultisite step refuses any URL with a port, which every
parallel-dev instance has, although WordPress has allowed ports in
multisite since 6.6. Replay the step's body ourselves: set siteurl/home,
run wp core multisite-convert, pin HTTP_HOST in wp-config.php so
Playground's 127.0.0.1 requests resolve to the network, then
network-activate our plugins and add /site2/.

Claude-Session: https://claude.ai/code/session_01VimJCrX57gt3NwTodoh9Hd"
```

---

### Task 2: `status` shows multisite instances

**Files:**

-   Modify: `scripts/parallel-dev.sh` (`cmd_status`, ~lines 557-590)

**Interfaces:**

-   Consumes: `.playground.json` field `multisite` written by Task 1.
-   Produces: an `MS` column in `status` output.

-   [ ] **Step 1: Add the column**

In `cmd_status`, change the header `printf` to:

```bash
	printf '%-28s %-32s %-6s %-42s %-8s %-6s %-4s %s\n' SLUG BRANCH PORT URL RUNNING DIRTY MS PREMIUM
```

Extend the `local` declaration with `multisite`, and change the `jq` read so it also pulls the field (missing on instances started before Task 1, hence the `// false`):

```bash
			IFS=$'\t' read -r port pid url premium multisite <<< "$(jq -r \
				'[.port, .pid, .url, .premium, (.multisite // false)] | map(. // "") | @tsv' \
				"$dir/.playground.json")"
```

Set a display value next to where `running` is decided:

```bash
		local ms="-"
		[ "$multisite" = "true" ] && ms="yes"
```

and add `"$ms"` to the row `printf` in the same position as the header (before premium), widening its format string to match: `'%-28s %-32s %-6s %-42s %-8s %-6s %-4s %s\n'`.

-   [ ] **Step 2: Verify**

```bash
bash -n scripts/parallel-dev.sh
scripts/parallel-dev.sh up ms-smoke --multisite --no-premium
scripts/parallel-dev.sh status | grep ms-smoke        # expect an MS column reading "yes"
scripts/parallel-dev.sh status | grep issue-142        # expect "-" in the MS column (started without the flag)
scripts/parallel-dev.sh down ms-smoke --remove
```

-   [ ] **Step 3: Ready to commit (ask the user first)**

```bash
git add scripts/parallel-dev.sh
git commit -m "parallel-dev: show multisite instances in status

Claude-Session: https://claude.ai/code/session_01VimJCrX57gt3NwTodoh9Hd"
```

---

### Task 3: Documentation and the upstream issue draft

**Files:**

-   Modify: `.claude/skills/worktree/SKILL.md` (section "### Multisite", lines 118-131)
-   Modify: `.claude/skills/wp-playground/SKILL.md` (only if `grep -n enableMultisite` finds something)
-   Create: `todos/playground-multisite-port-guard.md`

**Interfaces:**

-   Consumes: the `--multisite` flag from Task 1.

-   [ ] **Step 1: Rewrite the worktree skill's Multisite section**

Replace the whole "### Multisite" section (from the heading to the closing code fence before "## Copying Uncommitted Changes") with:

````markdown
### Multisite

If the issue involves network/multisite functionality, ask the user if they want a multisite install. If yes, add `--multisite`:

```bash
scripts/parallel-dev.sh up issue-name-short --multisite
```

You get a subdirectory network with two sites (`/` and `/site2/`), Simple History and Premium network-activated, and Network Admin at `<url>/wp-admin/network/`. Pass the flag on every `up`; Playground rebuilds from the blueprint each start.

Do **not** use Playground's `enableMultisite` blueprint step. It refuses any URL with a port ("WordPress multisites do not support custom ports"), and every parallel-dev URL has one. WordPress itself has allowed ports in multisite since 6.6; the flag replays what the step does after that outdated guard. Subdomain networks are not supported (wildcard DNS per slug, and impossible on the `localhost` fallback).
````

-   [ ] **Step 2: Check the wp-playground skill**

Run: `grep -n -i 'multisite' .claude/skills/wp-playground/SKILL.md`
If it mentions `enableMultisite`, replace that mention with one sentence pointing at `scripts/parallel-dev.sh up <slug> --multisite` and the reason above. If it does not mention multisite, leave it.

-   [ ] **Step 3: Draft the upstream issue (do not post)**

Create `todos/playground-multisite-port-guard.md`:

````markdown
# Upstream: Playground `enableMultisite` refuses ports that WordPress accepts

Draft for a GitHub issue on WordPress/wordpress-playground. Pär posts it.

**Title:** `enableMultisite` step rejects URLs with a port, but WordPress 6.6+ supports them

**Body:**

`enableMultisite` throws before doing anything when the Playground URL has a port:

> The current host is 127.0.0.1:9402, but WordPress multisites do not support custom ports.

That was true for the Network Setup screen up to WordPress 6.5, but 6.6 removed the port restriction (`wp-admin/includes/network.php` no longer has the "You cannot use port numbers" check), and `wp core multisite-convert` has never had one. So a local Playground CLI instance on any port cannot become a multisite, even though the code path after the guard works fine with the port kept in `DOMAIN_CURRENT_SITE`.

Repro with @wp-playground/cli 3.1.52:

```json
{ "steps": [ { "step": "enableMultisite" } ] }
```
````

```
npx @wp-playground/cli@latest server --port=9402 --blueprint=blueprint.json
```

Workaround that works (it is the step's own body without the guard): `setSiteOptions` siteurl/home to the port URL, `wp-cli` step `wp core multisite-convert --base=/`, then pin `$_SERVER['HTTP_HOST'] = '127.0.0.1:9402'` in wp-config.php via `runPHP`.

Suggested fix: drop the port check, or gate it on the WordPress version being below 6.6. Happy to send a PR.

````

- [ ] **Step 4: Ready to commit (ask the user first)**

The `todos/` draft is for the user; include it in the commit only if the user wants drafts tracked (check `git log --oneline -3 -- todos/` to see whether earlier drafts were committed, and follow that).

```bash
git add .claude/skills/worktree/SKILL.md
git commit -m "worktree skill: document --multisite instead of Playground's enableMultisite step

Claude-Session: https://claude.ai/code/session_01VimJCrX57gt3NwTodoh9Hd"
````

---

### Task 4: Use it for issue 142

Not a code task; the payoff. After Tasks 1-3:

-   [ ] **Step 1: Restart the 142 instance as a multisite**

```bash
scripts/parallel-dev.sh down issue-142-network
scripts/parallel-dev.sh up issue-142-network --multisite \
  --premium=/Users/bonnymacmini/Projects/Simple-History-Add-Ons/.claude/worktrees/network-module/simple-history-premium
```

-   [ ] **Step 2: Run the smoke test the probe could not**

```bash
D=.claude/worktrees/issue-142-network; URL=$(jq -r .url "$D/.playground.json")
AUTH=$(jq -r '.app_user + ":" + .app_password' "$D/.playground.json")
jar=$(mktemp); curl -s -c "$jar" -L -o /dev/null "$URL/wp-admin/"
curl -s -b "$jar" -o /dev/null -w 'network SH page %{http_code}\n' "$URL/wp-admin/network/admin.php?page=simple_history_network_page"   # expect 200, Premium's page
curl -s -u "$AUTH" "$URL/wp-json/simple-history/v1/network/events?per_page=3" | head -c 400; echo    # expect JSON, not 404
# Trigger a network-level event and confirm it lands in the network log:
curl -s -b "$jar" "$URL/wp-admin/network/settings.php" -o /tmp/ns.html
N=$(grep -o 'name="_wpnonce" value="[^"]*"' /tmp/ns.html | head -1 | cut -d'"' -f4)
curl -s -b "$jar" -o /dev/null -w 'settings POST %{http_code}\n' \
  --data-urlencode "_wpnonce=$N" --data-urlencode "action=siteoptions" --data-urlencode "registration=user" \
  --data-urlencode "site_name=issue-142-network network" --data-urlencode "admin_email=admin@example.com" \
  "$URL/wp-admin/network/settings.php"
curl -s -u "$AUTH" "$URL/wp-json/simple-history/v1/network/events?per_page=1&_fields=message,logger" ; echo   # expect the registration change
```

-   [ ] **Step 3: Record the result on issue 142** with `obsidian append` under a `> [!agent]` callout: which checks passed, the URL, and anything that broke, so the "Blocked: multisite browser smoke test" item can be closed.

---

## Self-review

-   **Spec coverage.** Option 2 = replay `enableMultisite` minus the guard via a `wp-cli` step (Task 1), documented (Task 3), applied to 142 (Task 4). The probe's two documented failure modes are each addressed: the port guard is bypassed by not using the step; the activation redirect is fixed by the HTTP_HOST patch, which is the piece the manual attempt lacked. Upstream report drafted, not posted (Task 3, per the "Pär posts all public content" rule).
-   **Placeholders.** None. Every code step has the code; every verify step has the commands and expected values.
-   **Type/name consistency.** `--multisite` flag, `$multisite` shell var, `.playground.json` key `multisite`, constant `SH_DEV_MULTISITE_HOST`, marker string `sh-parallel-dev-multisite`, file `scripts/playground-multisite-host.php`, function `blueprint_add_multisite_steps` are spelled identically in Tasks 1-3. The strip filter's wp-cli regex matches exactly the three commands the builder emits (`core multisite-convert`, `site create`, `plugin activate … --network`).
-   **Known risk to watch in Task 1 Step 9.** WP-CLI inside Playground runs with `--url` derived from `siteurl`; if `wp core multisite-convert` writes `DOMAIN_CURRENT_SITE` without the port (it should not: `get_clean_basedomain` only strips the scheme and path), the network admin will 302. The debug recipe in Step 9 covers it.
