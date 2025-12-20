<?php
$data = [
    'user_id' => $receiver_id,
    'notif' => [
        'title' => 'New Property Saved',
        'message' => 'User X saved your property!'
    ]
];

$ch = curl_init('http://localhost:3001/send_notification'); // optional HTTP endpoint on Node
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
