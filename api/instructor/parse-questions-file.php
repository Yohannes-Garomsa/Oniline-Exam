<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../vendor/autoload.php'; // for pdf parser

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

if(empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "File upload failed"]);
    exit();
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['csv','pdf','txt','doc','docx'];
if(!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Unsupported file type"]);
    exit();
}

$questions = [];

// helper to parse plain text into question objects
function parseTextQuestions($text) {
    $out = [];
    // split by question numbers like "1)"
    $parts = preg_split('/(?<=\n)(?=\d+\))/m', $text);
    foreach($parts as $part) {
        $lines = array_filter(array_map('trim', explode("\n", $part)));
        if(!$lines) continue;
        // first line should start with number)
        if(!preg_match('/^(\d+)\)\s*(.*)$/', $lines[0], $m)) continue;
        $qtext = $m[2];
        $options = [];
        $type = 'MCQ';
        for($i=1;$i<count($lines);$i++) {
            if(preg_match('/^([A-D])\)\s*(.*)$/i', $lines[$i], $om)) {
                $options[strtoupper($om[1])] = trim($om[2]);
            }
        }
        if(empty($options)) {
            // maybe TF statement in question
            if(stripos($qtext, 'true/false') !== false) {
                $type = 'True/False';
                $options = ['A'=>'True','B'=>'False'];
            } else {
                // no options; skip
                continue;
            }
        }
        $qobj = [
            'question' => $qtext,
            'type' => $type,
            'difficulty' => 'Medium',
            'course_id' => null,
            'explanation' => '',
            'options' => []
        ];
        foreach($options as $letter=>$textOpt) {
            $qobj['options'][] = ['letter'=>$letter,'text'=>$textOpt,'is_correct'=>0];
        }
        $out[] = $qobj;
    }
    return $out;
}

if($ext === 'csv') {
    $fh = fopen($file['tmp_name'], 'r');
    if(!$fh) {
        http_response_code(500);
        echo json_encode(["success"=>false,"message"=>"Unable to read file"]);
        exit();
    }
    $header = fgetcsv($fh);
    if(!$header) {
        http_response_code(400);
        echo json_encode(["success"=>false,"message"=>"Empty CSV"]);
        exit();
    }
    // normalize header
    $map = array_map('strtolower', $header);
    // required headers
    $required = ['question','type','course_id','optiona','optionb','optionc','optiond','correct'];
    foreach($required as $h) {
        if(!in_array($h, $map)) {
            http_response_code(400);
            echo json_encode(["success"=>false,"message"=>"CSV missing column $h"]);
            exit();
        }
    }
    while(($row = fgetcsv($fh)) !== false) {
        $row = array_combine($map, $row);
        $q = [];
        $q['question'] = $row['question'];
        $q['type'] = $row['type'] ?: 'MCQ';
        $q['difficulty'] = $row['difficulty'] ?? 'Medium';
        $q['course_id'] = $row['course_id'];
        $q['explanation'] = $row['explanation'] ?? '';
        if(strtolower($q['type']) === 'true/false') {
            // build TF options and mark correct answer from "correct" column
            $q['options'] = [];
            $correct = strtolower(trim($row['correct']));
            $q['options'][] = ['letter'=>'A','text'=>'True','is_correct'=>($correct === 'true' || $correct === 'a') ? 1 : 0];
            $q['options'][] = ['letter'=>'B','text'=>'False','is_correct'=>($correct === 'false' || $correct === 'b') ? 1 : 0];
        } else {
            $q['options'] = [];
            foreach(['optiona','optionb','optionc','optiond'] as $opt) {
                if(!empty($row[$opt])) {
                    $letter = strtoupper(substr($opt, -1));
                    $q['options'][] = ['letter'=>$letter,'text'=>$row[$opt],'is_correct'=>($row['correct'] == $letter ? 1 : 0)];
                }
            }
        }
        $questions[] = $q;
    }
    fclose($fh);
} else {
    // pdf, doc, docx, txt
    $text = '';
    if(in_array($ext, ['pdf','doc','docx'])) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file['tmp_name']);
            $text = $pdf->getText();
        } catch(Exception $e) {
            // fall back to plain text read
            $text = file_get_contents($file['tmp_name']);
        }
    } else {
        $text = file_get_contents($file['tmp_name']);
    }
    $questions = parseTextQuestions($text);
}

echo json_encode(["success"=>true,"questions"=>$questions]);
