<?php

use Illuminate\Support\Str;

test('opensearch:verify uses configured URL and handles mixed index states', function () {
    $base = 'http://opensearch.test';
    putenv("OPENSEARCH_URL={$base}");

    $indices = ['api_log', 'general_log', 'cron_log', 'integration_log', 'orm_log', 'error_log'];

    $map = [];

    // api_log exists and has documents
    $map["{$base}/api_log"] = '{}';
    $map["{$base}/api_log/_count"] = json_encode(['count' => 2]);

    // general_log exists but empty
    $map["{$base}/general_log"] = '{}';
    $map["{$base}/general_log/_count"] = json_encode(['count' => 0]);

    // cron_log does not exist
    $map["{$base}/cron_log"] = false;

    // integration_log exists but count endpoint fails
    $map["{$base}/integration_log"] = '{}';
    $map["{$base}/integration_log/_count"] = false;

    // orm_log exists and has docs
    $map["{$base}/orm_log"] = '{}';
    $map["{$base}/orm_log/_count"] = json_encode(['count' => 1]);

    // error_log exists and has docs
    $map["{$base}/error_log"] = '{}';
    $map["{$base}/error_log/_count"] = json_encode(['count' => 3]);

    $GLOBALS['__ll_file_get_contents_map'] = $map;
    $GLOBALS['__ll_stream_context'] = Str::random(8); // any non-null marker

    $this->artisan('opensearch:verify')->assertSuccessful();

    unset($GLOBALS['__ll_file_get_contents_map'], $GLOBALS['__ll_stream_context']);
    putenv('OPENSEARCH_URL');
});

