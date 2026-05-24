# Errors MetaData — Joomla 6 Administrator Module

A Joomla 6 administrator module that detects SEO metadata issues in your articles, categories and menu items.

## Features

- Detects **missing** meta descriptions and browser page titles
- Flags descriptions that are **too short** (< 120 chars) or **too long** (> 160 chars)
- Flags page titles that are **too long** (> 60 chars)
- Detects **duplicate** meta descriptions and page titles
- **Cross-checks** articles against their linked menu items — no false alerts when metadata is set on either side
- **CSV export** of all issues for batch processing
- Color-coded badges: 🔴 missing · 🟡 too short/long · 🔵 duplicate
- Bootstrap 5 tabs (Articles / Categories / Menu Items)
- Optional meta keywords check (disabled by default — ignored by search engines)
- Filter by published state
- Fully compatible with **Joomla 6** and the Atum admin template

## Requirements

- Joomla 6.x
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+ (JSON_SET support)

## Installation

1. Download the latest release ZIP
2. Go to **System → Extensions → Install**
3. Upload and install the ZIP
4. Go to **System → Modules (Administrator)**
5. Find *Errors MetaData* and assign it to the `cpanel` position

## Usage

The module displays three tabs:

| Tab | What it checks |
|-----|---------------|
| Articles | `metadesc`, `article_page_title` (from `attribs`) |
| Categories | `metadesc` |
| Menu Items | `menu-meta_description`, `page_title` (from `params`) |

Each item shows color-coded badges indicating the specific issue. Click the item title to edit it directly.

### CSV Export

Each tab has an **Export CSV** button that downloads all issues (ignoring the display limit) — useful for batch corrections with AI tools.

## Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| Check keywords | No | Flag missing meta keywords (not recommended — ignored by Google) |
| Items to analyse | Published only | Include unpublished items or not |
| Articles count | 5 | Max items to display (0 = unlimited) |
| Categories count | 5 | Max items to display |
| Menus count | 5 | Max items to display |
| Ordering | Recently added | Sort by creation or modification date |

## Useful Links

- [Joomla Extensions Directory](https://extensions.joomla.org/support/knowledgebase/)
- [Joomla Documentation](https://docs.joomla.org/Main_Page)

## License

GNU General Public License version 3 or later.  
See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).

## Credits

Developed by [web-eau.net](https://web-eau.net)  
Based on the original *Missing MetaData* module for Joomla 4 by [NosAdaptamos.com](https://nosadaptamos.com)
