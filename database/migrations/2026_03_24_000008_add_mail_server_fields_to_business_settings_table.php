<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('mail_mailer', 50)->nullable()->after('email_reply_to');
            $table->string('mail_host')->nullable()->after('mail_mailer');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username')->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_encryption', 20)->nullable()->after('mail_password');
            $table->unsignedSmallInteger('mail_timeout')->nullable()->after('mail_encryption');

            $table->boolean('incoming_enabled')->default(false)->after('mail_timeout');
            $table->string('incoming_protocol', 20)->nullable()->after('incoming_enabled');
            $table->string('incoming_host')->nullable()->after('incoming_protocol');
            $table->unsignedSmallInteger('incoming_port')->nullable()->after('incoming_host');
            $table->string('incoming_username')->nullable()->after('incoming_port');
            $table->text('incoming_password')->nullable()->after('incoming_username');
            $table->string('incoming_encryption', 20)->nullable()->after('incoming_password');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_timeout',
                'incoming_enabled',
                'incoming_protocol',
                'incoming_host',
                'incoming_port',
                'incoming_username',
                'incoming_password',
                'incoming_encryption',
            ]);
        });
    }
};

