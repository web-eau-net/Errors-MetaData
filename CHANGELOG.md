# Changelog — Errors MetaData

All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-05-23

### Added
- Standard Joomla file headers in all PHP files
- German (de-DE) translation
- Italian (it-IT) translation
- Update server configuration (auto-updates via Joomla Extension Manager)

### Changed
- Replaced Catalan (ca-ES) with German (de-DE)
- Updated author email to daniel@web-eau.net
- License updated to GNU GPL v2+

## [1.0.0] - 2026-05-23

### Initial Release
- Detects missing meta descriptions and browser page titles
- Flags descriptions too short (< 120 chars) or too long (> 160 chars)
- Flags page titles too long (> 60 chars)
- Detects duplicate meta descriptions and page titles
- Cross-checks articles ↔ menu items
- CSV export for batch corrections
- Color-coded badges: missing · too short/long · duplicate
- Bootstrap 5 tabs (Articles / Categories / Menu Items)
- Filter by published state
- Optional meta keywords check
- Languages: English, French, Spanish, Catalan
