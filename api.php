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

// ✅ 增加 JSON 请求体解析（兼容两种数据格式）
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_REQUEST = array_merge($_REQUEST, $input);
}

$action = $_REQUEST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['code' => 400, 'msg' => '缺少 action 参数']);
    exit;
}

$public_actions = ['login', 'verify_student', 'reset_password', 'verify_teacher', 'reset_teacher_password'];
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
    $GLOBALS['current_user'] = $payload;
}

switch ($action) {
    case 'login':
        studentLogin($mysqli); break;
    case 'verify_student':
        verifyStudent($mysqli); break;
    case 'reset_password':
        resetPassword($mysqli); break;
    case 'verify_teacher':
        verifyTeacher($mysqli); break;
    case 'reset_teacher_password':
        resetTeacherPassword($mysqli); break;
    case 'change_password':
        changePassword($mysqli); break;
    case 'get_courses':
        getMyCourses($mysqli); break;
    case 'get_activities':
        getActivities($mysqli); break;
    case 'get_activity_detail':
        getActivityDetail($mysqli); break;
    case 'confirm_checkin':
        confirmCheckin($mysqli); break;
    case 'get_checkin_history':
        getCheckinHistory($mysqli); break;
    case 'get_user_stats':
        getUserStats($mysqli); break;
    default:
        echo json_encode(['code' => 400, 'msg' => '未知 action']);
}

// ---------- 工具函数 ----------
function getDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

function callQwenAI($prompt) {
    $apiKey = QWEN_API_KEY;
    $data = [
        'model' => 'qwen-flash',
        'messages' => [
            ['role' => 'system', 'content' => '你是一个WiFi环境分析专家。'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1,
        'max_tokens' => 50
    ];
    $ch = curl_init(QWEN_API_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    }
    return null;
}

function studentLogin($mysqli) {
    $student_id = $_POST['student_id'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!$student_id || !$password) {
        echo json_encode(['code' => 400, 'msg' => '学号和密码不能为空']);
        return;
    }
    $stmt = $mysqli->prepare("SELECT id, student_id, name, password FROM student WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['password'] !== $password) {
            echo json_encode(['code' => 401, 'msg' => '密码错误']);
            return;
        }
        $payload = ['uid' => $row['id'], 'student_id' => $row['student_id'], 'name' => $row['name'], 'exp' => time() + 7*86400];
        $token = JWT::generateJWT($payload);
        echo json_encode(['code' => 200, 'data' => ['token' => $token, 'user' => $row]]);
    } else {
        echo json_encode(['code' => 401, 'msg' => '学号不存在']);
    }
}

function verifyStudent($mysqli) {
    $student_id = $_POST['student_id'] ?? '';
    $name = $_POST['name'] ?? '';
    if (empty($student_id) || empty($name)) {
        echo json_encode(['code' => 400, 'msg' => '学号和姓名不能为空']);
        return;
    }
    $stmt = $mysqli->prepare("SELECT id FROM student WHERE student_id = ? AND name = ?");
    $stmt->bind_param('ss', $student_id, $name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['code' => 200, 'msg' => '验证成功']);
    } else {
        echo json_encode(['code' => 401, 'msg' => '学号与姓名不匹配']);
    }
}

function resetPassword($mysqli) {
    $student_id = $_POST['student_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    if (!$student_id || !$new_password) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整']);
        return;
    }
    if (strlen($new_password) < 6) {
        echo json_encode(['code' => 400, 'msg' => '密码至少6位']);
        return;
    }
    $stmt = $mysqli->prepare("UPDATE student SET password = ? WHERE student_id = ?");
    $stmt->bind_param('ss', $new_password, $student_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['code' => 200, 'msg' => '密码重置成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '重置失败']);
    }
}

