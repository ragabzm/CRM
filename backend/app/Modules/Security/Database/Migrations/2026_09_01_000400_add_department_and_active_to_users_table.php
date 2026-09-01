<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TODO(Story 4.1): refuse to accept NEW tickets into an inactive department.
 * Deactivation here only guards the tickets that already exist; the write path
 * does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Many-to-one, and nullable: a staff member may legitimately belong
            // to no department. nullOnDelete keeps the user row intact if a
            // department is ever hard-deleted out of band.
            $table->foreignId('department_id')
                ->nullable()
                ->after('email')
                ->constrained('departments')
                ->nullOnDelete();

            // Deactivation, not deletion — see EnsureActiveUser. The row stays
            // so historical attribution ("assigned by Hana Yousef") still
            // resolves a name years later.
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_active');
        });
    }
};
