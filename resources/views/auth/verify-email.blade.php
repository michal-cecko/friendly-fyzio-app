@extends('layouts.auth', ['title' => 'Ověření e-mailu'])

@section('content')
    <livewire:verify-email-notice />
@endsection
