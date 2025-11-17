<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            
            // Order reference
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            
            // Delivery method choice
            $table->enum('delivery_method', ['supplier', 'platform'])->default('supplier');
            
            // Supplier information
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            
            // Platform logistics information (if using platform delivery)
            $table->foreignId('platform_courier_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->decimal('platform_delivery_fee', 10, 2)->default(0);
            $table->string('assigned_driver_name')->nullable();
            $table->string('assigned_driver_phone')->nullable();
            $table->string('assigned_vehicle_type')->nullable();
            $table->string('assigned_vehicle_number')->nullable();
            
            // Delivery addresses
            $table->text('pickup_address');
            $table->text('delivery_address');
            
            // Delivery timeline
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('estimated_delivery_date')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Tracking information
            $table->string('tracking_number')->unique()->nullable();
            $table->string('carrier_name')->nullable();
            
            // Delivery status
            $table->enum('status', [
                'pending',           // Waiting for supplier to choose method
                'awaiting_pickup',   // Waiting for pickup
                'picked_up',         // Items picked up
                'in_transit',        // On the way to customer
                'out_for_delivery',  // With delivery personnel
                'delivered',         // Successfully delivered
                'failed',            // Delivery failed
                'cancelled',         // Delivery cancelled
                'returned'           // Returned to supplier
            ])->default('pending');
            
            // Delivery details
            $table->decimal('package_weight', 8, 2)->nullable(); // in kg
            $table->json('package_dimensions')->nullable(); // {length, width, height}
            $table->integer('package_count')->default(1);
            
            // Delivery proof and notes
            $table->string('delivery_proof_image')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_signature')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Financial information
            $table->decimal('actual_delivery_cost', 10, 2)->nullable();
            $table->boolean('delivery_cost_paid')->default(false);
            $table->timestamp('delivery_cost_paid_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};