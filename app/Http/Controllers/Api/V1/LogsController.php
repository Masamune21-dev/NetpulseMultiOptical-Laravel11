<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::securityLogsText()]);
        }

        if ($role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $type = $request->query('type', '');
        if ($type !== 'security') {
            return response()->json(['success' => false, 'error' => 'Invalid log type']);
        }

        $logFile = storage_path('logs/security.log');
        if (!is_file($logFile)) {
            return response()->json(['success' => true, 'data' => 'No logs yet.']);
        }

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return response()->json(['success' => false, 'error' => 'Failed to read log file']);
        }

        $tail = array_slice($lines, -200);
        return response()->json(['success' => true, 'data' => implode(\"\\n\", $tail)]);
    }
}
