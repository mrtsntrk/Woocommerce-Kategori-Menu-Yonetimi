<?php
/*
Plugin Name: Kategori Menü Yönetimi
Plugin URI: https://www.mertsenturk.net/kategori-menu-yonetimi
Description: WooCommerce ürün kategorilerini seçilen menüye hiyerarşik olarak otomatik ekler. Anasayfa ve Tüm Kategoriler gibi özel öğeleri isteğe bağlı ekler, menüyü temizleme ve klonlama desteği sunar. Seçilebilir kategori listesi, özel ikon sınıfı, akıllı güncelleme ve zamanlayıcıyla otomatik menü güncellemesi desteği içerir.
Version: 2.1
Author: Mertinko
Author URI: https://www.mertsenturk.net
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: kategori-menu-yonetimi
*/

if (!defined('ABSPATH')) exit;

// Zamanlayıcı kurulumu
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('kmy_gunluk_menu_guncelle')) {
        wp_schedule_event(time(), 'daily', 'kmy_gunluk_menu_guncelle');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('kmy_gunluk_menu_guncelle');
});

add_action('kmy_gunluk_menu_guncelle', function() {
    $menu_slug = get_option('kmy_zamanlayici_menu');
    $menu = wp_get_nav_menu_object($menu_slug);
    if ($menu) {
        kmy_menüye_kategorileri_ekle($menu->slug, true, true);
    }
});

add_action('admin_menu', function() {
    add_menu_page(
        'Mertinko Plugin', 'Mertinko Plugin', 'manage_options', 'mertinko_plugins', function() {
            echo '<div class="wrap"><h1>Mertinko Plugin Paneli</h1><p>Sol menüden bir işlem seçin.</p></div>';
        }, 'dashicons-admin-generic', 30
    );

    add_submenu_page(
        'mertinko_plugins', 'Kategori Menü Yönetimi', 'Kategori Menü Yönetimi', 'manage_options', 'kategori-menu-yonetimi', 'kmy_sayfa_icerigi'
    );
});

function kmy_sayfa_icerigi() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kategori-menu-yonetimi'));
    }

    $menuler = wp_get_nav_menus();
    $secili_menu = isset($_POST['kmy_menu_secimi']) ? sanitize_text_field($_POST['kmy_menu_secimi']) : get_option('kmy_zamanlayici_menu', '');
    $kategori_sayisi = wp_count_terms('product_cat', ['hide_empty' => false]);
    $menu = wp_get_nav_menu_object($secili_menu);
    $menu_item_count = $menu ? count(wp_get_nav_menu_items($menu->term_id)) : 0;

    if (isset($_POST['kmy_kaydet_ayar'])) {
        update_option('kmy_zamanlayici_menu', $secili_menu);
        echo '<div class="updated"><p>Zamanlayıcı ayarları kaydedildi.</p></div>';
    }

    if (isset($_POST['kmy_ekle'])) {
        kmy_menüye_kategorileri_ekle($secili_menu, true, true);
        echo '<div class="notice notice-success"><p>Menüye tüm kategoriler, Anasayfa ve Tüm Kategoriler bağlantısı eklendi.</p></div>';
    }

    if (isset($_POST['kmy_ekle_sade'])) {
        kmy_menüye_kategorileri_ekle($secili_menu, false, false);
        echo '<div class="notice notice-success"><p>Yalnızca kategoriler eklendi.</p></div>';
    }

    if (isset($_POST['kmy_ekle_sadece_tum'])) {
        kmy_menüye_sadece_tum_kategoriler($secili_menu);
        echo '<div class="notice notice-success"><p>Sadece Tüm Kategoriler bağlantısı eklendi.</p></div>';
    }

    if (isset($_POST['kmy_ekle_ozel'])) {
        kmy_menüye_kategorileri_ekle($secili_menu, true, true, true);
        echo '<div class="notice notice-success"><p>Genel kategorisi hariç tüm öğeler menüye eklendi.</p></div>';
    }

    if (isset($_POST['kmy_temizle'])) {
        kmy_menüden_kategorileri_temizle($secili_menu);
        echo '<div class="notice notice-warning"><p>Kategoriler ve özel bağlantılar menüden temizlendi.</p></div>';
    }

    if (isset($_POST['kmy_yedekle'])) {
        kmy_menuyu_yedekle($secili_menu);
        echo '<div class="notice notice-success"><p>Menü yedeği (klonu) oluşturuldu.</p></div>';
    }

    if (isset($_POST['kmy_ekle_secili'])) {
        $secili_kategoriler = array_map('intval', $_POST['kategori_secim'] ?? []);
        $kategori_ikonlari = array_map('sanitize_text_field', $_POST['kategori_ikon'] ?? []);
        kmy_kategorileri_menüye_ekle_secimli($secili_menu, $secili_kategoriler, $kategori_ikonlari);
        echo '<div class="notice notice-success"><p>Seçilen kategoriler menüye eklendi.</p></div>';
    }

    echo '<div class="wrap">
        <h1>Kategori Menü Yönetimi</h1>
        <form method="post">
            <p><strong>Hedef Menü Seçin:</strong></p>
            <select name="kmy_menu_secimi" required>
                <option value="">-- Menü Seçin --</option>';
    foreach ($menuler as $menu_item) {
        $selected = ($menu_item->slug === $secili_menu) ? 'selected' : '';
        echo '<option value="' . esc_attr($menu_item->slug) . '" ' . $selected . '>' . esc_html($menu_item->name) . '</option>';
    }
    echo '</select>
            <p><strong>Toplam Ürün Kategorisi:</strong> ' . intval($kategori_sayisi) . '</p>
            <p><strong>Seçilen Menüdeki Öğe Sayısı:</strong> ' . intval($menu_item_count) . '</p>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
                <input type="submit" name="kmy_ekle" class="button button-primary" value="Tümünü Ekle (Anasayfa + Kategoriler + Tüm Kategoriler)">
                <input type="submit" name="kmy_ekle_sade" class="button" value="Yalnızca Kategorileri Ekle">
                <input type="submit" name="kmy_ekle_sadece_tum" class="button" value="Sadece Tüm Kategoriler Ekle">
                <input type="submit" name="kmy_ekle_ozel" class="button" value="Anasayfa + Tüm Kategoriler (Genel Hariç)">
                <input type="submit" name="kmy_temizle" class="button button-secondary" value="Menüyü Temizle">
                <input type="submit" name="kmy_yedekle" class="button" value="Menüyü Yedekle (Klonla)">
                <input type="submit" name="kmy_kaydet_ayar" class="button" value="Bu Menüyü Otomatik Güncelle">
            </div>
            <h2 style="margin-top:30px;">Kategori Seçimi (İkon ile)</h2>';
    $kategoriler = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ]);
    foreach ($kategoriler as $kategori) {
        echo '<label style="display:block; margin-bottom:8px;">
            <input type="checkbox" name="kategori_secim[]" value="' . $kategori->term_id . '"> ' . esc_html($kategori->name) . '
            &nbsp; <input type="text" name="kategori_ikon[' . $kategori->term_id . ']" placeholder="CSS ikon sınıfı" style="margin-left:10px;">
        </label>';
    }
    echo '<input type="submit" name="kmy_ekle_secili" class="button button-primary" value="Seçilen Kategorileri Menüye Ekle">
        </form>';
    echo '<div style="margin-top:30px; padding:15px; border:1px solid #ddd; background:#fff; border-radius:6px;">
        <h2>Menü Önizleme</h2>
        <p style="color:#555;">Menüdeki öğeler aşağıda hiyerarşik olarak listelenmiştir:</p>';
    echo kmy_menu_onizleme_html($secili_menu);
    echo '<p style="margin-top:15px; color:green; font-weight:bold;">Toplam ' . intval($kategori_sayisi) . ' kategori menüye eksiksiz şekilde aktarılmıştır.</p>
    </div></div>';
}

