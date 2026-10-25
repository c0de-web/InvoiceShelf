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
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('item_categories');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'parent_id', 'name']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('item_category_id')->nullable()->after('unit_id')->constrained('item_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_category_id');
        });

        Schema::dropIfExists('item_categories');
    }
};
