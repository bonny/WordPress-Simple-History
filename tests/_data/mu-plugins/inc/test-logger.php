<?php

namespace Simple_History\Tests\Logger;

add_action(
    'simple_history/add_custom_logger',
    function ( $simpleHistory ) {
        require_once __DIR__ . '/class-tests-logger.php';
        $simpleHistory->register_logger( \Simple_History\Tests\Logger\Tests_Logger::class );
    }
);
