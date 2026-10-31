
<?php

function connectDatabase(): PDO{
    $host = 'localhost';
    $dbname = 'network-analysis';
    $username = 'root';
    $password = '';

    try {
        return new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

    } catch (PDOException $e) {
        writeLog("Database connection failed: " . $e->getMessage(), "ERROR");
        throw $e;
    }
}



function writeInDatabase( array $data,  array $mlResponse,  array $llmResponse ): void {

    $deviceId = $data['device_id'] ?? null;

    try {
        $db = connectDatabase();

        $sql = "
            INSERT INTO `network_analysis_data` (
                `device_id`,
                `device_name`,
                `session_id`,
                `app_name`,
                `domain`,
                `domain_source`,
                `ip`,
                `dns`,
                `port`,
                `protocol`,
                `timestamp`,
                `request_size_bytes`,
                `response_size_bytes`,
                `total_bytes`,
                `connection_frequency`,
                `geo_location`,
                `risk_level`,
                `analysis_result`,
                `full_json_request`,
                `ml_response`,
                `llm_response`
            ) VALUES (
                :device_id,
                :device_name,
                :session_id,
                :app_name,
                :domain,
                :domain_source,
                :ip,
                :dns,
                :port,
                :protocol,
                :timestamp,
                :request_size_bytes,
                :response_size_bytes,
                :total_bytes,
                :connection_frequency,
                :geo_location,
                :risk_level,
                :analysis_result,
                :full_json_request,
                :ml_response,
                :llm_response
            )
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':device_id'             => $data['device_id'] ?? null,
            ':device_name'           => $data['device_name'] ?? null,
            ':session_id'            => $data['session_id'] ?? null,
            ':app_name'               => $data['app_name'] ?? null,
            ':domain'                 => $data['domain'] ?? null,
            ':domain_source'          => $data['domain_source'] ?? null,
            ':ip'                     => $data['ip'] ?? null,
            ':dns'                    => $data['dns'] ?? null,
            ':port'                   => $data['port'] ?? null,
            ':protocol'               => $data['protocol'] ?? null,
            ':timestamp'              => $data['timestamp'] ?? null,
            ':request_size_bytes'     => $data['request_size_bytes'] ?? null,
            ':response_size_bytes'    => $data['response_size_bytes'] ?? null,
            ':total_bytes'            => $data['total_bytes'] ?? null,
            ':connection_frequency'   => $data['connection_frequency'] ?? null,
            ':geo_location'           => $data['geo_location'] ?? null,
            ':risk_level'             => $data['risk_level'] ?? null,
            ':analysis_result'        => $data['analysis_result'] ?? null,

            ':full_json_request' => json_encode(
                $data,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
            ),

            ':ml_response' => json_encode(
                $mlResponse,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
            ),

            ':llm_response' => json_encode(
                $llmResponse,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
            ),
        ]);

        writeLog(
            "Successfully stored data in database.",
            "INFO",
            $deviceId
        );

    } catch (Throwable $e) {

        writeLog(
            "Failed to store data in database: " . $e->getMessage(),
            "ERROR",
            $deviceId
        );
    }
}