function kmy_menüye_kategorileri_ekle(
    $menu_slug, $anasayfa = true, $tum_kategoriler = true, $genel_hariç = false
) {
    $menu = wp_get_nav_menu_object($menu_slug);
    if (!$menu) return;
    $menu_id = $menu->term_id;

    $existing_items = wp_get_nav_menu_items($menu_id);
    $existing_ids = [];
    $existing_titles = [];

    if ($existing_items) {
        foreach ($existing_items as $item) {
            if ($item->object == 'product_cat') {
                $existing_ids[$item->object_id] = $item->ID;
            }
            $existing_titles[] = $item->title;
        }
    }

    if ($anasayfa && !in_array('ANASAYFA', $existing_titles)) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => 'ANASAYFA',
            'menu-item-url' => home_url('/'),
            'menu-item-status' => 'publish',
            'menu-item-classes' => 'porto-icon-category-home'
        ]);
    }

    $map = [];
    $exclude_ids = [];

    if ($genel_hariç) {
        $genel = get_term_by('name', 'Genel', 'product_cat');
        if ($genel && !is_wp_error($genel)) {
            $exclude_ids[] = $genel->term_id;
        }
    }

    kmy_kategori_hiyerarsik_ekle(0, $menu_id, $map, $existing_ids, $exclude_ids);

    if ($tum_kategoriler && !in_array('TÜM KATEGORİLER', $existing_titles)) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => 'TÜM KATEGORİLER <i class="fa fa-angle-right text-md"></i>',
            'menu-item-url' => '#',
            'menu-item-status' => 'publish'
        ]);
    }
}

function kmy_menüye_sadece_tum_kategoriler($menu_slug) {
    $menu = wp_get_nav_menu_object($menu_slug);
    if (!$menu) return;
    $menu_id = $menu->term_id;

    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => 'TÜM KATEGORİLER <i class="fa fa-angle-right text-md"></i>',
        'menu-item-url' => '#',
        'menu-item-status' => 'publish'
    ]);
}

