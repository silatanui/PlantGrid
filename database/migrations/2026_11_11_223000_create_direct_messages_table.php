<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 180);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
            $table->index(['sender_id', 'recipient_id']);
        });

        $daniel = DB::table('users')->where('email', 'daniel.mwangi@northridge.example')->value('id');
        $faith = DB::table('users')->where('email', 'faith.cheruiyot@northridge.example')->value('id');
        $grace = DB::table('users')->where('email', 'grace.wanjiku@northridge.example')->value('id');

        if ($daniel && $faith) {
            DB::table('direct_messages')->insert([
                [
                    'sender_id' => $daniel,
                    'recipient_id' => $faith,
                    'subject' => 'Packaging printer case',
                    'body' => "Faith, can you pick up PG-2026-0148 on the packaging label printer when you have a moment? The floor team is waiting on labels.\n\nThanks,\nDaniel",
                    'read_at' => null,
                    'created_at' => now()->subHours(5),
                    'updated_at' => now()->subHours(5),
                ],
                [
                    'sender_id' => $faith,
                    'recipient_id' => $daniel,
                    'subject' => 'Re: Packaging printer case',
                    'body' => "On it. I will check PG-LBL-0142 and update the case once the power supply swap is confirmed.",
                    'read_at' => now()->subHours(4),
                    'created_at' => now()->subHours(4),
                    'updated_at' => now()->subHours(4),
                ],
            ]);
        }

        if ($grace && $faith) {
            DB::table('direct_messages')->insert([
                'sender_id' => $grace,
                'recipient_id' => $faith,
                'subject' => 'QA printer jam follow-up',
                'body' => 'Hi Faith - the QA lab still sees intermittent jams on PG-PRT-0401 after clearing the tray. Can you advise when you are free?',
                'read_at' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_messages');
    }
};
