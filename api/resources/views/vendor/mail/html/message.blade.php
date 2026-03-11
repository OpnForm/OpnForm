@php
$appearance = isset($emailAppearance) && is_array($emailAppearance) ? $emailAppearance : [];
$logoUrl = $appearance['logoUrl'] ?? null;
@endphp
<x-mail::layout :email-appearance="$appearance">
    {{-- Header --}}
    <x-slot:header>
        @if (!(isset($noBranding) && $noBranding))
        @if(!empty($logoUrl))
        <x-mail::header :url="config('app.url')">
            <img src="{{ $logoUrl }}" class="logo" alt="Logo" style="max-height: 75px; height: 75px;">
        </x-mail::header>
        @else
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
        @endif
        @else
        <div style="margin-top:25px;" />
        @endif
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
    <x-slot:subcopy>
        <x-mail::subcopy>
            {{ $subcopy }}
        </x-mail::subcopy>
    </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            @if (!(isset($noBranding) && $noBranding))
            © {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>