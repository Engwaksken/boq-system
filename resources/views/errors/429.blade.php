@include('errors.layout', ['code' => 429, 'icon' => 'fa-gauge-high', 'heading' => __('Too Many Requests'), 'message' => __('You are doing that too often. Please wait a moment and try again.')])
