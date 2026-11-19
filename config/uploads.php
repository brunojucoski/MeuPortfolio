<?php

return [
    'vercel' => (bool) env('VERCEL', false),
    // Vercel caps the entire body, not each individual image. Reserve multipart headroom.
    'request_max_bytes' => env('UPLOAD_REQUEST_MAX_BYTES', env('VERCEL') ? 3 * 1024 * 1024 : 0),
    'pdf_max_bytes' => env('PDF_RESPONSE_MAX_BYTES', env('VERCEL') ? 4 * 1024 * 1024 : 0),
];
