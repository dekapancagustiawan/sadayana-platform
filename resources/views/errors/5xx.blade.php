@extends('errors.layout')

@section('code', $exception?->getStatusCode() ?? '500')
@section('tag', 'Kesalahan sistem')
@section('title', 'Ada Yang Bermasalah Di Sisi Kami')
@section('message', 'Ini kesalahan sistem, bukan kesalahan Anda. Permintaan tadi tidak selesai diproses. Coba beberapa saat lagi; kalau masih sama, kabari administrator.')
