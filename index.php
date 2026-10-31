
<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Network Analysis Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f5f6fa;
            color: #20232a;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1600px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 8px;
        }

        .subtitle {
            color: #777;
            margin: 0;
        }

        .status {
            color: #16803d;
            font-size: 13px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        #searchInput {
            width: 100%;
            max-width: 450px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        #searchInput:focus {
            border-color: #555;
        }

        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        th, td {
            padding: 13px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            white-space: nowrap;
        }

        th {
            background: #f9fafb;
            color: #555;
        }

        tr:hover td {
            background: #fafafa;
        }

        .risk {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .risk-benign {
            background: #e2f8e9;
            color: #16803d;
        }

        .risk-suspicious {
            background: #fff1d6;
            color: #a56600;
        }

        .risk-malicious {
            background: #ffe1e1;
            color: #c62828;
        }

        .risk-default {
            background: #eee;
            color: #555;
        }

        .details-button {
            padding: 7px 11px;
            border: 0;
            border-radius: 5px;
            background: #20232a;
            color: white;
            cursor: pointer;
        }

        .details-row td {
            white-space: normal;
            background: #f9fafb;
        }

        .details-content {
            display: grid;
            grid-template-columns: repeat(3, minmax(250px, 1fr));
            gap: 15px;
        }

        .json-card {
            min-width: 0;
        }

        .json-card h4 {
            margin: 5px 0 10px;
        }

        .json-card pre {
            padding: 12px;
            background: #20232a;
            color: #e8e8e8;
            border-radius: 7px;
            overflow-x: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 12px;
            line-height: 1.5;
        }

        .analysis-text {
            white-space: normal;
            min-width: 180px;
            max-width: 300px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
        }

        .pagination button {
            padding: 9px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
        }

        .pagination button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        #pageInfo {
            font-size: 14px;
            color: #555;
        }

        .message {
            padding: 30px;
            text-align: center;
            color: #777;
        }

        @media (max-width: 800px) {
            body {
                padding: 15px;
            }

            .toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            #searchInput {
                max-width: none;
            }

            .details-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Network Analysis</h1>
            <p class="subtitle">Live network traffic monitoring</p>
        </div>

        <div class="status" id="updateStatus">
            Connecting...
        </div>
    </div>

    <div class="toolbar">

        <input
            type="search"
            id="searchInput"
            placeholder="Search device, domain, IP, app, risk level..."
            autocomplete="off"
        >

        <div id="recordCount">
            Loading records...
        </div>

    </div>

    <div class="table-wrapper">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>App</th>
                    <th>Domain</th>
                    <th>IP</th>
                    <th>Port</th>
                    <th>Protocol</th>
                    <th>Total Bytes</th>
                    <th>Location</th>
                    <th>Risk</th>
                    <th>Timestamp</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody id="tableBody">
                <tr>
                    <td colspan="12" class="message">
                        Loading records...
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

    <div class="pagination">
        <button id="prevButton" disabled>Previous</button>
        <span id="pageInfo">Page 1</span>
        <button id="nextButton" disabled>Next</button>
    </div>

</div>

