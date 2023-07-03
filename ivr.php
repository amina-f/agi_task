#!/usr/bin/php
<?php

require "agi.php";
$agi = new AGI();

$agi->answer();
$agi->playback("welcome");
if($agi->pwIsSet()) {
    $pwArray = $agi->pwEnter('old');
    $count = 1;
    do {
        $pwData = $pwArray['data'];
        $pwResult = trim($pwArray['result']);
        if($pwData == 'timeout') {
            if($count == 3) {
                die($agi->playback("login-fail"));
            }
            $pwArray = $agi->getData("please-try-again", 10000, 4); 
        } else if($agi->pwValidate($pwResult) == FALSE) {
            if($count == 3) {
                die($agi->playback("login-fail"));
            }
            $pwArray = $agi->getData("sorry_login_incorrect", 10000, 4);
        } else {
            break;
        }
    } while($count++ < 3);  
}

// Select option
$optArray = $agi->getData("press", 10000, 1);
$optData = $optArray['data'];
$optResult = $optArray['result'];
if($optData == "timeout" || $optResult == "") {
    die($agi->playback("goodbye"));
}

switch ($optResult) {
    case '1':   // Call an extension
        $extArray = $agi->getData("enter-ext-of-person", 10000, 10);
        if (trim($extArray['data']) == "timeout") {
            die($agi->playback("goodbye"));
        }

        $extension = trim($extArray['result']);
        $callerExten = trim($agi->agiVars['accountcode']);
        if($extension == $callerExten) {
            die($agi->playback("sorry-cant-let-you-do-that"));
        } else {
            $agi->dial($extension);
        }
        break;

    case '2':   // Set or change password
        if($agi->pwIsSet()) {
            $oldPw = $agi->pwEnter('old')['result'];
            if($agi->pwValidate($oldPw) == FALSE) {
                die($agi->playback("login-fail")); 
            }
        }

        $newPw = $agi->pwEnter('new')['result'];
        if($agi->pwCheck($newPw)) {
            $reenteredPw = $agi->pwEnter('reenter')['result'];
            if($newPw == $reenteredPw) {
                if($agi->updatePw($newPw) == TRUE) {
                    $agi->playback("vm-passchanged");
                } else {
                    die($agi->playback("an-error-has-occurred"));
                }
            } else {
                die($agi->playback("passwords_not_match"));    
            }
        } else {
            die($agi->playback("sorry-cant-let-you-do-that"));
        }
        break;

    case '3': // Loopback to the IVR
        $agi->exec("Goto", array("sets", $agi->agiVars['extension'], 1));
        break;

    default: // Invalid option
        die($agi->playback("option-is-invalid"));
}

?>