<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Acceptance extends \Codeception\Module
{
    function testar() {
        $I = $this;
        $I->see("yolo");
        echo "testar";
    }
}
