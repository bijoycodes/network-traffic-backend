# Network Traffic Analysis

PHP application for collecting Android network-traffic records, sending the
records to a machine-learning service and a local language model, storing the
results, and browsing the stored analyses from a web dashboard.

The application currently contains:

- A browser dashboard in [`index.php`](./index.php).
- A JSON analysis API in [`api/index.php`](./api/index.php).
- MySQL persistence in [`includes/database.php`](./includes/database.php).
- CSV persistence in [`includes/csv.php`](./includes/csv.php).
- File logging in [`includes/log.php`](./includes/log.php).
- Vercel PHP runtime configuration in [`vercel.json`](./vercel.json).

## How the application works

1. An Android client sends a JSON object to `api/index.php`.
2. The API validates that the request is a JSON object rather than a JSON
   array.
3. The API sends the complete request object to the ML service:
   `http://localhost:8001/predict`.
4. The ML response must contain a non-empty string named
   `predicted_risk_level`.
5. The API sends the original Android data and ML response to the local LLM:
   `http://localhost:11434/api/chat`.
6. The LLM is requested to return JSON using the `gemma:7b` model.
7. The API validates that the LLM message contains a non-empty JSON object.
8. The request, ML response, and LLM response are stored in:
   - MySQL table `network_analysis_data`.
   - `network-analysis.csv`.
9. The API returns the ML prediction and parsed LLM explanation to the
   Android client.
10. The dashboard loads stored records from MySQL, supports searching and
    pagination, and displays the full JSON request, ML response, and LLM
    response in an expandable details row.

The LLM prompt explicitly treats the ML output as a prediction rather than
confirmed ground truth. It also instructs the model not to invent evidence,
confidence values, domains, permissions, or malware behavior.

## Requirements

- PHP 8.1 or later.
  - The code uses `array_is_list()`, scalar and nullable type declarations,
    and `JSON_THROW_ON_ERROR`.
- Apache or another PHP-capable web server.
- MySQL with a database named `network-analysis`.
- PHP PDO MySQL extension.
- PHP cURL extension.
- A running ML HTTP service at `http://localhost:8001/predict`.
- A running Ollama-compatible chat service at
  `http://localhost:11434/api/chat`.
- The `gemma:7b` model available to the local LLM service.
- Write permission for:
  - `network-analysis.csv`
  - `network-analysis.log`

The default database configuration is defined directly in
[`includes/database.php`](./includes/database.php):

| Setting | Default |
|---|---|
| Host | `localhost` |
| Database | `network-analysis` |
| Username | `root` |
| Password | empty |
| Character set | `utf8mb4` |

Change those values in `includes/database.php` for a different local
environment. The ML endpoint, LLM endpoint, and model are currently defined
at the top of [`api/index.php`](./api/index.php).

## Installation with XAMPP

1. Install XAMPP with Apache, PHP, and MySQL.
2. Place the project under the Apache document root, for example:

   ```text
   /Applications/XAMPP/xamppfiles/htdocs/research
   ```

