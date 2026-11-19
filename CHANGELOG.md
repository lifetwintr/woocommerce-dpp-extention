# Changelog

## 1.1.0 — 2026-11-18

- Varyasyon desteği: her varyasyon ayrı ürün olarak `external_parent_ref` ile gönderilir; varyasyon kaydedildiğinde ana ürün yeniden kuyruğa alınır.
- Şirket listesi: bağlantı testi `GET /companies` sonucunu önbelleğe alır, ayarlarda açılır liste sunar; tek şirket otomatik seçilir.
- Webhook alıcısı: `POST /wp-json/lifetwin/v1/webhook`, `dpp.published` olayı, HMAC-SHA256 imza + 5 dakika tolerans, teslim kimliği ile tekrar koruması.
- Toplu senkron: ayarlardan tüm ürünler ve ürün listesinden seçili ürünler için toplu işlem.
- Webhook secret ayrı şifreli seçenek olarak saklanır; kısmi form gönderimleri diğer ayarları sıfırlamaz.

## 1.0.0 — 2026-11-17

- WooCommerce ürünlerini LifeTwin Connector v1 `products/upsert` ucuyla senkronlar.
- API key/secret ayarları; secret AES-256-GCM ile şifrelenir.
- Bağlantı testi (`/auth/token` + `/account/capabilities`).
- Action Scheduler veya WP-Cron kuyruğu.
- Ürün panelinde durum, DPP pasaport linki ve manuel gönderim.
- Son 20 senkron kaydı.
- Ürün silme/çöp durumunda LifeTwin kaydını silmez, arşivler.
