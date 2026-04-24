<?php

$url = "https://two025-gp1-27-oogr.onrender.com"; // replace with your real API

$response = file_get_contents($url);

if ($response === FALSE) {
    echo "Error connecting to API";
} else {
    echo "API Response: <br>";
    echo $response;
}

?>