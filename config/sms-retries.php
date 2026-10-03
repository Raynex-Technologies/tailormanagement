<?php

return [
    'max_messages' => (int) env('SMS_RETRY_MAX_MESSAGES', 10),
    'max_seconds' => (int) env('SMS_RETRY_MAX_SECONDS', 40),
    'spacing_seconds' => (int) env('SMS_RETRY_SPACING_SECONDS', 2),
];
