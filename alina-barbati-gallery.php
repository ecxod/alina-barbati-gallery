<?php
/**
 * Plugin Name: Alina Barbati Gallery
 * Description: Fetch and render the public Barbati gallery from partnervermittlung-alina on any WordPress site.
 * Version: 1.0.0
 * Author: Christian Eichert 2026
 * Text Domain: alina-barbati-gallery
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) {
    exit;
}

const ABG_VERSION = '2026-06-06T18:05:00+02:00';
const ABG_OPTION_KEY = 'abg_settings';
const ABG_PAGE_OPTION_KEY = 'abg_page_id';
const ABG_DEFAULT_ENDPOINT = 'https://partnervermittlung-alina.de/wp-json/pv-partner-matching/v1/public/barbati';
const ABG_DEFAULT_CACHE_TTL = 300;

/**
 * Load the plugin text domain.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_load_textdomain(): void
{
    load_plugin_textdomain('alina-barbati-gallery', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'abg_load_textdomain');

/**
 * Return the default plugin settings.
 *
 * @return array<string,int|string>
 * @version 2026-06-06T18:05:00+02:00
 */
function abg_get_default_settings(): array
{
    return [
        'source_endpoint' => ABG_DEFAULT_ENDPOINT,
        'page_slug' => 'barbati',
        'menu_label' => 'Barbati',
        'accent_color' => '#926247',
        'card_radius' => 24,
        'card_gap' => 18,
        'columns_desktop' => 4,
        'columns_tablet' => 2,
        'columns_mobile' => 2,
        'cache_ttl' => ABG_DEFAULT_CACHE_TTL,
        'inject_menu' => 1,
        'custom_css' => '',
    ];
}

/**
 * Return the merged plugin settings.
 *
 * @return array<string,int|string>
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_get_settings(): array
{
    $settings = get_option(ABG_OPTION_KEY, []);

    return wp_parse_args(is_array($settings) ? $settings : [], abg_get_default_settings());
}

/**
 * Sanitize plugin settings before persistence.
 *
 * @param mixed $input Raw settings.
 * @return array<string,int|string>
 * @version 2026-06-06T18:05:00+02:00
 */
function abg_sanitize_settings($input): array
{
    $defaults = abg_get_default_settings();
    $input = is_array($input) ? $input : [];

    return [
        'source_endpoint' => esc_url_raw((string) ($input['source_endpoint'] ?? $defaults['source_endpoint'])),
        'page_slug' => sanitize_title((string) ($input['page_slug'] ?? $defaults['page_slug'])),
        'menu_label' => sanitize_text_field((string) ($input['menu_label'] ?? $defaults['menu_label'])),
        'accent_color' => sanitize_hex_color((string) ($input['accent_color'] ?? $defaults['accent_color'])) ?: (string) $defaults['accent_color'],
        'card_radius' => max(0, min(60, absint($input['card_radius'] ?? $defaults['card_radius']))),
        'card_gap' => max(0, min(48, absint($input['card_gap'] ?? $defaults['card_gap']))),
        'columns_desktop' => max(1, min(6, absint($input['columns_desktop'] ?? $defaults['columns_desktop']))),
        'columns_tablet' => max(1, min(4, absint($input['columns_tablet'] ?? $defaults['columns_tablet']))),
        'columns_mobile' => max(1, min(3, absint($input['columns_mobile'] ?? $defaults['columns_mobile']))),
        'cache_ttl' => max(60, min(DAY_IN_SECONDS, absint($input['cache_ttl'] ?? $defaults['cache_ttl']))),
        'inject_menu' => empty($input['inject_menu']) ? 0 : 1,
        'custom_css' => trim((string) sanitize_textarea_field((string) ($input['custom_css'] ?? ''))),
    ];
}

