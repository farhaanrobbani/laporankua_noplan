@props(['status' => 'default'])

@php
    $map = [
        'success' => ['Berhasil', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
        'generated' => ['Selesai', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
        'downloaded' => ['Terunduh', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
        'pending' => ['Menunggu', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'],
        'processing' => ['Diproses', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'],
        'failed' => ['Gagal', 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'],
        'appended' => ['Append', 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'],
        'pdf' => ['PDF', 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'],
        'word' => ['Word', 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'],
        'excel' => ['Excel', 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'],
        'print' => ['Print', 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200'],
        'default' => ['Default', 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'],
    ];

    [$label, $classes] = $map[strtolower((string) $status)] ?? [ucfirst((string) $status), 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex px-2 py-0.5 text-xs font-semibold rounded-full '.$classes]) }}>{{ $label }}</span>
