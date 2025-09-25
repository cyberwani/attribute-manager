<?php
declare(strict_types=1);

namespace Cyberwani\AttributeManager;

/**
 * Standalone render-attributes helper inspired by Elementor's API.
 *
 * Methods:
 * - add_render_attribute(string $element, string|array $key, mixed $value = null, bool $overwrite = false): self
 * - get_render_attributes(string $element): array
 * - set_render_attribute(string $element, string|array $key, mixed $value = null): self
 * - remove_render_attribute(string $element, ?string $key = null, $value = null): self
 * - get_render_attribute_string(string $element): string
 * - print_render_attribute_string(string $element): void
 *
 * Notes:
 * - Escaping mirrors WP's esc_attr using htmlspecialchars(…, ENT_QUOTES, 'UTF-8').
 * - Boolean attributes (e.g., disabled) can be set with value === true or by passing null to add_render_attribute.
 * - Multi-value attributes (e.g., class, rel) accept arrays and are de-duplicated.
 * - The "id" attribute is treated as singleton and will be overwritten (not merged).
 */
final class AttributeManager
{
	/** @var string[] Keys that should never join as space-separated lists. */
	private const SINGLETON_KEYS = ['id'];

	/** @var string[] Keys that typically accept space-separated multiple values and should be de-duplicated. */
	private const MULTI_VALUE_KEYS = ['class', 'rel', 'aria-labelledby', 'aria-describedby'];

	/** @var string Regex to sanitize attribute names. */
	private const ATTR_NAME_ALLOWED = '/[^a-zA-Z0-9:._-]/';

	/** Optional per-instance identifier (for debugging/logging) */
	private string $instanceId;

	/** @var array<string, array<string, mixed>> In-memory store: element => [attr => values] */
	private array $store = [];

	/**
	 * Create a new, empty manager. Each instance holds its own attribute store.
	 */
	public function __construct(?string $instanceId = null)
	{
		try {
			$this->instanceId = $instanceId ?? bin2hex(random_bytes(8));
		} catch (\Throwable $e) {
			$this->instanceId = $instanceId ?? uniqid('', true);
		}
	}

	/** Optional helper to read the instance id */
	public function id(): string
	{
		return $this->instanceId;
	}

	/**
	 * Add attributes to an element.
	 *
	 * @param string $element  A key/alias like "wrapper", "inner", etc.
	 * @param string|array $key Attribute name or an associative array of name => value.
	 * @param mixed $value     Attribute value(s). For boolean attributes, pass true or null.
	 * @param bool $overwrite  If true, replaces the attribute (or all when $key is array).
	 */
	public function add_render_attribute(string $element, $key, $value = null, bool $overwrite = false): self
	{
		if (is_array($key)) {
			// Add/overwrite from array payload
			if ($overwrite) {
				foreach ($key as $k => $v) {
					$this->set_render_attribute($element, (string)$k, $v);
				}
				return $this;
			}
			foreach ($key as $k => $v) {
				$this->mergeAttribute($element, (string)$k, $v);
			}
			return $this;
		}

		// Single attribute add/merge
		if ($overwrite) {
			return $this->set_render_attribute($element, (string)$key, $value);
		}

		return $this->mergeAttribute($element, (string)$key, $value);
	}

