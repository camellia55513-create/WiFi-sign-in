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

$action = $_REQUEST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['code' => 400, 'msg' => '缺少 action 参数']);
    exit;
}

$public_actions = ['login'];
if (!in_array($action, $public_actions)) {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['code' => 401, 'msg' => '请先登录']);
        exit;
    }
    $payload = JWT::verifyJWT($token);
    if (!$payload || ($payload['role'] ?? '') !== 'admin') {
        echo json_encode(['code' => 401, 'msg' => '无权限']);
        exit;
    }
    $GLOBALS['current_admin'] = $payload;
}

switch ($action) {
    case 'login':
        adminLogin($mysqli);
        break;
    // 学生
    case 'list_students':
        listStudents($mysqli);
        break;
    case 'import_students':
        importStudents($mysqli);
        break;
    case 'delete_student':
        deleteStudent($mysqli);
        break;
    // 教师
    case 'list_teachers':
        listTeachers($mysqli);
        break;
    case 'import_teachers':
        importTeachers($mysqli);
        break;
    case 'delete_teacher':
        deleteTeacher($mysqli);
        break;
    // 课程
    case 'list_courses':
        listCourses($mysqli);
        break;
    case 'create_course':
        createCourse($mysqli);
        break;
    case 'update_course':
        updateCourse($mysqli);
        break;
    case 'delete_course':
        deleteCourse($mysqli);
        break;
    case 'bind_student_course':
        bindStudentCourse($mysqli);
        break;
     case 'list_students_for_select':
        listStudentsForSelect($mysqli);
        break;
    case 'list_courses_for_select':
        listCoursesForSelect($mysqli);
        break;
    case 'add_student_to_course':
        addStudentToCourse($mysqli);
        break;
    case 'remove_student_from_course':
        removeStudentFromCourse($mysqli);
        break;
        case 'batch_add_students_to_course':
    batchAddStudentsToCourse($mysqli);
    break;
    case 'get_course_students':
        getCourseStudents($mysqli);
        break;
    default:
        echo json_encode(['code' => 400, 'msg' => '未知 action']);
}

function adminLogin($mysqli) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $mysqli->prepare("SELECT id, username FROM admin WHERE username = ? AND password = ?");
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $payload = ['uid' => $row['id'], 'username' => $row['username'], 'role' => 'admin', 'exp' => time() + 7*86400];
        $token = JWT::generateJWT($payload);
        echo json_encode(['code' => 200, 'data' => ['token' => $token, 'user' => $row]]);
    } else {
        echo json_encode(['code' => 401, 'msg' => '账号或密码错误']);
    }
}

