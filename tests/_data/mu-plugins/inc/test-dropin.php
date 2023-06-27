<?php

namespace Simple_History\Tests\Dropin;

add_action(
    'simple_history/add_custom_dropin',
    function ( $simpleHistory ) {
        require_once __DIR__ . '/class-tests-dropin.php';

        $simpleHistory->register_dropin( \Simple_History\Tests\Dropin\Tests_Dropin::class );
    }
);
