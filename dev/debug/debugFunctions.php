<?php
/**
 * @param string $message enter msg that show JS alert message
 * use for debug 
 */
function show(String $message) {
    $safeMessage = json_encode($message);
    
    echo "<script type='text/javascript'>alert($safeMessage);</script>";
}
/**
 * @param string $err mostly string err are string 
 * use for debug 
 * idk gemini recommend ts but the logs is works great
 */
function err_log($err) {
    if (is_array($err) || is_object($err)) {
        $err = print_r($err, true);
    }
    $timestamp = "[" . date('Y-m-d H:i:s') . "] ";
    error_log($timestamp . $err . PHP_EOL, 3, LOGS_PATH . DS . "logs.log");
}