3. Start Apache and MySQL from the XAMPP manager.
4. Create the database:

   ```sql
   CREATE DATABASE `network-analysis`
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

5. Create the `network_analysis_data` table using the schema below.
6. Start the ML service on port `8001`.
7. Start the local LLM service on port `11434` and make sure `gemma:7b` is
   available.
8. Open the dashboard:

   ```text
   http://localhost/research/
   ```

9. The analysis API is available at:

   ```text
   http://localhost/research/api/
   ```

## Database schema

The PHP code expects a MySQL table named `network_analysis_data` with the
following columns:

```sql
CREATE TABLE `network_analysis_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id` VARCHAR(255) NULL,
    `device_name` VARCHAR(255) NULL,
    `session_id` VARCHAR(255) NULL,
    `app_name` VARCHAR(255) NULL,
    `domain` VARCHAR(255) NULL,
    `domain_source` VARCHAR(100) NULL,
    `ip` VARCHAR(255) NULL,
    `dns` VARCHAR(255) NULL,
    `port` INT NULL,
    `protocol` VARCHAR(100) NULL,
    `timestamp` DATETIME(6) NULL,
    `request_size_bytes` BIGINT NULL,
    `response_size_bytes` BIGINT NULL,
    `total_bytes` BIGINT NULL,
    `connection_frequency` INT NULL,
    `geo_location` VARCHAR(255) NULL,
    `risk_level` VARCHAR(100) NULL,
    `analysis_result` TEXT NULL,
    `full_json_request` LONGTEXT NULL,
    `ml_response` LONGTEXT NULL,
    `llm_response` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_network_analysis_timestamp` (`timestamp`),
    INDEX `idx_network_analysis_device_id` (`device_id`),
    INDEX `idx_network_analysis_risk_level` (`risk_level`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

The application inserts all 21 data fields listed in the table. The JSON
columns contain JSON text rather than native MySQL JSON values, so the
dashboard parses them in JavaScript before displaying formatted output.

If the incoming `timestamp` values use ISO 8601 strings containing a `T` or
timezone suffix, confirm that the MySQL column type and server SQL mode accept
the values used by the client. The dashboard sorts records by `timestamp`
descending.

## Analysis API

### Endpoint

```text
POST /research/api/
Content-Type: application/json
```

The endpoint accepts only `POST`. Other methods return HTTP `405` and an
`Allow: POST` header.

### Request body

The body must be a JSON object. A typical request is:

```json
{
  "device_id": "dev_pixel_9_02",
  "device_name": "Pixel 7 Pro",
  "session_id": "sess_00003",
  "app_name": "com.spotify.music",
  "domain": "spotify.com",
  "domain_source": "observed",
  "ip": "35.186.224.25",
  "dns": "8.8.8.8",
  "port": 443,
  "protocol": "TCP/TLS",
  "timestamp": "2025-05-10T08:54:34.044Z",
  "request_size_bytes": 80,
  "response_size_bytes": 60,
  "total_bytes": 140,
  "connection_frequency": 1,
  "geo_location": "BD",
  "risk_level": "benign",
  "analysis_result": "Music audio stream streaming buffer"
}
```

The API does not require every application-specific field to be present in
the request. Missing fields are stored as `NULL` in MySQL or as empty CSV
fields. The `device_id` is used in log messages when it is present and
scalar.

### Successful response

On successful ML and LLM processing, the API returns HTTP `200`:

```json
{
  "success": true,
  "ml_prediction": {
    "predicted_risk_level": "suspicious"
  },
  "llm_explanation": {
    "summary": "A short explanation.",
    "predicted_label": "suspicious",
    "what_it_means": "A plain-language explanation.",
    "evidence": [],
    "limitations": [],
    "recommended_actions": [],
    "disclaimer": "This is an experimental automated assessment."
  }
}
```

`ml_prediction` is the complete decoded ML response. `llm_explanation` is the
decoded JSON contained in the LLM response's `message.content` field.

### Error responses

| Status | Condition | Response message |
|---:|---|---|
| `400` | Body is invalid JSON, not an object, or is a JSON list | `A valid JSON object is required.` |
| `405` | Request method is not `POST` | `Only POST requests are allowed.` |
| `502` | ML or LLM service fails, returns invalid JSON, or returns an invalid result | Service-specific failure message |
| `500` | Unexpected server-side exception | `An unexpected server error occurred.` |

For ML and LLM HTTP failures, the API uses a connection timeout of 10 seconds
and an overall request timeout of 100 seconds.

## ML service contract

The ML service receives the complete Android request object as JSON. It must
return a successful 2xx response containing a JSON object with a non-empty
string field:

```json
{
  "predicted_risk_level": "benign",
  "ml_connected": true
}
```

The API rejects the ML result if `predicted_risk_level` is missing, not a
string, or empty.

## LLM service contract

The API sends a request equivalent to:

```json
{
  "model": "gemma:7b",
  "stream": false,
  "format": "json",
  "messages": [
    {
      "role": "system",
      "content": "The project analysis system prompt."
    },
    {
      "role": "user",
      "content": "{\"android_app_data\":{},\"ml_prediction\":{}}"
    }
  ]
}
```

The LLM service must return a JSON object with a string at
`message.content`. That string must itself decode to a JSON object. The
expected explanation keys are:

- `summary`
- `predicted_label`
- `what_it_means`
- `evidence`
- `limitations`
- `recommended_actions`
- `disclaimer`

The API validates that the content is non-empty and decodes to an associative
JSON object. It does not enforce every expected explanation key after
decoding.

## Persistence

### MySQL

After both services succeed, `writeInDatabase()` inserts the source fields and
three JSON documents into `network_analysis_data` using a prepared statement:

- `full_json_request`: original Android request.
- `ml_response`: complete ML response.
- `llm_response`: complete LLM response.

Database write failures are logged, but the current database helper catches
the exception internally. The API can therefore still return a successful
analysis response when CSV writing and analysis processing succeeded but the
database insert failed. Check `network-analysis.log` when records appear to
be missing from the dashboard.

### CSV

The CSV file is [`network-analysis.csv`](./network-analysis.csv). Its header
contains:

```text
device_id,device_name,session_id,app_name,domain,domain_source,ip,dns,port,protocol,timestamp,request_size_bytes,response_size_bytes,total_bytes,connection_frequency,geo_location,risk_level,analysis_result,full_json_request,ml_response,llm_response
```

Each successful analysis appends one row. The three JSON fields are serialized
with `JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE`, and
`JSON_THROW_ON_ERROR`. The CSV helper also replaces line breaks and commas in
these JSON strings before passing them to `fputcsv()`. CSV consumers should
read the file using a CSV parser rather than splitting lines or commas
manually.

### Logs

The log file is [`network-analysis.log`](./network-analysis.log). Log entries
include:

- Timestamp.
- Uppercase log level.
- Device ID, or `unknown`.
- A short processing message.

Typical messages include request start, successful processing, service
validation failures, CSV persistence status, database persistence status, and
unexpected exceptions.

The CSV and log files may contain sensitive device, application, network, and
model-output data. Restrict access to them in production and do not expose
them through a public web directory without appropriate controls.

## Dashboard

Open the project root in a browser:

```text
http://localhost/research/
```

The dashboard uses a `POST` request to the current page with:

| Form field | Description |
|---|---|
| `action` | Must be `fetch` |
| `search` | Optional free-text search |
| `page` | One-based page number |

The dashboard returns 15 records per page. Search covers:

- `device_id`
- `device_name`
- `session_id`
- `app_name`
- `domain`
- `domain_source`
- `ip`
- `dns`
- `protocol`
- `geo_location`
- `risk_level`
- `analysis_result`
- `port`
- `total_bytes`

Search input is debounced by 350 milliseconds. Displayed values are HTML
escaped, and stored JSON is formatted in expandable detail cards for the
full request, ML response, and LLM response.

## Testing the API manually

With Apache, the ML service, and the LLM service running:

```bash
curl -i \
  -X POST \
  -H "Content-Type: application/json" \
  --data @request.json \
  http://localhost/research/api/
