# UNIRAD ACİL DURUM RUNBOOK

## 🔴 Site tamamen çöktü
1. Hosting panelinden PHP error log kontrol et
2. Son yüklenen plugin'i FTP ile `/wp-content/plugins/` altından klasör adını değiştirerek devre dışı bırak
3. `wp-config.php`'ye `define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);` ekle, `/wp-content/debug.log` oku

## 🔴 Çifte randevu (double booking)
1. Bookly Calendar'da çakışan iki kaydı tespit et
2. Sonra gelen hastayı ara, alternatif slot öner
3. Not: Slot 15dk kilitleme mevcut (`uqb_locked_` transient) ama %100 garanti değil
4. Kontrol: WooCommerce order'ların ikisi de ödendiyse birine refund/reschedule

## 🔴 Ödeme alındı ama Bookly'de randevu yok
1. WooCommerce → Orders → ilgili order → meta `_uqb_booking_date/_time` kontrol et
2. `write_to_bookly()` başarısız olmuş olabilir — error_log'da "UQB Bookly insert failed" ara
3. Randevuyu Bookly'ye manuel gir, `custom_service_name` alanına tarama adını yaz

## 🟡 Emailler gitmiyor
1. WP Admin → Email Dashboard → Brevo key doğru mu?
2. Brevo hesabında kredi/limit kontrolü
3. `unirad_dash_settings` option'ı boşalmış olabilir

## 🟡 Fiyatlar yanlış görünüyor
1. `docs/PRICING.md`'deki 8 lokasyon listesini kontrol et
2. Aria chatbot yanlış fiyat söylüyorsa: transient cache temizle
   (WP Admin → herhangi bir cache plugin → clear, veya `wp transient delete --all`)
3. Elementor sayfası eskiyse: Elementor → Tools → Regenerate CSS & sync

## 🟡 Dispute geldi
1. 48 saat kuralı: WooPayments → Disputes → kanıt yükle
2. Kanıt paketi: randevu onay emaili, hasta safety formu, hizmet kaydı (Bookly), varsa imzalı GDPR onayı
3. unirad-automation 24 saat sonra otomatik hatırlatma yollar

## Erişim Bilgileri (nerede ne var)
- WP Admin: https://unirad.co.uk/wp-admin
- Brevo API key: WP Admin → Email Dashboard → API Keys
- ScanBook API key: WP Admin → Settings → ScanBook Bridge
- Dispute webhook secret: `unirad_auto_webhook_secret` (wp_options)
- GitHub: https://github.com/fevzitorun/unirad-plugins
