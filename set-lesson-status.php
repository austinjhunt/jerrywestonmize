<?php
// Check if the query parameters are set
if (isset($_GET['appointment_id']) && isset($_GET['status'])) {
    $appointment_id = $_GET['appointment_id'];
    $status = $_GET['status'];
    $ameliaApiKey = getenv('AMELIA_API_KEY');

    // Initialize cURL
    $ch = curl_init();
    echo "Appointment ID: " . $appointment_id . "<br>";
    echo "Status: " . $status . "<br>";
    echo "API Key: " . $ameliaApiKey . "<br>";

    // Set cURL options
    $url = 'https://jerrywestonmize.com/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1/appointments/status/' . $appointment_id;

    echo $url;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Amelia: ' . $ameliaApiKey . '';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $data = array(
        'status' => $status,
        'packageCustomerId' => null
    );
    $json_data = json_encode($data);
    echo $json_data;
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Execute and fetch the response
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        echo 'No error';
    }
    // Output the response
    echo $response;
    curl_close($ch);

    // Output the response
    echo $response;

} else {
    echo "Please provide both appointment_id and status.";
}
?>