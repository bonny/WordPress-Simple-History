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

```
npx @wp-playground/cli@latest server --port=9402 --blueprint=blueprint.json
```

Workaround that works (it is the step's own body without the guard): `setSiteOptions` siteurl/home to the port URL, `wp-cli` step `wp core multisite-convert --base=/`, then pin `$_SERVER['HTTP_HOST'] = '127.0.0.1:9402'` in wp-config.php via `runPHP`.

Suggested fix: drop the port check, or gate it on the WordPress version being below 6.6. Happy to send a PR.
