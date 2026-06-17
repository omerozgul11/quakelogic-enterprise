<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When false, the UPS poller leaves this shipment alone so a manually-set
     * status and delivery details stick instead of being overwritten on sync.
     */
    public function up(): void
    {
        Schema::table('proposal_mailings', function (Blueprint $table) {
            $table->boolean('auto_track')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_mailings', function (Blueprint $table) {
            $table->dropColumn('auto_track');
        });
    }
};