// ---------- 学生 ----------
function listStudents($mysqli) {
    $result = $mysqli->query("SELECT id, student_id, name FROM student ORDER BY id DESC");
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

function importStudents($mysqli) {
    $students = json_decode($_POST['students'] ?? '[]', true);
    if (!is_array($students) || empty($students)) {
        echo json_encode(['code' => 400, 'msg' => '数据格式错误']);
        return;
    }
    $success = 0;
    $stmt = $mysqli->prepare("INSERT INTO student (student_id, name, password) VALUES (?, ?, '123456') ON DUPLICATE KEY UPDATE name = VALUES(name)");
    foreach ($students as $s) {
        if (empty($s['student_id']) || empty($s['name'])) continue;
        $stmt->bind_param('ss', $s['student_id'], $s['name']);
        if ($stmt->execute()) $success++;
    }
    echo json_encode(['code' => 200, 'msg' => "成功导入 {$success} 名学生"]);
}

function deleteStudent($mysqli) {
    $id = $_POST['id'] ?? 0;
    $stmt = $mysqli->prepare("DELETE FROM student WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) echo json_encode(['code' => 200, 'msg' => '删除成功']);
    else echo json_encode(['code' => 500, 'msg' => '删除失败']);
}

// ---------- 教师 ----------
function listTeachers($mysqli) {
    $result = $mysqli->query("SELECT id, teacher_id, name FROM teacher ORDER BY id DESC");
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

function importTeachers($mysqli) {
    $teachers = json_decode($_POST['teachers'] ?? '[]', true);
    if (!is_array($teachers) || empty($teachers)) {
        echo json_encode(['code' => 400, 'msg' => '数据格式错误']);
        return;
    }
    $success = 0;
    $stmt = $mysqli->prepare("INSERT INTO teacher (teacher_id, name, password) VALUES (?, ?, '123456') ON DUPLICATE KEY UPDATE name = VALUES(name)");
    foreach ($teachers as $t) {
        if (empty($t['teacher_id']) || empty($t['name'])) continue;
        $stmt->bind_param('ss', $t['teacher_id'], $t['name']);
        if ($stmt->execute()) $success++;
    }
    echo json_encode(['code' => 200, 'msg' => "成功导入 {$success} 名教师"]);
}

function batchAddStudentsToCourse($mysqli) {
    $course_id = $_POST['course_id'] ?? 0;
    $student_ids_json = $_POST['student_ids'] ?? '[]';
    $student_ids = json_decode($student_ids_json, true);
    
    if (!$course_id || !is_array($student_ids) || empty($student_ids)) {
        echo json_encode(['code' => 400, 'msg' => '参数错误']);
        return;
    }
    
    $success = 0;
    $failed = 0;
    
    // 先根据学号查询学生ID
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $sql = "SELECT id, student_id FROM student WHERE student_id IN ($placeholders)";
    $stmt = $mysqli->prepare($sql);
    $types = str_repeat('s', count($student_ids));
    $stmt->bind_param($types, ...$student_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $student_id_map = [];
    while ($row = $result->fetch_assoc()) {
        $student_id_map[$row['student_id']] = $row['id'];
    }
    
    $insert_stmt = $mysqli->prepare("INSERT INTO student_course (student_id, course_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()");
    
    foreach ($student_ids as $sid) {
        if (isset($student_id_map[$sid])) {
            $insert_stmt->bind_param('ii', $student_id_map[$sid], $course_id);
            if ($insert_stmt->execute()) {
                $success++;
            } else {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    
    echo json_encode(['code' => 200, 'msg' => "成功添加 {$success} 人，失败 {$failed} 人"]);
}


function deleteTeacher($mysqli) {
    $id = $_POST['id'] ?? 0;
    $mysqli->begin_transaction();
    try {
        // 删除教师时，将关联课程设为无教师（或删除课程，根据需求调整）
        $stmt = $mysqli->prepare("UPDATE courses SET teacher_id = NULL WHERE teacher_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt = $mysqli->prepare("DELETE FROM teacher WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $mysqli->commit();
        echo json_encode(['code' => 200, 'msg' => '删除成功']);
    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['code' => 500, 'msg' => '删除失败']);
    }
}

// ---------- 课程 ----------
function listCourses($mysqli) {
    $sql = "SELECT c.id, c.course_name, c.teacher_id, c.location, t.name as teacher_name 
            FROM courses c LEFT JOIN teacher t ON c.teacher_id = t.id ORDER BY c.id DESC";
    $result = $mysqli->query($sql);
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

function createCourse($mysqli) {
    $course_name = $_POST['course_name'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? 0;
    $location = $_POST['location'] ?? '';    // 新增
    if (!$course_name || !$teacher_id) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整']);
        return;
    }
    $stmt = $mysqli->prepare("INSERT INTO courses (course_name, teacher_id, location) VALUES (?, ?, ?)");
    $stmt->bind_param('sis', $course_name, $teacher_id, $location);
    if ($stmt->execute()) echo json_encode(['code' => 200, 'msg' => '创建成功']);
    else echo json_encode(['code' => 500, 'msg' => '创建失败']);
}

function updateCourse($mysqli) {
    $id = $_POST['id'] ?? 0;
    $course_name = $_POST['course_name'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? 0;
    $location = $_POST['location'] ?? '';
    $stmt = $mysqli->prepare("UPDATE courses SET course_name = ?, teacher_id = ?, location = ? WHERE id = ?");
    $stmt->bind_param('sisi', $course_name, $teacher_id, $location, $id);
    if ($stmt->execute()) echo json_encode(['code' => 200, 'msg' => '更新成功']);
    else echo json_encode(['code' => 500, 'msg' => '更新失败']);
}

function deleteCourse($mysqli) {
    $id = $_POST['id'] ?? 0;
    $stmt = $mysqli->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) echo json_encode(['code' => 200, 'msg' => '删除成功']);
    else echo json_encode(['code' => 500, 'msg' => '删除失败']);
}

// 学生下拉选项
function listStudentsForSelect($mysqli) {
    $result = $mysqli->query("SELECT id, student_id, name FROM student ORDER BY student_id");
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

// 课程下拉选项
function listCoursesForSelect($mysqli) {
    $result = $mysqli->query("SELECT id, course_name FROM courses ORDER BY course_name");
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

// 添加学生到课程
function addStudentToCourse($mysqli) {
    $student_id = $_POST['student_id'] ?? 0;
    $course_id = $_POST['course_id'] ?? 0;
    if (!$student_id || !$course_id) {
        echo json_encode(['code' => 400, 'msg' => '参数不完整']);
        return;
    }
    $stmt = $mysqli->prepare("INSERT INTO student_course (student_id, course_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()");
    $stmt->bind_param('ii', $student_id, $course_id);
    if ($stmt->execute()) {
        echo json_encode(['code' => 200, 'msg' => '添加成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '添加失败']);
    }
}

// 从课程移除学生
function removeStudentFromCourse($mysqli) {
    $student_id = $_POST['student_id'] ?? 0;
    $course_id = $_POST['course_id'] ?? 0;
    $stmt = $mysqli->prepare("DELETE FROM student_course WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param('ii', $student_id, $course_id);
    if ($stmt->execute()) {
        echo json_encode(['code' => 200, 'msg' => '移除成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '移除失败']);
    }
}

// 获取课程下的学生列表
function getCourseStudents($mysqli) {
    $course_id = $_POST['course_id'] ?? 0;
    $sql = "SELECT s.id, s.student_id, s.name FROM student s
            JOIN student_course sc ON s.id = sc.student_id
            WHERE sc.course_id = ? ORDER BY s.student_id";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(['code' => 200, 'data' => $list]);
}

function bindStudentCourse($mysqli) {
    $student_id = $_POST['student_id'] ?? 0;
    $course_id = $_POST['course_id'] ?? 0;
    $stmt = $mysqli->prepare("INSERT INTO student_course (student_id, course_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()");
    $stmt->bind_param('ii', $student_id, $course_id);
    if ($stmt->execute()) echo json_encode(['code' => 200, 'msg' => '绑定成功']);
    else echo json_encode(['code' => 500, 'msg' => '绑定失败']);
}
?>