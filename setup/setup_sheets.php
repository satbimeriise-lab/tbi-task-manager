<?php
// ============================================================
//  Setup Script — Initialize Google Sheet structure + sample data
//  Run ONCE: php setup/setup_sheets.php
//  or visit http://yoursite/tbi_task_manager/setup/setup_sheets.php
// ============================================================

// Protect this script from public access
define('SETUP_SECRET', 'TBI_SETUP_2024');  // Change this!
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if ($key !== SETUP_SECRET) {
        http_response_code(403);
        die('<h2>403 Forbidden</h2><p>Add ?key=YOUR_SETUP_SECRET to the URL</p>');
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$client = new Google\Client();
$client->setApplicationName(APP_NAME);
$client->setScopes([Google\Service\Sheets::SPREADSHEETS]);
$client->setAuthConfig(CREDENTIALS_PATH);
$service  = new Google\Service\Sheets($client);
$sheetId  = SPREADSHEET_ID;

function addSheet($service, $sheetId, $name) {
    $body = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => [[
            'addSheet' => [
                'properties' => ['title' => $name]
            ]
        ]]
    ]);
    try {
        $service->spreadsheets->batchUpdate($sheetId, $body);
        echo "✓ Created sheet: $name\n";
    } catch (Exception $e) {
        echo "! Sheet '$name' may already exist: " . $e->getMessage() . "\n";
    }
}

function writeRow($service, $sheetId, $sheet, $row, $values) {
    $range = "{$sheet}!A{$row}";
    $body  = new Google\Service\Sheets\ValueRange(['values' => [$values]]);
    $service->spreadsheets_values->update($sheetId, $range, $body, ['valueInputOption' => 'USER_ENTERED']);
}

function appendRows($service, $sheetId, $sheet, $rows) {
    $body = new Google\Service\Sheets\ValueRange(['values' => $rows]);
    $service->spreadsheets_values->append($sheetId, $sheet, $body, ['valueInputOption' => 'USER_ENTERED']);
}

echo "<pre>\n";
echo "=== TBI-MCE Task Manager — Sheet Setup ===\n\n";

// ── 1. Create sheets ──────────────────────────────────────────
foreach ([SHEET_EMPLOYEES, SHEET_TASKS, SHEET_APPROVALS, SHEET_USERS, SHEET_NOTIFICATIONS] as $sheet) {
    addSheet($service, $sheetId, $sheet);
}

// ── 2. Headers ────────────────────────────────────────────────
sleep(1);
writeRow($service, $sheetId, SHEET_EMPLOYEES,     1, ['Employee_ID','Name','Designation','Email','Phone','Photo_URL','Status']);
writeRow($service, $sheetId, SHEET_TASKS,         1, ['Task_ID','Employee_ID','Task_Title','Description','Priority','Assigned_Date','Deadline','Status','Days_Pending','Assigned_By','File_URL','Notes']);
writeRow($service, $sheetId, SHEET_APPROVALS,     1, ['Approval_ID','Task_ID','Employee_ID','Status','Approved_By','Comments','Approval_Date','Submission_Date']);
writeRow($service, $sheetId, SHEET_USERS,         1, ['User_ID','Username','Password_Hash','Designation','Employee_ID','Email','Name','Reset_Token','Reset_Expiry']);
writeRow($service, $sheetId, SHEET_NOTIFICATIONS, 1, ['Notif_ID','User_ID','Message','Type','Read_Status','Created_At']);
echo "✓ Headers written to all sheets\n";

// ── 3. Sample Employees ───────────────────────────────────────
$sampleEmployees = [
    ['EMP_001', 'Dr. Geetha Kiran A',   'CEO',                  'ceotbimeriise@mcehassan.ac.in', '+91 98765 43210', '', 'Active'],
    ['EMP_002', 'Dr. Mohana Lakshmi J',   'COO',                  'cootbimeriise@mcehassan.ac.in',  '+91 98765 43211', '', 'Active'],
    ['EMP_003', 'Mr.Darshan H D',      'Software Associate',   'satbimeriise@mcehassan.ac.in',   '+91 98765 43212', '', 'Active'],
    ['EMP_004', 'Miss. Ramya K V',    'Finance Associate',    'fatbimeriise@mcehassan.ac.in',  '+91 98765 43213', '', 'Active'],
    ['EMP_005', 'Ms. Madhurya H V',   'Innovation Associate', 'iatbimeriise@mcehassan.ac.in', '+91 98765 43214', '', 'Active'],
    ['EMP_006', 'Ms. Deeksha M S',   'Supporting Staff',     'sstbimeriise@mcehassan.ac.in', '+91 98765 43215', '', 'Active'],
];
appendRows($service, $sheetId, SHEET_EMPLOYEES, $sampleEmployees);
echo "✓ 6 sample employees added\n";

