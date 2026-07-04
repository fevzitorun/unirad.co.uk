# UNIRAD PLUGIN MİMARİSİ

> Son güncelleme: 2026-07-04

## Stack
WordPress + WooCommerce (ödeme) + Bookly (takvim) + Brevo (email/SMS) + Elementor (sayfalar)

## Custom Plugin'ler

| Plugin | Görev | Kritik Bağımlılık |
|---|---|---|
| `unirad-quick-booking-v2` | 3 adımlı booking akışı, slot yönetimi, Bookly yazımı, onay emailleri | WooCommerce, Bookly, Brevo |
| `unirad-hero` (v9) | Ana sayfa hero + hızlı fiyat hesaplayıcı | quick-booking ile fiyat senkron |
| `unirad-scan-button` | Scan seçici buton widget'ı | quick-booking ile fiyat senkron |
| `unirad-ai-core` | Aria AI chatbot | Fiyat bilgisi hardcoded — transient cache'e dikkat |
| `unirad-local-seo` | 28 Glasgow bölge landing page + schema | — |
| `unirad-email-dashboard` | Brevo istatistik paneli; Brevo API key burada saklanır (`unirad_dash_settings`) | Brevo |
| `unirad-social` | Sosyal medya otomasyonu | — |
| `unirad-crm` | Lead yönetimi | — |
| `unirad-automation` | Follow-up emailleri, payment failed, dispute, haftalık audit, aylık gelir raporu | Brevo, WooCommerce, Bookly |
| `unirad-director-dashboard` | Direktör KPI paneli | WooCommerce, Bookly |
| `scanbook-bridge` | ScanBook ↔ Bookly REST API köprüsü | Bookly |

## Kritik Veri Akışı (Booking)

```
Kullanıcı → unirad-quick-booking-v2 (3 adım)
  → WC()->cart->add_to_cart (bundle ürün 5253/5141/5234/5235)
  → slot 15dk transient ile kilitlenir (uqb_locked_YYYY-MM-DD)
  → WooCommerce checkout → ödeme
  → woocommerce_order_status_processing → write_to_bookly()
  → bookly_appointments'a INSERT (custom_service_name = gerçek tarama adı)
  → Brevo onay emaili (hasta + booking@unirad.co.uk)
```

## Kritik Sabitler (quick-booking-v2)

- `UQB_BOOKLY_STAFF_ID = 6`
- `UQB_BOOKLY_SERVICE_ID = 38` (fallback)
- `UQB_NOTIFY_EMAIL = booking@unirad.co.uk`
- Bundle ürünleri: 1→5253, 2→5141, 3→5234, 4→5235

## Bilinen Tuzaklar

1. **Fiyatlar 8 yerde yaşıyor** — bkz PRICING.md. Tek yerde değişiklik yetmez.
2. **Bilateral = 2 bölge** — JS'de `cur >= 4` limiti var (satır ~1780).
3. **Bookly local time** kullanır (Europe/London), UTC değil.
4. **Aria chatbot** cevapları transient'te cache'lenir — fiyat değişince transient temizle.
5. **Slot kilidi** 15 dakika — bkz `uqb_locked_` transient, `ajax_checkout` içinde set edilir.
6. **Takvimde servis adı** görünür (popup'ta custom_service_name) — Bookly Services'ta isimler kısa tutulmalı ("MRI Scan").
