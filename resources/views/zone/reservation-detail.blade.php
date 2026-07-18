@extends('layouts.public')

@section('content')
    <x-zone.layout title="Detail rezervace">
        <livewire:zone.reservation-detail :reservation="$reservation" />
    </x-zone.layout>
@endsection