// ── 4. Sample Users (hashed passwords) ───────────────────────
// Admin passwords: Admin@123   Employee passwords: Employee@123
$adminHash = password_hash('Admin@123',    PASSWORD_BCRYPT);
$empHash   = password_hash('Employee@123', PASSWORD_BCRYPT);

$sampleUsers = [
    ['USR_001', 'geetha',   $adminHash, 'CEO',                  'EMP_001', 'ceotbimeriise@mcehassan.ac.in', 'Dr. Geetha Kiran A',  '', ''],
    ['USR_002', 'mohana',   $adminHash, 'COO',                  'EMP_002', 'cootbimeriise@mcehassan.ac.in', 'Dr. Mohana Lakshmi J','', ''],
    ['USR_003', 'darshan',  $empHash,   'Software Associate',   'EMP_003', 'satbimeriise@mcehassan.ac.in',  'Mr. Darshan H D',     '', ''],
    ['USR_004', 'ramya',    $empHash,   'Finance Associate',    'EMP_004', 'fatbimeriise@mcehassan.ac.in',  'Miss. Ramya K V',     '', ''],
    ['USR_005', 'madhurya', $empHash,   'Innovation Associate', 'EMP_005', 'iatbimeriise@mcehassan.ac.in',  'Ms. Madhurya H V',    '', ''],
    ['USR_006', 'deeksha',  $empHash,   'Supporting Staff',     'EMP_006', 'sstbimeriise@mcehassan.ac.in',  'Ms. Deeksha M S',     '', ''],
    ['USR_007', 'admin',    $adminHash, 'CEO',                  'EMP_001', 'ceotbimeriise@mcehassan.ac.in', 'Dr. Geetha Kiran A',  '', ''],
];
appendRows($service, $sheetId, SHEET_USERS, $sampleUsers);
echo "✓ Users created\n";

// ── 5. Sample Tasks ───────────────────────────────────────────
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$nextWeek  = date('Y-m-d', strtotime('+7 days'));
$lastWeek  = date('Y-m-d', strtotime('-7 days'));

