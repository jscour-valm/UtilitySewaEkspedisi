<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SesiHabisTest extends TestCase
{
    public function test_sesi_habis_ke_login_dengan_pesan_dan_simpan_halaman_terakhir(): void
    {
        $this->get('/sesi-habis?kembali='.urlencode('/pengajuan/12?tab=dokumen'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username')
            ->assertSessionHas('url.intended', url('/pengajuan/12?tab=dokumen'));
    }

    public function test_kembali_ke_luar_aplikasi_diabaikan(): void
    {
        foreach (['//evil.test/x', 'https://evil.test', '/\\evil.test'] as $kembali) {
            $this->get('/sesi-habis?kembali='.urlencode($kembali))
                ->assertRedirect(route('login'))
                ->assertSessionMissing('url.intended');
        }
    }

    public function test_csrf_tidak_cocok_di_form_biasa_diarahkan_ke_sesi_habis(): void
    {
        Route::middleware('web')->post('/_tes-csrf', fn () => throw new TokenMismatchException);

        $this->post('/_tes-csrf')->assertRedirect(route('sesi.habis'));
    }

    public function test_csrf_tidak_cocok_di_api_tetap_419_json(): void
    {
        Route::middleware('web')->post('/api/_tes-csrf', fn () => throw new TokenMismatchException);

        $this->postJson('/api/_tes-csrf')->assertStatus(419);
    }
}
