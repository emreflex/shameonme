<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['department_id', 'name']);
            $t->unique(['id', 'department_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained();
            $t->foreignId('department_id')->nullable()->constrained();
            $t->foreignId('employee_id')->nullable()->constrained();
            $t->boolean('active')->default(true);
        });
        Schema::create('shift_types', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->uuid('number')->unique();
            $t->foreignId('department_id')->constrained();
            $t->foreignId('employee_id');
            $t->foreign(['employee_id', 'department_id'])->references(['id', 'department_id'])->on('employees');
            $t->foreignId('shift_type_id')->constrained();
            $t->date('date')->index();
            $t->unsignedInteger('sequence')->default(1);
            $t->string('duplicate_reason')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->string('review_status')->default('unchecked');
            $t->foreignId('created_by')->constrained('users');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['department_id', 'employee_id', 'date', 'shift_type_id', 'sequence'], 'shift_identity');
        });
        Schema::create('shift_financial_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shift_id')->unique()->constrained();
            foreach (['sales', 'credit', 'fast', 'expense', 'points', 'pos_kuveyt', 'pos_garanti', 'pos_ziraat', 'pos_other', 'coins', 'pos_total', 'expected_cash'] as $c) {
                $t->decimal($c, 15, 2)->default(0);
            }$t->decimal('counted_cash', 15, 2)->nullable();
            $t->decimal('difference', 15, 2)->nullable();
            $t->string('cash_status')->index();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained();
            $t->foreignId('shift_id')->constrained();
            $t->string('message');
            $t->timestamps();
            $t->unique('shift_id');
        });
        Schema::create('application_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value');
            $t->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('filename');
            $t->string('checksum');
            $t->json('preview');
            $t->string('status')->default('preview');
            $t->unsignedInteger('success')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->unsignedInteger('errors')->default(0);
            $t->timestamps();
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained();
            $t->string('fingerprint')->unique();
            $t->foreignId('shift_id')->constrained();
        });
    }

    public function down(): void
    {
        foreach (['import_rows', 'import_batches', 'application_settings', 'notifications', 'audit_logs', 'shift_financial_entries', 'shifts', 'shift_types'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('role_id');
            $t->dropConstrainedForeignId('department_id');
            $t->dropConstrainedForeignId('employee_id');
            $t->dropColumn('active');
        });
        foreach (['employees', 'roles', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
