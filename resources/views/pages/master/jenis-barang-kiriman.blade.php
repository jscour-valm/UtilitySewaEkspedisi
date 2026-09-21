@extends('layouts.app')

@section('title', 'Jenis Barang Kiriman')

@section('content')
<x-crud-simple-master
    title="Daftar Jenis Barang Kiriman"
    description="Master jenis barang untuk fitur Kiriman Rutin — dipakai sbg dasar tarif per jenis barang di halaman Kelola Tarif."
    addLabel="+ Tambah Jenis Barang"
    :items="$jenisBarang"
    idField="id_jenis_barang"
    nameField="nama_barang"
    apiEndpoint="/api/jenis-barang-kiriman"
    columnLabel="Nama Barang"
    placeholder="Contoh: Cat Pail (Per Koli)"
    itemNoun="jenis barang"
/>
@endsection
