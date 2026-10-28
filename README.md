# İstasyon · Petrol ve Market Yönetimi

Laravel 13, PHP 8.3+, MySQL 8+, Blade, Tailwind CSS 4, Alpine.js, Chart.js, PhpSpreadsheet ve Dompdf ile Türkçe işletme yönetimi uygulaması.

Petrol ve market bağımsız finansal kayıtlara sahiptir. Veriler MySQL'de kalıcıdır; dashboard ve raporlar gerçek vardiyalardan hesaplanır. Örnek satış veya hazır kullanıcı şifresi eklenmez.

## Kaynak Excel hakkında

Sağlanan dosya `YEDEK.xlsm`: tek `VARDİYA` sayfasında petrol ve market tabloları var. İstekte anılan yedi sayfalı `ISTASYON_MARKET_PROFESYONEL_RAPOR.xlsx` sağlanmadı. Kaynakta personel adları var; parasal girişler, tarih ve vardiya türü yok. **Bu dosyadan gerçek vardiya üretilmez.** Makrolar çalıştırılmaz.

Formüller ve sütun eşlemesi: [Excel analizi](docs/EXCEL-ANALIZI.md). Desteklenmeyen kitaplar sessizce yanlış eşlenmek yerine reddedilir. Yedi sayfalı farklı dosya için ilgili dosyanın ayrıca incelenip eşlemesinin eklenmesi gerekir.

## Çalışan modüller

- Oturum tabanlı güvenli giriş, deneme sınırlaması, CSRF, şifreli oturum, 30 dakika hareketsizlik süresi.
- Süper yönetici, departman yöneticisi ve personel rolleri; backend policy ve sorgu kapsamları. Personel yalnızca kendi kayıtlarını görür ve kendi taslak/sayım kayıtlarını düzenler.
- Petrol/market vardiya girişi ve anlık kasa hesabı. Hesaplar sunucuda yeniden yapılır; finansal alanlar DECIMAL(15,2), aritmetik kuruş bazında tamsayıdır.
- Sayım bekliyor, eksik, fazla ve dengeli kasa ayrımı; negatif beklenen nakit uyarısı. Satış ile net kâr birbirine karıştırılmaz.
- Taslak → sayım/kontrol/onay bekliyor → onaylandı → kapatıldı akışı. Onay için sayım; fark varsa gerekçe gerekir. Onaylı/kapalı kayıt düzeltmesi yönetici gerekçesiyle yeniden kontrole alınır.
- Benzersiz vardiya numarası, mükerrer kayıt gerekçesi, satır kilidi + sürüm kontrolü, transaction, soft delete ve önce/sonra denetim geçmişi.
- Tarih ve departman filtreli KPI'lar, grafikler, günlük kasa karşılaştırması, 11 rapor türü, personel karşılaştırması, arama, sıralama, sayfalama, yazdırma, PDF ve Excel.
- Personel/kullanıcı yönetimi, vardiya türü ekleme/pasifleştirme, işletme adı, bildirim merkezi, koyu tema ve mobil menü.
- Excel önizleme/onay akışı; hatalı, atlanan ve aktarılan satır sayıları; aynı aktarımı yinelememe kontrolü.
- Süper yönetici için şifre doğrulamalı, şifreli mantıksal yedek indirme. Boş hedefe kontrollü CLI geri yükleme.

## Windows + XAMPP kurulumu

### 1. Araçlar

PHP **8.3 veya 8.4**, Composer 2, MySQL **8.0 veya 8.4**, Node.js **24 LTS** kurun. Terminalde `php -v`, `composer --version`, `mysql --version`, `node --version` kontrol edin.

XAMPP sürümlerinin bir bölümü PHP 8.2 ve **MariaDB** içerir. Bunlar bu projenin istenen PHP/MySQL kurulumunun yerine geçmez. Gerekirse PHP 8.4'ü ayrı kurup PATH'e alın ve MySQL 8'i ayrı servis olarak çalıştırın. XAMPP Apache ile çalıştıracaksanız Apache'nin de uyumlu PHP sürümünü kullandığını doğrulayın. Daha kolay yerel başlatma için aşağıdaki `php artisan serve` komutunu kullanın.

Etkin PHP'nin `php.ini` dosyasında `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl`, `bcmath`, `dom`, `xml`, `xmlreader`, `xmlwriter` ve testler için `pdo_sqlite` etkin olmalıdır. `php --ini` etkin dosyayı gösterir. Standart XAMPP'te dizin genellikle `C:\xampp\php`; farklı PHP sürümü kurduysanız onun `php.ini` dosyasını düzenleyin. Değişiklikten sonra terminal/Apache'yi yeniden başlatın.

### 2. Dosyalar ve bağımlılıklar

Projeyi örneğin `C:\xampp\htdocs\istasyon` dizinine alın, PowerShell açın:

```powershell
cd C:\xampp\htdocs\istasyon
composer install --no-interaction --prefer-dist
npm ci
Copy-Item .env.example .env
```

