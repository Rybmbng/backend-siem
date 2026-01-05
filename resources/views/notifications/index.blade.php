@extends('layouts.app')

@section('content')
<div class="p-6 bg-slate-950 min-h-screen text-slate-200">
    <h2 class="text-2xl font-bold mb-6 text-blue-400">Notifier Management</h2>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            <form action="{{ route('notifications.update') }}" method="POST" class="space-y-6">
                @csrf
                <div class="bg-slate-900 p-5 rounded-lg border border-slate-800 shadow-xl">
                    <h3 class="text-emerald-500 font-bold mb-4 uppercase text-xs tracking-widest">WhatsApp & Telegram Config</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1">TELEGRAM TOKEN</label>
                            <input type="password" name="telegram_token" value="{{ $settings['telegram_token'] ?? '' }}" class="w-full bg-slate-800 border-slate-700 rounded text-sm p-2 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1">WA ADMIN NUMBERS (JSON)</label>
                            <input type="text" name="wa_numbers" value="{{ $settings['wa_numbers'] ?? '[]' }}" class="w-full bg-slate-800 border-slate-700 rounded text-sm p-2 font-mono">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2 px-4 rounded transition">
                        SIMPAN SETTINGS
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-slate-900 p-5 rounded-lg border border-slate-800 shadow-xl flex flex-col items-center">
                <h3 class="text-slate-400 font-bold mb-4 uppercase text-[10px] self-start">WA Pairing Status</h3>
                
                <div id="status-badge" class="mb-4 px-3 py-1 rounded-full text-[10px] font-bold bg-red-900/30 text-red-500 border border-red-500/50">
                    DISCONNECTED
                </div>

                <div class="bg-white p-2 rounded-lg mb-4 shadow-inner">
                    <img id="qr-code" src="{{ asset('storage/wa_qr.png') }}?v={{ time() }}" 
                         class="w-48 h-48 object-cover" 
                         onerror="this.src='https://via.placeholder.com/300?text=Wait+for+QR...'">
                </div>

                <p class="text-[10px] text-slate-500 text-center leading-relaxed">
                    Buka WhatsApp > Perangkat Tertaut > Tautkan Perangkat.<br>
                    Scan gambar di atas untuk mulai menerima notifikasi.
                </p>
                
                <button onclick="location.reload()" class="mt-4 text-[10px] text-blue-400 hover:underline">Refresh QR</button>
            </div>
        </div>
    </div>

    <div class="mt-8 bg-slate-900 p-5 rounded-lg border border-slate-800 shadow-xl">
    <h3 class="text-slate-400 font-bold mb-4 uppercase text-[10px]">Command History (WA Bot)</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950 text-slate-500 uppercase text-[9px]">
                <tr>
                    <th class="p-2">Waktu</th>
                    <th class="p-2">Pengirim</th>
                    <th class="p-2">Pesan</th>
                    <th class="p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach(DB::table('wa_histories')->orderBy('id','desc')->limit(10)->get() as $log)
                <tr class="border-b border-slate-800">
                    <td class="p-2">{{ $log->created_at }}</td>
                    <td class="p-2 font-mono text-blue-400">{{ $log->sender }}</td>
                    <td class="p-2 italic">"{{ $log->message }}"</td>
                    <td class="p-2">
                        <span class="{{ $log->status == 'executed' ? 'text-emerald-500' : 'text-red-500' }}">
                            {{ strtoupper($log->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>
<script>
    async function updateStatus() {
        try {
            // Gunakan IP Server Abang atau window.location.hostname biar otomatis
            const serverIp = window.location.hostname; 
            const response = await fetch(`http://${serverIp}:3111/status`);
            
            const data = await response.json();
            const badge = document.getElementById('status-badge');
            const qrImage = document.getElementById('qr-code');

            if (data.connected) {
                badge.innerText = 'CONNECTED: ' + data.user;
                badge.classList.remove('text-red-500', 'bg-red-900/30', 'border-red-500/50');
                badge.classList.add('text-emerald-400', 'bg-emerald-900/30', 'border-emerald-500/50');
                
                if (qrImage) {
                    const container = qrImage.parentElement;
                    container.innerHTML = '<div class="w-48 h-48 flex items-center justify-center text-emerald-500 font-bold text-4xl animate-bounce">✅</div><div class="text-emerald-400 text-[10px] font-bold mt-2">LINKED SUCCESS</div>';
                }
            }
        } catch (err) {
            console.log("API WA Offline atau terblokir CORS");
        }
    }

    setInterval(updateStatus, 3000);
    updateStatus();
</script>
@endsection