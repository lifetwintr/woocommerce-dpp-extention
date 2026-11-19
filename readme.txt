=== LifeTwin DPP for WooCommerce ===
Contributors: lifetwin
Tags: dpp, digital product passport, sustainability, woocommerce, espr
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 10.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce ürünlerini LifeTwin'e gönderir ve DPP pasaport bağlantısını ürüne ekler.

== Description ==

Bu eklenti, WooCommerce ürünlerini LifeTwin Connector API'si üzerinden
Dijital Ürün Pasaportu (DPP) sistemine bağlar.

Özellikler:

* API anahtarı ile güvenli sunucu taraflı bağlantı
* Ürün kaydedildiğinde otomatik senkron (Action Scheduler / WP-Cron)
* Manuel "Şimdi gönder" aksiyonu
* Ürün panelinde DPP durumu ve pasaport bağlantısı
* Hassas verilerin loglanmaması
* API secret'ının AES-256-GCM ile şifrelenmesi
* Son 20 senkron kaydı

Eklenti, LifeTwin'in `/api/v1/products/upsert` uç noktasını kullanır.
WooCommerce ürün SKU'su zorunludur.

== Installation ==

1. Eklenti klasörünü `wp-content/plugins/lifetwin-woocommerce` altına yükleyin.
2. WordPress panelinde Eklentiler > Yüklü Eklentiler üzerinden etkinleştirin.
3. WooCommerce > LifeTwin DPP menüsüne gidin.
4. LifeTwin panelinden aldığınız `lt_` API anahtarını, secret'ı ve şirket ID'sini girin.
5. "Bağlantıyı test et" butonuna basın.
6. Bir ürünü kaydedin veya ürün panelinden "Şimdi gönder" deyin.

== Frequently Asked Questions ==

= API secret nerede saklanır? =

Secret, WordPress tuzlarından türetilen anahtarla AES-256-GCM kullanılarak
şifrelenir ve ayrı bir seçenekte tutulur. Ekranda yalnız son dört karakter
gösterilir.

= Ürün SKU'su boşsa ne olur? =

Senkron yapılmaz ve ürün panelinde hata gösterilir. LifeTwin ürün kimliği
için SKU zorunludur.

= GTIN yoksa ne olur? =

Ürün yine gönderilir; ancak DPP kimlik alanı boş kalır. Ürün panelinde
pasaport bağlantısı oluşmaz. GTIN ekledikten sonra tekrar gönderin.

= Ürün silinirse LifeTwin'den de silinir mi? =

Hayır. Eklenti ürünü arşivler; LifeTwin kaydı korunur. Silme işlemi
LifeTwin panelinden yapılır.

= Varyantlar destekleniyor mu? =

İlk sürümde ana ürün gönderilir. Varyantlar ayrı ürün olarak
gönderilmez; bu yol haritasındadır.

== Screenshots ==

1. Bağlantı ayarları ve durum kartı.
2. Ürün düzenleme ekranındaki LifeTwin paneli.
3. Son senkron kayıtları.

== Changelog ==

= 1.1.0 =
* Varyasyonlar ayrı ürün olarak `external_parent_ref` ile gönderilir.
* `GET /companies` ile şirket listesi bağlantı testinde okunur ve ayarlarda seçilir.
* `dpp.published` webhook alıcısı; imza doğrulama ve tekrar koruması.
* Ayar ekranından ve ürün listesinden toplu senkron.

= 1.0.0 =
* İlk sürüm: ayarlar, bağlantı testi, ürün upsert, DPP linki, kuyruk ve log.

== Upgrade Notice ==

= 1.1.0 =
Varyant gönderimi, webhook alıcısı ve toplu senkron eklendi.

= 1.0.0 =
İlk sürüm.
