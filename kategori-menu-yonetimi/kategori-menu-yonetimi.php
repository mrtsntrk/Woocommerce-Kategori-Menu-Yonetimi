<?php
/*
Plugin Name: Kategori Menü Yönetimi
Plugin URI: https://www.mertsenturk.net/kategori-menu-yonetimi
Description: WooCommerce ürün kategorilerini seçilen menüye hiyerarşik olarak otomatik ekler. Anasayfa ve Tüm Kategoriler gibi özel öğeleri isteğe bağlı ekler, menüyü temizleme ve klonlama desteği sunar. Seçilebilir kategori listesi, özel ikon sınıfı, akıllı güncelleme, manuel kategori sıralama ve zamanlayıcıyla otomatik menü güncellemesi desteği içerir.
Version: 2.2
Author: Mertinko
Author URI: https://www.mertsenturk.net
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: kategori-menu-yonetimi
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page('Mertinko Plugin', 'Mertinko Plugin', 'manage_options', 'mertinko_plugins', function() {
        echo '<div class="wrap">';
        echo '<h1 style="font-size: 26px; margin-bottom: 10px;">📦 Mertinko Plugin Paneli</h1>';
        echo '<p style="font-size: 15px; max-width: 600px;">Bu eklenti WordPress sitenizde WooCommerce ürün kategorilerini otomatik olarak menüye eklemenize olanak tanır. Soldaki alt menüden bir işlem seçin.</p>';
        echo '<hr style="margin:20px 0">';
        echo '<p style="font-size: 13px; color: #666;">Geliştirici: <strong>Mertinko</strong><br>
              Web Sitesi: <a href="https://www.mertsenturk.net" target="_blank">www.mertsenturk.net</a><br>
              E-Posta: <a href="mailto:iletisim@mertsenturk.net">iletisim@mertsenturk.net</a></p>';
        echo '</div>';
    }, 'dashicons-admin-generic', 30);

    add_submenu_page('mertinko_plugins', 'Kategori Menü Yönetimi', 'Kategori Menü Yönetimi', 'manage_options', 'kategori-menu-yonetimi', 'kmy_admin_page');
});

function kmy_admin_page() {
    if (!current_user_can('manage_options')) return;

    $menuler = wp_get_nav_menus();
    $kategori_sayisi = wp_count_terms('product_cat', ['hide_empty' => false]);
    $siralar = get_option('kmy_kategori_sirasi', []);
    $kaldirilanlar = get_option('kmy_kaldirilan_kategoriler', []);

    echo '<div class="wrap">';
    echo '<h1 style="margin-bottom: 20px; font-size: 24px;">🧩 Kategori Menü Yönetimi</h1>';
    echo '<form method="post">';

    echo '<div class="postbox" style="padding:20px; margin:20px 0; border-left: 4px solid #007cba; background:#fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    echo '<h2 class="hndle">🎯 Menü Seçimi</h2>';
    echo '<select name="menu_secimi"><option value="">-- Menü Seçin --</option>';
    foreach ($menuler as $menu) {
        echo '<option value="' . esc_attr($menu->slug) . '">' . esc_html($menu->name) . '</option>';
    }
    echo '</select>';
    echo '<p>Toplam kategori sayısı: <strong>' . intval($kategori_sayisi) . '</strong></p>';
    echo '</div>';

    echo '<div class="postbox" style="padding:15px; margin:20px 0;">
        <h2 class="hndle">⚙️ İşlemler</h2>
        <input type="submit" name="tum_kategorileri_ekle" class="button button-primary" value="Ürün Kategorilerinin Tamamını Ekle">
        <input type="submit" name="tumunu_sil" class="button button-secondary" value="Tümünü Sil">
        <input type="submit" name="tumunu_ekle" class="button button-primary" value="Tümünü Ekle">
        <input type="submit" name="sadece_kategoriler" class="button" value="Sadece Kategoriler">
        <input type="submit" name="sadece_tum" class="button" value="Sadece Tüm Kategoriler">
    </div>';

    echo '<div class="postbox" style="padding:15px; margin:20px 0;">
        <h2 class="hndle">🖼️ Kategori Seçimi + İkon</h2>';
    $kategoriler = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach ($kategoriler as $kat) {
        echo '<div style="margin-bottom:8px;">
            <label><input type="checkbox" name="secili_kategoriler[]" value="' . $kat->term_id . '"> ' . esc_html($kat->name) . '</label>
            <input type="text" name="ikon[' . $kat->term_id . ']" placeholder="CSS sınıfı" style="margin-left:10px;">
        </div>';
    }
    echo '<input type="submit" name="ekle_secili" class="button button-primary" value="Seçilenleri Ekle">';
    echo '</div>';

    echo '<div class="postbox" style="padding:15px; margin:20px 0;">
        <h2 class="hndle">📊 Kategori Sıralama ve Kaldırma</h2>';
    kmy_kategori_agaci_olustur(0, 0, $siralar, $kaldirilanlar);
    echo '<input type="submit" name="sirayi_kaydet" class="button button-primary" value="Değişiklikleri Kaydet">';
    echo '</div>';

    echo '</form>';
    echo '</div>';
}

function kmy_kategori_agaci_olustur($parent = 0, $seviye = 0, $siralar = [], $kaldirilanlar = []) {
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent]);
    foreach ($terms as $term) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $seviye);
        $checked = in_array($term->term_id, $kaldirilanlar) ? 'checked' : '';
        $sira = isset($siralar[$term->term_id]) ? intval($siralar[$term->term_id]) : '';
        echo '<div style="margin-left:' . ($seviye * 20) . 'px; margin-bottom:5px; padding:6px; background:#f9f9f9; border-left:2px solid #ddd;">'
           . $indent . esc_html($term->name) .
           ' <input type="number" name="sira[' . $term->term_id . ']" value="' . esc_attr($sira) . '" style="width:60px;">
             <label style="color:red; margin-left:10px;"><input type="checkbox" name="kaldir[' . $term->term_id . ']" value="1" ' . $checked . '> ❌</label></div>';
        kmy_kategori_agaci_olustur($term->term_id, $seviye + 1, $siralar, $kaldirilanlar);
    }
}
