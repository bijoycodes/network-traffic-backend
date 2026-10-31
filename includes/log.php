<?php
function writeLog( string $message, string $level = "INFO", ?string $deviceId = null): void {
    $logFile = __DIR__ . "/../network-analysis.log";

    $timestamp = date("Y-m-d H:i:s");
    $deviceIdentifier = $deviceId ?? "unknown";

    $logMessage = sprintf(
        "[%s] [%s] [device_id=%s] %s%s",
        $timestamp,
        strtoupper($level),
        $deviceIdentifier,
        $message,
        PHP_EOL
    );

    file_put_contents(
        $logFile,
        $logMessage,
        FILE_APPEND | LOCK_EX
    );
}
