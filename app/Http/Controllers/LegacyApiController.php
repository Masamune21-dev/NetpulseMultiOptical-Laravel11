<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyApiController extends Controller
{
    public function data()
    {
        // Legacy placeholder endpoint for realtime updates.
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'No realtime data available'
        ]);
    }

    public function testConnection(Request $request)
    {
        $deviceId = (int) $request->query('id', 0);

        return response()->json([
            'success' => false,
            'message' => $deviceId > 0
                ? 'Test connection endpoint not implemented'
                : 'Invalid device id'
        ]);
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'json'], true)) {
            return response()->json(['error' => 'Unsupported format'], 400);
        }

        if ($format === 'json') {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No export data available'
            ]);
        }

        $filename = 'export-' . date('Ymd-His') . '.csv';
        $content = "timestamp,value\n";

        return response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
