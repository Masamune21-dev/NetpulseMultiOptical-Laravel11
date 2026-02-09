<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        return view('users.index', [
            'pageTitle' => 'Users',
            'currentUser' => $request->session()->get('auth.user'),
        ]);
    }
}
