<?php
declare(strict_types=1);

use Cyberwani\AttributeManager\AttributeManager;

/**
 * Always return a NEW AttributeManager instance.
 * Each call has an isolated attribute store (no leakage between shortcodes).
 *
 * Optionally pass your own identifier for debugging:
 *   $am = attribute_manager('shortcode-1');
 */
if (!function_exists('attribute_manager')) {
    function attribute_manager(?string $id = null): AttributeManager
    {
        return new AttributeManager($id);
    }
}
