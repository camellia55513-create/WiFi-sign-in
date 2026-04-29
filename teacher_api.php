<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

require_once 'config.php';
require_once 'jwt.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    echo json_encode(['code' => 500, 'msg' => '数据库连接失败']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// 兼容 JSON 请求体
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_REQUEST = array_merge($_REQUEST, $input);
}

$action = $_REQUEST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['code' => 400, 'msg' => '缺少 action 参数']);
    exit;
}

$public_actions = ['login', 'verify_teacher', 'reset_teacher_password'];
if (!in_array($action, $public_actions)) {
    $token = $_REQUEST['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['code' => 401, 'msg' => '请先登录']);
        exit;
    }
    $payload = JWT::verifyJWT($token);
    if (!$payload) {
        echo json_encode(['code' => 401, 'msg' => '登录已过期']);
        exit;
    }
    $GLOBALS['current_teacher'] = $payload;
}

switch ($action) {
    case 'login':
        teacherLogin($mysqli);
        break;
    case 'verify_teacher':
        verifyTeacher($mysqli);
        break;
    case 'reset_teacher_password':
        resetTeacherPassword($mysqli);
        break;
    case 'get_teacher_info':
        getTeacherInfo($mysqli);
        break;
    case 'change_password':
        teacherChangePassword($mysqli);
        break;
    case 'get_my_courses':
        getMyCourses($mysqli);
        break;
    case 'create_activity':
        createActivity($mysqli);
        break;
    case 'get_my_activities':
        getMyActivities($mysqli);
        break;
    case 'end_activity':
        endActivity($mysqli);
        break;
    case 'get_activity_attendance':
        getActivityAttendance($mysqli);
        break;
    case 'manual_checkin':
        manualCheckin($mysqli);
        break;
    default:
        echo json_encode(['code' => 400, 'msg' => '未知 action']);
}

// ---------- 函数实现 ----------

function teacherLogin($mysqli) {
    $teacher_id = $_REQUEST['teacher_id'] ?? '';
    $password   = $_REQUEST['password'] ?? '';
    if (!$teacher_id || !$password) {
        echo json_encode(['code' => 400, 'msg' => '工号和密码不能为空']);
        return;
    }
    $stmt = $mysqli->prepare("SELECT id, teacher_id, name, password FROM teacher WHERE teacher_id = ?");
    $stmt->bind_param('s', $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['password'] !== $password) {
            echo json_encode(['code' => 401, 'msg' => '密码错误']);
            return;
        }
        $payload = ['uid' => $row['id'], 'teacher_id' => $row['teacher_id'], 'name' => $row['name'], 'exp' => time() + 7*86400];
        $token = JWT::generateJWT($payload);
        echo json_encode(['code' => 200, 'data' => ['token' => $token, 'user' => $row]]);
    } else {
        echo json_encode(['code' => 401, 'msg' => '工号不存在']);
    }
}

function verifyTeacher($mysqli) {
    $teacher_id = $_REQUEST['teacher_id'] ?? '';
    $name = $_REQUEST['name'] ?? '';
    $stmt = $mysqli->prepare("SELECT id FROM teacher WHERE teacher_id = ? AND name = ?");
    $stmt->bind_param('ss', $teacher_id, $name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['code' => 200, 'msg' => '验证成功']);
    } else {
        echo json_encode(['code' => 401, 'msg' => '工号与姓名不匹配']);
    }
}

function resetTeacherPassword($mysqli) {
    $teacher_id = $_REQUEST['teacher_id'] ?? '';
    $new_password = $_REQUEST['new_password'] ?? '';
    if (strlen($new_password) < 6) {
        echo json_encode(['code' => 400, 'msg' => '密码至少6位']);
        return;
    }
    $stmt = $mysqli->prepare("UPDATE teacher SET password = ? WHERE teacher_id = ?");
    $stmt->bind_param('ss', $new_password, $teacher_id);
    $stmt->execute();
    echo json_encode(['code' => 200, 'msg' => '密码重置成功']);
}

