<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DevicesController extends Controller
{
    public function index(Request $request)
    {
        return view('devices.index', [
            'pageTitle' => 'Devices',
            'currentUser' => $request->session()->get('auth.user'),
        ]);
    }
}
