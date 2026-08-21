@extends('layouts.app')

@section('title', 'Daftar Armada')

@section('content')

<div class="rounded-xl bg-white shadow-sm p-6">
    <x-tabel-armada mode="detail" :limit="null" />
</div>

@endsection