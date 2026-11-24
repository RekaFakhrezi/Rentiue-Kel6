<?php
declare(strict_types=1);

namespace Rentique\Storage;

use RuntimeException;

/** One local document; the persistent lock also coordinates readers across atomic replacements. */
final class JsonStore
{
    private const TABLES = ['users', 'clothing_categories', 'clothings', 'rentals', 'rental_items', 'invoices', 'payments', 'rental_returns', 'inventory_logs'];
    private string $path;
    private ?array $state = null;
    private bool $readOnly = false;
    private bool $dirty = false;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__, 3).'/storage/data.json';
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder penyimpanan lokal tidak dapat dibuat.');
        }
        // Initialization uses the very same lock as all later reads and writes.
        $this->transaction(static fn() => null, true);
    }

    public function transaction(callable $action, bool $readOnly = false): mixed
    {
        if ($this->state !== null) {
            if ($this->readOnly && !$readOnly) {
                throw new RuntimeException('Transaksi baca tidak boleh mengubah data.');
            }
            return $action();
        }
        $lock = fopen($this->path.'.lock', 'c+b');
        if ($lock === false) { throw new RuntimeException('Penyimpanan lokal tidak dapat dikunci.'); }
        try {
            // ponytail: one document-wide lock; split storage only if local traffic outgrows it.
            if (!flock($lock, $readOnly ? LOCK_SH : LOCK_EX)) {
                throw new RuntimeException('Penyimpanan lokal tidak dapat dikunci.');
            }
            if (!is_file($this->path)) {
                if ($readOnly) {
                    flock($lock, LOCK_UN);
                    if (!flock($lock, LOCK_EX)) { throw new RuntimeException('Inisialisasi tidak dapat dikunci.'); }
                }
                clearstatcache(true, $this->path);
                if (!is_file($this->path)) {
                    $initial = require dirname(__DIR__, 3).'/database/seed.php';
                    $this->validate($initial);
                    $this->replaceFile($initial);
                }
            }
            $raw = file_get_contents($this->path);
            if ($raw === false) { throw new RuntimeException('Data lokal tidak dapat dibaca.'); }
            try {
                $state = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new RuntimeException('Data lokal rusak; berkas dipertahankan, pulihkan dari cadangan.', 0, $error);
            }
            $this->validate($state);
            $this->state = $state;
            $this->readOnly = $readOnly;
            $this->dirty = false;
            $result = $action();
            if ($this->dirty) { $this->replaceFile($this->state); }
            return $result;
        } finally {
            $this->state = null;
            $this->readOnly = false;
            $this->dirty = false;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validate(mixed $state): void
    {
        if (!is_array($state) || ($state['version'] ?? null) !== 1) {
            throw new RuntimeException('Format data lokal rusak atau tidak didukung; berkas tidak diubah.');
        }
        foreach (self::TABLES as $table) {
            if (!isset($state[$table]) || !is_array($state[$table]) || !array_is_list($state[$table])) {
                throw new RuntimeException('Tabel data lokal rusak: '.$table.'. Berkas tidak diubah.');
            }
            $ids = [];
            foreach ($state[$table] as $row) {
                if (!is_array($row) || !isset($row['id']) || !is_int($row['id']) || $row['id'] < 1 || isset($ids[$row['id']])) {
                    throw new RuntimeException('Rekaman data lokal rusak: '.$table.'. Berkas tidak diubah.');
                }
                $ids[$row['id']] = true;
            }
        }
    }

    private function replaceFile(array $state): void
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
        $temporary = tempnam(dirname($this->path), '.rentique-');
        if ($temporary === false) { throw new RuntimeException('Berkas sementara tidak dapat dibuat.'); }
        try {
            $file = fopen($temporary, 'wb');
            if ($file === false) { throw new RuntimeException('Berkas sementara tidak dapat dibuka.'); }
            try {
                $length = strlen($json);
                for ($offset = 0; $offset < $length;) {
                    $written = fwrite($file, substr($json, $offset));
                    if ($written === false || $written === 0) { throw new RuntimeException('Data lokal gagal ditulis.'); }
                    $offset += $written;
                }
                if (!fflush($file) || !fsync($file)) { throw new RuntimeException('Data lokal gagal disimpan.'); }
            } finally { fclose($file); }
            if (!rename($temporary, $this->path)) { throw new RuntimeException('Data lokal gagal diganti secara atomik.'); }
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
    }

    public function rows(string $table): array
    {
        if (!in_array($table, self::TABLES, true)) { throw new RuntimeException('Tabel penyimpanan tidak dikenal.'); }
        return $this->transaction(fn() => $this->state[$table], true);
    }

    public function find(string $table, int $id): ?array
    {
        foreach ($this->rows($table) as $row) {
            if ($row['id'] === $id) { return $row; }
        }
        return null;
    }

    public function insert(string $table, array $fields): int
    {
        return $this->transaction(function () use ($table, $fields) {
            $rows = $this->rows($table);
            $id = $rows ? max(array_column($rows, 'id')) + 1 : 1;
            $this->state[$table][] = ['id' => $id] + $fields + ['created_at' => date('c'), 'updated_at' => date('c')];
            $this->dirty = true;
            return $id;
        });
    }

    public function update(string $table, int $id, array $fields): void
    {
        $this->transaction(function () use ($table, $id, $fields) {
            foreach ($this->rows($table) as $index => $row) {
                if ($row['id'] === $id) {
                    unset($fields['id'], $fields['created_at']);
                    $this->state[$table][$index] = array_replace($row, $fields, ['updated_at' => date('c')]);
                    $this->dirty = true;
                    return;
                }
            }
            throw new RuntimeException('Rekaman penyimpanan tidak ditemukan.');
        });
    }
}