`Copy-Item` yalnızca ilk kurulumda kullanılmalıdır; mevcut `.env` ve APP_KEY değerini değiştirmeyin. TLS doğrulamasını kapatmayın. Composer lock dosyası GitHub'ın resmi codeload arşivlerini sabit commitlerle kullanır. `scripts/composer-codeload.py`, gelecekteki dependency güncellemesinden sonra ağ politikasına uygun resmi arşiv URL'lerine geçmek için kullanılabilir; paket sürümlerini değiştirmez.

### 3. MySQL veritabanı

MySQL yönetim istemcisinde yetkili hesabınızla:

```sql
CREATE DATABASE istasyon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'istasyon'@'localhost' IDENTIFIED BY '<kendi-guclu-veritabani-sifreniz>';
GRANT ALL PRIVILEGES ON istasyon.* TO 'istasyon'@'localhost';
```

Yer tutucuyu kendi güçlü şifrenizle değiştirin. Uygulama için root hesabını kullanmayın. `.env`:

```dotenv
APP_NAME="İstasyon"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=tr
APP_FALLBACK_LOCALE=tr
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=istasyon
DB_USERNAME=istasyon
DB_PASSWORD="kendi-veritabani-sifreniz"
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
```

`.env` dosyasını Git'e eklemeyin. İlk kurulum:

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan app:create-admin
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

`app:create-admin` ad, e-posta ve gizli şifreyi terminalde sorar; şifre en az 12 karakter, harf ve rakam içermelidir. Hazır hesap yoktur. `key:generate` yalnızca yeni kurulum içindir; mevcut APP_KEY'yi döndürmek oturum ve yedeklerin çözülememesine neden olur.

Giriş yaptıktan sonra personelleri departmanlarına ekleyin. Personel rolündeki hesapları ilgili personel ve departmanla eşleştirin. Ardından vardiya kaydı oluşturun. Frontend geliştirmede ayrı terminalde `npm run dev` kullanılabilir.

Apache kullanılırsa DocumentRoot projenin **public** dizini olmalıdır; proje kökünü web'e açmayın. `storage` ve `bootstrap/cache` PHP tarafından yazılabilir olmalı; `.env` dışarı sunulmamalıdır.

## Docker / bu bulut ortamı

Docker ve Compose kullanılabilir olmalı. Bulut makinesinde Node 24 ve Python 3 hazırdır. Aynı checkout'u kullanın; bulut görevleri zaten yalıtılmıştır, ayrıca Git worktree oluşturmayın.

```bash
bash scripts/cloud-install.sh
bash scripts/cloud-start.sh
# İlk yönetici (etkileşimli, şifre gizli):
docker compose exec app php artisan app:create-admin
```

Kurulum betiği mevcut `.env` değerlerini korur; boş yerel DB şifrelerini rastgele üretir. PHP 8.4 imajını kurar, Composer ve npm lock dosyalarından bağımlılıkları yükler, anahtar yoksa oluşturur, MySQL'i başlatır, migration/seed ve testleri çalıştırır. Başlangıç betiği hizmetleri tekrar başlatır, migration durumunu ve gerçek giriş sayfasını doğrular.

MySQL yalnızca Docker ağına, uygulama yalnızca loopback 8000 portuna açılır. Veritabanı `mysql_data` hacminde saklanır. **`docker compose down -v` verileri siler; kullanmayın.** Günlük durdurma: `docker compose stop`. `.env` değiştiğinde `docker compose up -d --force-recreate app` ile uygulamayı yenileyin. Docker geliştirme sunucusunda ortam değişkenlerini korumak için `--no-reload` kullanılır.

Windows'ta Docker alternatifi: `.env.example` dosyasını `.env` yapın, `DB_HOST=db` ayarlayın; `DB_PASSWORD` ve `DB_ROOT_PASSWORD` için ayrı güçlü değerler girin. `docker compose build`, `docker compose run --rm --no-deps app composer install`, `docker compose run --rm --no-deps app php artisan key:generate`, `npm ci`, `npm run build`, `docker compose up -d db`, `docker compose run --rm app php artisan migrate --seed`, `docker compose exec app php artisan app:create-admin` öncesinde `docker compose up -d app` çalıştırın.

Bulut proxy CA'sı `CODEX_PROXY_CERT` üzerinden build secret olarak verilir, imajda tutulmaz. TLS ve paket checksum doğrulamaları kapatılmaz. Composer runtime'ına sistem CA demeti salt okunur bağlanır. Özel kurumsal ağda proxy/CA gereksinimlerini ortam ayarlarından yönetin.

## Excel akışı

