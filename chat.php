<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();

$page_title = "Chat | CrimeAlert";
$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$error_message = '';

if ($other_user_id === 0 || $other_user_id === $current_user_id) {
    redirect('members.php');
}

// Fetch the other user's information
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$other_user_id]);
    $other_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$other_user) {
        throw new Exception("The user you are trying to chat with does not exist.");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    redirect('members.php');
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8" style="max-width: 800px;">
    <div class="bg-white rounded-xl shadow-md flex flex-col" style="height: 75vh;">
        <div class="p-4 border-b border-gray-200 flex items-center space-x-4">
            <a href="members.php" class="text-gray-500 hover:text-gray-800"><i class="fas fa-arrow-left"></i></a>
            <div class="h-10 w-10 rounded-full bg-indigo-500 text-white flex items-center justify-center font-bold">
                <?= strtoupper(substr($other_user['username'], 0, 1)) ?>
            </div>
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($other_user['username']) ?></h2>
        </div>

        <div id="message-area" class="flex-1 p-6 space-y-4 overflow-y-auto">
            <div class="text-center text-gray-400">Loading messages...</div>
        </div>

        <div class="p-4 border-t border-gray-200">
            <form id="send-message-form" class="flex space-x-3">
                <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off" required
                       class="flex-1 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageArea = document.getElementById('message-area');
    const sendMessageForm = document.getElementById('send-message-form');
    const messageInput = document.getElementById('message-input');
    const otherUserId = <?= $other_user_id ?>;
    const currentUserId = <?= $current_user_id ?>;

    // Function to scroll to the bottom of the message area
    function scrollToBottom() {
        messageArea.scrollTop = messageArea.scrollHeight;
    }

    // Function to fetch and display messages
    async function fetchMessages() {
        try {
            const response = await fetch(`api/chat_handler.php?action=get_messages&user_id=${otherUserId}`);
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const messages = await response.json();
            
            messageArea.innerHTML = ''; // Clear current messages

            if (messages.length === 0) {
                messageArea.innerHTML = '<div class="text-center text-gray-400">No messages yet. Start the conversation!</div>';
            } else {
                messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.classList.add('flex', 'flex-col');
                    
                    const bubble = document.createElement('div');
                    bubble.textContent = msg.message_text;
                    bubble.classList.add('p-3', 'rounded-lg', 'max-w-xs', 'md:max-w-md');

                    if (parseInt(msg.sender_id) === currentUserId) {
                        // My message
                        messageDiv.classList.add('items-end');
                        bubble.classList.add('bg-indigo-500', 'text-white');
                    } else {
                        // Other user's message
                        messageDiv.classList.add('items-start');
                        bubble.classList.add('bg-gray-200', 'text-gray-800');
                    }
                    
                    messageDiv.appendChild(bubble);
                    messageArea.appendChild(messageDiv);
                });
            }
            scrollToBottom();
        } catch (error) {
            console.error('Error fetching messages:', error);
            messageArea.innerHTML = '<div class="text-center text-red-500">Could not load messages.</div>';
        }
    }

    // Function to send a message
    async function sendMessage(e) {
        e.preventDefault();
        const messageText = messageInput.value.trim();
        if (messageText === '') return;

        const formData = new FormData();
        formData.append('receiver_id', otherUserId);
        formData.append('message_text', messageText);

        try {
            const response = await fetch('api/chat_handler.php?action=send_message', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                messageInput.value = ''; // Clear input
                await fetchMessages(); // Refresh messages to show the new one
            } else {
                throw new Error(result.error || 'Failed to send message.');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Could not send your message. Please try again.');
        }
    }

    // Attach event listener to the form
    sendMessageForm.addEventListener('submit', sendMessage);

    // Initial load of messages
    fetchMessages();

    // Poll for new messages every 3 seconds
    setInterval(fetchMessages, 3000);
});
</script>

<?php
require_once 'includes/footer.php';
?>