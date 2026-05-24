<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InterfacesController extends Controller
{
    public function index(Request $request)
    {
        return view('interfaces.index', [
            'pageTitle' => 'Interfaces',
            'currentUser' => $request->session()->get('auth.user'),
        ]);
    }
}