1. Excel aktarımından `.xlsx` veya `.xlsm` yükleyin (en fazla 5 MB; açılmış arşiv 40 MB; sayfa başına 10.000 satır).
2. Eski VARDİYA şablonunda tarih/vardiya yoksa kullanıcı tarih ve vardiya seçer. Boş/yalnızca isimli satır, toplam satırı ve önceden aktarılmış kayıt atlanır.
3. Önizlemede departman, personel, tarih, satış ve satır hatalarını kontrol edin. Formüllerin kayıtlı önbellek sonuçları finansal gerçek olarak kullanılmaz.
4. Onaylayın. Geçerli satırlar **taslak** olarak kaydedilir; başarısız satırlar görünür kalır. Bilinmeyen personel kendi departmanında açılır; vardiya türü önceden ayarlarda tanımlanmalıdır.
5. Raporların Excel çıktısında `KAYITLAR` sayfası tekrar içe alınabilir. `RAPOR ÖZETİ` sayfası salt rapor içindir. Dışa aktarılan personel/metin alanları Excel formül enjeksiyonunu önlemek için metin olarak yazılır.

Aktarım fingerprint'i departman/personel/tarih/vardiya ve finansal girişlerden hesaplanır. Aynı kişi+tarih+vardiya için farklı içerik mükerrer kayıt kontrolüne takılır; otomatik olarak ikinci kayda dönüşmez. Yetkili kullanıcı ek kaydı gerekçeyle vardiya formundan oluşturabilir.

## Testler

```bash
php artisan test
npm run build
# Docker karşılığı:
docker compose run --rm app php artisan test --compact
```

Varsayılan PHP testleri geçici SQLite bellek veritabanında çalışır; gerçek DB'yi değiştirmez. MySQL doğrulaması için **ayrı** `istasyon_test` veritabanı açıp uygulama DB kullanıcısına yetki verin:

```bash
docker compose run --rm -e DB_CONNECTION=mysql -e DB_DATABASE=istasyon_test app php artisan test --compact
```

MySQL testleri hedef şemayı sıfırlayabilir; asla gerçek veritabanı adını vermeyin. [Tarayıcı testinin ayrı veritabanında çalıştırılması](docs/TARAYICI-TESTI.md).

## Yedekleme ve geri yükleme

Sistem ayarlarında yalnızca süper yönetici, hesap şifresini tekrar girerek `.backup` indirir. İçerik Laravel Crypt ile kimliği doğrulanmış şekilde şifrelidir; parolalar düz metin değildir, kullanıcı şifre hash'leri de bu şifreli yedeğin içindedir. Yedek sunucunun public alanında saklanmaz. İndirdiğiniz dosyayı ve **aynı APP_KEY'yi** ayrı, erişimi sınırlı güvenli depolarda saklayın.

Bu işletme tablolarının mantıksal veri yedeğidir; uygulama kodu, migration dosyaları, sunucu yapılandırması, oturumlar, önbellek ve DB kullanıcıları ayrıca yedeklenmelidir. Büyük kurulumlarda sunucu yöneticisi ayrıca MySQL'in resmi yedekleme araçlarıyla düzenli yedek planlamalıdır.

Geri yükleme: uygulamayı bakım moduna alın; yeni **boş** MySQL veritabanını `.env` ile seçin; kaynakla aynı APP_KEY'yi güvenli biçimde sağlayın. `php artisan migrate` çalıştırın, **seed çalıştırmayın**. Ardından:

```bash
php artisan app:restore-backup /guvenli-dizin/istasyon.backup --confirm
```

Hedef işletme tablolarından biri doluysa veya anahtar uyumsuzsa işlem reddedilir. Veriler transaction içinde yüklenir; mevcut verilerin üstüne yazılmaz. Yeni hedefte doğrulama yaptıktan sonra yayın trafiğini yönlendirin. Yedek anahtarını paylaşmayın veya kaynak koda yazmayın.

## Yayına geçiş

Geliştirme sunucusu canlı yayın sunucusu değildir. Sunucuda PHP-FPM/Apache veya Nginx, MySQL 8, HTTPS ve doğru public DocumentRoot kullanın. `APP_ENV=production`, `APP_DEBUG=false`, gerçek `APP_URL`, `SESSION_SECURE_COOKIE=true` ayarlayın. Bağımlılıkları `composer install --no-dev --optimize-autoloader` ile kurun; `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` çalıştırın. Sadece gerekli storage/cache dizinlerine yazma izni verin; uygulama DB hesabına yalnızca kendi şeması için izin verin.

## Mimari ve kapsam

[Veritabanı ve güvenlik modeli](docs/MIMARI.md), [Excel analizi](docs/EXCEL-ANALIZI.md), [doğrulama sonuçları](docs/DOGRULAMA.md).

Bu sistem vardiya/kasa ve raporlama kapsamındadır. Pompa otomasyonu, ürün stokları, alış maliyeti, banka mutabakat API'si, e-fatura ve bordro modülleri yoktur; net kâr gösterilmez. Windows/XAMPP kurulum yönergesi hazırlanmıştır; bu Linux bulutunda Windows kurulumu çalıştırılmış değildir. Canlıya geçmeden işletmenizin gerçek iş akışıyla kabul testi ve yedekten geri dönüş provası yapın.
