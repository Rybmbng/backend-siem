@extends('layouts.app')

@section('header', 'Detection Rules Engine')

@section('content')
<div class="p-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="bg-slate-800 p-6 rounded-lg border border-slate-700 h-fit">
            <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add New Rule
            </h3>
            <form action="{{ route('rules.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs text-slate-400 uppercase">Rule Name</label>
                    <input type="text" name="name" placeholder="e.g. SSH Brute Force" class="w-full bg-slate-900 border-slate-700 rounded text-slate-200 text-sm focus:border-emerald-500" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-400 uppercase">Log Type</label>
                        <select name="log_type" class="w-full bg-slate-900 border-slate-700 rounded text-slate-200 text-sm">
                            <option value="nginx">Nginx</option>
                            <option value="auth">SSH (Auth)</option>
                            <option value="syslog">Syslog</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 uppercase">Keyword</label>
                        <input type="text" name="search_keyword" placeholder="e.g. 404" class="w-full bg-slate-900 border-slate-700 rounded text-slate-200 text-sm" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-400 uppercase">Threshold (Hits)</label>
                        <input type="number" name="threshold" value="5" class="w-full bg-slate-900 border-slate-700 rounded text-slate-200 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 uppercase">Window (Min)</label>
                        <input type="number" name="time_window_m" value="1" class="w-full bg-slate-900 border-slate-700 rounded text-slate-200 text-sm" required>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="auto_block" id="auto_block" class="rounded border-slate-700 bg-slate-900 text-emerald-500">
                    <label for="auto_block" class="text-sm text-slate-300">Enable Auto-Block IP</label>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded transition">SAVE RULE</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-slate-800 rounded-lg border border-slate-700 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-950 text-slate-400 text-xs uppercase">
                    <tr>
                        <th class="p-4">Name / Info</th>
                        <th class="p-4">Condition</th>
                        <th class="p-4 text-center">Auto Block</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($rules as $rule)
                    <tr class="hover:bg-slate-900/50 transition">
                        <td class="p-4">
                            <div class="text-sm font-bold text-slate-200">{{ $rule->name }}</div>
                            <div class="text-[10px] text-slate-500 uppercase">{{ $rule->log_type }}</div>
                        </td>
                        <td class="p-4">
                            <div class="text-xs text-slate-300 italic">"{{ $rule->search_keyword }}"</div>
                            <div class="text-[10px] text-slate-500">{{ $rule->threshold }} hits / {{ $rule->time_window_m }} min</div>
                        </td>
                        <td class="p-4 text-center">
                            <span class="{{ $rule->auto_block ? 'text-red-500' : 'text-slate-500' }}">
                                {!! $rule->auto_block ? 'YES' : 'NO' !!}
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            <a href="{{ route('rules.toggle', $rule->id) }}" class="px-2 py-1 rounded text-[10px] font-bold {{ $rule->is_active ? 'bg-emerald-900/30 text-emerald-500 border border-emerald-500/50' : 'bg-slate-700 text-slate-400' }}">
                                {{ $rule->is_active ? 'ACTIVE' : 'DISABLED' }}
                            </a>
                        </td>
                        <td class="p-4 text-right">
                            <form action="{{ route('rules.delete', $rule->id) }}" method="POST" onsubmit="return confirm('Hapus rule ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection