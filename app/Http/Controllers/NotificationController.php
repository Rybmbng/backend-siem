<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    public function index()
    {
        $settings = DB::table('settings')->pluck('value', 'key')->toArray();
        return view('notifications.index', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->except('_token');
        foreach ($data as $key => $value) {
            \DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
        return back()->with('success', 'Settings updated!');
    }

    // Fitur Bonus: Test WA
    public function testWhatsApp(Request $request)
    {
        $target = json_decode($request->wa_numbers)[0] ?? null;
        if (!$target) return response()->json(['error' => 'Isi nomor WA dulu!']);

        $res = Http::post("http://localhost:3111/send-broadcast", [
            'to' => $target,
            'message' => "Tes koneksi SIEM WhatsApp. Aman, Bang! ✅"
        ]);

        return $res->json();
    }
}