function verifyTeacher($mysqli) {
    $teacher_id = $_POST['teacher_id'] ?? '';
    $name = $_POST['name'] ?? '';
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
    $teacher_id = $_POST['teacher_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    if (strlen($new_password) < 6) {
        echo json_encode(['code' => 400, 'msg' => '密码至少6位']);
        return;
    }
    $stmt = $mysqli->prepare("UPDATE teacher SET password = ? WHERE teacher_id = ?");
    $stmt->bind_param('ss', $new_password, $teacher_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['code' => 200, 'msg' => '密码重置成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '重置失败']);
    }
}

function changePassword($mysqli) {
    $user = $GLOBALS['current_user'];
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    if (!$old_password || !$new_password) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整']);
        return;
    }
    $stmt = $mysqli->prepare("SELECT password FROM student WHERE id = ?");
    $stmt->bind_param('i', $user['uid']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['password'] !== $old_password) {
        echo json_encode(['code' => 400, 'msg' => '原密码错误']);
        return;
    }
    $stmt = $mysqli->prepare("UPDATE student SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $new_password, $user['uid']);
    $stmt->execute();
    echo json_encode(['code' => 200, 'msg' => '修改成功']);
}

function getMyCourses($mysqli) {
    $user = $GLOBALS['current_user'];
    $sql = "SELECT c.id, c.course_name, c.location, t.name as teacher_name 
            FROM courses c
            JOIN student_course sc ON c.id = sc.course_id
            JOIN teacher t ON c.teacher_id = t.id
            WHERE sc.student_id = ? ORDER BY c.id DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $user['uid']);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) $courses[] = $row;
    echo json_encode(['code' => 200, 'data' => $courses]);
}

function getActivities($mysqli) {
    $user = $GLOBALS['current_user'];
    $course_id = intval($_REQUEST['course_id'] ?? 0);
    
    $sql = "SELECT a.id, a.course_name, a.teacher_name, a.wifi_ssid, a.wifi_password, 
                   a.start_time, a.end_time, a.location, a.status
            FROM checkin_activity a
            JOIN student_course sc ON a.course_id = sc.course_id
            WHERE sc.student_id = ?";
    $params = [$user['uid']];
    $types = 'i';
    if ($course_id > 0) {
        $sql .= " AND a.course_id = ?";
        $params[] = $course_id;
        $types .= 'i';
    }
    $sql .= " ORDER BY a.start_time DESC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    $now = time();
    
    while ($row = $result->fetch_assoc()) {
        $start = strtotime($row['start_time']);
        $end = strtotime($row['end_time']);
        if ($now < $start) $row['status'] = 'pending';
        elseif ($now <= $end) $row['status'] = 'active';
        else $row['status'] = 'ended';
        
        $stmt2 = $mysqli->prepare("SELECT id FROM checkin_record WHERE activity_id = ? AND student_id = ?");
        $stmt2->bind_param('ii', $row['id'], $user['uid']);
        $stmt2->execute();
        $row['has_checked'] = $stmt2->get_result()->num_rows > 0;
        $row['is_late'] = 0;
        
        $activities[] = $row;
    }
    echo json_encode(['code' => 200, 'data' => $activities]);
}

function getActivityDetail($mysqli) {
    $activity_id = intval($_REQUEST['activity_id'] ?? 0);
    $stmt = $mysqli->prepare("SELECT id, course_name, teacher_name, wifi_ssid, wifi_password, bssid,
                                     start_time, end_time, location, location_lat, location_lng, location_radius
                              FROM checkin_activity WHERE id = ?");
    $stmt->bind_param('i', $activity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['code' => 200, 'data' => $row]);
    } else {
        echo json_encode(['code' => 404, 'msg' => '活动不存在']);
    }
}

