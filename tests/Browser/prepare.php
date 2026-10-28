<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Run only against a disposable database ending in _ui_test.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.'.config('database.default').'.database'), '_ui_test')) {
    throw new RuntimeException('Dedicated _ui_test database required.');
}
$credentials = ['email' => 'browser@tests.invalid', 'password' => bin2hex(random_bytes(16))];
$u = User::firstOrNew(['email' => $credentials['email']]);
$u->name = 'Arayüz Testi';
$u->password = $credentials['password'];
$u->role_id = Role::where('name', 'super_admin')->firstOrFail()->id;
$u->save();
foreach (Department::all() as $d) {
    Employee::firstOrCreate(['department_id' => $d->id, 'name' => 'Test Personeli']);
}
file_put_contents('/app/storage/framework/browser-credentials.json', json_encode($credentials));
chmod('/app/storage/framework/browser-credentials.json', 0600);
