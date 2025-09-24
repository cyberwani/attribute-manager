<?php
declare(strict_types=1);

use Cyberwani\AttributeManager\AttributeManager;

/**
 * Retrieve the singleton AttributeManager instance anywhere in your code.
 *
 * Example:
 *   $am = attribute_manager();
 *   $am->add_render_attribute('wrapper', 'class', ['a', 'b']);
 *   echo '<div ' . $am->get_render_attribute_string('wrapper') . '></div>';
 */
if (!function_exists('attribute_manager')) {
    function attribute_manager(): AttributeManager
    {
        return AttributeManager::instance();
    }
}
