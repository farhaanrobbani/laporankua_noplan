@php
    $toast = null;
    $importNotice = auth()->check()
        ? \Illuminate\Support\Facades\Cache::pull('import_notice:'.auth()->id())
        : null;

    if ($importNotice) {
        $toast = ['message' => $importNotice['message'], 'classes' => 'bg-amber-600'];
    } elseif (session('status')) {
        $toast = ['message' => session('status'), 'classes' => 'bg-green-600'];
    } elseif (session('error')) {
        $toast = ['message' => session('error'), 'classes' => 'bg-red-600'];
    }
@endphp

@if ($toast)
    <div id="app-toast" class="fixed top-4 right-4 z-50 max-w-sm {{ $toast['classes'] }} text-white text-sm font-medium rounded-lg shadow-lg px-4 py-3 flex items-start gap-3">
        <span class="flex-1">{{ $toast['message'] }}</span>
        <button type="button" onclick="document.getElementById('app-toast').remove()" class="font-bold leading-none">&times;</button>
    </div>
    <script>
        setTimeout(function () {
            var toast = document.getElementById('app-toast');
            if (toast) toast.remove();
        }, 5000);
    </script>
@endif
