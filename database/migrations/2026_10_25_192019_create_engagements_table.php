<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers');
            $table->unsignedInteger('creator_id')->nullable();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('estimate_id')->nullable();
            $table->foreign('estimate_id')->references('id')->on('estimates')->nullOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedBigInteger('contract_value')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->date('start_date')->nullable();
            $table->boolean('auto_invoice')->default(false);
            $table->timestamps();
        });

        Schema::create('engagement_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->unsignedBigInteger('amount');
            $table->decimal('percent', 6, 3)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->date('settled_at')->nullable();
            $table->string('settled_reference')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('engagement_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('engagement_id');
        });

        Schema::dropIfExists('engagement_milestones');
        Schema::dropIfExists('engagements');
    }
};
