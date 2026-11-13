@extends('layouts.landing')

@section('content')
    @foreach ($sections as $section)
        @include("landing.sections.{$section}")
    @endforeach
@endsection
