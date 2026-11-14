<?php

/*
| Livewire is used only inside Content Studio. Components are classes in
| app/Livewire with their templates in resources/views/livewire. Settings
| not listed here keep Livewire's defaults.
*/
return [

    // Keep Livewire's own component discovery away from our Blade components folder.
    'component_locations' => [
        resource_path('views/livewire'),
    ],

    'component_namespaces' => [],

    'component_layout' => 'components.layouts.studio',

    'class_namespace' => 'App\\Livewire',
    'class_path' => app_path('Livewire'),
    'view_path' => resource_path('views/livewire'),

    'make_command' => [
        'type' => 'class',
        'emoji' => false,
        'with' => ['js' => false, 'css' => false, 'test' => false],
    ],

    'temporary_file_upload' => [
        'disk' => 'local',
        // Uploads are checked again, per kind, by App\Studio\MediaLibrary. 30 MB ceiling.
        'rules' => ['required', 'file', 'max:30720'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => ['png', 'gif', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#6D28D9',
    ],

];