function getTeacherInfo($mysqli) {
    $tid = $GLOBALS['current_teacher']['uid'];
    $stmt = $mysqli->prepare("SELECT id, teacher_id, name FROM teacher WHERE id = ?");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode(['code' => 200, 'data' => $row]);
}

function teacherChangePassword($mysqli) {
    $tid = $GLOBALS['current_teacher']['uid'];
    $new = $_REQUEST['new_password'] ?? '';
    $stmt = $mysqli->prepare("UPDATE teacher SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $new, $tid);
    $stmt->execute();
    echo json_encode(['code' => 200, 'msg' => '修改成功']);
}

function getMyCourses($mysqli) {
    $tid = $GLOBALS['current_teacher']['uid'];
    $stmt = $mysqli->prepare("SELECT id, course_name, location FROM courses WHERE teacher_id = ?");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) $courses[] = $row;
    echo json_encode(['code' => 200, 'data' => $courses]);
}

function createActivity($mysqli) {
    $t = $GLOBALS['current_teacher'];
    $course_id      = intval($_REQUEST['course_id'] ?? 0);
    $wifi_ssid      = $_REQUEST['wifi_ssid'] ?? '';
    $bssid          = $_REQUEST['bssid'] ?? '';
    $wifi_password  = $_REQUEST['wifi_password'] ?? '';
    $start_time     = $_REQUEST['start_time'] ?? '';
    $end_time       = $_REQUEST['end_time'] ?? '';
    $latitude       = $_REQUEST['latitude'] ?? null;
    $longitude      = $_REQUEST['longitude'] ?? null;
    $location_radius = intval($_REQUEST['location_radius'] ?? 50);
    $env_fingerprint = $_REQUEST['env_fingerprint'] ?? '';

    if ($course_id <= 0 || !$wifi_ssid || !$bssid || !$start_time || !$end_time) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整：课程、WiFi SSID/BSSID、时间不能为空']);
        return;
    }

    $stmt = $mysqli->prepare("SELECT course_name, location FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param('ii', $course_id, $t['uid']);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    if (!$course) {
        echo json_encode(['code' => 400, 'msg' => '课程不存在或不属于您']);
        return;
    }

    $location = $course['location'] ?? '';

    $stmt = $mysqli->prepare("INSERT INTO checkin_activity 
        (course_id, course_name, teacher_name, wifi_ssid, wifi_password, bssid, 
         start_time, end_time, location, location_lat, location_lng, location_radius, 
         env_fingerprint, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('issssssssddds', 
        $course_id, $course['course_name'], $t['name'], $wifi_ssid, $wifi_password, $bssid,
        $start_time, $end_time, $location, $latitude, $longitude, $location_radius,
        $env_fingerprint);
    if ($stmt->execute()) {
        echo json_encode(['code' => 200, 'msg' => '活动创建成功', 'data' => ['activity_id' => $stmt->insert_id]]);
    } else {
        echo json_encode(['code' => 500, 'msg' => '创建失败']);
    }
}

function getMyActivities($mysqli) {
    $teacher = $GLOBALS['current_teacher'];
    $course_id = intval($_REQUEST['course_id'] ?? 0);
    $sql = "SELECT * FROM checkin_activity WHERE teacher_name = ?";
    $params = [$teacher['name']];
    $types = 's';
    if ($course_id > 0) {
        $sql .= " AND course_id = ?";
        $params[] = $course_id;
        $types .= 'i';
    }
    $sql .= " ORDER BY start_time DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        // 统计正常签到的学生数
        $stmt2 = $mysqli->prepare("SELECT COUNT(*) as total FROM checkin_record WHERE activity_id = ? AND attendance_status = 1");
        $stmt2->bind_param('i', $row['id']);
        $stmt2->execute();
        $cnt = $stmt2->get_result()->fetch_assoc()['total'];
        $row['checkin_count'] = $cnt;
        $activities[] = $row;
    }
    echo json_encode(['code' => 200, 'data' => $activities]);
}