```

Example `request.json`:

```json
{
  "device_id": "dev_test_001",
  "device_name": "Test Android",
  "session_id": "session_test_001",
  "app_name": "com.example.test",
  "domain": "example.com",
  "domain_source": "observed",
  "ip": "192.0.2.10",
  "dns": "8.8.8.8",
  "port": 443,
  "protocol": "TCP/TLS",
  "timestamp": "2026-10-31T12:00:00Z",
  "request_size_bytes": 100,
  "response_size_bytes": 250,
  "total_bytes": 350,
  "connection_frequency": 1,
  "geo_location": "unknown",
  "risk_level": "unknown",
  "analysis_result": "Test network record"
}
```

PHP syntax can be checked without making a network request:

```bash
php -l index.php
php -l api/index.php
php -l includes/csv.php
php -l includes/database.php
php -l includes/log.php
```

## Troubleshooting

### `A required analysis service failed`

- Confirm the ML service is listening on port `8001`.
- Confirm `POST /predict` accepts the request shape.
- Confirm the LLM service is listening on port `11434`.
- Confirm the `gemma:7b` model is installed.
- Check `network-analysis.log` for the underlying error.

### The API returns an invalid ML prediction error

Confirm the ML service returns valid JSON with a non-empty
`predicted_risk_level` string.

### The API returns an invalid LLM explanation error

Confirm the LLM response has a non-empty `message.content` value and that the
content is a JSON object, not Markdown, a code fence, or plain text.

### The dashboard cannot load records

- Confirm MySQL is running.
- Confirm the `network-analysis` database exists.
- Confirm `network_analysis_data` exists with the required columns.
- Verify the credentials in `includes/database.php`.
- Check PHP's PDO MySQL extension.
- Check `network-analysis.log` for database connection errors.

### CSV or log records are not created

Confirm that the web-server user can write to the project directory and that
the target files exist or can be created.

### Vercel deployment limitations

[`vercel.json`](./vercel.json) configures PHP files under `api/` for the
`vercel-php@0.9.0` runtime. The current application still points to
`localhost` for the ML and LLM services, uses local file persistence, and
uses a local MySQL configuration. Those dependencies are normally not
available in a Vercel deployment, and serverless local files are not durable.
Use reachable hosted services and a managed database or adapt the deployment
configuration before deploying outside the local XAMPP environment.

## Project layout

```text
research/
├── api/
│   └── index.php             JSON analysis API
├── includes/
│   ├── csv.php               CSV persistence helper
│   ├── database.php          MySQL connection and insert helper
│   └── log.php               File logging helper
├── index.php                 Dashboard and dashboard AJAX handler
├── network-analysis.csv      CSV analysis records
├── network-analysis.log      Application log
└── vercel.json               Vercel PHP runtime configuration
```

## Security and privacy notes

- Treat request data, network addresses, device identifiers, and model output
  as sensitive.
- Do not commit production logs or collected traffic data to a public
  repository.
- Restrict access to the CSV and log files.
- Use non-empty, least-privilege database credentials outside local XAMPP.
- Place the API behind HTTPS and appropriate authentication before exposing it
  to untrusted clients.
- The ML result is a prediction, not proof of malware or privacy-invasive
  behavior.
- The LLM receives the original Android data and ML response, so protect the
  LLM service and review its data-retention behavior.
