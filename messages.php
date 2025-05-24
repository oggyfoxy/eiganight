<?php
// messages.php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour voir vos messages.";
    header('Location: login.php?redirect=messages.php');
    exit;
}

$pageTitle = "Mes Messages - eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];
$conversations = [];

// The SQL query needs to be adjusted to better fit a "subject-like" display.
// For 1-on-1, the "subject" can be implicitly "Conversation avec [User]".
// We'll use the last message content as a sort of subject/preview.
$sql = "SELECT
            c.id as conversation_id,
            c.last_message_at,
            other_user.id as other_user_id,
            other_user.username as other_user_username,
            (SELECT m.content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) as last_message_content,
            (SELECT m.sender_id FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) as last_message_sender_id
        FROM conversations c
        JOIN conversation_participants cp_self ON c.id = cp_self.conversation_id AND cp_self.user_id = ?
        JOIN conversation_participants cp_other ON c.id = cp_other.conversation_id AND cp_other.user_id != ?
        JOIN users other_user ON cp_other.user_id = other_user.id
        GROUP BY c.id, c.last_message_at, other_user.id, other_user.username
        ORDER BY c.last_message_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $loggedInUserId, $loggedInUserId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Placeholder for actual unread logic
            $row['is_unread_placeholder'] = ($row['last_message_sender_id'] != $loggedInUserId && !empty($row['last_message_content']));
            $conversations[] = $row;
        }
    } else {
        error_log("Execute failed (MESSAGES_LIST_CONVOS): " . $stmt->error);
        $_SESSION['message_error'] = "Erreur lors du chargement de vos conversations.";
    }
    $stmt->close();
} else {
    error_log("Prepare failed (MESSAGES_LIST_CONVOS): " . $conn->error . " SQL: " . $sql);
    $_SESSION['message_error'] = "Erreur système lors du chargement des messages.";
}

include_once 'includes/header.php';
?>

<main class="container messages-page">
    <h1>Mes Messages</h1>

    <div class="new-message-link">
        <a href="message_start_conversation.php" class="button-primary">Nouveau Message</a>
    </div>

    <?php if (!empty($_SESSION['message_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_success']); unset($_SESSION['message_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error']); unset($_SESSION['message_error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($conversations)): ?>
        <div class="inbox-table-container card">
            <table class="inbox-table">
                <thead>
                    <tr>
                        <th>Sujet / Dernier Message</th>
                        <th class="message-partner-col">Avec</th>
                        <th class="message-date-col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $convo): ?>
                        <tr class="<?php echo ($convo['is_unread_placeholder']) ? 'unread' : ''; ?>">
                            <td class="message-subject">
                                <a href="message_view_conversation.php?id=<?php echo (int)$convo['conversation_id']; ?>">
                                    <?php
                                    $subject_preview = "Conversation avec " . htmlspecialchars($convo['other_user_username']);
                                    if (!empty($convo['last_message_content'])) {
                                        $prefix = ($convo['last_message_sender_id'] == $loggedInUserId) ? "Vous: " : "";
                                        $subject_preview = $prefix . htmlspecialchars(mb_strimwidth($convo['last_message_content'], 0, 80, "..."));
                                    } else {
                                        $subject_preview = "<em>Aucun message encore.</em>";
                                    }
                                    echo $subject_preview;
                                    ?>
                                </a>
                            </td>
                            <td class="message-partner-col">
                                <a href="view_profile.php?id=<?php echo (int)$convo['other_user_id']; ?>">
                                    <?php echo htmlspecialchars($convo['other_user_username']); ?>
                                </a>
                            </td>
                            <td class="message-date-col">
                                <?php echo date('d M, Y H:i', strtotime($convo['last_message_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="card" style="padding: 20px; text-align: center;">Vous n'avez aucune conversation pour le moment. <a href="message_start_conversation.php">Commencez-en une !</a></p>
    <?php endif; ?>

</main>

<?php
include_once 'includes/footer.php';
?>