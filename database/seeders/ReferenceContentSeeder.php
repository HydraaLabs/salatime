<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceContentSeeder extends Seeder
{
    public function run(): void
    {
        $quranTables = ['translators', 'chapters', 'chapter_translations', 'chapter_details', 'verse_translations'];
        $emptyTables = array_filter($quranTables, fn ($table) => ! DB::table($table)->exists());
        if (count($emptyTables) === count($quranTables)) {
            DB::transaction(fn () => $this->call([
                \Database\Seeders\Quran\Translation\TranslatorSeeder::class,
                \Database\Seeders\Quran\Chapter\ChapterSeeder::class,
                \Database\Seeders\Quran\Chapter\ChapterTranslationSeeder::class,
                \Database\Seeders\Quran\Chapter\ChapterTranslationSeederBangla::class,
                \Database\Seeders\Quran\Chapter\ChapterTranslationSeederSp::class,
                \Database\Seeders\Quran\Chapter\ChapterTranslationSeederAr::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder2::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder3::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder4::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder5::class,
                \Database\Seeders\Quran\Chapter\ChapterDetailSeeder6::class,
                \Database\Seeders\Quran\Chapter\Verse\Arabic\JalalSeeder::class,
                \Database\Seeders\Quran\Chapter\Verse\Bangla\VerseBanglaZohurulHoqueSeeder::class,
                \Database\Seeders\Quran\Chapter\Verse\Bangla\VerseBanglaZohurulHoqueSeeder2::class,
                \Database\Seeders\Quran\Chapter\Verse\Bangla\VerseBanglaZohurulHoqueSeeder3::class,
                \Database\Seeders\Quran\Chapter\Verse\Bangla\VerseBanglaZohurulHoqueSeeder4::class,
                \Database\Seeders\Quran\Chapter\Verse\English\VerseEnglishAhmedAliSeeder::class,
                \Database\Seeders\Quran\Chapter\Verse\English\VerseEnglishAhmedAliSeeder2::class,
                \Database\Seeders\Quran\Chapter\Verse\English\VerseEnglishAhmedAliSeeder3::class,
                \Database\Seeders\Quran\Chapter\Verse\English\VerseEnglishAhmedAliSeeder4::class,
                \Database\Seeders\Quran\Chapter\Verse\Spanish\VerseSpanishBornezSeeder::class,
            ]));
        } elseif ($emptyTables !== []) {
            $this->command?->warn('Existing Quran data detected: preserving it. Import missing content through the administration interface.');
        }

        $catalogues = [
            'dhikrs' => Quran\Dhikr\DhikrSeeder::class,
            'duas' => Quran\Dua\DuaSeeder::class,
            'sifat_names' => Quran\SifatName\SifatNameSeeder::class,
            'haram_codes' => Quran\HaramCode\HaramCodeSeeder::class,
            'categories' => Quran\Category\CategorySeeder::class,
            'payment_methods' => Quran\PaymentMethod\PaymentMethodSeeder::class,
            'reciters' => Quran\Reciter\ReciterSeeder::class,
        ];
        foreach ($catalogues as $table => $seeder) {
            if (! DB::table($table)->exists()) {
                DB::transaction(fn () => $this->call($seeder));
            }
        }
    }
}
