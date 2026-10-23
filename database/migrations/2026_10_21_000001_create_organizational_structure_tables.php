<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('organizational_unit_types')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('organizational_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organizational_unit_id', 'name']);
        });

        Schema::create('organizational_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('organizational_position_id')->constrained('organizational_positions')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('assignee_name', 160);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(
                ['organizational_position_id', 'start_date', 'end_date'],
                'org_assign_pos_dates_idx'
            );
        });

        Schema::create('organizational_unit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 40);
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('user_unit_access', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['organizational_unit_id', 'model_type', 'model_id']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_unit_access');
        Schema::dropIfExists('organizational_unit_histories');
        Schema::dropIfExists('organizational_assignments');
        Schema::dropIfExists('organizational_positions');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('organizational_unit_types');
    }
};
