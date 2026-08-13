<?php

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Null = use the standard post_price setting.
            $table->decimal('listing_fee', 8, 2)->nullable()->after('filter_group_id');
        });

        DB::table('settings')->where('key_id', 'post_price')->update(['value' => '0.99']);

        $categoryIds = CategoryTranslation::query()
            ->whereIn('name', ['Cars', 'Accommodation'])
            ->whereHas('category', fn ($q) => $q->whereNull('parent_id'))
            ->pluck('category_id');

        Category::whereIn('id', $categoryIds)->update(['listing_fee' => 2.99]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('listing_fee');
        });

        DB::table('settings')->where('key_id', 'post_price')->update(['value' => '5.00']);
    }
};
