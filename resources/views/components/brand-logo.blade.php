{{-- Company logo from config/branding.php; without a configured logo, a badge with the initials + the name. --}}
@props(['class' => 'erp-brand-logo', 'showName' => true])

@if($logo = \App\Support\Branding::logoUrl())
    <img src="{{ $logo }}" alt="{{ \App\Support\Branding::companyName() }}" {{ $attributes->merge(['class' => $class]) }}>
@else
    <span {{ $attributes->merge(['class' => 'erp-brand-text']) }}>
        <span class="erp-brand-initials">{{ \App\Support\Branding::initials() }}</span>
        @if($showName)
            <span class="erp-brand-name">{{ \App\Support\Branding::companyName() }}</span>
        @endif
    </span>
@endif
