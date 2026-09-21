@extends('layouts.app')

@section('title', 'Jenis Biaya Tambahan')

@section('content')
<x-crud-simple-master
    title="Daftar Jenis Biaya"
    description="Master referensi jenis biaya tambahan — dipakai KG pas nambahin biaya tambahan di pengajuan."
    addLabel="+ Tambah Jenis Biaya"
    :items="$jenisBiaya"
    idField="id_jenis_biaya"
    nameField="nama_biaya"
    apiEndpoint="/api/jenis-biaya"
    columnLabel="Nama Biaya"
    placeholder="Contoh: Helper/Bongkar Muat"
    itemNoun="jenis biaya"
/>
@endsection
