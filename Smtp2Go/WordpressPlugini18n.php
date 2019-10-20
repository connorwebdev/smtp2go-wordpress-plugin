<?php
namespace Smtp2Go;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://thefold.nz
 * @since      1.0.0
 *
 * @package    Smtp2go\WordpressPlugin

 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Smtp2go\WordpressPlugin
 * @author     The Fold <hello@thefold.co.nz>
 */
class WordpressPlugini18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadPluginTextdomain()
    {
        load_plugin_textdomain(
            'smtp2go-wordpress-plugin',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
