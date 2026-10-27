<?php

namespace Tests\Feature;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\ReceivingImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchService;
use App\Services\ReceivingCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReceivingCsvTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Supplier $supplier;
    private MaterialSubgroup $subgroup;
    private MaterialGroup $group;
    private PurchaseOrder $po;
    private array $scratch = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::create(['name' => 'CSV operator', 'email' => 'csv@test.local', 'password' => bcrypt('password')]);
        $this->actor->forceFill(['can_manage_operations' => true])->save();
        $this->actingAs($this->actor);
        $this->supplier = Supplier::create(['code' => 'SUP', 'name' => 'Supplier']);
        $this->group = MaterialGroup::create(['name' => 'Fish', 'unit' => 'kg']);
        $this->subgroup = MaterialSubgroup::create(['material_group_id' => $this->group->id, 'name' => 'Fish loin', 'code' => 'SUB']);
        $this->po = PurchaseOrder::create(['po_number' => 'PO-CSV', 'buyer_name' => 'Buyer', 'order_date' => '2026-01-01',
            'deadline' => '2026-12-31', 'status' => 'active', 'created_by' => $this->actor->id]);
    }

    protected function tearDown(): void
    {
        foreach ($this->scratch as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function path(string $suffix): string
    {
        $dir = storage_path('audit/tmp');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/csv-'.Str::uuid().$suffix;
        $this->scratch[] = $path;
        return $path;
    }

    private function csv(array $changes = [], string $extra = ''): string
    {
        return implode(',', ReceivingCsv::HEADERS)."\n".$this->row($changes).$extra;
    }

    private function row(array $changes = []): string
    {
        $data = array_replace(['po_number' => 'PO-CSV', 'subgroup' => 'SUB', 'supplier' => 'SUP', 'weight_kg' => '100.50',
            'container_type' => 'Keranjang', 'container_count' => '2', 'split_per_container' => 'true', 'received_date' => '2024-02-29', 'doc_number' => 'DOC'], $changes);
        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, array_values($data), ',', '"', '');
        rewind($stream);
        $row = stream_get_contents($stream);
        fclose($stream);
        return $row;
    }

    private function preview(string $bytes)
    {
        $path = $this->path('.csv');
        file_put_contents($path, $bytes);
        return $this->post(route('receiving.import'), ['csv_file' => new UploadedFile($path, 'receiving.csv', 'text/csv', null, true)]);
    }

    private function envelope(string $bytes): string
    {
        return $this->preview($bytes)->assertOk()->viewData('envelope');
    }

    private function snapshot(): array
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table->name] = DB::table($table->name)->get()->map(fn ($row) => (array) $row)->all();
        }
        return $snapshot;
    }

    public function test_clean_and_invalid_previews_do_not_write_any_table_or_counter(): void
    {
        $before = $this->snapshot();
        $response = $this->preview("\xEF\xBB\xBF".$this->csv(['container_type' => "Box,\nlarge", 'doc_number' => '<script>alert(1)</script>']));
        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('class="table-responsive csv-preview-scroll" role="region" tabindex="0"', false)
            ->assertSee('gulir horizontal untuk melihat semua kolom')
            ->assertSee('.csv-preview-scroll :is(th,td):is(:nth-child(2),:nth-child(3),:nth-child(4),:nth-child(9),:nth-child(10)){white-space:nowrap}', false);
        $this->assertNotNull($response->viewData('envelope'));
        $this->assertSame($before, $this->snapshot());
        $bad = $this->preview($this->csv(['weight_kg' => 'NaN']));
        $bad->assertOk();
        $this->assertNull($bad->viewData('envelope'));
        $this->assertSame($before, $this->snapshot());
        $this->assertEmpty(session()->getOldInput());
    }

    public static function invalidFields(): array
    {
        $cases = [];
        foreach (['0', '-1', 'NaN', 'INF', '1e2', '1.001', '10000000000', '.5', '1,5'] as $value) {
            $cases['weight '.$value] = ['weight_kg', $value];
        }
        foreach (['0', '-1', '101', '1.0', '1e1', '01'] as $value) {
            $cases['count '.$value] = ['container_count', $value];
        }
        foreach (['yes', '', '2'] as $value) {
            $cases['boolean '.$value] = ['split_per_container', $value];
        }
        foreach (['2025-02-29', '2024-02-30', '2026-1-01', 'tomorrow', ''] as $value) {
            $cases['date '.$value] = ['received_date', $value];
        }
        foreach (['po_number', 'subgroup', 'supplier'] as $field) {
            foreach (['', 'unknown', '=SUP', '+SUP', '@SUP', '-SUP'] as $value) {
                $cases[$field.' '.$value] = [$field, $value];
            }
        }
        $cases['container text'] = ['container_type', str_repeat('x', 101)];
        $cases['empty container'] = ['container_type', ''];
        $cases['document text'] = ['doc_number', str_repeat('x', 101)];
        return $cases;
    }

    #[DataProvider('invalidFields')]
    public function test_strict_field_validation(string $field, string $value): void
    {
        $result = app(ReceivingCsv::class)->parse($this->csv([$field => $value]));
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Baris 2: kolom '.$field, implode(' ', $result['errors']));
        $this->assertDatabaseCount('receiving_imports', 0);
    }

    public function test_structural_errors_bounds_and_physical_line_numbers(): void
    {
        $csv = app(ReceivingCsv::class);
        $header = implode(',', ReceivingCsv::HEADERS)."\n";
        foreach (["\xFF", "\0", "\x01", '', $header, $header."\n", $header.'"unclosed',
            $header.'bad"quote,x', $header.'"closed"bad,x', $header.'one,two',
            str_replace('doc_number', 'supplier', $header).$this->row(),
            str_replace('doc_number', 'unknown', $header).$this->row(),
            str_replace('po_number,', '', $header).$this->row(),
            str_repeat('x', ReceivingCsv::MAX_BYTES + 1),
            $this->csv(['container_type' => str_repeat('x', 16385)]),
            $header.str_repeat($this->row(), 501)] as $bytes) {
            $this->assertNotEmpty($csv->parse($bytes)['errors'], substr($bytes, 0, 80));
        }
        $result = $csv->parse($this->csv(['container_type' => "Box\nlarge"], $this->row(['weight_kg' => 'bad'])));
        $this->assertStringContainsString('Baris 4: kolom weight_kg', implode(' ', $result['errors']));
        $result = $csv->parse($header.str_repeat("\n", 150));
        $this->assertCount(100, $result['errors']);
        $this->assertSame(50, $result['remaining']);
        $rows = '';
        for ($i = 0; $i < 11; $i++) {
            $rows .= $this->row(['container_count' => '100', 'doc_number' => (string) $i]);
        }
        $this->assertStringContainsString('1.000', implode(' ', $csv->parse($header.$rows)['errors']));
        $this->assertNotEmpty($csv->parse($this->csv(['weight_kg' => '0.01', 'container_count' => '100']))['errors']);
        $this->assertEmpty($csv->parse($this->csv(['weight_kg' => '9999999999.99', 'container_count' => '100']))['errors']);
        $this->preview(str_repeat('x', ReceivingCsv::MAX_BYTES + 1))->assertSessionHasErrors('csv_file');
    }

    public function test_exact_resolution_and_ambiguous_or_inactive_references(): void
    {
        $csv = app(ReceivingCsv::class);
        $this->assertEmpty($csv->parse($this->csv(['supplier' => 's-u-p', 'subgroup' => 'Fish loin']))['errors']);
        MaterialSubgroup::create(['material_group_id' => $this->group->id, 'name' => 'Fish loin', 'code' => 'OTHER']);
        Supplier::create(['code' => 'OTHER', 'name' => 'Supplier']);
        $this->assertNotEmpty($csv->parse($this->csv(['subgroup' => 'Fish loin', 'supplier' => 'Supplier']))['errors']);
        foreach ([$this->subgroup, $this->group, $this->supplier] as $model) {
            $model->update(['is_active' => false]);
            $this->assertNotEmpty($csv->parse($this->csv())['errors']);
            $model->update(['is_active' => true]);
        }
        foreach (['completed', 'cancelled'] as $status) {
            $this->po->update(['status' => $status]);
            $this->assertNotEmpty($csv->parse($this->csv())['errors']);
        }
        $this->po->delete();
        $this->assertNotEmpty($csv->parse($this->csv())['errors']);
    }

    public function test_duplicate_rows_show_both_lines_and_digest_is_normalized_and_sorted(): void
    {
        $csv = app(ReceivingCsv::class);
        $result = $csv->parse($this->csv([], $this->row(['weight_kg' => '100.5', 'split_per_container' => '1'])));
        $this->assertStringContainsString('Baris 3: kolom CSV duplikat dengan Baris 2', implode(' ', $result['errors']));
        $a = $csv->parse($this->csv([], $this->row(['doc_number' => 'OTHER'])));
        $b = $csv->parse($this->csv(['doc_number' => 'OTHER'], $this->row(['weight_kg' => '100.5', 'split_per_container' => '1'])));
        $this->assertSame($a['digest'], $b['digest']);
    }

    public function test_permissions_template_and_hidden_controls(): void
    {
        $this->get(route('receiving.import.template'))->assertOk()->assertSee(implode(',', ReceivingCsv::HEADERS));
        $this->actor->forceFill(['can_manage_operations' => false, 'can_view_audit' => true])->save();
        $this->get(route('receiving.index'))->assertOk()->assertDontSee('Berkas CSV penerimaan');
        $this->post(route('receiving.import'))->assertForbidden();
        $this->post(route('receiving.import.commit'))->assertForbidden();
        $this->get(route('receiving.import.template'))->assertForbidden();
        auth()->logout();
        $this->post(route('receiving.import'))->assertRedirect(route('login'));
        $this->post(route('receiving.import.commit'))->assertRedirect(route('login'));
    }

    public function test_invalid_envelopes_actor_expiry_tampering_and_no_session_payload(): void
    {
        $envelope = $this->envelope($this->csv());
        $before = $this->snapshot();
        foreach ([null, [], 'truncated', substr($envelope, 0, -3), str_repeat('x', ReceivingCsv::MAX_ENVELOPE + 1)] as $bad) {
            $this->post(route('receiving.import.commit'), ['envelope' => $bad])->assertSessionHasErrors('csv_file');
            $this->assertArrayNotHasKey('envelope', session()->getOldInput());
        }
        $this->withServerVariables(['CONTENT_LENGTH' => ReceivingCsv::MAX_ENVELOPE + 65537])->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
        $this->withServerVariables(['CONTENT_LENGTH' => 0]);
        $other = User::create(['name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password')]);
        $other->forceFill(['can_manage_operations' => true])->save();
        $this->actingAs($other)->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
        $this->actingAs($this->actor);
        $this->travel(31)->minutes();
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
        $this->travelBack();
        $this->assertDatabaseCount('receiving_imports', 0);
        $this->assertDatabaseCount('receivings', 0);
        unset($before);
    }

    public function test_changed_reference_or_mapping_requires_new_preview(): void
    {
        foreach ([[$this->subgroup, 'name', 'Changed'], [$this->supplier, 'code', 'CHANGED'], [$this->group, 'is_active', false], [$this->po, 'status', 'in_progress']] as [$model, $field, $value]) {
            $envelope = $this->envelope($this->csv());
            $old = $model->fresh()->$field;
            $model->update([$field => $value]);
            $before = $this->snapshot();
            $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
            $this->assertSame($before, $this->snapshot());
            $model->update([$field => $old]);
        }
        $envelope = $this->envelope($this->csv());
        $this->po->delete();
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
    }

    public function test_success_retry_and_global_duplicate_policy_survive_receiving_deletion(): void
    {
        $bytes = $this->csv([], $this->row(['weight_kg' => '12.25', 'container_count' => '1', 'split_per_container' => 'false', 'doc_number' => 'SECOND']));
        $envelope = $this->envelope($bytes);
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope, 'actor_id' => 999])->assertRedirect(route('receiving.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('receivings', 2);
        $this->assertDatabaseCount('production_batches', 4);
        $this->assertDatabaseCount('batch_movements', 5);
        $this->assertDatabaseCount('audit_events', 7);
        $this->assertDatabaseCount('receiving_imports', 1);
        $receipt = ReceivingImport::sole();
        $this->assertCount(2, $receipt->receiving_ids);
        $this->assertSame($this->actor->id, $receipt->actor_id);
        $this->assertEquals(112.75, DB::table('raw_material_stocks')->sum('current_stock_kg'));
        $snapshot = $this->snapshot();
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $this->snapshot());
        $other = User::create(['name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password')]);
        $other->forceFill(['can_manage_operations' => true])->save();
        $this->actingAs($other);
        $reordered = $this->csv(['weight_kg' => '12.25', 'container_count' => '1', 'split_per_container' => 'false', 'doc_number' => 'SECOND'], $this->row());
        $duplicate = $this->envelope($reordered);
        Receiving::query()->delete();
        $snapshot = $this->snapshot();
        $this->post(route('receiving.import.commit'), ['envelope' => $duplicate])->assertSessionHasErrors(['csv_file' => 'Isi CSV sudah pernah diimpor.']);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_later_batch_failure_rolls_back_nested_transactions_and_nonce_can_retry(): void
    {
        $envelope = $this->envelope($this->csv([], $this->row(['doc_number' => 'SECOND'])));
        $before = $this->snapshot();
        $real = app(BatchService::class);
        $mock = \Mockery::mock(BatchService::class);
        $calls = 0;
        $mock->shouldReceive('createFromReceiving')->andReturnUsing(function ($receiving) use ($real, &$calls) {
            $result = $real->createFromReceiving($receiving);
            if (++$calls === 2) {
                throw new \RuntimeException('SQL SECRET');
            }
            return $result;
        });
        $this->app->instance(BatchService::class, $mock);
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors(['csv_file' => 'Baris 3: kolom CSV gagal disimpan. Tidak ada perubahan; coba lagi.']);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, DB::transactionLevel());
        $this->app->instance(BatchService::class, $real);
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('receivings', 2);
        $this->assertDatabaseCount('receiving_imports', 1);
    }

    public function test_concurrent_posts_on_independent_connections_commit_exactly_once(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl required for concurrent POST verification.');
        }
        $bytes = $this->csv();
        $envelope = $this->envelope($bytes);
        foreach ([true, false] as $sameNonce) {
            $database = $this->path('.sqlite');
            $pdo = new \PDO('sqlite:'.$database);
            $schema = DB::select("SELECT type, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END");
            foreach ($schema as $item) {
                if ($item->type === 'table') {
                    $pdo->exec($item->sql);
                }
            }
            foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) {
                foreach (DB::table($table->name)->get() as $row) {
                    $values = (array) $row;
                    $statement = $pdo->prepare('INSERT INTO "'.$table->name.'" ('.implode(',', array_map(fn ($name) => '"'.$name.'"', array_keys($values))).') VALUES ('.implode(',', array_fill(0, count($values), '?')).')');
                    $statement->execute(array_values($values));
                }
            }
            foreach ($schema as $item) {
                if ($item->type !== 'table') {
                    $pdo->exec($item->sql);
                }
            }
            $pdo = null;
            $go = $this->path('.go');
            $outputs = [$this->path('.json'), $this->path('.json')];
            $ready = [$this->path('.ready'), $this->path('.ready')];
            $tokens = [$envelope, $sameNonce ? $envelope : $this->envelope($bytes)];
            $pids = [];
            foreach ([0, 1] as $index) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    try {
                        config(['database.connections.sqlite.database' => $database]);
                        DB::purge('sqlite');
                        DB::statement('PRAGMA busy_timeout = 5000');
                        touch($ready[$index]);
                        $deadline = microtime(true) + 10;
                        while (!is_file($go) && microtime(true) < $deadline) {
                            usleep(1000);
                        }
                        $response = $this->post(route('receiving.import.commit'), ['envelope' => $tokens[$index]]);
                        file_put_contents($outputs[$index], json_encode(['status' => $response->getStatusCode(), 'errors' => session('errors')?->all() ?? []]));
                        exit(0);
                    } catch (\Throwable $e) {
                        file_put_contents($outputs[$index], json_encode(['failure' => get_class($e)]));
                        exit(1);
                    }
                }
                $pids[] = $pid;
            }
            $deadline = microtime(true) + 10;
            while ((!is_file($ready[0]) || !is_file($ready[1])) && microtime(true) < $deadline) {
                usleep(1000);
            }
            touch($go);
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            $errors = [];
            foreach ($outputs as $output) {
                $result = json_decode(file_get_contents($output), true, 8, JSON_THROW_ON_ERROR);
                $this->assertSame(302, $result['status']);
                $errors = array_merge($errors, $result['errors']);
            }
            $this->assertCount($sameNonce ? 0 : 1, $errors);
            if (!$sameNonce) {
                $this->assertSame(['Isi CSV sudah pernah diimpor.'], $errors);
            }
            $pdo = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM receiving_imports')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM receivings')->fetchColumn());
            $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM production_batches')->fetchColumn());
            $this->assertSame(5, (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
            $pdo = null;
        }
    }

    public function test_audit_insert_failure_rolls_back_all_rows_counters_and_receipt(): void
    {
        $envelope = $this->envelope($this->csv([], $this->row(['doc_number' => 'SECOND'])));
        DB::unprepared("CREATE TRIGGER csv_fail_audit BEFORE INSERT ON audit_events WHEN (SELECT COUNT(*) FROM receivings) = 2 BEGIN SELECT RAISE(ABORT, 'SECRET AUDIT'); END");
        $before = $this->snapshot();
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasErrors('csv_file');
        $this->assertSame($before, $this->snapshot());
        DB::unprepared('DROP TRIGGER csv_fail_audit');
        $this->post(route('receiving.import.commit'), ['envelope' => $envelope])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('receiving_imports', 1);
    }
}
