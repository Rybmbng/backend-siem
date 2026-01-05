@extends('layouts.app')

@section('header', 'Command Center')

@section('content')
    <div class="flex flex-col h-full">
        
        <div class="bg-slate-900 border-b border-slate-800 p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-slate-800 p-4 rounded border border-slate-700">
                <div class="text-slate-400 text-xs uppercase">Total Logs</div>
                <div class="text-2xl font-bold text-blue-400" id="stat-total-logs">0</div>
            </div>
            <div class="bg-slate-800 p-4 rounded border border-slate-700">
                <div class="text-slate-400 text-xs uppercase">Detected Attacks</div>
                <div class="text-2xl font-bold text-red-500" id="stat-attacks">0</div>
            </div>
            <div class="bg-slate-800 p-4 rounded border border-slate-700">
                <div class="text-slate-400 text-xs uppercase">Active Agents</div>
                <div class="text-2xl font-bold text-emerald-400" id="stat-active-agents">0</div>
            </div>
             <div class="bg-slate-800 p-2 rounded border border-red-900/50 flex gap-2 items-center">
                <input type="text" id="manual-ip" placeholder="192.168.x.x" class="bg-slate-900 border border-slate-700 text-sm p-2 w-full rounded focus:outline-none focus:border-red-500">
                <button onclick="manualBlock()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-bold">BLOCK</button>
            </div>
        </div>

        <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
            
            <div class="flex-1 flex flex-col border-r border-slate-800">
                
                <div class="p-4 border-b border-slate-800 bg-slate-950/50 overflow-x-auto whitespace-nowrap">
                    <h3 class="text-xs font-bold text-slate-400 mb-2 uppercase">Connected Agents Status</h3>
                    <div class="flex gap-4" id="agent-list-horizontal">
                        <div class="text-slate-500 text-sm">Loading Agents...</div>
                    </div>
                </div>

                <div class="p-3 bg-slate-950 border-b border-slate-800 text-xs font-bold text-slate-400 flex justify-between">
                    <span>LIVE SECURITY LOGS</span>
                    <span class="text-emerald-500 animate-pulse">REALTIME MONITORING</span>
                </div>
                <div class="flex-1 overflow-y-auto bg-slate-900 p-0 relative">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-950 text-slate-500 text-xs sticky top-0 z-10 shadow-sm shadow-slate-800">
                            <tr>
                                <th class="p-3">Time</th>
                                <th class="p-3">Host</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Message</th>
                            </tr>
                        </thead>
                        <tbody id="log-table-body" class="text-xs font-mono divide-y divide-slate-800">
                            </tbody>
                    </table>
                </div>
            </div>

            <div class="w-full md:w-96 bg-slate-950 flex flex-col border-l border-slate-800">
                <div class="p-3 border-b border-slate-800 text-xs font-bold text-red-400 bg-red-950/20">
                    BLOCKED IP LIST (BLACKHOLE)
                </div>
                <div class="flex-1 overflow-y-auto p-0">
                    <table class="w-full text-left">
                        <thead class="bg-slate-900 text-slate-500 text-xs sticky top-0">
                            <tr>
                                <th class="p-3">IP Address</th>
                                <th class="p-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="blacklist-body" class="text-xs divide-y divide-slate-800">
                            </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // --- 1. Fetch Dashboard Stats & Agents (Updated for new layout) ---
        async function fetchStats() {
            try {
                const res = await fetch(`${API_BASE}/dashboard/stats`);
                const data = await res.json();
                
                document.getElementById('stat-total-logs').innerText = data.stats.total_logs.toLocaleString();
                document.getElementById('stat-attacks').innerText = data.stats.attacks.toLocaleString();
                document.getElementById('stat-active-agents').innerText = data.active_count;

                // Update Horizontal Agent List
                const list = document.getElementById('agent-list-horizontal');
                list.innerHTML = '';
                
                data.agents.forEach(agent => {
                    const statusColor = agent.is_online ? 'bg-emerald-500' : 'bg-slate-600';
                    // Card lebih compact untuk tampilan horizontal
                    const html = `
                    <div class="inline-block bg-slate-900 p-3 rounded border border-slate-800 w-64 flex-shrink-0">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full ${statusColor}"></div>
                                <span class="font-bold text-sm text-slate-300 truncate">${agent.hostname}</span>
                            </div>
                            <span class="text-[10px] text-slate-500">${agent.ip}</span>
                        </div>
                        <div class="flex gap-2 text-[10px] text-slate-400">
                            <div class="flex-1">CPU: ${parseFloat(agent.cpu).toFixed(0)}% <div class="bg-slate-800 h-1 mt-1 rounded-full overflow-hidden"><div class="bg-blue-500 h-full" style="width: ${agent.cpu}%"></div></div></div>
                            <div class="flex-1">RAM: ${parseFloat(agent.ram).toFixed(0)}% <div class="bg-slate-800 h-1 mt-1 rounded-full overflow-hidden"><div class="bg-purple-500 h-full" style="width: ${agent.ram}%"></div></div></div>
                        </div>
                    </div>`;
                    list.innerHTML += html;
                });
            } catch (e) { console.error("Stats Error", e); }
        }

        // --- 2. Fetch Live Logs (Sama) ---
        async function fetchLogs() {
            try {
                const res = await fetch(`${API_BASE}/dashboard/logs`);
                const data = await res.json();
                const tbody = document.getElementById('log-table-body');
                tbody.innerHTML = '';
                if (data.data) {
                    data.data.forEach(log => {
                        let msg = log.message;
                        if (msg.toLowerCase().includes('failed') || msg.toLowerCase().includes('error')) { msg = `<span class="text-red-400">${msg}</span>`; } 
                        else if (msg.toLowerCase().includes('sudo')) { msg = `<span class="text-yellow-400">${msg}</span>`; }
                        tbody.innerHTML += `<tr class="hover:bg-slate-800/50 transition"><td class="p-3 text-slate-500 whitespace-nowrap">${log.event_time}</td><td class="p-3 text-blue-400 font-bold">${log.hostname}</td><td class="p-3 text-purple-400">${log.log_type}</td><td class="p-3 text-slate-300 truncate max-w-xl">${msg}</td></tr>`;
                    });
                }
            } catch (e) { console.error("Log Error", e); }
        }

        // --- 3. Fetch Blacklist & Actions (Sama, updated CSRF) ---
        async function fetchBlacklist() {
            try {
                const res = await fetch(`${API_BASE}/dashboard/blacklist`);
                const data = await res.json();
                const tbody = document.getElementById('blacklist-body');
                tbody.innerHTML = '';
                data.forEach(item => {
                    tbody.innerHTML += `<tr class="group hover:bg-slate-900"><td class="p-3 text-red-400 font-mono">${item.ip_address}</td><td class="p-3 text-right"><button onclick="unblockIP('${item.ip_address}')" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-400 px-2 py-1 rounded border border-slate-700">Unblock</button></td></tr>`;
                });
            } catch (e) { }
        }

        async function manualBlock() {
            const ip = document.getElementById('manual-ip').value;
            if(!ip) return alert("Isi IP dulu!");
            await fetch(`${API_BASE}/dashboard/block`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ ip_address: ip }) });
            document.getElementById('manual-ip').value = ''; fetchBlacklist();
        }
        async function unblockIP(ip) {
            if(!confirm(`Lepas ${ip}?`)) return;
            await fetch(`${API_BASE}/dashboard/unblock`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ ip_address: ip }) });
            fetchBlacklist();
        }

        // Init & Loops
        fetchStats(); fetchLogs(); fetchBlacklist();
        setInterval(() => { fetchStats(); fetchBlacklist(); }, 2000);
        setInterval(fetchLogs, 1000);
    </script>
@endsection