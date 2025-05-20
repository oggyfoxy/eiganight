<?php
// 数据库连接
$conn = new mysqli("localhost", "root", "root", "cinema-userr");

// 获取并验证数据
$film_id = isset($_POST['film_id']) ? intval($_POST['film_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$segment = isset($_POST['segment']) ? trim($_POST['segment']) : '';
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

if ($film_id > 0 && $user_id > 0 && !empty($comment_text)) {
    $sql = "INSERT INTO comments (user_id, film_id, segment, comment_text, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "<script>alert('Database preprocessing failure'); window.history.back();</script>";
        exit;
    }
    $stmt->bind_param("iiss", $user_id, $film_id, $segment, $comment_text);
    
    if ($stmt->execute()) {
        echo "<script>alert('Comment successfully posted！'); window.history.back();</script>";
    } else {
        echo "<script>alert('Posted comment failed：" . $stmt->error . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Please write the compelte comment'); window.history.back();</script>";
}

$conn->close();
?>