function confirmCheckin($mysqli) {
    $user = $GLOBALS['current_user'];
    $activity_id = intval($_POST['activity_id'] ?? 0);
    $scans = $_POST['scans'] ?? '';
    $device_info = $_POST['device_info'] ?? '';
    $latitude  = $_POST['latitude']  ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!$activity_id || empty($scans)) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整']); return;
    }
    $scans = json_decode($scans, true);
    if (!is_array($scans)) {
        echo json_encode(['code' => 400, 'msg' => '扫描数据格式错误']); return;
    }

    $stmt = $mysqli->prepare("SELECT id, course_name, bssid, env_fingerprint, start_time, end_time, 
                              location_lat, location_lng, location_radius FROM checkin_activity WHERE id = ?");
    $stmt->bind_param('i', $activity_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    if (!$activity) {
        echo json_encode(['code' => 404, 'msg' => '活动不存在']); return;
    }

    $stmt = $mysqli->prepare("SELECT id FROM checkin_record WHERE activity_id = ? AND student_id = ?");
    $stmt->bind_param('ii', $activity_id, $user['uid']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['code' => 400, 'msg' => '您已签到过']); return;
    }

    $now = time();
    $start = strtotime($activity['start_time']);
    $end   = strtotime($activity['end_time']);
    if ($now < $start || $now > $end) {
        echo json_encode(['code' => 400, 'msg' => '不在签到时间范围内']); return;
    }
    $is_late = 0;

    $student_bssids = [];
    foreach ($scans as $scan) {
        $bssid = strtoupper(str_replace(':', '', $scan['bssid'] ?? ''));
        if ($bssid) $student_bssids[] = $bssid;
    }
    $student_bssids = array_unique($student_bssids);
    if (empty($student_bssids)) {
        echo json_encode(['code' => 400, 'msg' => '未扫描到任何WiFi']); return;
    }

    $ref_sql = "SELECT DISTINCT bssid_set FROM checkin_wifi_fingerprint WHERE activity_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $mysqli->prepare($ref_sql);
    $stmt->bind_param('i', $activity_id);
    $stmt->execute();
    $ref_res = $stmt->get_result();
    $peer_bssids = [];
    while ($row = $ref_res->fetch_assoc()) {
        $set = json_decode($row['bssid_set'], true);
        if (is_array($set)) $peer_bssids = array_merge($peer_bssids, $set);
    }
    $peer_bssids = array_unique($peer_bssids);

    $teacher_fp = json_decode($activity['env_fingerprint'], true);
    $teacher_bssids = [];
    if (is_array($teacher_fp)) {
        foreach ($teacher_fp as $w) {
            $b = strtoupper(str_replace(':', '', $w['BSSID'] ?? ''));
            if ($b) $teacher_bssids[] = $b;
        }
        $teacher_bssids = array_unique($teacher_bssids);
    }

    $target_bssid = strtoupper(str_replace(':', '', $activity['bssid']));
    $reference_list = !empty($peer_bssids) ? $peer_bssids : $teacher_bssids;
    $final_decision = false;

    if (empty($reference_list)) {
        $final_decision = in_array($target_bssid, $student_bssids);
    } else {
        $prompt = "请判断学生扫描的WiFi BSSID列表是否与参考列表属于同一教室环境。\n参考列表：" . implode(', ', $reference_list) . "\n学生列表：" . implode(', ', $student_bssids) . "\n请仅回复'是'或'否'。";
        $ai_resp = callQwenAI($prompt);
        $ai_similar = (strpos($ai_resp, '是') !== false);
        $final_decision = $ai_similar || (in_array($target_bssid, $student_bssids) && !empty($reference_list));
    }

    if (!$final_decision) {
        echo json_encode(['code' => 400, 'msg' => 'WiFi环境验证失败，请确认您已到达教室']); return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $scans_json = json_encode($scans);
    $stmt = $mysqli->prepare("INSERT INTO checkin_record (student_id, activity_id, scans, checkin_time, ip_address, device_info, status, is_late) 
                              VALUES (?, ?, ?, NOW(), ?, ?, 1, ?)");
    $stmt->bind_param('iisssi', $user['uid'], $activity_id, $scans_json, $ip, $device_info, $is_late);
    $stmt->execute();
    $record_id = $stmt->insert_id;

    $bssid_json = json_encode(array_values($student_bssids));
    $stmt2 = $mysqli->prepare("INSERT INTO checkin_wifi_fingerprint (activity_id, student_id, record_id, bssid_set) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param('iiis', $activity_id, $user['uid'], $record_id, $bssid_json);
    $stmt2->execute();

    $stmt3 = $mysqli->prepare("INSERT INTO student_statistics (student_id, total_checkins, last_checkin_time) VALUES (?, 1, NOW()) 
                               ON DUPLICATE KEY UPDATE total_checkins = total_checkins + 1, last_checkin_time = NOW()");
    $stmt3->bind_param('i', $user['uid']);
    $stmt3->execute();

    echo json_encode(['code' => 200, 'msg' => '签到成功', 'data' => ['course_name' => $activity['course_name']]]);
}

function getCheckinHistory($mysqli) {
    $user = $GLOBALS['current_user'];
    $page = intval($_REQUEST['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $stmt = $mysqli->prepare("SELECT r.id, r.activity_id, r.checkin_time, a.course_name 
                              FROM checkin_record r LEFT JOIN checkin_activity a ON r.activity_id = a.id 
                              WHERE r.student_id = ? ORDER BY r.checkin_time DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('iii', $user['uid'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) $history[] = $row;
    echo json_encode(['code' => 200, 'data' => $history]);
}

function getUserStats($mysqli) {
    $user = $GLOBALS['current_user'];
    $stmt = $mysqli->prepare("SELECT total_checkins FROM student_statistics WHERE student_id = ?");
    $stmt->bind_param('i', $user['uid']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc() ?: ['total_checkins' => 0];
    echo json_encode(['code' => 200, 'data' => $stats]);
}
?>