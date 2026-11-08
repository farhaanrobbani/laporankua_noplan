@props(['title' => 'Belum ada data', 'message' => '', 'actionUrl' => null, 'actionLabel' => null])

<div class="text-center py-8">
    <div class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600">
        <x-heroicon-o-inbox class="w-12 h-12" />
    </div>
    <p class="mt-2 text-gray-500 dark:text-gray-400 font-medium">{{ $title }}</p>
    @if ($message)
        <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">{{ $message }}</p>
    @endif
    @if ($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700">
            {{ $actionLabel }}
        </a>
    @endif
</div>