function endActivity($mysqli) {
    $teacher = $GLOBALS['current_teacher'];
    $aid = intval($_REQUEST['activity_id'] ?? 0);
    $stmt = $mysqli->prepare("UPDATE checkin_activity SET end_time = NOW() WHERE id = ? AND teacher_name = ?");
    $stmt->bind_param('is', $aid, $teacher['name']);
    $stmt->execute();
    echo json_encode(['code' => 200, 'msg' => '活动已提前结束']);
}

function autoMarkAbsent($mysqli, $activity_id, $course_id) {
    $sql = "INSERT INTO checkin_record (student_id, activity_id, scans, checkin_time, ip_address, device_info, status, attendance_status)
            SELECT s.id, ?, NULL, NOW(), '', 'system_auto', 0, 0
            FROM student s JOIN student_course sc ON s.id = sc.student_id
            WHERE sc.course_id = ? 
            AND s.id NOT IN (SELECT student_id FROM checkin_record WHERE activity_id = ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('iii', $activity_id, $course_id, $activity_id);
    $stmt->execute();
}

function getActivityAttendance($mysqli) {
    $aid = intval($_REQUEST['activity_id'] ?? 0);
    $teacher = $GLOBALS['current_teacher'];

    // 获取活动基本信息
    $stmt = $mysqli->prepare("SELECT * FROM checkin_activity WHERE id = ? AND teacher_name = ?");
    $stmt->bind_param('is', $aid, $teacher['name']);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    if (!$activity) {
        echo json_encode(['code' => 403, 'msg' => '无权查看']);
        return;
    }

    // 活动结束后自动标记缺勤
    if (strtotime($activity['end_time']) < time()) {
        autoMarkAbsent($mysqli, $aid, $activity['course_id']);
    }

    // 已签到（正常出勤）
    $checked = [];
    $sql = "SELECT r.id, r.student_id, r.checkin_time, r.is_late, s.student_id as stu_num, s.name 
            FROM checkin_record r JOIN student s ON r.student_id = s.id 
            WHERE r.activity_id = ? AND r.attendance_status = 1 ORDER BY r.checkin_time";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $checked[] = $row;

    // 缺勤
    $absent = [];
    $sql = "SELECT r.id, r.student_id, s.student_id as stu_num, s.name 
            FROM checkin_record r JOIN student s ON r.student_id = s.id 
            WHERE r.activity_id = ? AND r.attendance_status = 0";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $absent[] = $row;

    // 未签到（已选课但无任何记录的学生）
    $unchecked = [];
    $sql = "SELECT s.id, s.student_id, s.name FROM student s
            JOIN student_course sc ON s.id = sc.student_id
            WHERE sc.course_id = ? 
            AND s.id NOT IN (SELECT student_id FROM checkin_record WHERE activity_id = ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $activity['course_id'], $aid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $unchecked[] = $row;

    echo json_encode(['code' => 200, 'data' => [
        'activity'   => $activity,   // 活动基本信息
        'checked'    => $checked,
        'unchecked'  => $unchecked,
        'absent'     => $absent
    ]]);
}

function manualCheckin($mysqli) {
    $aid = intval($_REQUEST['activity_id'] ?? 0);
    $sid = intval($_REQUEST['student_id'] ?? 0);
    $stmt = $mysqli->prepare("SELECT id FROM checkin_record WHERE activity_id = ? AND student_id = ?");
    $stmt->bind_param('ii', $aid, $sid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['code' => 400, 'msg' => '该学生已有记录']);
        return;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $mysqli->prepare("INSERT INTO checkin_record (student_id, activity_id, scans, checkin_time, ip_address, device_info, status, attendance_status) 
                              VALUES (?, ?, NULL, NOW(), ?, 'teacher_manual', 1, 1)");
    $stmt->bind_param('iis', $sid, $aid, $ip);
    $stmt->execute();
    // 更新统计
    $stmt2 = $mysqli->prepare("INSERT INTO student_statistics (student_id, total_checkins, last_checkin_time) VALUES (?, 1, NOW()) 
                               ON DUPLICATE KEY UPDATE total_checkins = total_checkins + 1, last_checkin_time = NOW()");
    $stmt2->bind_param('i', $sid);
    $stmt2->execute();
    echo json_encode(['code' => 200, 'msg' => '补签成功']);
}
?>