<?php

return ['graph_base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'), 'graph_version' => env('META_GRAPH_VERSION', 'v23.0'), 'timeout' => (int) env('META_WHATSAPP_TIMEOUT', 10)];
