<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            if (!Schema::hasColumn('playlists', 'image_url')) {
                $table->string('image_url')->nullable()->after('description');
            }
            if (!Schema::hasColumn('playlists', 'owner')) {
                $table->string('owner')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('playlists', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('owner');
            }
            if (!Schema::hasColumn('playlists', 'tracks_count')) {
                $table->integer('tracks_count')->default(0)->after('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'owner', 'is_public', 'tracks_count']);
        });
    }
};
