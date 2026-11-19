# LifeTwin DPP for WooCommerce

WooCommerce ürünlerini LifeTwin Connector v1 API'sine bağlayan WordPress
eklentisi.

## Kapsam (v1.1.0)

- Sunucu taraflı `lt_` API anahtarı + secret ile bağlantı.
- AES-256-GCM ile şifrelenmiş API secret ve webhook secret.
- Bağlantı testi (`/auth/token` + `/account/capabilities`).
- `GET /companies` ile şirket listesi; ayarlarda açılır liste, tek şirket otomatik seçilir.
- Ürün oluşturma/güncelleme (`POST /products/upsert`).
- Varyasyonlar ayrı ürün olarak `external_parent_ref` ile gönderilir.
- Action Scheduler (varsa) veya WP-Cron kuyruğu.
- Manuel "Şimdi gönder", ürün listesinden toplu işlem ve ayardan tüm ürünleri gönderme.
- Ürün panelinde durum, pasaport linki ve hata.
- `dpp.published` webhook alıcısı (`/wp-json/lifetwin/v1/webhook`).
- Son 20 senkron kaydı.

## Kapsam dışı (sonraki sürümler)

- Gecelik uzlaştırma.
- WPML/Polylang çoklu dil alanları.
- Görsel aktarımı.
- Sipariş/müşteri senkronu (DPP ürün odaklıdır).

## Gereksinimler

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.1+
- OpenSSL AES-256-GCM desteği
- LifeTwin'de DPP modülü açık bir tenant ve `lt_` API anahtarı

## Kurulum

1. Bu klasörü `wp-content/plugins/lifetwin-woocommerce` altına kopyalayın.
2. WordPress panelinde etkinleştirin.
3. **WooCommerce > LifeTwin DPP** ekranında API anahtarı, secret ve şirket
   ID'sini girin.
4. "Bağlantıyı test et" ile tenant ve DPP modülünü doğrulayın.
5. Ürünü kaydedin; kuyruk kısa süre içinde LifeTwin'e gönderir.

## Webhook kurulumu

1. LifeTwin panelinde `dpp.published` olayına abone bir webhook uç noktası oluşturun.
2. Adres: `https://ornek.com/wp-json/lifetwin/v1/webhook`
3. Üretilen secret'ı eklenti ayarlarındaki **DPP webhook** kartına girin.

## Mimari

```
lifetwin-woocommerce.php     bootstrap, HPOS bildirimi
src/
  Autoloader.php             Composer'sız PSR-4
  Plugin.php                 yaşam döngüsü, WooCommerce kontrolü
  Settings.php               seçenekler, şirket/tip, bağlantı durumu
  Crypto.php                 AES-256-GCM secret
  TokenStore.php             transient token cache
  Client.php                 HTTP, token yenileme, hata eşleme
  Mapper.php                 WC ürün → connector payload
  Queue.php                  olay → kuyruk
  Sync.php                   upsert + ürün meta durumu
  Logger.php                 WC logger + son 20 kayıt
  Admin.php                  menü, formlar, meta box
admin/                       ayar, ürün paneli, log görünümleri
assets/admin.css
tests/                       PHPUnit birim testleri
```

## Ürün eşleme

| WooCommerce | LifeTwin |
| --- | --- |
| `sku` | `sku` (zorunlu) |
| `_global_unique_id`, `_gtin`, `_barcode` | `metadata.productIdentification.gtin` |
| `name`, `description` | `name`, `description` |
| Marka özniteliği / `_lifetwin_brand` | `metadata.productIdentification.brand` |
| `product_cat` | `categories[]` |
| `weight` | `weight_kg` (kg'a çevrilir) |
| Ölçüler | `dimensions.*_cm` |
| `price`, `stock` | `price`, `stock` |
| `publish/draft` | `active/draft` |
| `_lifetwin_materials` (JSON) | `metadata.materialComposition.materials` |

`_lifetwin_materials` yalnız "Malzeme alanlarını DPP'ye gönder" açıksa
gönderilir. Örnek:

```json
[
  {"materialName": "Organic Cotton", "percentage": 95, "origin": "TR"},
  {"materialName": "Elastane", "percentage": 5, "origin": "TR"}
]
```

## Geliştirme

```bash
# PHP sözdizimi
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# Testler (WordPress kurulumu gerekmez; stub'lar tests/bootstrap.php içinde)
composer install
vendor/bin/phpunit
```

LifeTwin deposundaki PHPUnit ile de çalıştırılabilir:

```bash
/path/to/lifetwinio/vendor/bin/phpunit -c phpunit.xml.dist
```

## Güvenlik notları

- Secret hiçbir zaman loglanmaz; `Logger::redact` ek savunmadır.
- API çağrıları yalnız sunucudan yapılır; tarayıcıya token/secret gitmez.
- Webhook imzası `X-Lifetwin-Signature: t=<unix>,v1=<hex>` biçiminde HMAC-SHA256 ile doğrulanır; 5 dakikadan eski istekler ve tekrarlanan teslim kimlikleri reddedilir.
- Ürün silme, LifeTwin'de silme yerine arşivleme olarak işaretlenir.
- API adresi koddan/filtreden gelir; kullanıcı arayüzünden değiştirilemez.

## Lisans

GPL-2.0-or-later. Ayrıntı: `LICENSE`.
