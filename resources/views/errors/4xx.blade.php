@extends('errors.layout')

@section('code', $exception?->getStatusCode() ?? '400')
@section('tag', 'Permintaan tidak dapat diproses')
@section('title', 'Permintaan Ini Tidak Bisa Diproses')
@section('message', 'Ada yang tidak sesuai pada permintaan tadi sehingga tidak bisa dilanjutkan. Kembali ke halaman sebelumnya lalu ulangi dari tombol yang tersedia.')
