
<?php

require_once __DIR__ . "/../includes/database.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = connectDatabase();

    // Request parameters
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 15)));

    $offset = ($page - 1) * $limit;

    // Searchable columns
    $searchColumns = [
        'device_id',
        'device_name',
        'session_id',
        'app_name',
        'domain',
        'domain_source',
        'ip',
        'dns',
        'protocol',
        'geo_location',
        'risk_level',
        'analysis_result'
    ];

    $where = '';
    $params = [];

    if ($search !== '') {
        $conditions = [];

        foreach ($searchColumns as $column) {
            $conditions[] = "`$column` LIKE ?";
            $params[] = "%{$search}%";
        }

        $conditions[] = "CAST(port AS CHAR) LIKE ?";
        $params[] = "%{$search}%";

        $conditions[] = "CAST(total_bytes AS CHAR) LIKE ?";
        $params[] = "%{$search}%";

        $where = 'WHERE (' . implode(' OR ', $conditions) . ')';
    }

    // Total matching records
    $countSql = "
        SELECT COUNT(*)
        FROM network_analysis_data
        $where
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRecords / $limit));

    // Fetch records
    $sql = "
        SELECT
            id,
            device_id,
            device_name,
            session_id,
            app_name,
            domain,
            domain_source,
            ip,
            dns,
            port,
            protocol,
            timestamp,
            request_size_bytes,
            response_size_bytes,
            total_bytes,
            connection_frequency,
            geo_location,
            risk_level,
            analysis_result,
            full_json_request,
            ml_response,
            llm_response
        FROM network_analysis_data
        $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);

    $index = 1;

    foreach ($params as $param) {
        $stmt->bindValue($index++, $param, PDO::PARAM_STR);
    }

    $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($index, $offset, PDO::PARAM_INT);

    $stmt->execute();

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ],
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {

    error_log('Network Analysis API Error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch network analysis records.'
    ]);
}
