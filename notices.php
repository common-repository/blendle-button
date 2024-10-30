<?php
require_once __DIR__ . '/blendle-button-sdk/vendor/autoload.php';

/**
 * Echos a notice in the Wordpress dashboard when
 * the plugin is active and no credentials are found
 */
function plugin_not_configured() {
  $is_active = is_plugin_active('blendle-button/blendle-button.php');
  if ($is_active) {
    $credentials = get_blendle_button_public_key();

    if (!$credentials) {
      $notice = '<div class="notice notice-warning is-dismissible">'.
                '<p>We noticed your <strong>Blendle Button</strong> credentials are missing, the button won\'t work without them. '.
                '<a href="'.admin_url('options-general.php?page=blendle_button').'">Add them now</a>?</p>'.
                '</div>';

      echo $notice;
    }
  }
}

add_action('admin_notices', 'plugin_not_configured');

function validate_credentials() {
  if (is_plugin_active('blendle-button/blendle-button.php')) {
    $credentials = get_blendle_button_public_key();
    $blendle_button_sdk = get_blendle_button_sdk();

    if (!$credentials) {
      return;
    }

    try {
      $valid = $blendle_button_sdk && $blendle_button_sdk->testCredentials();
    } catch (BlendleButton\InvalidCredentialsException $e) {
      $valid = false;
    }

    if (!$valid) {
      $notice = '<div class="notice notice-error is-dismissible">'.
                '<p>We noticed your <strong>Blendle Button</strong> Provider UID, Public Key or API Secret is incorrect, the button won\'t work without them.</p>'.
                '<p>Please ensure you\'ve exactly copied the ones provided in the <a href="http://portal.blendle.nl" target="_blank">Blendle Portal</a>, '.
                'or check the <a href="http://pay-docs.blendle.io/wordpress.html" target="_blank">Blendle Button WordPress plugin documentation</a> for help.</p>'.
                '</div>';

      echo $notice;
    }
  }
}

add_action('admin_notices', 'validate_credentials');
