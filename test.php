<?php
$arr = [
    'name' => 'ayesa',
    'email' => 'ayesa@gmail.com',
    'mobile' => 8514092388,
    'address' => 'kolkata',
    'lists' => [
        'id' => 1,
        'sdf' => 1
    ]
];

$doc = [
    [
        'title' => 'Aadhar Front',
        'image' => 'https://dev.ehostingguru.com/school-shiksha/upload/image/0ff10d3e0198061c6551229bcea350ce.png'
    ],
    [
        'title' => 'Aadhar Back',
        'image' => 'https://dev.ehostingguru.com/school-shiksha/upload/image/0ff10d3e0198061c6551229bcea350ce.png'
    ],
    [
        'title' => 'H.S Marksheet',
        'image' => 'https://dev.ehostingguru.com/school-shiksha/upload/image/0ff10d3e0198061c6551229bcea350ce.png'
    ],

];

file_put_contents(__DIR__ . '/dev.json', json_encode($doc));
