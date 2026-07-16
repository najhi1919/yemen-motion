<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(
                "Unsupported database driver [{$driver}] for work category ID reservation."
            );
        }

        $legacyMax = (int) (DB::table('works')
            ->whereNotNull('category_id')
            ->max('category_id') ?? 0);

        $catalogMax = (int) (DB::table('work_categories')->max('id') ?? 0);

        if ($driver === 'pgsql') {
            $this->reservePostgresAllocator($legacyMax, $catalogMax);

            return;
        }

        $this->reserveSqliteAllocator($legacyMax, $catalogMax);
    }

    public function down(): void
    {
        // Intentionally a no-op: lowering the allocator could reuse identifiers that
        // were already issued. Earlier taxonomy migrations handle full table rollback.
    }

    private function reservePostgresAllocator(int $legacyMax, int $catalogMax): void
    {
        $sequence = DB::selectOne(
            "SELECT pg_get_serial_sequence('work_categories', 'id') AS sequence_name"
        );

        $sequenceName = $sequence?->sequence_name;

        if (! is_string($sequenceName) || $sequenceName === '') {
            throw new RuntimeException('Unable to resolve the work_categories.id PostgreSQL sequence.');
        }

        $sequenceState = DB::selectOne(
            'SELECT last_value, is_called FROM '.$this->quotePostgresIdentifier($sequenceName)
        );

        if ($sequenceState === null) {
            throw new RuntimeException('Unable to read the work_categories.id PostgreSQL sequence.');
        }

        $lastValue = (int) $sequenceState->last_value;
        $isCalled = in_array($sequenceState->is_called, [true, 1, '1', 't', 'true'], true);
        $currentNext = $isCalled ? $lastValue + 1 : $lastValue;
        $targetNext = max(1, $legacyMax + 1, $catalogMax + 1, $currentNext);

        DB::select(
            'SELECT setval(CAST(? AS regclass), ?, false)',
            [$sequenceName, $targetNext]
        );
    }

    private function reserveSqliteAllocator(int $legacyMax, int $catalogMax): void
    {
        $sequence = DB::table('sqlite_sequence')
            ->where('name', 'work_categories')
            ->first(['seq']);

        $currentNext = $sequence === null ? 1 : ((int) $sequence->seq) + 1;
        $targetNext = max(1, $legacyMax + 1, $catalogMax + 1, $currentNext);
        $storedSequence = $targetNext - 1;

        $updated = DB::table('sqlite_sequence')
            ->where('name', 'work_categories')
            ->update(['seq' => $storedSequence]);

        if ($updated === 0 && ! DB::table('sqlite_sequence')->where('name', 'work_categories')->exists()) {
            DB::table('sqlite_sequence')->insert([
                'name' => 'work_categories',
                'seq' => $storedSequence,
            ]);
        }
    }

    private function quotePostgresIdentifier(string $identifier): string
    {
        return implode('.', array_map(
            static fn (string $part): string => '"'.str_replace('"', '""', $part).'"',
            explode('.', $identifier)
        ));
    }
};