	/**
	 * Set attributes (replace) for an element.
	 *
	 * @param string $element
	 * @param string|array $key
	 * @param mixed $value
	 */
	public function set_render_attribute(string $element, $key, $value = null): self
	{
		if (!isset($this->store[$element])) {
			$this->store[$element] = [];
		}

		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->setSingleAttribute($element, (string)$k, $v);
			}
			return $this;
		}

		$this->setSingleAttribute($element, (string)$key, $value);
		return $this;
	}

	/**
	 * Remove attribute(s).
	 *
	 * @param string      $element
	 * @param string|null $key    If null, removes the entire element’s attributes.
	 * @param mixed|null  $value  If provided, removes only matching value(s) from a multi-value attribute.
	 */
	public function remove_render_attribute(string $element, ?string $key = null, $value = null): self
	{
		if (!isset($this->store[$element])) {
			return $this;
		}

		if ($key === null) {
			unset($this->store[$element]);
			return $this;
		}

		$key = $this->sanitizeAttrName($key);

		if (!array_key_exists($key, $this->store[$element])) {
			return $this;
		}

		if ($value === null) {
			unset($this->store[$element][$key]);
			return $this;
		}

		// Remove specific value(s)
		$current = $this->store[$element][$key];

		if (is_array($current)) {
			$valuesToRemove = is_array($value) ? $value : [$value];
			$valuesToRemove = $this->normalizeValues($valuesToRemove);

			$remaining = array_values(array_udiff(
				$current,
				$valuesToRemove,
				static function ($a, $b): int {
					return strcmp((string)$a, (string)$b);
				}
			));

			if (empty($remaining)) {
				unset($this->store[$element][$key]);
			} else {
				$this->store[$element][$key] = $remaining;
			}
		} else {
			// scalar/boolean: remove entirely if matches loosely
			if ((string)$current === (string)$value || $value === true) {
				unset($this->store[$element][$key]);
			}
		}

		return $this;
	}

	/**
	 * Get raw attributes array for an element (normalized).
	 * Returns: [attr => string|bool|string[]]
	 */
	public function get_render_attributes(string $element): array
	{
		if (!isset($this->store[$element])) {
			return [];
		}
		// Return deep copy to prevent accidental external mutation
		return json_decode(json_encode($this->store[$element], JSON_THROW_ON_ERROR), true);
	}

	/**
	 * Build the HTML attribute string for an element.
	 * Example: id="x" class="a b" aria-label=".."
	 */
	public function get_render_attribute_string(string $element): string
	{
		if (!isset($this->store[$element])) {
			return '';
		}

		$parts = [];

		foreach ($this->store[$element] as $rawName => $rawVal) {
			$name = $this->sanitizeAttrName($rawName);
			if ($name === '') {
				continue;
			}

			if ($rawVal === null || $rawVal === false) {
				// skip null/false
				continue;
			}

			// Boolean attribute
			if ($rawVal === true) {
				$parts[] = $name;
				continue;
			}

			// Normalize arrays/scalars
			$values = is_array($rawVal) ? $this->normalizeValues($rawVal) : [$rawVal];

			// De-duplicate when appropriate
			if ($this->isMultiValue($name)) {
				$values = array_values(array_unique($values, SORT_STRING));
			}

			// For singleton keys like id, take the last value
			if ($this->isSingleton($name)) {
				$value = (string)end($values);
				if ($value === '') {
					continue;
				}
				$parts[] = sprintf('%s="%s"', $name, self::esc_attr($value));
				continue;
			}

			// Join remaining values with a single space
			$values = array_values(array_filter($values, static fn($v) => $v !== ''));
			if (empty($values)) {
				continue;
			}

			$joined = implode(' ', $values);
			$parts[] = sprintf('%s="%s"', $name, self::esc_attr($joined));
		}

		return implode(' ', $parts);
	}

	/**
	 * Echo the attribute string.
	 */
	public function print_render_attribute_string(string $element): void
	{
		echo $this->get_render_attribute_string($element);
	}

	/* ---------------------------------------------------------------------
	 * Internal helpers
	 * ------------------------------------------------------------------- */

	private function mergeAttribute(string $element, string $key, $value): self
	{
		$key = $this->sanitizeAttrName($key);
		if ($key === '') {
			return $this;
		}

		if (!isset($this->store[$element])) {
			$this->store[$element] = [];
		}

		// Boolean attribute when $value === null
		if ($value === null) {
			$this->store[$element][$key] = true;
			return $this;
		}

		// Singleton keys (like id) -> overwrite behavior on add
		if ($this->isSingleton($key)) {
			$this->setSingleAttribute($element, $key, $value);
			return $this;
		}

		// Merge arrays/scalars
		$incoming = is_array($value) ? $this->normalizeValues($value) : $this->normalizeValues([$value]);

		if (!array_key_exists($key, $this->store[$element])) {
			$this->store[$element][$key] = $this->isMultiValue($key) ? $incoming : (string)end($incoming);
			return $this;
		}

		$current = $this->store[$element][$key];

		if ($this->isMultiValue($key)) {
			// Merge + de-dupe for multi-value keys
			$merged = array_values(array_unique(
				array_merge(is_array($current) ? $current : [$current], $incoming),
				SORT_STRING
			));
			$this->store[$element][$key] = $merged;
		} else {
			// Non-multi keys: take the last scalar
			$this->store[$element][$key] = (string)end($incoming);
		}

		return $this;
	}

	private function setSingleAttribute(string $element, string $key, $value): void
	{
		$key = $this->sanitizeAttrName($key);
		if ($key === '') {
			return;
		}

		if (!isset($this->store[$element])) {
			$this->store[$element] = [];
		}

		if ($value === null) {
			// null means boolean attribute enabled
			$this->store[$element][$key] = true;
			return;
		}

		if ($this->isSingleton($key)) {
			// id, etc. -> keep the last provided value
			$vals = is_array($value) ? $this->normalizeValues($value) : $this->normalizeValues([(string)$value]);
			$this->store[$element][$key] = (string)end($vals);
			return;
		}

		if ($this->isMultiValue($key)) {
			$vals = is_array($value) ? $this->normalizeValues($value) : $this->normalizeValues([$value]);
			// De-duplicate
			$this->store[$element][$key] = array_values(array_unique($vals, SORT_STRING));
			return;
		}

		// Scalar
		$this->store[$element][$key] = (string)$value;
	}

	private function isSingleton(string $key): bool
	{
		return in_array(strtolower($key), self::SINGLETON_KEYS, true);
	}

	private function isMultiValue(string $key): bool
	{
		return in_array(strtolower($key), self::MULTI_VALUE_KEYS, true);
	}

	private function sanitizeAttrName(string $name): string
	{
		$name = trim($name);
		if ($name === '') {
			return '';
		}
		// strip invalid characters while preserving valid ones
		return preg_replace(self::ATTR_NAME_ALLOWED, '', $name) ?? '';
	}

	/**
	 * Normalize a list of values to trimmed strings.
	 *
	 * @param array<int, mixed> $values
	 * @return array<int, string>
	 */
	private function normalizeValues(array $values): array
	{
		$out = [];
		foreach ($values as $v) {
			if ($v === null || $v === false) {
				// skip null/false (only true is meaningful as boolean attribute)
				continue;
			}
			if (is_bool($v)) {
				$out[] = $v ? 'true' : 'false';
			} else {
				$s = trim((string)$v);
				if ($s !== '') {
					$out[] = $s;
				}
			}
		}
		return $out;
	}

	/**
	 * Safe attribute escaping (WordPress-like esc_attr).
	 */
	public static function esc_attr(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);
	}
}
