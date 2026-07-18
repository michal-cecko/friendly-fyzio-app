@extends('layouts.auth', ['title' => 'Obnovení hesla'])

@section('content')
    <livewire:reset-password :token="$token" />
@endsection
