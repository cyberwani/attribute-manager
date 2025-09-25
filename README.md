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
composer require cyberwani/attribute-manager:^2.0
```

> Requires PHP **7.4+**.

Composer autoloads the package via PSR-4 (`Cyberwani\\RenderAttributes\\`) and loads a small helper from `src/helpers.php`.

---

## Quick Start

```php
<?php

// Always returns a NEW instance (no shared state).
$am = attribute_manager();

$am->add_render_attribute('wrapper', [
    'id'         => 'custom-widget-id',
    'class'      => ['custom-widget-wrapper-class', 'my-extra-class'],
    'role'       => 'region',
    'aria-label' => 'Products',
]);

$am->add_render_attribute('inner', [
    'class'       => 'custom-widget-inner-class',
    'data-custom' => 'custom-widget-information',
]);
?>
<div <?php echo $am->get_render_attribute_string('wrapper'); ?>>
  <div <?php $am->print_render_attribute_string('inner'); ?>></div>
</div>
```

**Example output:**

```html
<div id="custom-widget-id" class="custom-widget-wrapper-class my-extra-class" role="region" aria-label="Products">
  <div class="custom-widget-inner-class" data-custom="custom-widget-information"></div>
</div>
```

---

## Using in a WordPress Shortcode (safe with multiple renders)

Ensures attributes from one shortcode do **not** leak to another because each call creates a fresh manager.

```php
function my_shortcode_cb($atts = [], $content = null) {
    $atts = shortcode_atts([
        'element_id' => '',
        'css'        => '',
    ], $atts, 'my_shortcode');

    // NEW instance per render — prevents attribute leakage between shortcodes
    $am = attribute_manager();

    $am->add_render_attribute('wrapper', [
        'id'         => $atts['element_id'],
        'class'      => [
            'cdhl_panorama-viewer_wrapper',
            vc_shortcode_custom_css_class($atts['css'], ' '),
        ],
        'role'       => 'abc',
        'aria-label' => 'xyz',
    ]);

    // Add additional classes on the fly
    $am->add_render_attribute('wrapper', 'class', 'xxxxxx');

    ob_start(); ?>
      <div <?php echo $am->get_render_attribute_string('wrapper'); ?>>
          <?php cardealer_vehicle_360_view_images_html($vehicle_360_images); ?>
      </div>
    <?php
    return ob_get_clean();
}
add_shortcode('my_shortcode', 'my_shortcode_cb');
```

---

## API

All methods live on `Cyberwani\RenderAttributes\AttributeManager`. Use the helper `attribute_manager(?string $id = null): AttributeManager` to obtain a **new** instance for each render/scope.

### `add_render_attribute(string $element, string|array $key, mixed $value = null, bool $overwrite = false): self`
- Add or merge attributes for a logical element alias (e.g., `wrapper`, `inner`).
- `$key` can be a string or an associative array of `name => value`.
- `$value = null` enables a **boolean attribute** (e.g., `disabled` → `disabled`).
- Merge behavior:
  - `class`, `rel`, `aria-labelledby`, `aria-describedby` are **multi-value** keys that merge and de-duplicate.
  - `id` is a **singleton** key; last value wins (overwrite semantics).
- Set `$overwrite = true` to replace instead of merging.

### `get_render_attributes(string $element): array`
- Returns a normalized **raw** array of attributes for inspection/debugging, e.g.:
  ```php
  [
    'id'    => 'custom-widget-id',
    'class' => ['a', 'b', 'c'],
    'role'  => 'region',
  ]
  ```

### `set_render_attribute(string $element, string|array $key, mixed $value = null): self`
- Replace attribute(s) completely (no merge).  
- `$value = null` turns the attribute into a boolean (present without value).

### `remove_render_attribute(string $element, ?string $key = null, $value = null): self`
- Remove everything for `$element` when `$key` is `null`.
- Remove a single attribute when `$value` is `null`.
- Remove a specific **value** from multi-value attributes when `$value` is provided.

### `get_render_attribute_string(string $element): string`
- Returns an **escaped**, space-separated attribute string suitable for inlining in HTML, e.g.:
  ```php
  id="x" class="a b" aria-label="Products"
  ```

### `print_render_attribute_string(string $element): void`
- Echoes the same string (shortcut for templates).

---

## Escaping & Safety

- Values are escaped with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` (WordPress-like `esc_attr`).  
- `null` / `false` values are **ignored**. `true` becomes a **boolean attribute** (`disabled`, `required`, etc.).  
- Attribute **names** are sanitized (alphanumeric plus `: . _ -`); invalid characters are stripped.

---

## Behavior Details

- **Singleton attributes**: `id` is treated specially—latest value wins.  
- **Multi-value attributes**: `class`, `rel`, `aria-labelledby`, `aria-describedby` are merged and de-duplicated.  
- **Boolean attributes**:
  ```php
  $am->add_render_attribute('button', 'disabled'); // or value = null
  // -> disabled
  ```
- **Removing values**:
  ```php
  $am->add_render_attribute('el', 'class', ['btn', 'btn-primary', 'mt-2']);
  $am->remove_render_attribute('el', 'class', 'mt-2'); // leaves btn btn-primary
  ```

---

## Helper

This package ships a tiny helper:

```php
$am = attribute_manager();         // returns a NEW AttributeManager
$am = attribute_manager('debug');  // optional ID, handy in debugging/logging
```

If you prefer not to use the helper, instantiate directly:

```php
$am = new \Cyberwani\RenderAttributes\AttributeManager();
```

---

## Upgrading from 1.x

- **Breaking**: The singleton has been removed in v2.  
- **Do this now**:
  - Replace `Cyberwani\RenderAttributes\AttributeManager::instance()` with `attribute_manager()`.
  - Expect a **fresh** manager each time you call `attribute_manager()`; keep a local `$am` if you need to reuse it within a render.

---

## Versioning

We follow **Semantic Versioning**.  
- Backward-compatible changes → `MINOR`.  
- Breaking changes → `MAJOR`.

---

## Changelog (excerpt)

### [2.0.0] — 2025-09-25
**Breaking**
- Each call to `attribute_manager()` now returns a **new** `AttributeManager` instance (no singleton).
- Removed `AttributeManager::instance()`.

**Fixed**
- Attribute/class leakage when rendering the same shortcode multiple times.

**Migration**
- Replace `Cyberwani\RenderAttributes\AttributeManager::instance()` with `attribute_manager()`.
- If you previously relied on shared global state, keep a single `$am` instance inside your scope and pass it where needed.

(See full details in [`CHANGELOG.md`](CHANGELOG.md).)

---

## Testing (optional)

```bash
composer require --dev pestphp/pest phpunit/phpunit
vendor/bin/pest
```

---

## Security

If you discover a security vulnerability, please contact the maintainer privately before public disclosure.

---

## License

Released under the [MIT License](LICENSE).
