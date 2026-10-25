<?php

return [
    'sns_topics' => array_values(array_filter(array_map('trim', explode(',', env('EMAIL_TRACKING_SNS_TOPICS', ''))))),
];
