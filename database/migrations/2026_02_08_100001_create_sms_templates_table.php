<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->json('templates')->nullable()->comment('JSON: order_created, order_status_change, order_delivered, order_delivery_date_change, order_cancelled, order_payment, order_due_date_reminder');
            $table->timestamps();
        });

        // Seed default row with placeholder templates
        DB::table('sms_templates')->insert([
            'templates' => json_encode([
                'order_created' => 'Hello {customer_name}, your order #{order_number} for {garments} was created on {order_date}. Total: {total_amount}. Due date: {due_date}.',
                'order_status_change' => 'Hello {customer_name}, your order #{order_number} for {garments} is now {status}. Due date: {due_date}.',
                'order_delivered' => 'Hello {customer_name}, your order #{order_number} for {garments} has been delivered. Thank you!',
                'order_delivery_date_change' => 'Hello {customer_name}, your order #{order_number} due date has been updated from {old_due_date} to {new_due_date}.',
                'order_cancelled' => 'Hello {customer_name}, your order #{order_number} for {garments} has been cancelled.',
                'order_payment' => 'Hello {customer_name}, we received payment of {amount_paid} for order #{order_number}. Balance due: {balance_due}.',
                'order_due_date_reminder' => 'Hello {customer_name}, reminder: your order #{order_number} for {garments} is due on {due_date}. Total: {total_amount}, Balance: {balance_due}.',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
