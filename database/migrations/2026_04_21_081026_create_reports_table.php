<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Main reports table ────────────────────────────────────────────────
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Human-readable ticket ID e.g. RPT-2026-00043
            $table->string('ticket_id', 20)->unique();

            // Reporter — nullable for guest reports
            $table->foreignId('reporter_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Guest reporter info (when not authenticated)
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();

            // ── Classification ────────────────────────────────────────────────
            $table->enum('category', [
                'bug',              // App / UI bug
                'payment',          // Payment issue
                'order',            // Order problem
                'seller',           // Seller misconduct / fraud
                'product',          // Fake / misleading product
                'account',          // Account issue (login, access)
                'content',          // Inappropriate content
                'billing',          // Billing / commission dispute
                'delivery',         // Delivery problem
                'safety',           // Safety / scam concern
                'suggestion',       // Feature request / improvement
                'other',            // Anything else
            ])->default('other');

            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'critical',         // Security / fraud / safety
            ])->default('medium');

            // ── Content ───────────────────────────────────────────────────────
            $table->string('subject');              // Short summary
            $table->text('description');            // Full description
            $table->json('attachments')->nullable(); // Array of file paths

            // ── Related context (optional links for admin context) ────────────
            $table->foreignId('related_order_id')
                ->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('related_seller_id')
                ->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('related_product_id')->nullable();
            $table->string('related_url')->nullable(); // Any specific page URL

            // ── Status lifecycle ──────────────────────────────────────────────
            $table->enum('status', [
                'open',         // Newly filed, not yet reviewed
                'in_review',    // Admin picked it up
                'waiting',      // Waiting for reporter's reply
                'resolved',     // Issue fixed / answered
                'closed',       // Closed without action
                'rejected',     // Spam / invalid / duplicate
            ])->default('open');

            // ── Admin handling ────────────────────────────────────────────────
            $table->foreignId('assigned_to')
                ->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->text('admin_notes')->nullable();   // Internal notes (not shown to reporter)
            $table->string('resolution')->nullable();  // Short resolution summary shown to reporter

            // ── Tracking ──────────────────────────────────────────────────────
            $table->string('reporter_ip', 45)->nullable();  // IPv4 or IPv6
            $table->string('reporter_locale', 10)->nullable(); // e.g. 'en', 'my'
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Duplicate detection
            $table->unsignedBigInteger('duplicate_of')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('ticket_id');
            $table->index('status');
            $table->index('category');
            $table->index('priority');
            $table->index(['reporter_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('created_at');
        });

        // ── Report comments (threaded back-and-forth) ─────────────────────────
        Schema::create('report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->text('body');
            $table->json('attachments')->nullable();

            // 'reporter' | 'admin' | 'system'
            $table->enum('author_type', ['reporter', 'admin', 'system'])->default('reporter');

            // Internal note — visible to admins only
            $table->boolean('is_internal')->default(false);

            $table->timestamps();
            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_comments');
        Schema::dropIfExists('reports');
    }
};