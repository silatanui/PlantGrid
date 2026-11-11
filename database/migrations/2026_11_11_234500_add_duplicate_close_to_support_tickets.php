<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('close_reason', 40)->nullable()->after('resolution_notes');
            $table->foreignId('duplicate_of_ticket_id')
                ->nullable()
                ->after('close_reason')
                ->constrained('support_tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_ticket_id');
            $table->dropColumn('close_reason');
        });
    }
};
