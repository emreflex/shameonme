# Yalıtılmış tarayıcı testi

`istasyon_ui_test` adında yalnızca test için kullanılan MySQL veritabanı oluşturun; uygulama kullanıcısına yalnızca bu test şemasında yetki verin. Uygulama DB'niz `istasyon` olarak kalmalıdır. Aşağıdaki komutlar test şemasında sahte test personeli ve işlemi oluşturur; gerçek işletme veritabanına yazmaz.

```bash
docker compose run --rm -e DB_DATABASE=istasyon_ui_test app php artisan migrate --seed
docker compose run --rm -e DB_DATABASE=istasyon_ui_test app php tests/Browser/prepare.php
docker compose run -d --name istasyon-ui-test -e DB_DATABASE=istasyon_ui_test -p 127.0.0.1:8001:8001 app php artisan serve --host=0.0.0.0 --port=8001 --no-reload
# Chromium kurulu değilse: npx playwright install chromium
CHROMIUM_PATH=/usr/bin/chromium npx playwright test
```

Windows PowerShell'de kurulu Playwright Chromium kullanılabilir: `npx playwright install chromium`, ardından `npx playwright test`. `CHROMIUM_PATH` verilmezse Playwright'ın indirdiği tarayıcı seçilir.

Hazırlık betiği yalnızca `_ui_test` ile biten veritabanında çalışır. Sabit şifre içermez; rastgele geçici şifre, Git'ten dışlanan `storage/framework/browser-credentials.json` içine izinleri sınırlandırılmış biçimde yazılır. Test login, canlı petrol/market hesabı, boş/sıfır sayım, kayıt, onay, kapatma, PDF ve mobil ekranı doğrular. Görüntüler `storage/framework` altındadır.

Testi yeniden çalıştırmak için yalnızca bu atıl test şemasını `php artisan migrate:fresh --seed` komutuyla (açıkça `-e DB_DATABASE=istasyon_ui_test` verilerek) sıfırlayın; ardından `prepare.php` adımını tekrarlayın. **Gerçek uygulama DB'sine fresh uygulamayın.**

İş bitince yalnızca test hizmetini kapatın:

```bash
docker stop istasyon-ui-test
docker rm istasyon-ui-test
```

Geçici kimlik dosyasını güvenli biçimde kaldırın. İşletme hesabı oluşturmak için `app:create-admin` kullanın; test hesabını gerçek işletme hesabı olarak kullanmayın.
