@props(['title', 'summary' => null, 'open' => false])

<section x-data="{ open: {{ $open ? 'true' : 'false' }} }" {{ $attributes->merge(['class' => 'mb-6 last:mb-0']) }}>
    <div class="flex items-center justify-between gap-3 mb-3">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h4>
        <button
            type="button"
            @click="open = ! open"
            :aria-expanded="open"
            class="sm:hidden inline-flex min-h-[44px] items-center gap-2 rounded-md px-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
        >
            @if ($summary !== null)
                <span class="text-xs font-semibold">{{ $summary }}</span>
            @endif
            <svg :class="{ 'rotate-180': open }" class="h-5 w-5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </div>

    <div :class="! open ? 'hidden sm:block' : ''">
        {{ $slot }}
    </div>
</section>
