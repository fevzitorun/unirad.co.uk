# UNIRAD OPERASYON PROTOKOLÜ
## Sekreterya Randevu & Hatırlatma Prosedürü + Aylık Takvim Verimi Denetimi

> Sürüm 1.0 — 2026-07-04
> No-show deseni ve takvim mimarisi bölümleri, randevu/gelir verisi geldiğinde
> gerçek rakamlarla güncellenecektir (bkz. bekleyen analiz).

---

## BÖLÜM 1 — RANDEVU ALMA PROTOKOLÜ

### Telefonla randevu alırken (her aramada, sırayla)
1. **Kimlik:** Ad-soyad, doğum tarihi, cep telefonu, email — email yoksa randevu YAZILMAZ (hatırlatma zinciri çalışmaz)
2. **Tarama:** Hangi bölge(ler)? Bilateral mi? → fiyatı net söyle (PRICING.md'den)
3. **Güvenlik ön taraması:** Kalp pili / vücutta metal / hamilelik ihtimali → şüphe varsa klinik ekibe yönlendir, randevuyu "beklemede" al
4. **Ödeme:** Telefonda randevu = ödeme linki SMS/email ile GÖNDERİLİR. Ödemesiz slot en fazla **4 saat** tutulur, sonra serbest bırakılır
5. **Onay cümlesi (aynen okunur):** "Randevunuz [gün] [saat]. 24 saat öncesine kadar ücretsiz değişiklik yapabilirsiniz. Gelmediğiniz takdirde ücret iadesi yapılamaz."

### Online (web) randevular
- Sistem otomatik işler — sekreterya sadece **sabah 09:00 ve öğlen 14:00'te** yeni order listesini kontrol eder
- Kontrol noktası: WooCommerce order VAR ama Bookly'de randevu YOK ise → RUNBOOK.md "Ödeme alındı ama Bookly'de yok" prosedürü

### Çakışma önleme
- Slot 15 dk otomatik kilitlenir (checkout başlayınca)
- Manuel randevu girerken: ÖNCE Bookly takvimine bak, SONRA WooCommerce bekleyen ödemelere bak (Dashboard → Bekleyen Ödemeler) — bekleyen ödeme de slot işgal eder

---

## BÖLÜM 2 — HATIRLATMA ZİNCİRİ

| Zaman | Kanal | İçerik | Kim |
|---|---|---|---|
| Randevu anında | Email | Onay + adres + hazırlık talimatı | Otomatik (sistem) |
| -48 saat | SMS | "Randevunuz yarından sonra [saat]. Değişiklik: 0141 846 9116" | Sekreterya (Brevo SMS) |
| -24 saat | Telefon | **Sesli arama** — cevap yoksa SMS bırak | Sekreterya |
| -3 saat | SMS | "Bugün [saat] randevunuz. Metal eşya getirmeyin." | Sekreterya |
| No-show olursa | Telefon | Aynı gün ara: sebep öğren + yeniden planla | Sekreterya |

**Altın kural:** -24 saat aramasında hastaya ulaşılamadıysa hasta "riskli" işaretlenir; -3 saat SMS'ine ek olarak tekrar aranır.

### No-show sonrası
1. Aynı gün telefon → sebep NOT EDİLİR (aylık denetimde kullanılacak)
2. Yeniden planlanırsa: yeni randevu SADECE ödeme tamamlanmışsa verilir
3. No-show kaydı tutulur: tarih, saat, gün, tarama türü, hasta tipi (yeni/tekrar), sebep

---

## BÖLÜM 3 — AYLIK TAKVİM VERİMİ DENETİMİ

Her ayın ilk Pazartesi'si doldurulur. Kaynak: Bookly export + WooCommerce aylık rapor (otomatik email).

### 3.1 Kapasite Skoru
```
Açık slot saati (ay toplamı):        _____ saat
Dolu slot saati:                     _____ saat
KAPASİTE KULLANIMI = Dolu / Açık:    _____ %   (hedef: >%65)
```

### 3.2 Saat Geliri (hizmet bazlı)
| Hizmet | Adet | Toplam Gelir | Toplam Süre (sa) | **£/saat** |
|---|---|---|---|---|
| 1 bölge MRI (60dk) | | | | |
| 2 bölge / bilateral (90dk) | | | | |
| 3-4 bölge (90dk) | | | | |
| Silver (180dk) | | | | |
| Gold/Platinum (240dk) | | | | |
| Özel taramalar | | | | |

> Referans (liste fiyatı / süre): 1 bölge £290/sa · 2 bölge £330/sa ·
> 3 bölge £463/sa · 4 bölge £597/sa · Silver £213/sa · Gold £315/sa
> **Dikkat:** 3-4 bölge en yüksek saat geliri — pazarlama bunlara itmeli.
> Silver paketi saat başına EN DÜŞÜK gelir — takvimi doldurduğu ama kazandırmadığı ay işaretle.

### 3.3 No-Show Analizi
```
Toplam randevu:            _____
No-show:                   _____   ORAN: _____ %  (hedef: <%5)
Gün dağılımı:  Pzt__ Sal__ Çar__ Per__ Cum__ Cmt__
Saat dağılımı: 09-12__  12-15__  15-18__  18-20__
Hasta tipi:    Yeni__  Tekrar__
-24h aramasına ulaşılamayanlardan no-show: _____ (hatırlatma açığı göstergesi)
```

### 3.4 Aksiyon Kararları (her ay en az 1)
- [ ] En düşük £/saat hizmet için fiyat/süre revizyonu değerlendirildi mi?
- [ ] En yüksek no-show gün/saat bloğuna depozito şartı konulacak mı?
- [ ] Boş kalan saat bloklarına kampanya (Google Ads saat hedefleme) açılacak mı?

---

## BÖLÜM 4 — TAKVİM MİMARİSİ (taslak — veri ile finalize edilecek)

Genel ilke (veri gelince rakamlarla doğrulanacak):
- **Sabah 09:30-12:00:** Uzun paketler (Silver/Gold) — hasta aç karnına gelir, gecikme riski düşük
- **Öğlen 12:00-15:00:** 2-4 bölge taramalar (yüksek £/saat bloğu)
- **Akşam 17:00-20:00:** Tek bölge hızlı taramalar — iş çıkışı hastası, no-show riski yüksek olduğundan kısa slotlar
- **Tampon:** Paketlerden sonra 30 dk; tek bölgeler arası tampon yok (30dk grid yeterli)
- **Cumartesi:** Sadece yüksek gelirli işlemler (3-4 bölge + paketler) — haftanın en değerli günü tek bölgeye harcanmaz
