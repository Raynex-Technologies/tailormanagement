<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('garment_categories')) {
            Schema::create('garment_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('gender_scope')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('garment_option_groups')) {
            Schema::create('garment_option_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('garment_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('input_type')->default('select');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
                $table->unique(['garment_category_id', 'slug'], 'garment_groups_category_slug_unique');
            });
        }

        if (! Schema::hasTable('garment_options')) {
            Schema::create('garment_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('garment_option_group_id')->constrained()->cascadeOnDelete();
                $table->string('label');
                $table->string('value');
                $table->text('description')->nullable();
                $table->decimal('price_adjustment', 12, 2)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
                $table->unique(['garment_option_group_id', 'value'], 'garment_options_group_value_unique');
            });
        }

        if (! Schema::hasTable('measurement_fields')) {
            Schema::create('measurement_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('garment_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('unit')->default('cm');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
                $table->unique(['garment_category_id', 'slug'], 'measurement_fields_category_slug_unique');
            });
        }

        if (! Schema::hasTable('measurement_profiles')) {
            Schema::create('measurement_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('profile_name')->default('Default');
                $table->string('gender_scope')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('measurement_values')) {
            Schema::create('measurement_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('measurement_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
                $table->decimal('value', 10, 2)->nullable();
                $table->string('unit')->default('cm');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['measurement_profile_id', 'measurement_field_id'], 'measurement_values_profile_field_unique');
            });
        } elseif (! Schema::hasIndex('measurement_values', 'measurement_values_profile_field_unique')) {
            Schema::table('measurement_values', function (Blueprint $table) {
                $table->unique(['measurement_profile_id', 'measurement_field_id'], 'measurement_values_profile_field_unique');
            });
        }

        if (! Schema::hasTable('appointment_types')) {
            Schema::create('appointment_types', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('default_duration_minutes')->default(30);
                $table->unsignedInteger('buffer_before_minutes')->default(0);
                $table->unsignedInteger('buffer_after_minutes')->default(0);
                $table->boolean('requires_approval')->default(true);
                $table->boolean('is_public')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('office_availability_windows')) {
            Schema::create('office_availability_windows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('appointment_type_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedInteger('slot_interval_minutes')->default(30);
                $table->unsignedInteger('capacity')->default(1);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->index(['branch_id', 'day_of_week'], 'availability_branch_day_index');
                $table->index(['appointment_type_id', 'day_of_week'], 'availability_type_day_index');
            });
        }

        if (! Schema::hasTable('office_unavailability_periods')) {
            Schema::create('office_unavailability_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('appointment_type_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->text('reason')->nullable();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->boolean('is_full_day')->default(false);
                $table->boolean('repeats_yearly')->default(false);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->index(['branch_id', 'starts_at'], 'unavailability_branch_start_index');
                $table->index(['appointment_type_id', 'starts_at'], 'unavailability_type_start_index');
            });
        }

        if (! Schema::hasTable('online_bookings')) {
            Schema::create('online_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('booking_number')->unique();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('booking_type')->index();
                $table->string('status')->default('submitted')->index();
                $table->string('customer_name');
                $table->string('customer_phone');
                $table->string('customer_whatsapp')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_location')->nullable();
                $table->string('preferred_contact_method')->nullable();
                $table->string('preferred_language')->nullable();
                $table->text('notes')->nullable();
                $table->date('needed_by_date')->nullable();
                $table->date('event_date')->nullable();
                $table->boolean('is_urgent')->default(false);
                $table->string('source')->default('public_booking');
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('decline_reason')->nullable();
                $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('measurement_option')->nullable();
                $table->foreignId('measurement_profile_id')->nullable()->constrained()->nullOnDelete();
                $table->json('payload')->nullable();
                $table->text('internal_note')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['booking_type', 'status'], 'online_bookings_type_status_index');
                $table->index(['customer_phone', 'created_at'], 'online_bookings_phone_created_index');
            });
        }

        if (! Schema::hasTable('online_booking_items')) {
            Schema::create('online_booking_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('online_booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('garment_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('garment_name')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('fabric_source')->nullable();
                $table->string('fabric_type')->nullable();
                $table->string('primary_color')->nullable();
                $table->string('secondary_color')->nullable();
                $table->string('preferred_fit')->nullable();
                $table->string('occasion')->nullable();
                $table->text('style_description')->nullable();
                $table->text('special_instructions')->nullable();
                $table->decimal('estimated_budget_min', 12, 2)->nullable();
                $table->decimal('estimated_budget_max', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('online_booking_item_options')) {
            Schema::create('online_booking_item_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('online_booking_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('garment_option_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('garment_option_id')->nullable()->constrained()->nullOnDelete();
                $table->text('custom_value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('online_booking_images')) {
            Schema::create('online_booking_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('online_booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('online_booking_item_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('uploaded_by_type')->nullable();
                $table->string('path');
                $table->string('disk')->default('public_uploads');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->string('caption')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->string('appointment_number')->unique();
                $table->foreignId('online_booking_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('appointment_type_id')->constrained()->restrictOnDelete();
                $table->dateTime('scheduled_start_at')->index();
                $table->dateTime('scheduled_end_at')->index();
                $table->string('status')->default('pending_approval')->index();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('location_note')->nullable();
                $table->text('internal_note')->nullable();
                $table->text('customer_note')->nullable();
                $table->boolean('requested_by_customer')->default(true);
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('declined_by')->nullable()->index();
                $table->timestamp('declined_at')->nullable();
                $table->text('decline_reason')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->index(['branch_id', 'scheduled_start_at'], 'appointments_branch_start_index');
                $table->index(['appointment_type_id', 'scheduled_start_at'], 'appointments_type_start_index');
            });
        }

        if (! Schema::hasTable('appointment_reschedule_requests')) {
            Schema::create('appointment_reschedule_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
                $table->dateTime('requested_start_at');
                $table->dateTime('requested_end_at');
                $table->text('reason')->nullable();
                $table->string('status')->default('pending')->index();
                $table->string('requested_by_type')->nullable();
                $table->unsignedBigInteger('requested_by_id')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointment_status_histories')) {
            Schema::create('appointment_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
                $table->string('old_status')->nullable();
                $table->string('new_status');
                $table->unsignedBigInteger('changed_by')->nullable()->index();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_histories');
        Schema::dropIfExists('appointment_reschedule_requests');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('online_booking_images');
        Schema::dropIfExists('online_booking_item_options');
        Schema::dropIfExists('online_booking_items');
        Schema::dropIfExists('online_bookings');
        Schema::dropIfExists('office_unavailability_periods');
        Schema::dropIfExists('office_availability_windows');
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('measurement_values');
        Schema::dropIfExists('measurement_profiles');
        Schema::dropIfExists('measurement_fields');
        Schema::dropIfExists('garment_options');
        Schema::dropIfExists('garment_option_groups');
        Schema::dropIfExists('garment_categories');
    }
};