/**
 * Register the settings object used by the plugin settings page.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_register_settings(): void
{
    register_setting('abg_settings_group', ABG_OPTION_KEY, [
        'type' => 'array',
        'sanitize_callback' => 'abg_sanitize_settings',
        'default' => abg_get_default_settings(),
    ]);
}
add_action('admin_init', 'abg_register_settings');

/**
 * Ensure the Barbati landing page exists and stores its ID.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_ensure_page_exists(): int
{
    $settings = abg_get_settings();
    $stored_page_id = absint(get_option(ABG_PAGE_OPTION_KEY, 0));

    if ($stored_page_id > 0 && get_post_status($stored_page_id)) {
        return $stored_page_id;
    }

    $existing_page = get_page_by_path((string) $settings['page_slug']);
    if ($existing_page instanceof WP_Post) {
        update_option(ABG_PAGE_OPTION_KEY, (int) $existing_page->ID);

        return (int) $existing_page->ID;
    }

    $page_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => (string) $settings['menu_label'],
        'post_name' => (string) $settings['page_slug'],
        'post_content' => '[alina_barbati_gallery]',
    ], true);

    if ($page_id instanceof WP_Error) {
        if (function_exists('\Sentry\captureMessage')) {
            \Sentry\captureMessage('Barbati gallery page could not be created: ' . $page_id->get_error_message());
        }

        return 0;
    }

    update_option(ABG_PAGE_OPTION_KEY, (int) $page_id);

    return (int) $page_id;
}

/**
 * Activate the plugin and provision default content.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_activate_plugin(): void
{
    if (!get_option(ABG_OPTION_KEY)) {
        add_option(ABG_OPTION_KEY, abg_get_default_settings());
    }

    abg_ensure_page_exists();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'abg_activate_plugin');

/**
 * Flush rewrite rules on deactivation.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_deactivate_plugin(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'abg_deactivate_plugin');

/**
 * Register the public frontend stylesheet.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_register_assets(): void
{
    wp_register_style(
        'abg-gallery',
        plugin_dir_url(__FILE__) . 'assets/alina-barbati-gallery.css',
        [],
        file_exists(__DIR__ . '/assets/alina-barbati-gallery.css') ? (string) filemtime(__DIR__ . '/assets/alina-barbati-gallery.css') : ABG_VERSION
    );
}
add_action('wp_enqueue_scripts', 'abg_register_assets');
add_action('enqueue_block_assets', 'abg_register_assets');

/**
 * Register the Gutenberg block used for page embedding.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_register_block(): void
{
    wp_register_script(
        'abg-block-editor',
        plugin_dir_url(__FILE__) . 'assets/alina-barbati-gallery-block.js',
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render'],
        file_exists(__DIR__ . '/assets/alina-barbati-gallery-block.js') ? (string) filemtime(__DIR__ . '/assets/alina-barbati-gallery-block.js') : ABG_VERSION,
        true
    );

    register_block_type(__DIR__ . '/blocks/barbati-gallery', [
        'editor_script' => 'abg-block-editor',
        'render_callback' => 'abg_render_gallery_block',
    ]);
}
add_action('init', 'abg_register_block');

/**
 * Add the settings page below the native Settings menu.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_register_admin_menu(): void
{
    add_options_page(
        __('Barbati Gallery', 'alina-barbati-gallery'),
        __('Barbati Gallery', 'alina-barbati-gallery'),
        'manage_options',
        'alina-barbati-gallery',
        'abg_render_settings_page'
    );
}
add_action('admin_menu', 'abg_register_admin_menu');

/**
 * Add a shortcut to the settings page on the plugin list.
 *
 * @param array<int,string> $links Existing links.
 * @return array<int,string>
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_plugin_action_links(array $links): array
{
    array_unshift(
        $links,
        '<a href="' . esc_url(admin_url('options-general.php?page=alina-barbati-gallery')) . '">'
        . esc_html__('Settings', 'alina-barbati-gallery')
        . '</a>'
    );

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'abg_plugin_action_links');

/**
 * Render the plugin settings page.
 *
 * @version 2026-06-06T18:05:00+02:00
 */
