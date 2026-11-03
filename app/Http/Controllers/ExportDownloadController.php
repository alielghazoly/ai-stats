<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportDownloadController
{
    /** Signed temporary link (`signed` middleware), no Bearer because the admin opens it from the browser. */
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}-[a-z0-9_.-]+\.(csv|xlsx)$/', $file, $m), 404);

        $path = storage_path('app/exports/' . $file);
        abort_unless(is_file($path), 404);

        return response()->download($path, substr($file, 37), [
            'Content-Type' => $m[1] === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
