<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

$offer_id = isset($_GET['offer_id']) ? (int)$_GET['offer_id'] : 0;

$stmt = $conn->prepare("SELECT o.*,
                        u1.username as sender_username, u1.full_name as sender_name, u1.phone as sender_phone,
                        u2.username as receiver_username, u2.full_name as receiver_name, u2.phone as receiver_phone,
                        i_wanted.title as wanted_title, i_wanted.photo as wanted_photo,
                        i_offered.title as offered_title, i_offered.photo as offered_photo,
                        (SELECT COUNT(*) FROM messages m WHERE m.offer_id = o.id) as message_count
                        FROM offers o
                        JOIN users u1 ON o.sender_id = u1.id
                        JOIN users u2 ON o.receiver_id = u2.id
                        JOIN items i_wanted ON o.item_wanted_id = i_wanted.id
                        JOIN items i_offered ON o.item_offered_id = i_offered.id
                        WHERE o.id = ? AND (o.sender_id = ? OR o.receiver_id = ?)");
$stmt->bind_param("iii", $offer_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$offer = $stmt->get_result()->fetch_assoc();

if (!$offer) {
    setFlash('error', 'Percakapan tidak ditemukan.');
    header('Location: messages.php');
    exit();
}

// Chat hanya untuk penawaran yang sudah diterima (riwayat tetap bisa dibaca)
$can_send = $offer['status'] === 'accepted';
if (!$can_send && !in_array($offer['status'], ['completed', 'cancelled'], true)) {
    setFlash('info', 'Chat tersedia setelah penawaran diterima.');
    header('Location: my-offers.php');
    exit();
}
if ($offer['status'] === 'cancelled' && (int)$offer['message_count'] === 0) {
    setFlash('info', 'Penawaran ini sudah dibatalkan.');
    header('Location: my-offers.php');
    exit();
}

$i_am_sender = $offer['sender_id'] == $_SESSION['user_id'];
$other_user_id = $i_am_sender ? $offer['receiver_id'] : $offer['sender_id'];
$other_user_name = $i_am_sender ? $offer['receiver_name'] : $offer['sender_name'];
$other_phone = $i_am_sender ? $offer['receiver_phone'] : $offer['sender_phone'];
$wa_number = $can_send ? whatsappNumber($other_phone) : null;

// Mark messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE offer_id = ? AND receiver_id = ?");
$stmt->bind_param("ii", $offer_id, $_SESSION['user_id']);
$stmt->execute();

$stmt = $conn->prepare("SELECT m.*, u.username, u.full_name
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.offer_id = ?
                        ORDER BY m.id ASC");
$stmt->bind_param("i", $offer_id);
$stmt->execute();
$messages = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title>Chat dengan <?= e($other_user_name) ?> - SWAP-IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-content">
        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-user-info">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <h3><a href="profile.php?id=<?= (int)$other_user_id ?>"><?= e($other_user_name) ?></a></h3>
                        <p class="chat-deal-info">
                            <i class="fas fa-exchange-alt"></i>
                            <?= e($offer['offered_title']) ?> ⇄ <?= e($offer['wanted_title']) ?>
                        </p>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <?php if ($wa_number): ?>
                        <a href="https://wa.me/<?= e($wa_number) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm" title="Hubungi via WhatsApp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    <?php endif; ?>
                    <a href="messages.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <div class="deal-banner deal-<?= e($offer['status']) ?>">
                <?php if ($offer['status'] === 'accepted'): ?>
                    <span><i class="fas fa-handshake"></i> Barter sedang berjalan. Sudah saling tukar barang?</span>
                    <div class="deal-actions">
                        <form method="POST" action="offer-action.php" class="inline-form"
                              onsubmit="return confirm('Konfirmasi barang sudah saling ditukar?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$offer_id ?>">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="redirect" value="chat">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Barter Selesai</button>
                        </form>
                        <form method="POST" action="offer-action.php" class="inline-form"
                              onsubmit="return confirm('Batalkan barter ini? Kedua barang akan kembali tersedia.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$offer_id ?>">
                            <input type="hidden" name="action" value="cancel_deal">
                            <input type="hidden" name="redirect" value="chat">
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-ban"></i> Batalkan</button>
                        </form>
                    </div>
                <?php elseif ($offer['status'] === 'completed'): ?>
                    <span><i class="fas fa-check-circle"></i> Barter ini sudah selesai. Percakapan hanya bisa dibaca.</span>
                <?php else: ?>
                    <span><i class="fas fa-ban"></i> Barter ini dibatalkan. Percakapan hanya bisa dibaca.</span>
                <?php endif; ?>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received' ?>" data-message-id="<?= (int)$msg['id'] ?>">
                            <div class="message-content">
                                <?php if ($msg['sender_id'] != $_SESSION['user_id']): ?>
                                    <strong><?= e($msg['full_name']) ?></strong>
                                <?php endif; ?>
                                <p><?= nl2br(e($msg['message'])) ?></p>
                                <span class="message-time" title="<?= e(date('d M Y H:i', strtotime($msg['created_at']))) ?>"><?= timeAgo($msg['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="chat-empty">
                        <i class="fas fa-comments"></i>
                        <p>Belum ada pesan. Mulai percakapan dan atur tempat ketemuan!</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($can_send): ?>
                <div class="chat-input-container">
                    <div class="quick-replies">
                        <button type="button" class="quick-reply">Kapan bisa ketemuan?</button>
                        <button type="button" class="quick-reply">Ketemu di kampus saja ya</button>
                        <button type="button" class="quick-reply">Barangnya masih ada?</button>
                        <button type="button" class="quick-reply">Terima kasih! 🙏</button>
                    </div>
                    <form id="chatForm" class="chat-form" data-no-loading>
                        <textarea name="message" id="messageInput"
                                  placeholder="Ketik pesan... (Enter untuk kirim, Shift+Enter baris baru)"
                                  rows="2" maxlength="2000" required></textarea>
                        <button type="submit" class="btn btn-primary" id="sendBtn" aria-label="Kirim">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
    <script>
        const offerId = <?= (int)$offer_id ?>;
        const baseUrl = <?= json_encode(BASE_URL) ?>;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const canSend = <?= $can_send ? 'true' : 'false' ?>;
        const chatMessages = document.getElementById('chatMessages');
        let lastMessageId = 0;
        let isUserScrolling = false;
        let polling = false;

        document.querySelectorAll('.message').forEach(el => {
            lastMessageId = Math.max(lastMessageId, parseInt(el.dataset.messageId || 0, 10));
        });

        function scrollToBottom(force = false) {
            if (force || !isUserScrolling) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        chatMessages.addEventListener('scroll', function () {
            const nearBottom = chatMessages.scrollTop + chatMessages.clientHeight >= chatMessages.scrollHeight - 50;
            isUserScrolling = !nearBottom;
        });

        function createMessageElement(msg) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${msg.is_mine ? 'message-sent' : 'message-received'} message-fade-in`;
            messageDiv.dataset.messageId = msg.id;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';

            if (!msg.is_mine) {
                const nameStrong = document.createElement('strong');
                nameStrong.textContent = msg.full_name;
                contentDiv.appendChild(nameStrong);
            }

            // textContent + CSS white-space: pre-wrap -> aman dari XSS
            const msgP = document.createElement('p');
            msgP.className = 'message-text';
            msgP.textContent = msg.message;
            contentDiv.appendChild(msgP);

            const timeSpan = document.createElement('span');
            timeSpan.className = 'message-time';
            timeSpan.textContent = msg.time_ago;
            contentDiv.appendChild(timeSpan);

            messageDiv.appendChild(contentDiv);
            return messageDiv;
        }

        // Tambah pesan sekali saja (polling & kirim bisa mengembalikan pesan yang sama)
        function appendMessage(msg) {
            if (chatMessages.querySelector(`[data-message-id="${msg.id}"]`)) {
                return false;
            }
            const empty = chatMessages.querySelector('.chat-empty');
            if (empty) empty.remove();
            chatMessages.appendChild(createMessageElement(msg));
            lastMessageId = Math.max(lastMessageId, parseInt(msg.id, 10));
            return true;
        }

        async function fetchNewMessages() {
            if (polling || document.hidden) return;
            polling = true;
            try {
                const response = await fetch(`${baseUrl}api/get_messages.php?offer_id=${offerId}&last_id=${lastMessageId}`);
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    let newFromOther = false;
                    data.messages.forEach(msg => {
                        if (appendMessage(msg) && !msg.is_mine) newFromOther = true;
                    });
                    scrollToBottom();
                    if (newFromOther) playNotificationSound();
                }

                // Status barter berubah (diselesaikan/dibatalkan pihak lain) -> muat ulang
                if (data.success && canSend && data.offer_status && data.offer_status !== 'accepted') {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            } finally {
                polling = false;
            }
        }

        function playNotificationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.1);
            } catch (e) {
                // Audio tidak didukung
            }
        }

        if (canSend) {
            const chatForm = document.getElementById('chatForm');
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendBtn');
            let sending = false;

            async function sendMessage() {
                const message = messageInput.value.trim();
                if (!message || sending) return;

                sending = true;
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const formData = new FormData();
                    formData.append('offer_id', offerId);
                    formData.append('message', message);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch(`${baseUrl}api/send_message.php`, { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        messageInput.value = '';
                        appendMessage(data.message);
                        scrollToBottom(true);
                        messageInput.focus();
                    } else {
                        alert('Gagal mengirim pesan: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    alert('Terjadi kesalahan saat mengirim pesan');
                } finally {
                    sending = false;
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                }
            }

            chatForm.addEventListener('submit', function (e) {
                e.preventDefault();
                sendMessage();
            });

            messageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            document.querySelectorAll('.quick-reply').forEach(btn => {
                btn.addEventListener('click', function () {
                    messageInput.value = this.textContent;
                    messageInput.focus();
                });
            });

            messageInput.focus();
            setInterval(fetchNewMessages, 3000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchNewMessages(); });
        }

        scrollToBottom(true);
    </script>
</body>
</html>
<?php $conn->close(); ?>
