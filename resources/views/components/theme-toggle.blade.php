<button
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
    :aria-label="dark ? 'Mode terang' : 'Mode gelap'"
    {{ $attributes->merge(['class' => 'p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 transition-colors']) }}
    type="button"
>
    <x-heroicon-o-sun x-show="dark" x-cloak class="w-5 h-5" />
    <x-heroicon-o-moon x-show="!dark" x-cloak class="w-5 h-5" />
</button>
