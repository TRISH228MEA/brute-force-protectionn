
<?php
session_start();

define('SUPABASE_URL', 'https://udxotjgeuwklzlqvdrux.supabase.co/rest/v1/');
define('SUPABASE_ANON_KEY', 'sb_publishable_lJmjV5s_B8X8J6ZEH9rKkA_NK0aFu8k');


function supabase_request($method, $endpoint, $data = null, $accessToken = null) {
    $url = rtrim(SUPABASE_URL, '/') . $endpoint;

    $token = $accessToken ?: SUPABASE_ANON_KEY;

    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token
    ];

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true
        ]
    ];

    if ($data !== null) {
        $options['http']['content'] = json_encode($data);
    }

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    return json_decode($response ?: '{}', true) ?? [];
}
