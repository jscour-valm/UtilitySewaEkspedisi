@extends('layouts.app')

@section('title', 'Penerima Email')

@section('content')
@php
    $grupEmail = collect(\App\Models\AturanEmail::KEJADIAN)->groupBy('grup', true);
@endphp

<div class="flex flex-col gap-4 pb-2">
    <x-ui.alert-success />
    <x-ui.alert-error />
    @include('pages.pengaturan.partials.tabel-belum-ada')

    <form method="POST" action="{{ route('pengaturan.email.simpan') }}" data-tandai-ubah class="rounded-xl bg-white p-6 shadow-sm">
        @csrf
        <h1 class="mb-4 text-2xl font-bold text-gray-900">Penerima Email Notifikasi</h1>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead>
                    <tr class="kepala-tabel text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Kejadian</th>
                        @foreach(\App\Models\AturanEmail::ROLE as $role)
                            <th class="px-3 py-2 text-center">{{ $role }}</th>
                        @endforeach
                    </tr>
                </thead>
                @foreach($grupEmail as $grup => $kejadianGrup)
                    {{-- Satu kelompok: judul rata kiri, baris di bawahnya menjorok --}}
                    <tbody class="divide-y divide-gray-100">
                        <tr><td colspan="{{ count(\App\Models\AturanEmail::ROLE) + 1 }}" class="px-3 pb-2 pt-5 text-sm font-semibold text-gray-900">{{ $grup }}</td></tr>
                        @foreach($kejadianGrup as $kejadian => $k)
                            <tr>
                                <td class="py-2 pl-9 pr-3 text-gray-700">{{ $k['label'] }}</td>
                                @foreach(\App\Models\AturanEmail::ROLE as $role)
                                    @php
                                        $nilai = old("email.$kejadian.$role", in_array($role, $email[$kejadian]['to'], true) ? 'to' : (in_array($role, $email[$kejadian]['cc'], true) ? 'cc' : ''));
                                    @endphp
                                    <td class="px-3 py-2 text-center">
                                        <select name="email[{{ $kejadian }}][{{ $role }}]" @disabled(! $tabelSiap)
                                            class="rounded-md border px-1.5 py-1 text-xs focus:border-avian-green focus:outline-none disabled:bg-gray-50
                                            {{ $nilai === 'to' ? 'border-avian-green bg-avian-green-light font-semibold text-avian-green' : ($nilai === 'cc' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-400') }}">
                                            <option value="" @selected($nilai === '')>—</option>
                                            <option value="to" @selected($nilai === 'to')>To</option>
                                            <option value="cc" @selected($nilai === 'cc')>CC</option>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" @disabled(! $tabelSiap)
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">Simpan Penerima Email</button>
        </div>
    </form>
</div>
@endsection
