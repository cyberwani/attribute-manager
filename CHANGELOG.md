# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-09-25
### Breaking
- Each call to `attribute_manager()` now returns a **new** `AttributeManager` instance (no singleton).
- Removed `AttributeManager::instance()`.

### Fixed
- Attribute/class leakage when rendering the same shortcode multiple times.

### Migration
- Replace `Cyberwani\RenderAttributes\AttributeManager::instance()` with `attribute_manager()`.
- If you previously relied on shared global state, keep a single `$am` instance inside your scope and pass it where needed.

### Added
- Optional per-instance identifier (constructor param) and `id()` accessor for debugging.


## [1.0.0] - 2025-09-24
### Added
- Initial stable release.
- Standalone `AttributeManager` with methods:
  - `add_render_attribute`
  - `get_render_attributes`
  - `set_render_attribute`
  - `remove_render_attribute`
  - `get_render_attribute_string`
  - `print_render_attribute_string`
- Safe attribute escaping (`htmlspecialchars`, ENT_QUOTES, UTF-8).
- Multi-value merging for `class`, `rel`, `aria-labelledby`, `aria-describedby`.
- Singleton handling for `id` (last value wins).
- Global helper function `attribute_manager()` via Composer `files` autoload.