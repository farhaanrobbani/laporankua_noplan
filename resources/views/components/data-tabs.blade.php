@php
    $tabs = [
        ['route' => 'data.index', 'label' => 'Data Import', 'active' => request()->routeIs('data.index')],
        ['route' => 'data.pelaksanaan-kantor', 'label' => 'Pelaksanaan Kantor', 'active' => request()->routeIs('data.pelaksanaan-kantor')],
        ['route' => 'data.pelaksanaan-luar-kantor', 'label' => 'Pelaksanaan Luar Kantor', 'active' => request()->routeIs('data.pelaksanaan-luar-kantor')],
        ['route' => 'data.duplikat', 'label' => 'Duplikat', 'active' => request()->routeIs('data.duplikat')],
        ['route' => 'data.bukan-duplikat', 'label' => 'Bukan Duplikat', 'active' => request()->routeIs('data.bukan-duplikat')],
        ['route' => 'data.rusak', 'label' => 'Rusak', 'active' => request()->routeIs('data.rusak')],
        ['route' => 'data.skp', 'label' => 'SKP', 'active' => request()->routeIs('data.skp')],
    ];
@endphp

<div
    class="-mx-4 sm:mx-0 mb-6"
    x-data
    x-init="$nextTick(() => {
        const bar = $el.querySelector('[data-tabs-scroll]');
        const active = bar?.querySelector('[data-active=true]');
        if (bar && active) {
            bar.scrollLeft = Math.max(0, active.offsetLeft - (bar.clientWidth - active.offsetWidth) / 2);
        }
    })"
>
    <div data-tabs-scroll class="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700 px-4 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($tabs as $tab)
            <a
                href="{{ route($tab['route']) }}"
                data-active="{{ $tab['active'] ? 'true' : 'false' }}"
                class="inline-flex shrink-0 items-center whitespace-nowrap min-h-[44px] px-4 py-2 text-sm font-medium border-b-2 -mb-px transition {{ $tab['active'] ? 'border-indigo-400 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                {{ __($tab['label']) }}
            </a>
        @endforeach
    </div>
</div>
