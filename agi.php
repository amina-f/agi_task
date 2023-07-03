#!/usr/bin/php
<?php

class AGI {
    public $in;
    public $out;
    public $dbConn;
    public $agiVars = array();
    private $dbVars;
    private $ivrPw;

    function __construct() {
        $this->in = fopen('php://stdin', 'r');
        $this->out = fopen('php://stdout', 'w');
        fflush($this->out);
        $this->dbVars = array("servername"=>"localhost", "username"=>"asterisk", "password"=>"test123", "dbName"=>"asterisk", "tableName"=>"ps_auths");
        $this->dbConn = new mysqli($this->dbVars["servername"], $this->dbVars["username"], $this->dbVars["password"], $this->dbVars["dbName"]);
        if($this->dbConn->connect_error) {
            die('Connection failed:' . $this->dbConn->connect_error);
        }
        while($temp = trim(fgets($this->in))) {
            if($temp == "" && $temp == '\n') {
                break;        
            }
            $asteriskVars = explode(":", $temp);
            $name = str_replace("agi_", "", trim($asteriskVars[0]));
            $this->agiVars[$name] = trim($asteriskVars[1]);
        }
        $this->ivrPw = $this->dbSelect("ivr_password", "id", $this->agiVars['callerid']);
    }

    function __destruct() {
        fclose($this->in);
        fclose($this->out);
        $this->dbConn->close();
    }

    function pwIsSet() {
        if($this->ivrPw == "") {
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    function pwValidate($input) {
        if(trim($input) == trim($this->ivrPw)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function pwCheck($input) {
        if(preg_match("/[0-9]{4}/", trim($input))) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

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
        return $this->getData($soundfile, 5000, 4);
    }

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

    function agiWrite($command) {
        fwrite($this->out, trim($command) . "\n");
        fflush($this->out);
        $input = trim(fgets($this->in));
        return $this->checkInput($input);
    }

    function dbSelect($selection, $column, $value) {
        $query = "SELECT $selection FROM " . $this->dbVars["dbName"] . "." . $this->dbVars["tableName"] . " WHERE $column='$value'";
        $queryResult = $this->dbConn->query($query);
        return $queryResult->fetch_assoc()[$selection];
    }

    function dbUpdate($setColumn, $setValue, $conditionColumn, $conditionValue) {
        $query = "UPDATE " . $this->dbVars["dbName"] . "." . $this->dbVars["tableName"] . " SET $setColumn='" . $setValue . "' WHERE $conditionColumn='" . $conditionValue . "'";
        return $this->dbConn->query($query);
    }

    function updatePw($newPw) {
        return $this->dbUpdate("ivr_password", $newPw, "id", $this->agiVars['callerid']);
    }

    function exec($application, $options) {
        if(is_array($options)) {
            $options = implode(',', $options);
        }
        return $this->agiWrite("EXEC $application $options");
    }

    function getData($soundfile, $timeout, $maxdigits) {
        return $this->agiWrite("GET DATA $soundfile $timeout $maxdigits");
    }

    function answer() {
        $this->agiWrite("ANSWER");
    }

    function dial($exten) {
        $this->exec("Dial", array(trim($this->agiVars['type']) . "/$exten", 10));
    }

    function playback($soundfile) {
        $this->exec("Playback", $soundfile);
    }
}

?>