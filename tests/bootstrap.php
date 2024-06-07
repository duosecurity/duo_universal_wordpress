<?php

// "Mock" this wordpress function so unit tests can still use it
function plugin_dir_path($file) {
    return './';
}

// First we need to load the composer autoloader, so we can use WP Mock
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

// Bootstrap WP_Mock to initialize built-in features
WP_Mock::bootstrap();
