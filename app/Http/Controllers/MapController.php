<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index(Request $request)
    {
        return view('map.index', [
            'pageTitle' => 'Network Map',
            'currentUser' => $request->session()->get('auth.user'),
        ]);
    }
}
