<?php

namespace Tests\Feature;

use App\Exceptions\BackupException;
use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The deletion rules, which are the only part of the backup feature that can
 * destroy something. Taking a backup is verified end to end against the real
 * server (mongodump + restore); what is covered here is every path that can
 * remove an archive, because a wrong answer there is unrecoverable.
 *
 * Archives are faked as small files on disk — these tests are about which
 * files get deleted and when, not about dumping a database.
 */
class BackupRetentionTest extends TestCase
{
    private string $dir;
    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/nautiqs-backup-test-' . uniqid();
        File::ensureDirectoryExists($this->dir);

        config([
            'backup.path'           => $this->dir,
            'backup.retention_days' => 30,
            'backup.keep_minimum'   => 2,
        ]);

        BackupRun::truncate();
        $this->service = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        BackupRun::truncate();

        parent::tearDown();
    }

    /** Create a fake archive on disk plus the row describing it. */
    private function archive(int $ageDays, bool $downloaded = false, string $status = BackupRun::STATUS_OK): BackupRun
    {
        $when = now()->subDays($ageDays);
        $name = 'nautiqs-' . $when->format('Ymd-His') . '-' . uniqid() . '.tar.gz';

        File::put($this->dir . '/' . $name, str_repeat('x', 1024));

        return BackupRun::create([
            'filename'      => $name,
            'status'        => $status,
            'trigger'       => BackupRun::TRIGGER_SCHEDULED,
            'size_bytes'    => 1024,
            'started_at'    => $when,
            'finished_at'   => $when,
            'downloaded_at' => $downloaded ? $when->copy()->addDay() : null,
        ]);
    }

    public function test_nothing_is_pruned_when_there_is_nothing_to_prune(): void
    {
        $this->assertSame(
            ['deleted' => 0, 'freed_bytes' => 0, 'kept' => 0],
            $this->service->prune(),
        );
    }

    public function test_recent_archives_are_never_pruned_even_once_downloaded(): void
    {
        $this->archive(ageDays: 1, downloaded: true);
        $this->archive(ageDays: 10, downloaded: true);
        $this->archive(ageDays: 29, downloaded: true);

        $result = $this->service->prune();

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(3, BackupRun::whereNull('deleted_at')->count());
    }

    public function test_an_old_archive_that_was_never_downloaded_is_kept(): void
    {
        // Four fresh ones so keep_minimum is satisfied by others.
        for ($i = 1; $i <= 4; $i++) {
            $this->archive(ageDays: $i);
        }
        $old = $this->archive(ageDays: 40, downloaded: false);

        $result = $this->service->prune();

        $this->assertSame(0, $result['deleted'], 'An archive with no copy off the server must never be deleted.');
        $this->assertTrue($old->refresh()->isAvailable());
    }

    public function test_an_old_downloaded_archive_is_pruned(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->archive(ageDays: $i);
        }
        $old = $this->archive(ageDays: 40, downloaded: true);
        $path = $old->path();

        $result = $this->service->prune();

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1024, $result['freed_bytes']);
        $this->assertFileDoesNotExist($path);

        // The row survives as the record that the backup existed.
        $old->refresh();
        $this->assertNotNull($old->deleted_at);
        $this->assertSame(BackupRun::STATUS_OK, $old->status);
    }

    public function test_the_newest_archives_are_protected_from_every_deletion_path(): void
    {
        // Everything is old and downloaded — only keep_minimum stands between
        // this call and an empty server.
        $runs = collect(range(1, 5))->map(fn ($i) => $this->archive(ageDays: 30 + $i, downloaded: true));

        $result = $this->service->prune();

        $this->assertSame(3, $result['deleted']);
        $this->assertSame(2, BackupRun::whereNull('deleted_at')->count(), 'keep_minimum archives must remain.');

        // …and the same holds when forced.
        $forced = $this->service->prune(force: true);
        $this->assertSame(0, $forced['deleted']);
        $this->assertSame(2, BackupRun::whereNull('deleted_at')->count());
    }

    public function test_force_prunes_old_archives_that_were_never_downloaded(): void
    {
        for ($i = 1; $i <= 2; $i++) {
            $this->archive(ageDays: $i);            // protected by keep_minimum
        }
        $this->archive(ageDays: 40, downloaded: false);
        $this->archive(ageDays: 50, downloaded: false);

        $this->assertSame(0, $this->service->prune()['deleted']);
        $this->assertSame(2, $this->service->prune(force: true)['deleted']);
    }

    public function test_a_protected_archive_cannot_be_deleted_individually(): void
    {
        $newest = $this->archive(ageDays: 1);
        $this->archive(ageDays: 2);
        $older = $this->archive(ageDays: 40, downloaded: true);

        $this->assertTrue($this->service->isProtected($newest));
        $this->assertFalse($this->service->deleteFile($newest));
        $this->assertFileExists($newest->path());

        $this->assertFalse($this->service->isProtected($older));
        $this->assertTrue($this->service->deleteFile($older));
    }

    public function test_failed_runs_are_never_counted_as_archives(): void
    {
        $this->archive(ageDays: 40, downloaded: true, status: BackupRun::STATUS_FAILED);

        $this->assertCount(0, $this->service->availableRuns());
        $this->assertSame(0, $this->service->prune(force: true)['deleted']);
    }

    public function test_a_row_whose_file_vanished_is_not_available(): void
    {
        $run = $this->archive(ageDays: 1);
        unlink($run->path());

        $this->assertFalse($run->refresh()->isAvailable());
        $this->assertCount(0, $this->service->availableRuns());
    }

    public function test_archivable_excludes_protected_and_recent_archives(): void
    {
        $this->archive(ageDays: 1);
        $this->archive(ageDays: 2);
        $this->archive(ageDays: 40);
        $this->archive(ageDays: 50);

        $archivable = $this->service->archivableRuns();

        $this->assertCount(2, $archivable);
        $this->assertTrue($archivable->every(fn (BackupRun $r) => $r->ageInDays() >= 30));
    }

    public function test_prunable_is_the_downloaded_subset_of_archivable(): void
    {
        $this->archive(ageDays: 1);
        $this->archive(ageDays: 2);
        $this->archive(ageDays: 40, downloaded: true);
        $this->archive(ageDays: 50, downloaded: false);

        $this->assertCount(2, $this->service->archivableRuns());
        $this->assertCount(1, $this->service->prunableRuns());
    }

    public function test_bundle_skips_missing_files_and_refuses_an_empty_bundle(): void
    {
        $present = $this->archive(ageDays: 40, downloaded: true);
        $gone    = $this->archive(ageDays: 41, downloaded: true);
        unlink($gone->path());

        $zip = $this->service->bundle(collect([$present, $gone]));
        $this->assertFileExists($zip);

        $archive = new \ZipArchive();
        $archive->open($zip);
        $this->assertSame(1, $archive->numFiles);
        $this->assertSame($present->filename, $archive->getNameIndex(0));
        $archive->close();
        unlink($zip);

        $this->expectException(BackupException::class);
        $this->service->bundle(collect([$gone]));
    }

    public function test_marking_downloaded_is_what_unlocks_pruning(): void
    {
        for ($i = 1; $i <= 2; $i++) {
            $this->archive(ageDays: $i);
        }
        $old = $this->archive(ageDays: 40);

        $this->assertSame(0, $this->service->prune()['deleted']);

        $this->service->markDownloaded(collect([$old]), 'admin@nautiqs.fr');

        $old->refresh();
        $this->assertNotNull($old->downloaded_at);
        $this->assertSame('admin@nautiqs.fr', $old->downloaded_by);
        $this->assertSame(1, $this->service->prune()['deleted']);
    }
}
