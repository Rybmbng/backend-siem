<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SIEM Dashboard') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Custom Scrollbar biar ganteng (dari kode lama Abang) */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-slate-900">
    <div class="min-h-screen flex" x-data="{ sidebarOpen: false }">
        
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-20 bg-black/50 lg:hidden"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed z-30 inset-y-0 left-0 w-64 transition duration-300 transform bg-slate-950 border-r border-slate-800 overflow-y-auto lg:translate-x-0 lg:static lg:inset-0">
            <div class="flex items-center justify-center h-16 bg-slate-950 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 bg-emerald-500 rounded-full blink"></div>
                    <span class="text-slate-200 font-bold tracking-widest uppercase">SIEM Center</span>
                </div>
            </div>

            <nav class="mt-5 px-2 space-y-1">
                {{-- Loop dari DATABASE via AppServiceProvider --}}
                @foreach($sidebarMenus as $menu)
                    @php
                        // Cek Route Aktif
                        // $menu->active_routes otomatis jadi array karena casting di Model
                        $isActive = request()->routeIs($menu->active_routes);
                        
                        $activeClass = $isActive 
                            ? 'bg-slate-800 text-white' 
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white';
                            
                        // Cek apakah route valid (bukan #)
                        $url = ($menu->route && $menu->route !== '#') ? route($menu->route) : '#';
                    @endphp

                    <a href="{{ $url }}" 
                       class="group flex items-center px-2 py-2 text-base leading-6 font-medium rounded-md transition ease-in-out duration-150 {{ $activeClass }}">
                        
                        {{-- Render SVG Icon --}}
                        <div class="mr-4 h-6 w-6 {{ $isActive ? 'text-emerald-500' : 'text-slate-500 group-hover:text-slate-300' }}">
                            {!! $menu->icon !!}
                        </div>
                        
                        {{ $menu->title }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden">
            
            <header class="flex justify-between items-center py-4 px-6 bg-slate-900 border-b border-slate-800">
                <div class="flex items-center">
                    <button @click="sidebarOpen = true" class="text-slate-500 focus:outline-none lg:hidden">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 6H20M4 12H20M4 18H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <h2 class="text-xl font-semibold text-slate-200 ml-4 lg:ml-0">
                        @yield('header', 'Dashboard') 
                    </h2>
                </div>

                <div class="flex items-center">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-400 bg-slate-800 hover:text-slate-300 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>
                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-900">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>