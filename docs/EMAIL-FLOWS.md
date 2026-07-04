# UNIRAD EMAIL OTOMASYON AKIŞLARI

> Son güncelleme: 2026-07-04 · Tüm gönderimler Brevo API üzerinden (info@unirad.co.uk)

## Aktif Akışlar

### AKIŞ 1 — Booking Onayı (unirad-quick-booking-v2)
- Tetik: `woocommerce_payment_complete` / `order_status_processing`
- → Hasta: randevu onayı (tarih, saat, tarama, adres)
- → Admin (booking@): [New Booking] bildirimi
- Tekrar koruması: `_uqb_patient_email_sent` order meta

### AKIŞ 2 — Callback Talebi (unirad-quick-booking-v2)
- Tetik: callback kategorisi form submit (Cardiac, Platinum, Pelvis Sports, GP)
- → Hasta: "Sizi arayacağız" otomatik yanıt
- → Admin: anında bildirim
- Bookly'ye `[CALLBACK]` prefix'li kayıt düşer

### AKIŞ 3 — Scan Sonrası Follow-up (unirad-automation) ✨YENİ
- Tetik: günlük cron 08:00 (`unirad_auto_daily`)
- +1 gün: rapor yolda bildirimi (`fu_report`)
- +3 gün: Google review talebi (`fu_review`) — UA_REVIEW_URL sabitini gerçek GBP linki ile güncelle!
- +30 gün: sağlık check-in + follow-up scan CTA (`fu_health`)
- +365 gün: yıllık tarama önerisi, Full Body paketleri (`fu_annual`)
- Tekrar koruması: `wp_unirad_auto_log` tablosu (flow + ref_id UNIQUE)

### AKIŞ 4 — Ödeme Başarısız (unirad-automation) ✨YENİ
- Tetik: `woocommerce_order_status_failed`
- → Hasta: retry linki (`get_checkout_payment_url`)
- → Admin: bildirim

### AKIŞ 5 — Dispute (unirad-automation) ✨YENİ
- Tetik: `woocommerce_payments_dispute_created` hook + `POST /wp-json/unirad/v1/dispute` webhook
- Webhook auth: `X-Unirad-Secret` header = `unirad_auto_webhook_secret` option
- → Admin: acil email; 24 saat sonra hatırlatma (`unirad_auto_dispute_reminder`)

### Abandoned Lead Recovery (unirad-quick-booking-v2, mevcut)
- Booking'e başlayıp bitirmeyen lead'lere hatırlatma emaili

## Raporlar

| Rapor | Zamanlama | Alıcı |
|---|---|---|
| Haftalık site audit | Pazartesi 07:00 | booking@ |
| Aylık gelir özeti (hizmet bazlı) | Ayın 1'i 07:30 | UA_DIRECTOR_EMAIL |

## Brevo Contact Senkronu
Sipariş completed/processing olunca contact upsert edilir:
FIRSTNAME, LASTNAME, SMS, LAST_SCAN, LAST_ORDER, LAST_ORDER_GBP, PATIENT=true

## Yapılandırma
- Brevo API key: WP Admin → 📧 Email Dashboard → API Keys (`unirad_dash_settings`)
- Direktör email: `UA_DIRECTOR_EMAIL` sabiti (unirad-automation.php)
- Review linki: `UA_REVIEW_URL` sabiti — **kurulumda gerçek Google review linki girilmeli**