function abg_render_settings_page(): void
{
    $settings = abg_get_settings();
    ?>
    <div class="wrap" id="abg-settings-page" data-id="abg-settings-page">
        <h1><?php echo esc_html__('Barbati Gallery Settings', 'alina-barbati-gallery'); ?></h1>
        <p><?php echo esc_html__('Use the shortcode [alina_barbati_gallery] or the Barbati Gallery block to place the module on any page.', 'alina-barbati-gallery'); ?></p>

        <form id="abg-settings-form" data-id="abg-settings-form" method="post" action="options.php">
            <?php settings_fields('abg_settings_group'); ?>
        </form>

        <table class="form-table" id="abg-settings-table" data-id="abg-settings-table">
            <tr>
                <th scope="row">
                    <label for="abg-source-endpoint"><?php echo esc_html__('Source endpoint', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="url" class="regular-text" id="abg-source-endpoint" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[source_endpoint]" value="<?php echo esc_attr((string) $settings['source_endpoint']); ?>" form="abg-settings-form">
                    <p class="description"><?php echo esc_html__('Anonymous JSON feed that exposes the approved Barbati photos.', 'alina-barbati-gallery'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-page-slug"><?php echo esc_html__('Page slug', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="abg-page-slug" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[page_slug]" value="<?php echo esc_attr((string) $settings['page_slug']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-menu-label"><?php echo esc_html__('Menu label', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="abg-menu-label" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[menu_label]" value="<?php echo esc_attr((string) $settings['menu_label']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-accent-color"><?php echo esc_html__('Accent color', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="abg-accent-color" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr((string) $settings['accent_color']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-card-radius"><?php echo esc_html__('Card radius', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-card-radius" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[card_radius]" min="0" max="60" value="<?php echo esc_attr((string) $settings['card_radius']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-card-gap"><?php echo esc_html__('Card gap', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-card-gap" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[card_gap]" min="0" max="48" value="<?php echo esc_attr((string) $settings['card_gap']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-columns-mobile"><?php echo esc_html__('Mobile columns', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-columns-mobile" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[columns_mobile]" min="1" max="3" value="<?php echo esc_attr((string) $settings['columns_mobile']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-columns-tablet"><?php echo esc_html__('Tablet columns', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-columns-tablet" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[columns_tablet]" min="1" max="4" value="<?php echo esc_attr((string) $settings['columns_tablet']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-columns-desktop"><?php echo esc_html__('Desktop columns', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-columns-desktop" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[columns_desktop]" min="1" max="6" value="<?php echo esc_attr((string) $settings['columns_desktop']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-cache-ttl"><?php echo esc_html__('Cache lifetime in seconds', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <input type="number" id="abg-cache-ttl" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[cache_ttl]" min="60" step="60" value="<?php echo esc_attr((string) $settings['cache_ttl']); ?>" form="abg-settings-form">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-inject-menu"><?php echo esc_html__('Inject top menu link', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <label for="abg-inject-menu">
                        <input type="checkbox" id="abg-inject-menu" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[inject_menu]" value="1" <?php checked(!empty($settings['inject_menu'])); ?> form="abg-settings-form">
                        <?php echo esc_html__('Append the Barbati page link to classic and block navigation output when possible.', 'alina-barbati-gallery'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="abg-custom-css"><?php echo esc_html__('Custom CSS', 'alina-barbati-gallery'); ?></label>
                </th>
                <td>
                    <textarea class="large-text code" rows="10" id="abg-custom-css" name="<?php echo esc_attr(ABG_OPTION_KEY); ?>[custom_css]" form="abg-settings-form"><?php echo esc_textarea((string) $settings['custom_css']); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'alina-barbati-gallery'), 'primary', 'submit', true, ['form' => 'abg-settings-form']); ?>
    </div>
    <?php
}

/**
 * Build a transient key for one remote source URL and limit.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_get_cache_key(string $endpoint, int $limit): string
{
    return 'abg_gallery_' . md5($endpoint . '|' . $limit);
}

/**
 * Fetch gallery items from the anonymous remote endpoint.
 *
 * @return array<int,array<string,mixed>>
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_fetch_gallery_items(string $endpoint, int $limit): array
{
    $settings = abg_get_settings();
    $cache_key = abg_get_cache_key($endpoint, $limit);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $response = wp_remote_get($endpoint, [
        'timeout' => 12,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        if (function_exists('\Sentry\captureMessage')) {
            \Sentry\captureMessage('Barbati gallery fetch failed: ' . $response->get_error_message());
        }

        return [];
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $payload = json_decode((string) $body, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($payload)) {
        if (function_exists('\Sentry\captureMessage')) {
            \Sentry\captureMessage('Barbati gallery endpoint returned invalid payload. HTTP ' . $status_code . ': ' . substr((string) $body, 0, 500));
        }

        return [];
    }

    $items = isset($payload['items']) && is_array($payload['items']) ? array_values($payload['items']) : [];
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    set_transient($cache_key, $items, max(60, (int) $settings['cache_ttl']));

    return $items;
}

/**
 * Render one gallery module instance.
 *
 * @param array<string,mixed> $attributes Shortcode or block attributes.
 * @return string
 * @version 2026-06-06T18:05:00+02:00
 */
function abg_render_gallery(array $attributes = []): string
{
    $settings = abg_get_settings();
    $endpoint = isset($attributes['source_endpoint']) && is_string($attributes['source_endpoint']) && $attributes['source_endpoint'] !== ''
        ? esc_url_raw($attributes['source_endpoint'])
        : (string) $settings['source_endpoint'];
    $limit = isset($attributes['limit']) ? max(0, absint($attributes['limit'])) : 0;
    $columns_desktop = isset($attributes['columns_desktop']) ? max(1, min(6, absint($attributes['columns_desktop']))) : (int) $settings['columns_desktop'];
    $columns_tablet = isset($attributes['columns_tablet']) ? max(1, min(4, absint($attributes['columns_tablet']))) : (int) $settings['columns_tablet'];
    $columns_mobile = isset($attributes['columns_mobile']) ? max(1, min(3, absint($attributes['columns_mobile']))) : (int) $settings['columns_mobile'];
    $items = $endpoint !== '' ? abg_fetch_gallery_items($endpoint, $limit) : [];

    wp_enqueue_style('abg-gallery');

    $custom_css = trim((string) $settings['custom_css']);
    if ($custom_css !== '') {
        wp_add_inline_style('abg-gallery', $custom_css);
    }

    $wrapper_style = sprintf(
        '--abg-accent:%1$s;--abg-gap:%2$spx;--abg-radius:%3$spx;--abg-columns-desktop:%4$s;--abg-columns-tablet:%5$s;--abg-columns-mobile:%6$s;',
        esc_attr((string) $settings['accent_color']),
        esc_attr((string) $settings['card_gap']),
        esc_attr((string) $settings['card_radius']),
        esc_attr((string) $columns_desktop),
        esc_attr((string) $columns_tablet),
        esc_attr((string) $columns_mobile)
    );

    ob_start();
    ?>
    <section class="abg-gallery-shell" id="abg-gallery-shell" data-id="abg-gallery-shell" style="<?php echo esc_attr($wrapper_style); ?>">
        <?php if (empty($items)) : ?>
            <div class="abg-gallery-empty" id="abg-gallery-empty" data-id="abg-gallery-empty">
                <p><?php echo esc_html__('No Barbati photos are available right now.', 'alina-barbati-gallery'); ?></p>
            </div>
        <?php else : ?>
            <div class="abg-gallery-grid" id="abg-gallery-grid" data-id="abg-gallery-grid">
                <?php foreach ($items as $index => $item) : ?>
                    <article class="abg-gallery-card" id="abg-gallery-card-<?php echo esc_attr((string) $index); ?>" data-id="abg-gallery-card">
                        <a class="abg-gallery-link" href="<?php echo esc_url((string) ($item['fullUrl'] ?? $item['photoUrl'] ?? '')); ?>" target="_blank" rel="noopener">
                            <img
                                class="abg-gallery-image"
                                src="<?php echo esc_url((string) ($item['photoUrl'] ?? '')); ?>"
                                alt="<?php echo esc_attr__('Barbati gallery photo', 'alina-barbati-gallery'); ?>"
                                loading="lazy"
                                width="<?php echo esc_attr((string) ((int) ($item['photoWidth'] ?? 0) > 0 ? (int) $item['photoWidth'] : 900)); ?>"
                                height="<?php echo esc_attr((string) ((int) ($item['photoHeight'] ?? 0) > 0 ? (int) $item['photoHeight'] : 1200)); ?>"
                            >
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

/**
 * Render the gallery shortcode.
 *
 * @param array<string,mixed> $atts Shortcode attributes.
 * @return string
 * @version 2026-06-06T18:05:00+02:00
 */
function abg_render_gallery_shortcode(array $atts = []): string
{
    $attributes = shortcode_atts([
        'limit' => 0,
        'columns_desktop' => '',
        'columns_tablet' => '',
        'columns_mobile' => '',
        'source_endpoint' => '',
    ], $atts, 'alina_barbati_gallery');

    return abg_render_gallery($attributes);
}
add_shortcode('alina_barbati_gallery', 'abg_render_gallery_shortcode');

/**
 * Render the Gutenberg block instance.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return string
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_render_gallery_block(array $attributes = []): string
{
    return abg_render_gallery($attributes);
}

/**
 * Return the Barbati page URL.
 *
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_get_page_url(): string
{
    $page_id = abg_ensure_page_exists();

    return $page_id > 0 ? (string) get_permalink($page_id) : '';
}

/**
 * Append the Barbati link to classic navigation menus when enabled.
 *
 * @param string $items Existing HTML.
 * @param stdClass $args Menu arguments.
 * @return string
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_inject_classic_menu_item(string $items, $args): string
{
    if (is_admin()) {
        return $items;
    }

    $settings = abg_get_settings();
    if (empty($settings['inject_menu'])) {
        return $items;
    }

    $page_url = abg_get_page_url();
    if ($page_url === '' || strpos($items, $page_url) !== false) {
        return $items;
    }

    $theme_location = is_object($args) && isset($args->theme_location) ? (string) $args->theme_location : '';
    if ($theme_location === '' || !in_array($theme_location, ['primary', 'menu-1', 'header', 'top'], true)) {
        return $items;
    }

    $items .= sprintf(
        '<li class="menu-item abg-menu-item"><a href="%1$s">%2$s</a></li>',
        esc_url($page_url),
        esc_html((string) $settings['menu_label'])
    );

    return $items;
}
add_filter('wp_nav_menu_items', 'abg_inject_classic_menu_item', 20, 2);

/**
 * Append the Barbati link to block theme navigation markup when enabled.
 *
 * @param string $block_content Rendered HTML.
 * @param array<string,mixed> $block Parsed block data.
 * @return string
 * @version 2026-06-06T16:35:00+02:00
 */
function abg_inject_block_navigation_item(string $block_content, array $block): string
{
    if (is_admin() || ($block['blockName'] ?? '') !== 'core/navigation') {
        return $block_content;
    }

    $settings = abg_get_settings();
    if (empty($settings['inject_menu'])) {
        return $block_content;
    }

    $page_url = abg_get_page_url();
    if ($page_url === '' || strpos($block_content, $page_url) !== false || strpos($block_content, 'abg-menu-item') !== false) {
        return $block_content;
    }

    $item_markup = sprintf(
        '<li class="wp-block-navigation-item abg-menu-item"><a class="wp-block-navigation-item__content" href="%1$s"><span class="wp-block-navigation-item__label">%2$s</span></a></li>',
        esc_url($page_url),
        esc_html((string) $settings['menu_label'])
    );

    return preg_replace('/<\/ul>/', $item_markup . '</ul>', $block_content, 1) ?: $block_content;
}
add_filter('render_block', 'abg_inject_block_navigation_item', 20, 2);
