<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'invoices.send_email';

    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('email_sending_enabled')->default(false)->after('mail_timeout');
            $table->boolean('email_order_created_enabled')->default(false)->after('email_sending_enabled');
            $table->boolean('email_payment_received_enabled')->default(false)->after('email_order_created_enabled');
        });

        Schema::create('customer_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_payment_id')->nullable()->constrained('order_payments')->nullOnDelete();
            $table->string('type', 50);
            $table->string('delivery_key')->unique();
            $table->string('recipient')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['branch_id', 'created_at']);
        });

        $tables = config('permission.table_names');
        $guard = config('auth.defaults.guard');
        $now = now();

        DB::table($tables['permissions'])->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => $guard],
            ['created_at' => $now, 'updated_at' => $now],
        );
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->value('id');

        if ($permissionId) {
            DB::table($tables['role_has_permissions'])->where('permission_id', $permissionId)->delete();
            DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->delete();
            DB::table($tables['permissions'])->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('customer_email_deliveries');

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email_sending_enabled',
                'email_order_created_enabled',
                'email_payment_received_enabled',
            ]);
        });
    }
};
