# Plugin development

Runtime requirements are WordPress 6.6 and PHP 7.4. `Tested up to: 7.2` is the requested release declaration; local fixtures do not establish installed WordPress 7.2 compatibility.

Development helpers, fixtures, standards configuration, and build documentation are kept in this repository. Installable release ZIPs contain only the plugin runtime and its source assets. The PHP fixture is restricted to CLI execution.

## Build

Run the build from the repository root. When maintaining editor source separately from an installed plugin, use the optional sync command to copy browser assets to that plugin:

```powershell
# Run from the repository root
npx --yes npm@12.2.1 ci
npx --yes npm@12.2.1 run build
npx --yes npm@12.2.1 run lint:js
npx --yes npm@12.2.1 run lint:css
# Optional, for a separate installed plugin:
node scripts/sync-plugin.cjs 'C:/path/to/installed/woorise'
```

The standard WordPress scripts toolchain uses the modern JSX runtime supplied by WordPress 6.6 and later. WordPress supplies React and its packages at runtime through `build/index.asset.php`. Both resizer packages must be updated together. Their GPLv3 license is included with the public assets; bundled dependency notices are retained in `build/index.js.LICENSE.txt`. Keep `package-lock.json` in the development project. Package only `assets/`, `build/`, `includes/`, `languages/`, `src/`, `readme.txt`, and `woorise.php` inside a top-level `woorise/` directory. Keep development helpers, npm files, and standards configuration out of the plugin ZIP.

## Verification

Run checks from the repository root. Supply the final plugin directory explicitly so tests exercise the distributed PHP and browser assets. With PHP_CodeSniffer and WordPress Coding Standards installed, run `phpcs --standard=phpcs.xml.dist "$env:WOORISE_PLUGIN_PATH/woorise.php" "$env:WOORISE_PLUGIN_PATH/includes"`. PHPCompatibilityWP can additionally check those runtime files with `--runtime-set testVersion 7.4-`.

```powershell
$env:WOORISE_PLUGIN_PATH = (Get-Location).Path
$env:WORDPRESS_PATH = 'C:/path/to/wordpress'
php dev/embeds.php $env:WORDPRESS_PATH $env:WOORISE_PLUGIN_PATH
$env:CHROME_PATH = 'C:/path/to/chrome.exe'
node dev/browser.cjs
```

The browser checks require Playwright and axe-core, available normally or through `NODE_PATH`. They execute the shipped bundle with React and fixtures for WordPress controls. Network requests are intercepted. PHP checks use actual WordPress URL/escaping functions with isolated query fixtures. These checks do not exercise a live installation, live Woorise campaigns, or every editor/theme integration.

## Translations

Generate the template with WordPress CLI:

```powershell
wp i18n make-pot $env:WOORISE_PLUGIN_PATH "$env:WOORISE_PLUGIN_PATH/languages/woorise.pot" --domain=woorise
```

Compile translated PO files to MO and JSON files with WordPress CLI. Extracting the built editor bundle as well as the source lets JSON catalogs target `build/index.js`; `wp_set_script_translations()` is registered for both the block editor and the conditions script. WordPress.org language packs remain supported. The POT is a translation template, not a completed translation into another language.

## Mapped domains and parameter forwarding

URL embeds and editor previews activate Woorise standard-embed mode so full-height campaign sections fit their content without a resize loop. Explicit HTTP(S) campaign URLs can use mapped domains. Each URL-based iframe trusts its own origin, and resize messages must come from that iframe's window. This also rejects spoofed messages from sandboxed frames or other campaigns sharing the same origin. When an iframe redirects to a different origin, add that destination explicitly, for example:

```php
add_filter( 'woorise_embed_trusted_origins', static function ( $origins ) {
	$origins[] = 'https://campaigns.example.com';
	return $origins;
} );
```

The filter also receives the campaign URL as a second argument; this is empty when registering editor settings. Only return origins you intend to trust. This setting concerns the plugin's URL-based iframes; ID embeds use Woorise's service script.

Customer query parameters continue to be forwarded automatically. Known nonce, password, and authentication parameter names are removed recursively from both the forwarded parameters and `woorise-source`. Tracking parameters retain their existing behavior: they remain in the source URL rather than being forwarded separately. Arbitrarily named sensitive values cannot be identified automatically.

`woorise_embed_excluded_query_args` filters the case-insensitive exclusion list, allowing a site to add its own private parameter names or permit a non-secret field that happens to share an excluded name. `woorise_embed_query_args` filters the final prefill arguments before the reserved source, embed ID, and standard embed-type parameters are added. These are trusted PHP extension points; callbacks remain responsible for any values they reintroduce.

## Dependency security

Checked against the npm registry on 2026-10-22. The direct packages are current stable releases. WordPress packages and React are development dependencies because WordPress supplies their runtime implementations. The bundled resizer dependencies have no advisories in `npm audit --omit=dev`.

The lockfile includes targeted overrides for patched js-yaml, smol-toml, serialize-javascript, postcss-selector-parser, and KaTeX releases. These address advisories in transitive build dependencies pinned to older versions upstream. Review the overrides when updating WordPress scripts and remove them once upstream ranges include the fixes. Verify the production webpack build and CSS parser checks when changing them.

One upstream advisory remains: `braces <=3.0.3` can exhaust the stack with deeply nested patterns (GHSA-vfj7-8cjw-p6xm). The registry's newest stable release is still 3.0.3. npm reports 27 affected development packages through this dependency chain; these are not 27 independent vulnerabilities. The plugin does not ship this toolchain or process glob patterns in PHP. Keep the build inputs trusted, and refresh the lockfile when an upstream patch is available. Do not run `npm audit fix --force`: its proposed downgrade of WordPress tooling would undo this update.
