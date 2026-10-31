<?php
function writeInCsv( array $data, array $mlResponse, array $llmResponse ): void {
    $file = __DIR__ . "/../network-analysis.csv";

    $handle = fopen($file, "a");

    if ($handle) {
        $full_json_request = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
        $ml_response = json_encode( $mlResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
        $llm_response = json_encode( $llmResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );

        $full_json_request = str_replace( ["\r\n", "\r", "\n", ","], ["\\n", "\\n", "\\n", "\\,"], $full_json_request );
        $ml_response = str_replace( ["\r\n", "\r", "\n", ","], ["\\n", "\\n", "\\n", "\\,"], $ml_response );
        $llm_response = str_replace( ["\r\n", "\r", "\n", ","], ["\\n", "\\n", "\\n", "\\,"], $llm_response );
    
        fputcsv($handle, [
            $data['device_id'] ?? '',
            $data['device_name'] ?? '',
            $data['session_id'] ?? '',
            $data['app_name'] ?? '',
            $data['domain'] ?? '',
            $data['domain_source'] ?? '',
            $data['ip'] ?? '',
            $data['dns'] ?? '',
            $data['port'] ?? '',
            $data['protocol'] ?? '',
            $data['timestamp'] ?? '',
            $data['request_size_bytes'] ?? '',
            $data['response_size_bytes'] ?? '',
            $data['total_bytes'] ?? '',
            $data['connection_frequency'] ?? '',
            $data['geo_location'] ?? '',
            $data['risk_level'] ?? '',
            $data['analysis_result'] ?? '',
            $full_json_request,
            $ml_response,
            $llm_response,
        ]);

        fclose($handle);
        writeLog("Successfully wrote data to CSV file.", "INFO", $data['device_id'] ?? null);
    } else {
        writeLog("Failed to open CSV file for writing.", "ERROR", $data['device_id'] ?? null);
    }
}