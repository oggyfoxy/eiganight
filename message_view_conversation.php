<?php
// message_view_conversation.php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour voir cette conversation.";
    header('Location: login.php');
    exit;
}

$pageTitle = "Conversation - eiganights"; // Will be updated with partner's name
$loggedInUserId = (int)$_SESSION['user_id'];
$conversation_id = null;
$conversation_partner = null;
$messages_list = []; // Renamed from $messages to avoid conflict if we have a $message variable later

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $conversation_id = (int)$_GET['id'];
} else {
    $_SESSION['message_error'] = "ID de conversation invalide.";
    header('Location: messages.php');
    exit;
}

// 1. Verify the logged-in user is part of this conversation
$sql_check_participant = "SELECT COUNT(*) as count 
                          FROM conversation_participants 
                          WHERE conversation_id = ? AND user_id = ?";
$stmt_check = $conn->prepare($sql_check_participant);
if ($stmt_check) {
    $stmt_check->bind_param("ii", $conversation_id, $loggedInUserId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $participation = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($participation['count'] == 0) {
        $_SESSION['message_error'] = "Vous n'êtes pas autorisé à voir cette conversation.";
        header('Location: messages.php');
        exit;
    }
} else {
    error_log("Prepare failed (MSG_VIEW_CHECK_PART): " . $conn->error);
    $_SESSION['message_error'] = "Erreur système (V01).";
    header('Location: messages.php');
    exit;
}

// 2. Get the other participant's details for display
$sql_get_partner = "SELECT u.id, u.username 
                    FROM conversation_participants cp
                    JOIN users u ON cp.user_id = u.id
                    WHERE cp.conversation_id = ? AND cp.user_id != ?";
$stmt_partner = $conn->prepare($sql_get_partner);
if ($stmt_partner) {
    $stmt_partner->bind_param("ii", $conversation_id, $loggedInUserId);
    $stmt_partner->execute();
    $result_partner = $stmt_partner->get_result();
    if ($partner_row = $result_partner->fetch_assoc()) {
        $conversation_partner = $partner_row;
        $pageTitle = "Conversation avec " . htmlspecialchars($conversation_partner['username']) . " - eiganights";
    } else {
        // This shouldn't happen in a 1-on-1 chat if the above participant check passed
        // Could happen if a user was deleted but their participation record remained.
        $_SESSION['message_error'] = "Impossible de trouver le partenaire de conversation.";
        error_log("Could not find conversation partner for convo ID: $conversation_id and user ID: $loggedInUserId");
        header('Location: messages.php');
        exit;
    }
    $stmt_partner->close();
} else {
    error_log("Prepare failed (MSG_VIEW_GET_PARTNER): " . $conn->error);
    $_SESSION['message_error'] = "Erreur système (V02).";
    header('Location: messages.php');
    exit;
}


// 3. Handle sending a new message
$send_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content'])) {
    $content = trim($_POST['message_content']);

    if (empty($content)) {
        $send_error = "Le message ne peut pas être vide.";
    } else {
        $sql_insert_msg = "INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_msg);
        if ($stmt_insert) {
            $stmt_insert->bind_param("iis", $conversation_id, $loggedInUserId, $content);
            if ($stmt_insert->execute()) {
                // Update conversation's last_message_at timestamp
                $stmt_update_convo = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
                if ($stmt_update_convo) {
                    $stmt_update_convo->bind_param("i", $conversation_id);
                    $stmt_update_convo->execute();
                    $stmt_update_convo->close();
                }
                // No success message needed, just refresh the page to show the new message
                header("Location: message_view_conversation.php?id=" . $conversation_id . "#latest_message"); // Go to bottom
                exit;
            } else {
                error_log("Execute failed (MSG_VIEW_SEND): " . $stmt_insert->error);
                $send_error = "Erreur lors de l'envoi du message.";
            }
            $stmt_insert->close();
        } else {
            error_log("Prepare failed (MSG_VIEW_SEND): " . $conn->error);
            $send_error = "Erreur système (V03).";
        }
    }
}


// 4. Fetch all messages for this conversation
$sql_messages = "SELECT m.id, m.sender_id, m.content, m.sent_at, u.username as sender_username
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.conversation_id = ?
                 ORDER BY m.sent_at ASC";
$stmt_messages = $conn->prepare($sql_messages);
if ($stmt_messages) {
    $stmt_messages->bind_param("i", $conversation_id);
    if ($stmt_messages->execute()) {
        $result_messages = $stmt_messages->get_result();
        while ($row = $result_messages->fetch_assoc()) {
            $messages_list[] = $row;
        }
    } else {
        error_log("Execute failed (MSG_VIEW_FETCH_MSGS): " . $stmt_messages->error);
        $_SESSION['message_error'] = "Erreur lors du chargement des messages de la conversation.";
        // Don't redirect, allow page to load and show error
    }
    $stmt_messages->close();
} else {
    error_log("Prepare failed (MSG_VIEW_FETCH_MSGS): " . $conn->error);
    $_SESSION['message_error'] = "Erreur système (V04).";
}


include_once 'includes/header.php'; // $pageTitle is set dynamically
?>

<main class="container messages-page view-conversation-page">
    <p class="breadcrumb-link"><a href="messages.php">« Retour à Mes Messages</a></p>
    <h1>Conversation avec <?php echo htmlspecialchars($conversation_partner['username'] ?? 'Utilisateur Inconnu'); ?></h1>

    <?php if (!empty($_SESSION['message_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_success']); unset($_SESSION['message_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error']); unset($_SESSION['message_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($send_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($send_error); ?></div>
    <?php endif; ?>

    <div class="messages-container card">
        <?php if (!empty($messages_list)): ?>
            <?php foreach ($messages_list as $msg): ?>
                <div class="message-item <?php echo ($msg['sender_id'] == $loggedInUserId) ? 'sent' : 'received'; ?>">
                    <div class="message-bubble">
                        <p class="message-content"><?php echo nl2br(htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                    <div class="message-meta">
                        <?php if ($msg['sender_id'] != $loggedInUserId): ?>
                            <span class="sender-name"><?php echo htmlspecialchars($msg['sender_username']); ?></span> -
                        <?php endif; ?>
                        <span class="message-timestamp"><?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="latest_message"></div> <!-- Anchor for scrolling to bottom -->
        <?php else: ?>
            <p>Aucun message dans cette conversation. Envoyez le premier !</p>
        <?php endif; ?>
    </div>

    <form method="POST" action="message_view_conversation.php?id=<?php echo $conversation_id; ?>" class="send-message-form card">
        <div class="form-group">
            <label for="message_content" class="visually-hidden">Votre message</label>
            <textarea name="message_content" id="message_content" rows="3" placeholder="Écrivez votre message..." required></textarea>
        </div>
        <button type="submit" class="button-primary">Envoyer</button>
    </form>

</main>

<?php
include_once 'includes/footer.php';
?>