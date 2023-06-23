#!/usr/bin/php
<?php

$in = fopen('php://stdin', 'r');
$out = fopen('php://stdout', 'w');
fflush($out);

$servername = "localhost";
$username = "asterisk";
$password = "test123";
$dbName = "asterisk";
$tableName = "ps_auths";

// Check if input password matches database entry
function pwValidate($input, $pw) {
    if(trim($input) == trim($pw)) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Check if input password consists of only digits
function pwCheck($input) {
    if(preg_match("/[0-9]{4}/", trim($input))) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Enter old, new or reenter password
function pwEnter($type = 'old') {
    switch($type) {
        case 'old':
            $soundfile = 'enter-password';
            break;
        case 'new':
            $soundfile = 'vm-newpassword';
            break;
        case 'reenter':
            $soundfile = 'vm-reenterpassword';
            break;
        default:
            $soundfile = 'enter-password';
            break;
    }
    return agiWrite("GET DATA " . $soundfile . " 5000 4");
}

// Evaluate input
function checkInput($input) {
    trim($input);
    $info = array('code' => 0, 'result' => '', 'data' => '');
    $info['code'] = substr($input, 0, 3);
    $input = trim(substr($input, 3));
    $input = str_replace('result=', '', $input);
    $arr = explode(' ', $input);
    $info['result'] = trim($arr[0]);
    $len = count($arr);
    while($len>1) {
        $temp = trim($arr[count($arr) - $len + 1]);
        $temp = str_replace('(', '', $temp);
        $temp = str_replace(')', '', $temp);
        $info['data'] .= trim($temp);
        if($len > 2) {
            $info['data'] .= ',';
        }
        $len--;
    }
    return $info;
}

// Write a command to stdout and recieve response from stdin
function agiWrite($command) {
    fwrite($GLOBALS['out'], trim($command) . "\n");
    fflush($GLOBALS['out']);
    $input = trim(fgets($GLOBALS['in']));
    return checkInput($input);
}

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbName);
if($conn->connect_error) {
    die('Connection failed:' . $conn->connect_error);
}

// Get variables sent by Asterisk 
while($temp = trim(fgets($in))) {
    if($temp == "" && $temp == '\n') {
        break;        
    }
    $asteriskVars = explode(":", $temp);
    $name = str_replace("agi_", "", trim($asteriskVars[0]));
    $agiVars[$name] =  trim($asteriskVars[1]);
}

// Get current password from database
$sqlQuery = "select ivr_password from " . $dbName . "." . $tableName . " where id='" . $agiVars['callerid'] . "'";
$temp = $conn->query($sqlQuery);
$queryResult = $temp->fetch_assoc()['ivr_password'];

// Greeting and asking for password
agiWrite("EXEC Answer");
agiWrite("EXEC Playback welcome");
if($queryResult != "") {
    $pwArray = pwEnter('old');
    $pwInfo = $pwArray['data'];
    $pwInput = $pwArray['result'];
    $count = 1;
    do {
        if($pwInfo == 'timeout') {
            if($count == 3) {
                die("EXEC Playback login-fail");
            }
            $pwArray = agiWrite("GET DATA please-try-again 10000 4"); 
        } else if(pwValidate($pwInput, $queryResult) == FALSE) {
            if($count == 3) {
                die("EXEC Playback login-fail");
            }
            $pwArray = agiWrite("GET DATA sorry_login_incorrect 10000 4");
        } else {
            break;
        }
        $pwInfo = $pwArray['data'];
        $pwInput = trim($pwArray['result']);
    } while($count++ < 3);  
}

// Select option
$optArray = agiWrite("GET DATA press 10000 1");
$optInfo = $optArray['data'];
$optInput = $optArray['result'];
if($optInfo == "timeout" || $optInput == "") {
    die("EXEC Playback goodbye");
}

switch ($optInput) {
    case '1':   // Call an extension
        $extArray = agiwrite("GET DATA enter-ext-of-person 10000 10");
        $extension = trim($extArray['result']);
        $extInfo = $extArray['data'];
        if ($extInfo == "timeout" || $extension == "") {
            die("EXEC Playback goodbye");
        }
        $callerExten = $agiVars['accountcode'];
        if(trim($extension) == trim($callerExten)) {
            die("EXEC Playback sorry-cant-let-you-do-that\n");
        } else {
            agiWrite("EXEC Dial " . trim($agiVars['type']) . "/" . $extension . ",10");
        }
        break;

    case '2':   // Set or change password
        if($queryResult != "") {
            $oldPw = pwEnter('old')['result'];
            if(pwValidate($oldPw, $queryResult) === FALSE) {
                die("EXEC Playback login-fail\n"); 
            }
        }
        $newPw = pwEnter('new')['result'];
        if(pwCheck($newPw)) {
            $reenteredPw = pwEnter('reenter')['result'];
            if($newPw == $reenteredPw) {
                $sqlQuery = "update " . $dbName . "." . $tableName . " set ivr_password='" . $newPw . "' where id='" . $agiVars['callerid'] . "'";
                if($conn->query($sqlQuery) == TRUE) {
                    agiWrite("EXEC Playback vm-passchanged");
                } else {
                    die("EXEC Playback an-error-has-occurred\n");
                }
            } else {
                die("EXEC Playback passwords_not_match\n");    
            }
        } else {
            die("EXEC Playback sorry-cant-let-you-do-that\n");
        }
        break;

    case '3': // Loopback to the IVR
        agiWrite("EXEC Goto sets," . $agiVars['extension'] . ",1");
        break;

    default: // Invalid option
        die("EXEC Playback option-is-invalid");
}

fclose($in);
fclose($out);
$conn->close();
?>