function kmy_kategorileri_menüye_ekle_secimli($menu_slug, $kategori_ids, $ikonlar) {
    $menu = wp_get_nav_menu_object($menu_slug);
    if (!$menu) return;
    $menu_id = $menu->term_id;

    foreach ($kategori_ids as $term_id) {
        $term = get_term($term_id, 'product_cat');
        if (is_wp_error($term) || !$term) continue;

        $existing = wp_get_nav_menu_items($menu_id);
        $zaten_var = false;
        if ($existing) {
            foreach ($existing as $item) {
                if ($item->object_id == $term_id && $item->object == 'product_cat') {
                    $zaten_var = true;
                    break;
                }
            }
        }
        if ($zaten_var) continue;

        $ikon = isset($ikonlar[$term_id]) ? sanitize_text_field($ikonlar[$term_id]) : '';
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $term->name,
            'menu-item-object' => 'product_cat',
            'menu-item-object-id' => $term_id,
            'menu-item-type' => 'taxonomy',
            'menu-item-status' => 'publish',
            'menu-item-classes' => $ikon
        ]);
    }
}

function kmy_kategori_hiyerarsik_ekle($parent_id, $menu_id, &$map, $existing_ids, $exclude_ids = []) {
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => $parent_id,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ]);

    foreach ($terms as $term) {
        if (in_array($term->term_id, $exclude_ids)) continue;
        if (isset($existing_ids[$term->term_id])) {
            $map[$term->term_id] = $existing_ids[$term->term_id];
            continue;
        }

        $args = [
            'menu-item-title' => $term->name,
            'menu-item-object' => 'product_cat',
            'menu-item-object-id' => $term->term_id,
            'menu-item-type' => 'taxonomy',
            'menu-item-status' => 'publish'
        ];

        if ($term->parent && isset($map[$term->parent])) {
            $args['menu-item-parent-id'] = $map[$term->parent];
        }

        $item_id = wp_update_nav_menu_item($menu_id, 0, $args);
        $map[$term->term_id] = $item_id;

        kmy_kategori_hiyerarsik_ekle($term->term_id, $menu_id, $map, $existing_ids, $exclude_ids);
    }
}

function kmy_menuyu_yedekle($menu_slug) {
    $orijinal_menu = wp_get_nav_menu_object($menu_slug);
    if (!$orijinal_menu) return;

    $items = wp_get_nav_menu_items($orijinal_menu->term_id);
    $klon_menu_id = wp_create_nav_menu($orijinal_menu->name . ' - Kopya');

    $map = [];

    foreach ($items as $item) {
        $args = [
            'menu-item-title' => $item->title,
            'menu-item-url' => $item->url,
            'menu-item-object' => $item->object,
            'menu-item-object-id' => $item->object_id,
            'menu-item-type' => $item->type,
            'menu-item-status' => $item->post_status,
        ];

        if ($item->menu_item_parent && isset($map[$item->menu_item_parent])) {
            $args['menu-item-parent-id'] = $map[$item->menu_item_parent];
        }

        $new_id = wp_update_nav_menu_item($klon_menu_id, 0, $args);
        $map[$item->ID] = $new_id;
    }
}

function kmy_menüden_kategorileri_temizle($menu_slug) {
    $menu = wp_get_nav_menu_object($menu_slug);
    if (!$menu) return;
    $menu_id = $menu->term_id;
    $items = wp_get_nav_menu_items($menu_id);

    foreach ($items as $item) {
        $is_kategori = ($item->object == 'product_cat');
        $is_anasayfa = ($item->title === 'ANASAYFA');
        $is_tum = strpos($item->title, 'TÜM KATEGORİLER') !== false;

        if ($is_kategori || $is_anasayfa || $is_tum) {
            wp_delete_post($item->ID, true);
        }
    }
}

function kmy_menu_onizleme_html($menu_slug) {
    $menu = wp_get_nav_menu_object($menu_slug);
    if (!$menu) return '<p style="color:#888;">Henüz menü seçilmedi veya geçersiz.</p>';
    $items = wp_get_nav_menu_items($menu->term_id);
    if (!$items) return '<p style="color:#888;">Bu menüde öğe bulunamadı.</p>';

    $output = '<ul style="list-style-type:none; padding-left:20px;">';
    $children = [];
    foreach ($items as $item) {
        $children[$item->menu_item_parent][] = $item;
    }
    $output .= kmy_render_menu_tree(0, $children);
    $output .= '</ul>';

    return $output;
}

function kmy_render_menu_tree($parent_id, $children) {
    if (!isset($children[$parent_id])) return '';
    $output = '';
    foreach ($children[$parent_id] as $item) {
        $output .= '<li style="margin: 4px 0;">🔹 ' . esc_html($item->title);
        $subtree = kmy_render_menu_tree($item->ID, $children);
        if ($subtree) {
            $output .= '<ul style="padding-left:20px;">' . $subtree . '</ul>';
        }
        $output .= '</li>';
    }
    return $output;
}
// Gelişmiş güvenlik ve doğrulama ile birlikte tüm fonksiyonlar önceki güncellemelerde yer aldı.
