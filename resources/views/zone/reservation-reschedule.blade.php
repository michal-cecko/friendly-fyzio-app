@extends('layouts.public')

@section('content')
    <x-zone.layout title="Přesunout termín">
        <livewire:zone.reschedule-reservation :reservation="$reservation" />
    </x-zone.layout>
@endsection
