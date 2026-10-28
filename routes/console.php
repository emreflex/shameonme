<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('app:create-admin', function () {
    $name = $this->ask('Ad soyad');
    $email = $this->ask('E-posta');
    $password = $this->secret('Şifre (en az 12 karakter, harf ve rakam)');
    $v = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:150', 'email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    $u = new User(compact('name', 'email', 'password'));
    $u->role_id = Role::where('name', 'super_admin')->firstOrFail()->id;
    $u->save();
    $this->info('Yönetici oluşturuldu.');
})->purpose('Gizli şifre girişiyle ilk yöneticiyi oluşturur');

Artisan::command('app:restore-backup {file} {--confirm}', function () {
    if (! $this->option('confirm')) {
        $this->error('Yalnızca boş ve migration uygulanmış hedefe --confirm ile geri yükleyin.');

        return 1;
    }
    $tables = ['roles', 'departments', 'employees', 'users', 'shift_types', 'shifts', 'shift_financial_entries', 'audit_logs', 'notifications', 'application_settings', 'import_batches', 'import_rows'];
    $file = $this->argument('file');
    if (! is_file($file) || filesize($file) > 100 * 1024 * 1024) {
        $this->error('Yedek bulunamadı veya 100 MB sınırını aşıyor.');

        return 1;
    }
    try {
        $payload = json_decode(Crypt::decryptString(file_get_contents($file)), true, 512, JSON_THROW_ON_ERROR);
        if (($payload['format'] ?? null) !== 'istasyon-logical-v1' || array_keys($payload['tables'] ?? []) !== $tables) {
            throw new RuntimeException('Yedek biçimi desteklenmiyor.');
        }
        DB::transaction(function () use ($payload, $tables) {
            foreach ($tables as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Hedef boş değil. Mevcut veriler değiştirilmedi.');
                }
            }
            foreach ($tables as $table) {
                foreach (array_chunk($payload['tables'][$table], 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
        $this->info('İşletme verileri geri yüklendi. Oturumlar geri yüklenmez.');

        return 0;
    } catch (Throwable $e) {
        $this->error('Geri yükleme başarısız: dosya/anahtar uyumu ve hedefin boş olduğunu kontrol edin.');

        return 1;
    }
})->purpose('Şifreli işletme yedeğini yalnızca boş veritabanına yükler');

Artisan::command('app:initialize', function () {
    if (blank(config('app.key'))) {
        return $this->call('key:generate');
    }
    $this->info('Mevcut uygulama anahtarı korundu.');

    return 0;
})->purpose('Yalnızca eksik uygulama anahtarını oluşturur');
