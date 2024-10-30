<?php
require_once __DIR__ . '/../blendle-button-sdk/src/BlendleButton/client.php';

$blendle_button_sdk = false;

function get_blendle_button_sdk() {
  global $blendle_button_sdk;

  if ($blendle_button_sdk) {
    return $blendle_button_sdk;
  } else {
    try {
      $blendle_button_sdk = new BlendleButton\Client(
        get_blendle_button_provider_uid(),
        get_blendle_button_api_secret(),
        get_blendle_button_public_key(),
        array('production' => blendle_button_use_production())
      );

      return $blendle_button_sdk;
    } catch (Exception $e) {
      return false;
    }
  }
}

function blendle_button_get_supported_locales () {
  return array(
    array('locale'=>'de_DE', 'title'=>'German (Deutsch)', 'default'=> false),
    array('locale'=>'nl_NL', 'title'=>'Dutch (Nederlands)', 'default'=> true),
    array('locale'=>'en_US', 'title'=>'English', 'default'=> false)
  );
}

function blendle_button_filter_default_locale ($value) {
  return $value['default'] == 1;
}

function blendle_button_filter_wordpress_locale ($value) {
  return $value['locale'] == get_locale();
}

function blendle_button_filter_current_locale ($value) {
  return $value['locale'] == get_option('blendle_button_locale');
}

function get_blendle_button_locale () {
  $supported_locales = blendle_button_get_supported_locales();

  $_current_value = array_filter($supported_locales, 'blendle_button_filter_current_locale');
  $current_value = reset($_current_value);

  $_wordpress_locale = array_filter($supported_locales, 'blendle_button_filter_wordpress_locale');
  $wordpress_locale = reset($_wordpress_locale);

  $_default_locale = array_filter($supported_locales, 'blendle_button_filter_default_locale');
  $default_locale = reset($_default_locale);

  if ($current_value) {
    return $current_value;
  }

  if ($wordpress_locale) {
    return $wordpress_locale;
  }

  return $default_locale;
}

function get_blendle_button_provider_uid() {
  return get_option('blendle_button_provider_uid');
}

function get_blendle_button_type_with_fallback() {
  return get_option('blendle_button_provider_button_type', 'item');
}

# Generated a BlendleButton post ID for use with the Blendle Button Javascript SDK
function get_blendle_button_post_div_id($post) {
  return 'blendle-button-item-' . $post->ID;
}

function blendle_button_use_production() {
  return get_option('blendle_button_provider_use_production', false);
}

function get_blendle_button_public_key() {
  if (blendle_button_use_production()) {
    return get_option('blendle_button_provider_production_key');
  }

  return get_option('blendle_button_provider_staging_key');
}

function get_blendle_button_api_secret() {
  if (blendle_button_use_production()) {
    return get_option('blendle_button_provider_production_token');
  }

  return get_option('blendle_button_provider_staging_token');
}

# Check if the Blendle Button is enabled in the Wordpress post meta API
function blendle_button_enabled($post) {
  return has_shortcode($post->post_content, 'blendlebutton');
}

# Generate a JWT that is used by the Blendle Button Javascript SDK to register the item
function blendle_button_generate_item_jwt($post) {
  $blendle_button_sdk = get_blendle_button_sdk();

  if (!$blendle_button_sdk) {
    return;
  }

  return $blendle_button_sdk->generateItemToken($post->ID, array(
    'title' => get_the_title($post->ID),
    'description' => blendle_button_get_excerpt($post->ID),
    'words' => str_word_count(strip_tags($post->post_content)),
    'url' => get_permalink($post->ID)
  ));
}

function blendle_button_get_excerpt($post_id) {
  return apply_filters('the_excerpt', get_post_field('post_excerpt', $post_id));
}

function blendle_button_post_is_acquired($post) {
  try {
    $blendle_button_sdk = get_blendle_button_sdk();

    if(!blendle_button_enabled($post)) {
      return true;
    }

    $token = isset($_SERVER['HTTP_X_PWB_TOKEN']) ? $_SERVER['HTTP_X_PWB_TOKEN'] : null;

    if (!$token) {
      return false;
    }

    return (
      $blendle_button_sdk->isItemAcquired($post->ID, $token) ||
      $blendle_button_sdk->isActiveSubscription($token)
    );
  } catch (Exception $e) {
    return false;
  }
}
