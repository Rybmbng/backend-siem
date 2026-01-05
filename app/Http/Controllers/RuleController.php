<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RuleController extends Controller
{
    public function index()
    {
        $rules = DB::table('detection_rules')->orderBy('id', 'desc')->get();
        return view('rules.index', compact('rules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'log_type' => 'required',
            'search_keyword' => 'required',
            'threshold' => 'required|numeric',
            'time_window_m' => 'required|numeric',
        ]);

        DB::table('detection_rules')->insert([
            'name' => $request->name,
            'log_type' => $request->log_type,
            'search_keyword' => $request->search_keyword,
            'threshold' => $request->threshold,
            'time_window_m' => $request->time_window_m,
            'auto_block' => $request->has('auto_block'),
            'is_active' => true,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Rule berhasil ditambahkan!');
    }

    public function toggle($id)
    {
        $rule = DB::table('detection_rules')->where('id', $id)->first();
        DB::table('detection_rules')->where('id', $id)->update([
            'is_active' => !$rule->is_active
        ]);
        return back();
    }

    public function destroy($id)
    {
        DB::table('detection_rules')->where('id', $id)->delete();
        return back()->with('success', 'Rule dihapus!');
    }
}