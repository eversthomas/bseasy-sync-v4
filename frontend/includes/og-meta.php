<?php
/**
 * Open Graph / Social Sharing Meta-Tags.
 *
 * Gibt og:title, og:description, og:type und og:url aus,
 * wenn die aktuelle Seite den [bes_members]-Shortcode enthält.
 * Ermöglicht korrekte Link-Vorschauen in sozialen Netzwerken.
 *
 * @package BSEasySync
 * @since 4.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', 'bes_output_og_meta', 1);

/**
 * Gibt OG-Meta-Tags aus, wenn die Seite [bes_members] enthält.
 */
function bes_output_og_meta(): void
{
    if (!is_singular()) {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return;
    }

    if (!has_shortcode($post->post_content, 'bes_members')) {
        return;
    }

    // Bereits OG-Tags vorhanden (z. B. durch SEO-Plugin)? Dann nicht überschreiben.
    // Wir geben nur aus, wenn noch keine og:title gesetzt ist.
    // (Einfache Heuristik: wir geben immer aus, SEO-Plugins laufen meist mit höherer Priorität
    //  und überschreiben ggf. — oder umgekehrt, je nach Hook-Priorität.)

    $site_name   = get_bloginfo('name');
    $page_title  = get_the_title($post->ID);
    $og_title    = $page_title ? ($page_title . ' – ' . $site_name) : $site_name;
    $og_desc     = wp_strip_all_tags(get_bloginfo('description')) ?: __('Mitglieder-Verzeichnis', BES_TEXT_DOMAIN);
    $og_url      = get_permalink($post->ID);
    $og_type     = 'website';

    // Bild: erstes Mitgliederbild verwenden, falls vorhanden
    $og_image = bes_og_get_first_member_image();
    if (!$og_image && has_post_thumbnail($post->ID)) {
        $og_image = get_the_post_thumbnail_url($post->ID, 'large');
    }

    echo "\n<!-- Open Graph (BS Easy Sync) -->\n";
    echo '<meta property="og:type"        content="' . esc_attr($og_type)  . '">' . "\n";
    echo '<meta property="og:title"       content="' . esc_attr($og_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($og_desc)  . '">' . "\n";
    echo '<meta property="og:url"         content="' . esc_url($og_url)    . '">' . "\n";
    if ($og_image) {
        echo '<meta property="og:image"   content="' . esc_url($og_image)  . '">' . "\n";
    }
    echo '<meta property="og:site_name"   content="' . esc_attr($site_name) . '">' . "\n";
    echo "<!-- /Open Graph -->\n\n";
}

/**
 * Gibt die URL des ersten verfügbaren Mitgliederbilds zurück.
 *
 * @return string URL oder ''
 */
function bes_og_get_first_member_image(): string
{
    if (!function_exists('bes_members_file_exists') || !bes_members_file_exists()) {
        return '';
    }

    if (!defined('BES_IMG') || !defined('BES_UPLOADS_URL')) {
        return '';
    }

    $members = bes_members_get_all();
    foreach ($members as $member) {
        $id = $member['member.id'] ?? $member['member']['id'] ?? $member['id'] ?? null;
        if (!$id) continue;

        $img_path = BES_IMG . $id . '.png';
        if (file_exists($img_path)) {
            return BES_UPLOADS_URL . 'img/' . $id . '.png';
        }
    }
    return '';
}
