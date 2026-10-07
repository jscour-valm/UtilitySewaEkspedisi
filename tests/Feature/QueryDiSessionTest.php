<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QueryDiSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'query.sesi'])
            ->get('/_tes-query', fn () => response()->json(request()->query()))
            ->name('tes.query');
    }

    public function test_parameter_filter_disimpan_lalu_url_dibersihkan(): void
    {
        $this->get('/_tes-query?dari=2026-10-01&sampai=2026-10-07&sort_pengajuan=harga&tab=x')
            ->assertRedirect(url('/_tes-query?tab=x'));

        $this->get('/_tes-query')->assertExactJson([
            'dari' => '2026-10-01', 'sampai' => '2026-10-07', 'sort_pengajuan' => 'harga',
        ]);
    }

    public function test_parameter_baru_digabung_dan_kosong_menghapus(): void
    {
        $this->get('/_tes-query?dari=2026-10-01&sort_pengajuan=harga&order_pengajuan=asc');
        $this->get('/_tes-query?sort_pengajuan=&order_pengajuan=');

        $this->get('/_tes-query')->assertExactJson(['dari' => '2026-10-01']);
    }
}
