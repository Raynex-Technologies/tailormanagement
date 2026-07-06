<?php

return [
    'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
    'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
    'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', ''),
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID', ''),
    'content_api_base_url' => env('TWILIO_CONTENT_API_BASE_URL', 'https://content.twilio.com/v1'),
    'messages_api_base_url' => env('TWILIO_MESSAGES_API_BASE_URL', 'https://api.twilio.com/2010-04-01'),
    'timeout' => env('TWILIO_TIMEOUT', 30),
    'retry_times' => env('TWILIO_RETRY_TIMES', 2),
    'retry_sleep' => env('TWILIO_RETRY_SLEEP', 500),
];
