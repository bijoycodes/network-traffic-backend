<?php

$ml_end_point = "http://192.168.10.199:8001/predict";
$llm_end_point = "http://192.168.10.199:11434/api/chat";
$llm_model = "gemma:7b";
$llm_prompt = "
        This is sended by my mobile App
        %s 
        
        This is the output of my ML
        %s

        Now write a proper message for it
";


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$jsonBody = file_get_contents('php://input');

$data = json_decode($jsonBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body.'
    ]);
    exit;
}

$get_form_ml = '';

try{
    $ch_ml = curl_init($ml_end_point);
    curl_setopt_array($ch_ml, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 100
    ]);

    $response = curl_exec($ch_ml);

    if ($response === false) {

    } else {
        $get_form_ml = $response;
    }
}catch(Exception $e){
    $get_form_ml = '';
}


$final_response = "";
try{
    $ch_llm = curl_init($llm_end_point);
    $data_body = [
        "model" => $llm_model,
        "messages" => [
            [
                "role"=> "user",
                "content" => sprintf($llm_prompt, $jsonBody, $get_form_ml),
            ]
        ],
        "stream" => false,
    ];
    curl_setopt_array($ch_llm, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data_body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 100
    ]);
    $response_llm = curl_exec($ch_llm);
    if ($response_llm === false) {

    } else {
        $final_response = $response_llm;
    }
}catch(Exception $e){
    $final_response = "";
}

echo $final_response;