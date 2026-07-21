@extends('layouts.app')

@section('title', 'Fee Reports — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Fee Reports'],
    ]" />

    <x-section-header title="Fee Reports" :sub="'Collection and student payment status · AY '.$academicYear" />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($cards as $card)
            <a href="{{ route($card['route']) }}"
                class="group flex items-start gap-3 rounded-lg border {{ $card['tone'] }} p-4 transition hover:shadow-sm">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-white text-dugsi-primary shadow-sm">
                    <x-icon :name="$card['icon']" :size="18" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                        <x-icon name="chevron-right" :size="14" class="text-slate-400 transition group-hover:text-dugsi-primary" />
                    </div>
                    <p class="mt-0.5 text-xs text-slate-600">{{ $card['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
