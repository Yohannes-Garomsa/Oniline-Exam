<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=students_list.csv");

require_once '../../middleware/auth.php';
require_once '../../models/Report.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

$report = new Report();
$students = $report->exportStudentsList();

// Generate CSV
$csv = "Student ID,First Name,Last Name,Email,Phone,Department,Year,Section,GPA,Registered,Status,Exams Taken,Average Score\n";

foreach($students as $student) {
    $csv .= implode(',', [
        $student['user_id'],
        $student['first_name'],
        $student['last_name'],
        $student['email'],
        $student['phone'] ?? 'N/A',
        $student['department'] ?? 'N/A',
        $student['year_of_study'] ?? 'N/A',
        $student['section'] ?? 'N/A',
        $student['gpa'] ?? 'N/A',
        $student['created_at'],
        $student['status'],
        $student['exams_taken'] ?? 0,
        round($student['average_score'] ?? 0, 2) . '%'
    ]) . "\n";
}

echo $csv;
?>