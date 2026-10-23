# Woorise WordPress Plugin

Embed forms, surveys, quizzes, giveaways, and other [Woorise](https://woorise.com/) campaigns in WordPress using the Woorise block, a shortcode, or site-wide display conditions.

A Woorise account is required. Campaign content and entries are hosted by Woorise.

## Requirements

- WordPress 6.6 or later
- PHP 7.4 or later

## Installation

Install [Woorise from WordPress.org](https://wordpress.org/plugins/woorise/), or download the installable `woorise.1.5.1.zip` asset from the [latest GitHub release](https://github.com/woorise/wordpress-plugin/releases/latest) and upload it through Plugins → Add New → Upload Plugin.

The attached plugin ZIP excludes development tools. GitHub's automatic source archives contain the complete development repository.

## Usage

- **Block editor:** add the Woorise block and paste the campaign's Embed ID from its Share page. Existing campaign URLs remain supported.
- **Shortcode:** add `[woorise id="ABC123456"]` to a page or post.
- **Multiple pages or site-wide:** create an embed in Woorise → Add New Embed and choose its placement and display conditions.

See the [WordPress embedding guide](https://woorise.com/help/wordpress) for full instructions.

## Releases

See [CHANGELOG.md](CHANGELOG.md) and [GitHub Releases](https://github.com/woorise/wordpress-plugin/releases).

The 1.4.0 and 1.5.0 tags restore the original WordPress.org plugin packages without changing their contents. Their original release dates are recorded in the changelog and release notes; the GitHub imports were made later. Package sources and SHA-256 checksums are recorded in [dev/release-provenance.json](dev/release-provenance.json).

## Development

This repository contains the plugin runtime, editor source, generated assets, and development helpers. Build and test tools are excluded from the installable plugin ZIP.

Use a Node version supported by `package.json` and the pinned npm version:

```sh
npx --yes npm@12.2.1 ci
npx --yes npm@12.2.1 run build
npx --yes npm@12.2.1 run lint:js
npx --yes npm@12.2.1 run lint:css
```

WordPress supplies React and its packages at runtime through the generated dependency manifest. The build retains third-party license notices and copies the pinned iframe-resizer parent asset from npm.

PHP checks, browser regressions, translations, extension filters, and known build-tool advisories are documented in [dev/README.md](dev/README.md).

If you maintain the editor source separately from the installed plugin, `node scripts/sync-plugin.cjs /absolute/path/to/woorise` copies the editor and browser assets to that plugin directory. PHP, release metadata, and translations remain in the plugin directory.

## Support

Use [Woorise Help](https://woorise.com/help/) or [WordPress.org support](https://wordpress.org/support/plugin/woorise/). Use GitHub issues for plugin code defects.

## License

GPL-2.0-or-later. Bundled third-party components retain their own licenses; notices are included with the assets and generated build.
