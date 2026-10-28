# Doğrulama kaydı

Bu bulut oturumunda Linux üzerinde PHP 8.4, Laravel 13.39.0 ve MySQL 8.4.13 kullanıldı. Windows/XAMPP kurulumu bu ortamda çalıştırılmadı.

## Gerçekleştirilen kontroller

- `npm run build`: başarılı. Vite derlemesi ve yerel CSS/JS çıktıları hazır; dış font servisine bağımlılık yok.
- `php artisan test --compact`: **31 test / 150 assertion**, tamamı geçti. SQLite bellek veritabanı.
- Aynı suite: **31 test / 150 assertion**, tamamı geçti. Ayrı MySQL `istasyon_test` veritabanı.
- Chromium Playwright: giriş → petrol/market canlı hesap → boş/sıfır sayım → kayıt → onay → kapatma → rapor/PDF → 390 px mobil menü/personel ekranı: geçti. JS sayfa hatası yok; mobil yatay sayfa taşması yok. Testler ayrı `istasyon_ui_test` şemasında çalıştı. Yedi dashboard grafiği ve açık/koyu tema için ayrı tarayıcı kontrolü de geçti. Test hizmeti kapatıldı, geçici kimlik dosyası kaldırıldı.
- Gerçek `YEDEK.xlsm` uygulamanın `WorkbookImporter` servisiyle okundu: **0 aday / 15 atlanan / 0 hata**. Gerçek vardiya oluşturulmadı, VBA çalıştırılmadı.
- MySQL migration ve idempotent seed: başarılı.
- `scripts/cloud-install.sh`: gerçek makinede tamamlandı; mevcut anahtar korundu, kilitli bağımlılıklar kuruldu, derleme ve testler geçti.
- `scripts/cloud-start.sh`: hizmetler tekrar başlatıldı; migration durumu ve gerçek giriş sayfasının Türkçe içeriği doğrulandı.
- `composer validate --strict`: geçerli. `composer update --lock --no-install` yalnızca metadata özetini yeniledi; paket sürümü değişmedi. Composer güvenlik denetiminde bildirim bulunmadı.
- `npm audit --omit=dev`: 0 güvenlik bildirimi.
- Laravel Pint: 54 PHP dosyası için stil kontrolü geçti.
- Rota önbelleği oluşturuldu ve geliştirmeye devam etmek için temizlendi.

## Kritik davranışlar

Petrol/market hesapları, POS, beklenen nakit, kasa farkı; boş ve sıfır sayım; eksik/fazla kasa; negatif beklenen nakit; para hassasiyeti ve büyük POS toplamı; çalışan/departman izolasyonu; yetkisiz onay ve yedek erişimi; kapalı kayıt koruması; gerekçeli düzeltme; eşzamanlı sürüm çakışması; mükerrer vardiya; soft delete; dönem grupları; Excel upload/önizleme/onay; Excel dışa/içe aktarma; tekrar aktarım engeli; PDF; şifreli yedeğin açılması ve boş hedefe geri yükleme test edildi.

## Kalan dış doğrulamalar

İlk gerçek yönetici kullanıcı tarafından gizli şifre girişiyle oluşturulur. Örnek işletme satışları veya hazır kullanıcı şifresi eklenmedi. Windows/XAMPP, canlı HTTPS sunucusu, gerçek işletme kabul testi ve yeni yayınlanmış görevden snapshot geri dönüşü bu oturumda doğrulanmadı. Yedi sayfalı farklı Excel dosyası sağlanmadığından o dosyanın eşlemesi desteklenmiş gibi gösterilmez.

Kurulum ve başlatma talimatları ortam taslağına kaydedildi; taslağın kaydedilmesi yayınlama değildir. Ortam ayarlarında inceleme/kaydetme ve ardından yayınlama kullanıcı tarafından yapılır.
