# Kaynak dosya ve iş kuralları

İncelenen kaynak: YEDEK.xlsm. Makrolar çalıştırılmadı. Dosya tek `VARDİYA` sayfası içeriyor; istek metninde anılan yedi sayfalı xlsx sağlanmadı.

Petrol: B2:O2 başlıkları, 3–12 personel satırları; 13 toplam satırı.
Market: B24:O24 başlıkları, 25–29 personel satırları; 30 toplam satırı.
Petrol girişleri C satış, D veresiye, E FAST, F gider, G puan, H/I/J banka POS, M nakit, N bozuk. K=H+I+J; L=C-(D+F+G+K+E); O=(M+N)-L.
Market girişleri C satış, D gider, E FAST, F/G/H banka POS, M nakit, N bozuk. K=F+G+H+I+J; L=C-(D+E+K); O=(M+N)-L. I/J sütunları başlıksız ve boş; dolu olduklarında veri kaybını önlemek için toplamları Diğer POS olarak aktarılır, önizlemede uyarılır.

Kaynakta tarih ve vardiya türü yok. Personel adları mevcut fakat parasal girişler boş. Dolayısıyla bu dosyada aktarılacak gerçek vardiya yok. İsim içeren boş satırlar vardiya olarak alınmaz. Toplam satırları dışlanır. Tarih/vardiya bilgisi gereken eski şablon aktarımında kullanıcı tarafından belirtilir, kendiliğinden üretilmez.

Backend kuruş cinsinden tamsayılarla hesap yapar; veritabanında DECIMAL(15,2) saklar. Petrol ve market ayrı departman ilişkileriyle korunur. Diğer POS yeni şemada desteklenir, eski kaynakta yoktur. Petrol beklenen nakit=satış-veresiye-FAST-gider-puan-POS. Market beklenen nakit=satış-gider-FAST-POS. Sayılan nakit boşsa fark NULL; sıfırsa gerçek sayımdır. Fark=(nakit+bozuk)-beklenen. Boş sayımı sıfır sayan Excel davranışı düzeltilir. Negatif beklenen tutar uyarılır; net kâr verisi üretilmez.
