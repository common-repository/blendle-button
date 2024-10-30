<?php
require_once(__DIR__ . '/lib/helpers.php');

# Init Blendle Button button tinyMCE plugin
function init_blendle_tinymce_plugin() {
  if (current_user_can('edit_posts') && current_user_can('edit_pages')) {
    add_filter('mce_external_plugins', 'add_blendle_tinymce_button');
    add_filter('mce_buttons', 'register_blendle_tinymce_button', 0);
  }
}

# Register Blendle Button button with tinyMCE
function register_blendle_tinymce_button( $buttons ) {
  array_push($buttons, 'BlendleButton');
  return $buttons;
}

# Add Blendle Button button to tinyMCE
function add_blendle_tinymce_button( $plugin_array ) {
  $plugin_array['BlendleButton'] = plugins_url( '/js/pay_with_blendle-plugin.js', __FILE__ );
  return $plugin_array;
}

add_action( 'admin_init', 'init_blendle_tinymce_plugin' );
