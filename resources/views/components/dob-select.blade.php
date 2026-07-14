@props([
    'name' => 'dob',
    'value' => null,
    'minYear' => null,
    'maxYear' => null,
    'default' => null,
])

@php
    $bounds = \App\Support\AcademicYear::birthYearBounds();
@endphp

<x-date-select
    :name="$name"
    label="Date of Birth"
    :value="$value"
    :default="$default ?? \App\Support\AcademicYear::defaultDob()"
    :min-year="$minYear ?? $bounds['min']"
    :max-year="$maxYear ?? $bounds['max']"
    :required="true"
    :hint="'Day · Month · Year — secondary school ages ('.($minYear ?? $bounds['min']).'–'.($maxYear ?? $bounds['max']).')'"
    {{ $attributes }}
/>
