<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('settings.index', [
            'pageTitle' => 'Settings',
            'currentUser' => $request->session()->get('auth.user'),
        ]);
    }
}
