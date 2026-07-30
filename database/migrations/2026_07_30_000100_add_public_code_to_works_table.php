<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function up(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->string('public_code', 20)->nullable()->after('id');
        });

        $used = [];

        DB::table('works')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($works) use (&$used): void {
                foreach ($works as $work) {
                    do {
                        $code = $this->generateCode();
                    } while (isset($used[$code]));

                    $used[$code] = true;
                    DB::table('works')
                        ->where('id', $work->id)
                        ->update(['public_code' => $code]);
                }
            });

        Schema::table('works', function (Blueprint $table): void {
            $table->unique('public_code');
        });

        Schema::table('works', function (Blueprint $table): void {
            $table->string('public_code', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });
    }

    private function generateCode(): string
    {
        $suffix = '';
        $maximum = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < 10; $index++) {
            $suffix .= self::ALPHABET[random_int(0, $maximum)];
        }

        return 'YM-W-'.$suffix;
    }
};