<script>
    const API_URL = 'api/analysis.php';

    const REFRESH_INTERVAL = 20000;
    const PAGE_LIMIT = 15;

    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('tableBody');
    const recordCount = document.getElementById('recordCount');
    const updateStatus = document.getElementById('updateStatus');

    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const pageInfo = document.getElementById('pageInfo');

    let currentPage = 1;
    let totalPages = 1;

    let lastRecords = null;
    let searchTimer = null;
    let activeController = null;

    /**
     * Escape HTML.
     */
    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    /**
     * Format JSON.
     */
    function formatJSON(value) {
        if (!value) {
            return 'No data available';
        }

        try {
            const parsed = typeof value === 'string'
                ? JSON.parse(value)
                : value;

            return escapeHTML(JSON.stringify(parsed, null, 2));
        } catch (error) {
            return escapeHTML(value);
        }
    }

    /**
     * Risk level class.
     */
    function getRiskClass(risk) {
        const value = String(risk ?? '').toLowerCase();

        if (value === 'benign') return 'risk-benign';
        if (value === 'suspicious') return 'risk-suspicious';
        if (value === 'malicious') return 'risk-malicious';

        return 'risk-default';
    }

    /**
     * Render records.
     */
    function renderRecords(records) {

        // Remember which details are currently open.
        const openedDetails = new Set();

        document.querySelectorAll('.details-row:not([hidden])').forEach(row => {
            openedDetails.add(row.id);
        });

        if (!records.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="12" class="message">
                        No records found.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = records.map(record => {
            const id = Number(record.id);
            const detailsId = `details-${id}`;
            const isOpen = openedDetails.has(detailsId);

            return `
                <tr>
                    <td>${escapeHTML(record.id)}</td>

                    <td>
                        <strong>${escapeHTML(record.device_name)}</strong>
                        <br>
                        <small>${escapeHTML(record.device_id)}</small>
                    </td>

                    <td>${escapeHTML(record.app_name)}</td>
                    <td>${escapeHTML(record.domain)}</td>
                    <td>${escapeHTML(record.ip)}</td>
                    <td>${escapeHTML(record.port)}</td>
                    <td>${escapeHTML(record.protocol)}</td>
                    <td>${escapeHTML(record.total_bytes)}</td>
                    <td>${escapeHTML(record.geo_location)}</td>

                    <td>
                        <span class="risk ${getRiskClass(record.risk_level)}">
                            ${escapeHTML(record.risk_level)}
                        </span>
                    </td>

                    <td>${escapeHTML(record.timestamp)}</td>

                    <td>
                        <button
                            class="details-button"
                            type="button"
                            data-id="${id}"
                        >
                            ${isOpen ? 'Hide' : 'View'}
                        </button>
                    </td>
                </tr>

                <tr
                    class="details-row"
                    id="${detailsId}"
                    ${isOpen ? '' : 'hidden'}
                >
                    <td colspan="12">

                        <div class="details-content">

                            <div class="json-card">
                                <h4>Full JSON Request</h4>
                                <pre>${formatJSON(record.full_json_request)}</pre>
                            </div>

                            <div class="json-card">
                                <h4>ML Response</h4>
                                <pre>${formatJSON(record.ml_response)}</pre>
                            </div>

                            <div class="json-card">
                                <h4>LLM Response</h4>
                                <pre>${formatJSON(record.llm_response)}</pre>
                            </div>

                        </div>

                        <p class="analysis-text">
                            <strong>Analysis Result:</strong>
                            ${escapeHTML(record.analysis_result)}
                        </p>

                        <p>
                            <strong>Session:</strong>
                            ${escapeHTML(record.session_id)}
                        </p>

                        <p>
                            <strong>DNS:</strong>
                            ${escapeHTML(record.dns)}
                        </p>

                        <p>
                            <strong>Domain Source:</strong>
                            ${escapeHTML(record.domain_source)}
                        </p>

                        <p>
                            <strong>Request Size:</strong>
                            ${escapeHTML(record.request_size_bytes)} bytes
                            |
                            <strong>Response Size:</strong>
                            ${escapeHTML(record.response_size_bytes)} bytes
                            |
                            <strong>Connections:</strong>
                            ${escapeHTML(record.connection_frequency)}
                        </p>

                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Fetch records from API.
     *
     * force = true cancels an older request.
     */
    async function fetchRecords(page = currentPage, force = false) {

        if (activeController && !force) {
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        const controller = new AbortController();
        activeController = controller;

        currentPage = page;

        const params = new URLSearchParams({
            search: searchInput.value.trim(),
            page: currentPage,
            limit: PAGE_LIMIT
        });

        try {
            const response = await fetch(`${API_URL}?${params.toString()}`, {
                method: 'GET',
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'API request failed.');
            }

            // Ignore results from an outdated request.
            if (activeController !== controller) {
                return;
            }

            const records = result.records;
            const pagination = result.pagination;

            // Compare new records with previously fetched records.
            const newData = JSON.stringify(records);
            const hasChanged = newData !== lastRecords;

            // Only update the table when data actually changes.
            if (hasChanged) {
                renderRecords(records);
                lastRecords = newData;
            }

            totalPages = pagination.total_pages;

            recordCount.textContent =
                `${pagination.total_records} records found`;

            pageInfo.textContent =
                `Page ${pagination.current_page} of ${pagination.total_pages}`;

            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = currentPage >= totalPages;

            const now = new Date().toLocaleTimeString();

            updateStatus.textContent = hasChanged
                ? `Updated: ${now}`
                : `No changes · Checked: ${now}`;

        } catch (error) {

            if (error.name === 'AbortError') {
                return;
            }

            console.error('Fetch error:', error);

            updateStatus.textContent = 'Connection error. Retrying...';

            if (lastRecords === null) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="12" class="message">
                            Unable to load records.
                        </td>
                    </tr>
                `;
            }

        } finally {

            if (activeController === controller) {
                activeController = null;
            }
        }
    }

    /**
     * Search with debounce.
     */
    searchInput.addEventListener('input', () => {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {
            lastRecords = null;
            fetchRecords(1, true);
        }, 350);

    });

    /**
     * Pagination.
     */
    prevButton.addEventListener('click', () => {
        if (currentPage > 1) {
            lastRecords = null;
            fetchRecords(currentPage - 1, true);
        }
    });

    nextButton.addEventListener('click', () => {
        if (currentPage < totalPages) {
            lastRecords = null;
            fetchRecords(currentPage + 1, true);
        }
    });

    /**
     * Toggle details.
     */
    tableBody.addEventListener('click', event => {

        const button = event.target.closest('.details-button');

        if (!button) return;

        const details = document.getElementById(
            `details-${button.dataset.id}`
        );

        if (!details) return;

        details.hidden = !details.hidden;

        button.textContent = details.hidden ? 'View' : 'Hide';
    });

    /**
     * Initial fetch.
     */
    fetchRecords(1, true);

    /**
     * Automatically check for changes every 20 seconds.
     */
    setInterval(() => {

        // Avoid unnecessary requests when the browser tab is hidden.
        if (document.hidden) {
            return;
        }

        fetchRecords(currentPage);

    }, REFRESH_INTERVAL);

</script>

</body>
</html>
