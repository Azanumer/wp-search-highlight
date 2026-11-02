# Search Highlight

Highlights the terms a visitor searched for on WordPress search results
pages, so they can see at a glance why each result matched.

## Features

- **Tag-safe highlighting** — HTML is split on tags and only text nodes
  are searched, so attributes and markup can never be broken.
- **Longest-term-first** — multi-word phrases highlight as one phrase
  before their single-word parts.
- **Case-insensitive, Unicode-aware** matching.
- **Content + excerpts** — highlight in post content by default, or extend
  to excerpts from Settings → Search Highlight.
- **`wsh_terms` filter** — themes/plugins can adjust the term list.
- **One CSS rule** — the default yellow `<mark>` style is printed on
  search pages only and is trivially overridden by a theme.

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate it. Search your site and watch the terms light up.

## License

MIT — see `LICENSE`.
