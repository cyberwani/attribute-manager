# cyberwani/attribute-manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cyberwani/attribute-manager.svg?style=flat)](https://packagist.org/packages/cyberwani/attribute-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/cyberwani/attribute-manager.svg?style=flat)](https://packagist.org/packages/cyberwani/attribute-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat)](LICENSE)

A tiny, **standalone** PHP library that mirrors Elementor’s **render attributes** API so you can safely build HTML attribute strings anywhere (not just in WordPress/Elementor).

- Familiar API: `add_render_attribute()`, `get_render_attribute_string()`, etc.
- Safe escaping (`esc_attr`-equivalent).
- Multi-value merging for `class`, `rel`, and common `aria-*` list attributes.
- Singleton handling for `id` (last value wins).
- Simple **singleton helper**: `attribute_manager()`.

---

## Installation

```bash
composer require cyberwani/attribute-manager:^1.0
