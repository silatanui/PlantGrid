<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dateTime('sla_due_at')->nullable()->after('due_at');
            $table->dateTime('sla_breached_at')->nullable()->after('sla_due_at');
            $table->dateTime('sla_at_risk_notified_at')->nullable()->after('sla_breached_at');
            $table->index('sla_due_at');
        });

        $hours = ['critical' => 4, 'high' => 8, 'medium' => 24, 'low' => 72];
        foreach (DB::table('support_tickets')->select('id', 'priority', 'reported_at', 'due_at', 'status')->get() as $row) {
            $reported = $row->reported_at ? \Carbon\Carbon::parse($row->reported_at) : now();
            $slaDue = $reported->copy()->addHours($hours[$row->priority] ?? 24);
            $updates = ['sla_due_at' => $slaDue];
            if (empty($row->due_at)) {
                $updates['due_at'] = $slaDue;
            }
            if (! in_array($row->status, ['resolved', 'closed'], true) && $slaDue->isPast()) {
                $updates['sla_breached_at'] = $slaDue;
            }
            DB::table('support_tickets')->where('id', $row->id)->update($updates);
        }
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['sla_due_at']);
            $table->dropColumn(['sla_due_at', 'sla_breached_at', 'sla_at_risk_notified_at']);
        });
    }
};
