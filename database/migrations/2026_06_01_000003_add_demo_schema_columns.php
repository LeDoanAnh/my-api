<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'type')) {
                $table->enum('type', ['consumable', 'returnable'])->default('returnable')->after('status');
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations', 'location_code')) {
                $table->string('location_code', 20)->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('locations', 'address')) {
                $table->string('address')->nullable()->after('location_name');
            }
        });

        Schema::table('asset_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_requests', 'status')) {
                $table->string('status')->default('pending')->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (Schema::hasColumn('asset_requests', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'address')) {
                $table->dropColumn('address');
            }

            if (Schema::hasColumn('locations', 'location_code')) {
                $table->dropUnique(['location_code']);
                $table->dropColumn('location_code');
            }
        });

        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
