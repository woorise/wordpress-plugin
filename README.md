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

See the [full Woorise plugin changelog](https://woorise.com/help/wordpress#woorise-plugin-changelog).

## Development

See the [developer guide](dev/README.md) for build instructions, testing, translations, and customization filters.

## Support

Use [Woorise Help](https://woorise.com/help/) or [WordPress.org support](https://wordpress.org/support/plugin/woorise/). Use GitHub issues for plugin code defects.

## License

GPL-2.0-or-later. Bundled third-party components retain their own licenses; notices are included with the assets and generated build.
