# UNIRAD PRICING — TEK KAYNAK (Single Source of Truth)

> Fiyat değişikliği yapılacaksa ÖNCE bu dosya güncellenir, sonra kod ve site.
> Son güncelleme: 2026-07-04 (Temmuz 2026 zam uygulandı)

## MRI Taramaları (Bölge Bazlı)

| Bölge Sayısı | Fiyat | WooCommerce ID |
|---|---|---|
| 1 bölge | £290 | 5253 |
| 2 bölge (veya 1 bilateral) | £495 | 5141 |
| 3 bölge | £695 | 5234 |
| 4 bölge (veya 2 bilateral) | £895 | 5235 |

**Kural:** Bilateral (çift taraf) seçim = 2 bölge sayılır. Maksimum 4 bölge.

## Özel Taramalar

| Tarama | Fiyat | Not |
|---|---|---|
| Breast MRI | £420 | |
| Cardiac MRI | £575 | Callback gerekli |
| Prostate MRI | £495 | |
| Small Bowel MRI | £495 | |
| Abdomen & Pelvis MRI | £495 | |
| Pelvis (Sports Groin) MRI | £380 | Callback gerekli |

## Full Body Paketleri

| Paket | Fiyat | Kapsam | Süre |
|---|---|---|---|
| Silver | £640 | Brain + Abdomen & Pelvis | 3 saat |
| Gold | £1,260 | Brain + Whole Spine + Abdomen & Pelvis | 4 saat |
| Platinum | £1,660 | Cardiac + Brain + Spine + Abdomen & Pelvis | 4 saat, Callback |

## GP Konsültasyonları

| Hizmet | Fiyat |
|---|---|
| GP Consultation (In-Clinic) | £99 |
| Online GP Consultation (After Scan) | £50 |
| GP Consultation (Online) | £45 |
| GP Referral Letter (Before Scan) | £40 |

## Fiyatın Yaşadığı Yerler (değişiklikte hepsi güncellenmeli)

1. `unirad-quick-booking-v2.php` — `bundle_prices`, `SPECIAL` JS objesi, paket fiyatları
2. `unirad-scan-button.php` — scan listesi fiyatları
3. `unirad-hero-v9.php` — `BUNDLE`, `SPECIAL`, `priceBoth`, "From £" metni
4. `unirad-ai-core.php` — Aria chatbot fiyat bilgisi
5. `unirad-local-seo.php` — landing page fiyatları
6. WooCommerce ürünleri (DB) — ürün #5253/5141/5234/5235 + özel tarama ürünleri
7. Elementor sayfaları: `/services/our-pricing/` (widget 89895ff), `/services/`
8. Bookly servis fiyatları (DB)
