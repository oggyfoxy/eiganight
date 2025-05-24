<?php
// message_start_conversation.php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour démarrer une conversation.";
    header('Location: login.php?redirect=message_start_conversation.php');
    exit;
}

$pageTitle = "Démarrer une Conversation - eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];
$friends = [];

// Fetch user's friends
$sqlFriends = "
    SELECT u.id, u.username
    FROM friendships f
    JOIN users u ON (CASE
                        WHEN f.user_one_id = ? THEN f.user_two_id
                        ELSE f.user_one_id
                    END) = u.id
    WHERE (f.user_one_id = ? OR f.user_two_id = ?)
      AND f.status = 'accepted'
    ORDER BY u.username ASC";

$stmtFriends = $conn->prepare($sqlFriends);
if ($stmtFriends) {
    $stmtFriends->bind_param("iii", $loggedInUserId, $loggedInUserId, $loggedInUserId);
    if ($stmtFriends->execute()) {
        $resultFriends = $stmtFriends->get_result();
        while ($row = $resultFriends->fetch_assoc()) {
            $friends[] = $row;
        }
    } else {
        error_log("Execute failed (MSG_START_FRIENDS_SEL): " . $stmtFriends->error);
        $_SESSION['message_error'] = "Erreur lors du chargement de votre liste d'amis.";
    }
    $stmtFriends->close();
} else {
    error_log("Prepare failed (MSG_START_FRIENDS_SEL): " . $conn->error);
    $_SESSION['message_error'] = "Erreur système lors du chargement de vos amis.";
}


// Handle selection of a friend to start/view conversation
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['with_user_id'])) {
    $friendUserId = filter_input(INPUT_GET, 'with_user_id', FILTER_VALIDATE_INT);

    if ($friendUserId && $friendUserId !== $loggedInUserId) {
        // Check if they are actually friends (security check)
        $areFriends = false;
        foreach ($friends as $friend) {
            if ($friend['id'] == $friendUserId) {
                $areFriends = true;
                break;
            }
        }

        if (!$areFriends) {
            $_SESSION['message_error'] = "Vous ne pouvez démarrer une conversation qu'avec vos amis.";
            header('Location: message_start_conversation.php');
            exit;
        }

        // Find existing conversation or create a new one
        // A conversation is defined by its participants.
        // We need to find a conversation_id where both loggedInUserId and friendUserId are participants.
        $sqlFindConvo = "SELECT cp1.conversation_id
                         FROM conversation_participants cp1
                         JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                         WHERE cp1.user_id = ? AND cp2.user_id = ?
                         LIMIT 1";
        $stmtFind = $conn->prepare($sqlFindConvo);
        if ($stmtFind) {
            $stmtFind->bind_param("ii", $loggedInUserId, $friendUserId);
            $stmtFind->execute();
            $resultFind = $stmtFind->get_result();
            $existing_convo = $resultFind->fetch_assoc();
            $stmtFind->close();

            if ($existing_convo) {
                // Conversation already exists, redirect to it
                header('Location: message_view_conversation.php?id=' . $existing_convo['conversation_id']);
                exit;
            } else {
                // Create new conversation
                $conn->begin_transaction();
                try {
                    // 1. Create conversation entry
                    $stmtCreateConvo = $conn->prepare("INSERT INTO conversations (created_at, last_message_at) VALUES (NOW(), NOW())");
                    if (!$stmtCreateConvo || !$stmtCreateConvo->execute()) {
                        throw new Exception("Failed to create conversation record: " . ($stmtCreateConvo ? $stmtCreateConvo->error : $conn->error));
                    }
                    $newConversationId = $conn->insert_id;
                    $stmtCreateConvo->close();

                    // 2. Add logged-in user as participant
                    $stmtAddSelf = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                    if (!$stmtAddSelf || !$stmtAddSelf->bind_param("ii", $newConversationId, $loggedInUserId) || !$stmtAddSelf->execute()) {
                         throw new Exception("Failed to add self to conversation: " . ($stmtAddSelf ? $stmtAddSelf->error : $conn->error));
                    }
                    $stmtAddSelf->close();

                    // 3. Add friend as participant
                    $stmtAddFriend = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                     if (!$stmtAddFriend || !$stmtAddFriend->bind_param("ii", $newConversationId, $friendUserId) || !$stmtAddFriend->execute()) {
                         throw new Exception("Failed to add friend to conversation: " . ($stmtAddFriend ? $stmtAddFriend->error : $conn->error));
                    }
                    $stmtAddFriend->close();

                    $conn->commit();
                    $_SESSION['message_success'] = "Nouvelle conversation démarrée.";
                    header('Location: message_view_conversation.php?id=' . $newConversationId);
                    exit;

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error creating conversation: " . $e->getMessage());
                    $_SESSION['message_error'] = "Impossible de démarrer la conversation. (MSC01)";
                    header('Location: message_start_conversation.php');
                    exit;
                }
            }
        } else {
            error_log("Prepare failed (MSG_START_FIND_CONVO): " . $conn->error);
            $_SESSION['message_error'] = "Erreur système (MSC02).";
        }
    } else {
        $_SESSION['message_error'] = "ID d'utilisateur invalide pour démarrer la conversation.";
    }
}


include_once 'includes/header.php';
?>

<main class="container messages-page">
    <h1>Démarrer une Nouvelle Conversation</h1>
    <p><a href="messages.php">« Retour à Mes Messages</a></p>

    <?php if (!empty($_SESSION['message_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_success']); unset($_SESSION['message_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error']); unset($_SESSION['message_error']); ?></div>
    <?php endif; ?>

    <section class="friend-list-for-messaging card">
        <h2>Choisissez un ami avec qui discuter :</h2>
        <?php if (!empty($friends)): ?>
            <ul class="friend-list">
                <?php foreach ($friends as $friend): ?>
                    <li>
                        <a href="message_start_conversation.php?with_user_id=<?php echo (int)$friend['id']; ?>">
                            <?php echo htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Vous n'avez aucun ami pour le moment. <a href="users_list.php">Trouvez des utilisateurs et ajoutez-les !</a></p>
        <?php endif; ?>
    </section>
</main>

<?php
include_once 'includes/footer.php';
?>