$sampleTasks = [
    ['TSK_001', 'EMP_003', 'Develop TBI Website Homepage',         'Design and code the homepage for TBI-MCE website with responsive layout',         'High',   date('Y-m-d',strtotime('-5 days')), $nextWeek,  'In Progress', 0,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_002', 'EMP_003', 'Fix Login Page Bug',                   'Debug and resolve authentication issue on login page reported by users',           'High',   date('Y-m-d',strtotime('-2 days')), $today,     'Completed',   0,             'Dr. Mohana Lakshmi J', '', 'Fixed JWT token expiry issue'],
    ['TSK_003', 'EMP_003', 'Setup Cloud Hosting',                  'Configure AWS EC2 instance and deploy the web application',                        'Medium', date('Y-m-d',strtotime('-10 days')),$lastWeek,  'Approved',    0,             'Dr. Geetha Kiran A', '', 'Deployed successfully'],
    ['TSK_004', 'EMP_004', 'Prepare Monthly Budget Report',        'Compile and submit financial report for the month of May 2024',                    'High',   date('Y-m-d',strtotime('-3 days')), $today,     'Pending',     0,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_005', 'EMP_004', 'Process Startup Grant Applications',   'Review and process 5 startup grant applications received this week',               'High',   date('Y-m-d',strtotime('-5 days')), $yesterday, 'Pending',     1,             'Dr. Mohana Lakshmi J', '', ''],
    ['TSK_006', 'EMP_004', 'Vendor Payment Processing',            'Process outstanding vendor payments and update accounts',                          'Medium', date('Y-m-d',strtotime('-7 days')), $lastWeek,  'Approved',    0,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_007', 'EMP_005', 'Organize Innovation Workshop',         'Plan and coordinate the upcoming innovation workshop for TBI startups',            'High',   date('Y-m-d',strtotime('-4 days')), $nextWeek,  'In Progress', 0,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_008', 'EMP_005', 'Startup Mentoring Sessions',           'Conduct weekly mentoring sessions for 3 portfolio startups',                      'Medium', date('Y-m-d',strtotime('-6 days')), $today,     'Completed',   0,             'Dr. Mohana Lakshmi J', '', 'Sessions completed, reports uploaded'],
    ['TSK_009', 'EMP_005', 'Research Report on AI Startups',       'Prepare comprehensive report on AI startup ecosystem in Karnataka',               'Low',    date('Y-m-d',strtotime('-14 days')),$lastWeek,  'Rejected',    7,             'Dr. Geetha Kiran A', '', 'Report needs more data. Please add case studies.'],
    ['TSK_010', 'EMP_006', 'Office Maintenance Schedule',          'Coordinate maintenance schedule for office equipment and facilities',              'Low',    date('Y-m-d',strtotime('-3 days')), $nextWeek,  'Pending',     0,             'Dr. Mohana Lakshmi J', '', ''],
    ['TSK_011', 'EMP_006', 'Visitor Management Setup',             'Set up and configure visitor management system for TBI front desk',               'Medium', date('Y-m-d',strtotime('-5 days')), $yesterday, 'Pending',     1,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_012', 'EMP_001', 'Annual Report Preparation',            'Coordinate and oversee preparation of TBI annual report for 2023-24',             'High',   date('Y-m-d',strtotime('-10 days')),$nextWeek,  'In Progress', 0,             'Dr. Mohana Lakshmi J', '', ''],
    ['TSK_013', 'EMP_002', 'Team Performance Reviews',             'Conduct quarterly performance reviews for all TBI staff',                        'High',   date('Y-m-d',strtotime('-3 days')), $nextWeek,  'Pending',     0,             'Dr. Geetha Kiran A', '', ''],
    ['TSK_014', 'EMP_002', 'Policy Documentation Update',          'Update HR policies and documentation for compliance requirements',                'Medium', date('Y-m-d',strtotime('-7 days')), $lastWeek,  'Approved',    0,             'Dr. Geetha Kiran A', '', ''],
];
appendRows($service, $sheetId, SHEET_TASKS, $sampleTasks);
echo "✓ " . count($sampleTasks) . " sample tasks added\n";

// ── 6. Sample Approvals ───────────────────────────────────────
$sampleApprovals = [
    ['APR_001', 'TSK_002', 'EMP_003', 'Pending',  '',                   '',                                            '',                      date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['APR_002', 'TSK_003', 'EMP_003', 'Approved', 'Dr. Geetha Kiran A',   'Excellent work! Deployment is clean.',         date('Y-m-d H:i:s', strtotime('-8 days')),  date('Y-m-d H:i:s', strtotime('-10 days'))],
    ['APR_003', 'TSK_006', 'EMP_004', 'Approved', 'Dr. Geetha Kiran A',   'Payments processed correctly.',               date('Y-m-d H:i:s', strtotime('-5 days')),  date('Y-m-d H:i:s', strtotime('-7 days'))],
    ['APR_004', 'TSK_008', 'EMP_005', 'Pending',  '',                   '',                                            '',                      date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['APR_005', 'TSK_009', 'EMP_005', 'Rejected', 'Dr. Mohana Lakshmi J',   'Report needs more data. Please add case studies.', date('Y-m-d H:i:s', strtotime('-12 days')), date('Y-m-d H:i:s', strtotime('-13 days'))],
    ['APR_006', 'TSK_014', 'EMP_002', 'Approved', 'Dr. Geetha Kiran A',   'Documentation looks comprehensive.',          date('Y-m-d H:i:s', strtotime('-5 days')),  date('Y-m-d H:i:s', strtotime('-7 days'))],
];
appendRows($service, $sheetId, SHEET_APPROVALS, $sampleApprovals);
echo "✓ " . count($sampleApprovals) . " sample approvals added\n";

echo "\n=== Setup Complete! ===\n";
echo "\nLogin Credentials:\n";
echo "  Admin (CEO): username=admin      password=Admin@123\n";
echo "  Admin (CEO): username=geetha    password=Admin@123\n";
echo "  Admin (COO): username=mohana    password=Admin@123\n";
echo "  Employee:    username=darshan   password=Employee@123\n";
echo "  Employee:    username=ramya     password=Employee@123\n";
echo "  Employee:    username=madhurya  password=Employee@123\n";
echo "  Employee:    username=deeksha   password=Employee@123\n";
echo "\n! IMPORTANT: Delete or secure this setup script after running!\n";
echo "</pre>\n";
