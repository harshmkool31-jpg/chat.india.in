<?php

session_start();
date_default_timezone_set('Asia/Kolkata');

ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

/* =========================================================
   OPENAI API KEY – PLEASE SET YOUR OWN KEY
========================================================= */
// Get your API key from https://platform.openai.com/api-keys
define('OPENAI_API_KEY', 'your-openai-api-key-here'); // <-- REPLACE THIS

/* =========================================================
   PATHS & HELPERS
========================================================= */

function getDataDir() { return __DIR__ . '/data'; }
function loadJson($path, $default = []) {
    if (!file_exists($path)) return $default;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}
function saveJson($path, array $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function safeNumber($number) { return preg_replace('/[^0-9]/', '', (string)$number); }
function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function initialLetter($name) { $name = trim($name); return $name === '' ? '?' : strtoupper(substr($name, 0, 1)); }
function getProfilePhotoPath(array $users, $number) { return trim((string)($users[$number]['profile_photo'] ?? '')); }
function loadUsers() { return loadJson(getDataDir() . '/users.json', []); }
function buildUserStorageDirectory($number) {
    $folderName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$number);
    $folderName = trim($folderName, '_');
    if ($folderName === '') $folderName = 'user';
    $directory = getDataDir() . '/' . $folderName;
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    return $directory;
}

/* =========================================================
   AUTHENTICATION
========================================================= */

$senderNumber = safeNumber($_SESSION['sender_number'] ?? '');
$senderName = trim($_SESSION['sender_name'] ?? '');
if ($senderNumber === '' || $senderName === '') {
    header('Location: index.php');
    exit;
}

$users = loadUsers();
$senderFolder = buildUserStorageDirectory($senderNumber);
$aiChatFile = $senderFolder . '/ai_chat_records.json';

// Ensure file exists
if (!file_exists($aiChatFile)) {
    saveJson($aiChatFile, ['messages' => []]);
}

/* =========================================================
   OPENAI API CALL FUNCTION
========================================================= */

function callOpenAI($messages) {
    $apiKey = OPENAI_API_KEY;
    // If the key is the placeholder or empty, return null to trigger fallback
    if ($apiKey === 'your-openai-api-key-here' || empty($apiKey)) {
        return null;
    }

    // Check if cURL is available
    if (!function_exists('curl_init')) {
        return null;
    }

    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => 'gpt-3.5-turbo', // or 'gpt-4' if you have access
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 500,
    ];

    $payload = json_encode($data);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // only if needed

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        // Log error for debugging (you can write to a file if needed)
        error_log("OpenAI API error: $error, HTTP code: $httpCode");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['choices'][0]['message']['content'])) {
        error_log("OpenAI API invalid response: " . print_r($decoded, true));
        return null;
    }

    return trim($decoded['choices'][0]['message']['content']);
}

/* =========================================================
   AI RESPONSE GENERATOR (with OpenAI fallback)
========================================================= */

function getAIResponse($userMessage, $history = []) {
    // Prepare conversation history for OpenAI
    $messagesForAPI = [];
    // Add system prompt
    $messagesForAPI[] = ['role' => 'system', 'content' => 'You are a helpful AI assistant.'];

    // Add previous conversation (last 10 messages)
    foreach ($history as $msg) {
        $role = ($msg['sender'] === 'user') ? 'user' : 'assistant';
        $messagesForAPI[] = ['role' => $role, 'content' => $msg['message']];
    }

    // Add the current user message
    $messagesForAPI[] = ['role' => 'user', 'content' => $userMessage];

    // Try OpenAI
    $aiReply = callOpenAI($messagesForAPI);
    if ($aiReply !== null) {
        return $aiReply;
    }

    // Fallback to local responses
    $msg = strtolower(trim($userMessage));
    $responses = [
        '/\b(hi|hello|hey|howdy|greetings)\b/' => [
            "Hello! How can I help you today?",
            "Hey there! Nice to meet you.",
            "Hi! What's on your mind?"
        ],
        '/\bhow are you\b/' => [
            "I'm just a program, but I'm functioning perfectly! How about you?",
            "Doing great! Thanks for asking."
        ],
        '/\bwhat is your name\b/' => [
            "I'm your AI assistant, you can call me ChatBot.",
            "I go by AI Assistant. Nice to chat with you!"
        ],
        '/\bwho are you\b/' => [
            "I'm an artificial intelligence created to chat with you.",
            "I'm your friendly AI chatbot!"
        ],
        '/\b(bye|goodbye|see you)\b/' => [
            "Goodbye! Have a great day!",
            "See you later! Take care.",
            "Bye! Feel free to come back anytime."
        ],
        '/\b(help|what can you do)\b/' => [
            "I can chat with you about almost anything. Ask me a question!",
            "I'm here to talk, answer questions, or just keep you company."
        ],
        '/\b(thanks|thank you|thx)\b/' => [
            "You're welcome!",
            "Happy to help!",
            "My pleasure."
        ],
        '/\b(your name|your creator|who made you)\b/' => [
            "I was created by a developer who loves coding and AI.",
            "My creator is a talented programmer who built me to chat."
        ],
        '/\b(weather|temperature|rain)\b/' => [
            "I don't have access to real-time weather data, but you can check a weather site!",
            "I can't tell you the weather, but I hope it's nice where you are."
        ],
        '/\b(time|date|what day)\b/' => [
            "I don't have a clock, but you can check your device for the current time.",
            "Time flies when you're chatting! Look at your screen for the time."
        ],
        '/\b(how old are you|age)\b/' => [
            "I'm as old as the code that runs me – constantly updated!",
            "Age is just a number for AI. I'm timeless."
        ]
    ];
    foreach ($responses as $pattern => $candidates) {
        if (preg_match($pattern, $msg)) {
            return $candidates[array_rand($candidates)];
        }
    }
    // Fallback responses
    $fallbacks = [
        "That's interesting! Tell me more.",
        "I see. Can you elaborate?",
        "Hmm, I'm not sure how to respond to that, but I'm learning!",
        "Interesting point! What else?",
        "I appreciate your message. Could you clarify?",
        "I'm not programmed to answer that, but I'd love to chat about something else.",
        "That's a good question. I'll think about it!",
        "I'm sorry, I don't have an answer for that yet."
    ];
    return $fallbacks[array_rand($fallbacks)];
}

/* =========================================================
   AJAX: GET MESSAGES
========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'messages') {
    $data = loadJson($aiChatFile, ['messages' => []]);
    $messages = $data['messages'] ?? [];
    $lastId = trim($_GET['last_id'] ?? '');
    if ($lastId === '') {
        // Full load
        $html = renderAIChatMessages($messages, $senderNumber);
        $lastId = !empty($messages) ? end($messages)['id'] ?? '' : '';
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'mode' => 'full', 'html' => $html, 'last_id' => $lastId]);
        exit;
    }
    // Polling for new messages
    $lastIndex = -1;
    foreach ($messages as $index => $msg) {
        if ((string)($msg['id'] ?? '') === (string)$lastId) {
            $lastIndex = $index;
            break;
        }
    }
    if ($lastIndex === -1) {
        $html = renderAIChatMessages($messages, $senderNumber);
        $lastId = !empty($messages) ? end($messages)['id'] ?? '' : '';
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'mode' => 'full', 'html' => $html, 'last_id' => $lastId]);
        exit;
    }
    $newMessages = array_slice($messages, $lastIndex + 1);
    $previousDate = null;
    if ($lastIndex >= 0 && !empty($messages[$lastIndex]['date'])) {
        $previousDate = $messages[$lastIndex]['date'];
    } elseif ($lastIndex >= 0 && !empty($messages[$lastIndex]['time'])) {
        $previousDate = date('Y-m-d', strtotime($messages[$lastIndex]['time']));
    }
    $newHtml = renderAIChatMessages($newMessages, $senderNumber, $previousDate);
    $lastId = !empty($messages) ? end($messages)['id'] ?? '' : '';
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'mode' => 'new', 'html' => $newHtml, 'last_id' => $lastId]);
    exit;
}

/* =========================================================
   RENDER MESSAGES
========================================================= */
function renderAIChatMessages($messages, $senderNumber, $existingLastDate = null) {
    $html = '';
    $lastDate = $existingLastDate ?? '';
    foreach ($messages as $index => $msg) {
        if (!empty($msg['deleted'])) continue;
        $messageId = $msg['id'] ?? ('message_' . $index);
        $messageDate = !empty($msg['date']) ? $msg['date'] : date('Y-m-d');
        if ($messageDate !== $lastDate) {
            $dateText = date('d M Y', strtotime($messageDate));
            $html .= '<div class="date-separator">' . esc($dateText) . '</div>';
            $lastDate = $messageDate;
        }
        $sender = $msg['sender'] ?? 'user';
        $isMine = ($sender === 'user');
        $class = $isMine ? 'mine' : '';
        $html .= '<div class="message-row ' . $class . '" data-message-id="' . esc($messageId) . '" data-date-key="' . esc($messageDate) . '">';
        $html .= '<div class="bubble">';
        $html .= '<button class="message-menu-btn" onclick="toggleMenu(this)" type="button">⋮</button>';
        $html .= '<div class="message-menu">';
        $html .= '<button type="button" onclick="copyMessage(this)">📋 Copy</button>';
        $html .= '<button type="button" class="danger" onclick="deleteMessage(this)">🗑 Delete</button>';
        $html .= '</div>';
        if (!$isMine) {
            $html .= '<div class="sender-name">🤖 AI</div>';
        }
        $text = trim((string)($msg['message'] ?? ''));
        if ($text !== '') {
            $html .= '<div class="message-text">' . nl2br(linkifyText($text)) . '</div>';
        }
        $readState = !empty($msg['read']);
        $readTick = '';
        if ($isMine) {
            $readTick = '<span class="message-ticks ' . ($readState ? 'read' : 'sent') . '" data-read-status="' . ($readState ? 'read' : 'sent') . '" title="' . ($readState ? 'Seen' : 'Sent') . '">' . ($readState ? '✓✓' : '✓') . '</span>';
        }
        $displayTime = $msg['time'] ?? '';
        if (empty($displayTime) && !empty($msg['created_at'])) {
            $displayTime = date('H:i', strtotime($msg['created_at']));
        }
        $html .= '<div class="message-time">' . esc($displayTime) . $readTick . '</div>';
        $html .= '</div></div>';
    }
    return $html;
}

function linkifyText($text) {
    $escaped = esc($text);
    $pattern = '/((https?|ftp):\/\/[^\s<]+)/i';
    return preg_replace($pattern, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $escaped);
}

/* =========================================================
   POST: SEND MESSAGE & GET AI RESPONSE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_ai_message') {
    $userMessage = trim($_POST['message'] ?? '');
    if ($userMessage === '') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Message is empty.']);
        exit;
    }
    // Load existing messages
    $data = loadJson($aiChatFile, ['messages' => []]);
    $messages = $data['messages'] ?? [];
    $now = time();
    $date = date('Y-m-d', $now);
    $time = date('H:i:s', $now);

    // User message
    $userMsg = [
        'id' => uniqid('msg_', true),
        'sender' => 'user',
        'message' => $userMessage,
        'time' => $time,
        'date' => $date,
        'read' => true,
        'created_at' => date('Y-m-d H:i:s', $now)
    ];
    $messages[] = $userMsg;

    // Generate AI response (will use OpenAI if available, fallback to local)
    $history = array_slice($messages, -10); // last 10 messages for context
    $aiReply = getAIResponse($userMessage, $history);

    $aiMsg = [
        'id' => uniqid('msg_', true),
        'sender' => 'ai',
        'message' => $aiReply,
        'time' => date('H:i:s', time()),
        'date' => date('Y-m-d', time()),
        'read' => true,
        'created_at' => date('Y-m-d H:i:s', time())
    ];
    $messages[] = $aiMsg;

    // Save
    $data['messages'] = $messages;
    saveJson($aiChatFile, $data);

    // Return HTML for both messages
    $previousDate = null;
    if (count($messages) >= 2) {
        $prev = $messages[count($messages)-3] ?? null;
        if ($prev && !empty($prev['date'])) $previousDate = $prev['date'];
    }
    $newHtml = renderAIChatMessages([$userMsg, $aiMsg], $senderNumber, $previousDate);

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'html' => $newHtml,
        'last_id' => $aiMsg['id']
    ]);
    exit;
}

/* =========================================================
   POST: DELETE MESSAGE (deletes both user and ai paired)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ai_message') {
    $id = trim($_POST['message_id'] ?? '');
    if ($id === '') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid message.']);
        exit;
    }
    $data = loadJson($aiChatFile, ['messages' => []]);
    $messages = $data['messages'] ?? [];
    $index = -1;
    foreach ($messages as $i => $msg) {
        if ((string)($msg['id'] ?? '') === (string)$id) {
            $index = $i;
            break;
        }
    }
    if ($index === -1) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Message not found.']);
        exit;
    }
    $deletedIds = [$id];
    if ($index > 0 && $messages[$index-1]['sender'] !== $messages[$index]['sender']) {
        $deletedIds[] = $messages[$index-1]['id'];
        unset($messages[$index-1]);
    }
    if ($index < count($messages)-1 && $messages[$index+1]['sender'] !== $messages[$index]['sender']) {
        $deletedIds[] = $messages[$index+1]['id'];
        unset($messages[$index+1]);
    }
    unset($messages[$index]);
    $data['messages'] = array_values($messages);
    saveJson($aiChatFile, $data);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'deleted_ids' => $deletedIds]);
    exit;
}

/* =========================================================
   MARK READ (for user messages)
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    $data = loadJson($aiChatFile, ['messages' => []]);
    $messages = $data['messages'] ?? [];
    $updated = false;
    foreach ($messages as &$msg) {
        if ($msg['sender'] === 'user' && empty($msg['read'])) {
            $msg['read'] = true;
            $updated = true;
        }
    }
    unset($msg);
    if ($updated) {
        $data['messages'] = $messages;
        saveJson($aiChatFile, $data);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

/* =========================================================
   NORMAL PAGE LOAD
========================================================= */
$data = loadJson($aiChatFile, ['messages' => []]);
$messages = $data['messages'] ?? [];
$messagesMarkup = renderAIChatMessages($messages, $senderNumber);
$lastMessageId = !empty($messages) ? end($messages)['id'] ?? '' : '';
$senderPhoto = getProfilePhotoPath($users, $senderNumber);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Chat</title>
<link rel="icon" type="image/png" href="../images/hhh%20picture.png">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; width: 100%; height: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #eef2f7; overflow: hidden; }
.chat-app { width: 100%; height: 100vh; display: flex; flex-direction: column; background: #fff; }
.chat-header { height: 72px; min-height: 72px; position: fixed; width: 100%; display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e6eaf0; box-shadow: 0 2px 12px rgba(0,0,0,.04); z-index: 20; }
.back { width: 42px; height: 42px; border: 0; background: #f3f6fa; border-radius: 50%; font-size: 21px; cursor: pointer; transition: background 0.2s; }
.back:hover { background: #e2e8f0; }
.profile { width: 46px; height: 46px; min-width: 46px; border-radius: 50%; overflow: hidden; background: #dbeafe; display: flex; justify-content: center; align-items: center; font-weight: 700; color: #2563eb; cursor: pointer; position: relative; font-size: 28px; }
.profile img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
.header-info { flex: 1; min-width: 0; cursor: pointer; }
.header-info strong { display: flex; align-items: center; gap: 8px; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status { font-size: 12px; color: #64748b; margin-top: 3px; }
.status.online { color: #16a34a; }
.header-search { position: relative; }
.search-button { width: 40px; height: 40px; border: 0; border-radius: 50%; background: #f1f5f9; cursor: pointer; font-size: 18px; transition: background 0.2s; }
.search-button:hover { background: #e2e8f0; }
.search-box { display: none; position: absolute; right: 0; top: 48px; width: 280px; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,.15); z-index: 100; }
.search-box.show { display: block; }
.search-box input { width: 100%; padding: 10px 12px; border: 1px solid #dbe2ea; border-radius: 9px; outline: none; font-size: 14px; }
.messages { position:relative; flex: 1; overflow-y: auto; padding: 20px clamp(10px,5vw,80px); background: radial-gradient(circle at top, #f8fbff, #edf2f7); }
.date-separator { position: sticky; top: 60px; z-index: 5; width: max-content; margin: 12px auto; padding: 6px 16px; border-radius: 20px; background: rgba(255,255,255,.92); backdrop-filter: blur(4px); border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(15,23,42,.08); color: #64748b; font-size: 11px; font-weight: 600; }
.message-row { display: flex; margin-bottom: 10px; }
.message-row.mine { justify-content: flex-end; }
.bubble { position: relative; max-width: min(75%,520px); padding: 9px 38px 9px 12px; border-radius: 15px; background: #fff; border: 1px solid #e3e8ef; box-shadow: 0 2px 8px rgba(15,23,42,.05); }
.mine .bubble { background: #dbeafe; border-color: #bfdbfe; }
.sender-name { font-size: 12px; font-weight: 600; color: #2563eb; margin-bottom: 3px; }
.message-text { white-space: pre-wrap; word-break: break-word; font-size: 14px; line-height: 1.45; }
.message-text a { color: #2563eb; text-decoration: underline; }
.message-time { font-size: 10px; color: #64748b; margin-top: 5px; text-align: right; display:flex; align-items:center; justify-content:flex-end; gap:5px; }
.message-ticks { font-size: 13px; line-height: 1; letter-spacing: -3px; font-weight: 700; min-width: 16px; display:inline-block; text-align:left; }
.message-ticks.sent { color:#64748b; }
.message-ticks.read { color:#2563eb; }
.last-message-tag { position:sticky; left:90%; bottom:18px; z-index:15; display:none; border:1px solid #dbe2ea; background:rgba(255,255,255,.96); color:#2563eb; border-radius:22px; padding:9px 14px; box-shadow:0 6px 20px rgba(15,23,42,.16); cursor:pointer; font-size:13px; font-weight:700; top: 90%;}
.last-message-tag.show { display:block; }
.last-message-tag:hover { background:#eff6ff; }
.message-menu-btn { position: absolute; right: 5px; top: 5px; width: 26px; height: 26px; border: 0; background: transparent; border-radius: 50%; cursor: pointer; color: #64748b; font-size: 17px; }
.message-menu-btn:hover { background: rgba(0,0,0,.06); }
.message-menu { position: absolute; right: 7px; top: 34px; width: 180px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 12px 30px rgba(15,23,42,.18); display: none; z-index: 50; overflow: hidden; }
.message-menu.show { display: block; }
.message-menu button { width: 100%; padding: 10px 14px; text-align: left; border: 0; background: #fff; cursor: pointer; font-size: 13px; transition: background 0.15s; }
.message-menu button:hover { background: #f1f5f9; }
.message-menu .danger { color: #dc2626; }
.composer { display: flex; align-items: flex-end; gap: 8px; padding: 10px 14px; background: #fff; border-top: 1px solid #e5e7eb; }
.attach-label { display: none; } /* No attachments for AI chat */
#message { flex: 1; field-sizing:content; resize: none; min-height: 42px; max-height: 130px; padding: 11px 14px; border: 1px solid #d8dee8; border-radius: 22px; outline: none; font: inherit; transition: border-color 0.2s; }
#message:focus { border-color: #2563eb; }
.send { width: 44px; height: 44px; border: 0; border-radius: 50%; background: #2563eb; color: #fff; font-size: 18px; cursor: pointer; transition: background 0.2s; }
.send:hover { background: #1d4ed8; }
.send:disabled { opacity: .55; cursor: not-allowed; }
.empty { height: 100%; display: flex; justify-content: center; align-items: center; color: #64748b; text-align: center; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.65); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.modal.show { display: flex; }
.modal-content { position: relative; background: #fff; border-radius: 20px; max-width: 480px; width: 100%; padding: 28px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.modal-close { position: absolute; right: 12px; top: 12px; width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f1f5f9; cursor: pointer; font-size: 20px; transition: background 0.2s; }
.modal-close:hover { background: #e2e8f0; }
.big-profile { width: 200px; height: 200px; max-width: 80vw; max-height: 70vh; margin: 10px auto; border-radius: 50%; overflow: hidden; background: #dbeafe; display: flex; align-items: center; justify-content: center; font-size: 80px; color: #2563eb; border: 4px solid #e2e8f0; }
.big-profile img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
.info-row { padding: 9px 0; border-bottom: 1px solid #e5e7eb; }
.info-label { color: #64748b; font-size: 12px; }
.info-value { font-size: 14px; margin-top: 3px; word-break: break-word; }
.typing-indicator { display: none; align-items: center; gap: 4px; padding: 8px 12px; margin: 4px 0; background: #f1f5f9; border-radius: 18px; width: fit-content; }
.typing-indicator span { width: 8px; height: 8px; background: #94a3b8; border-radius: 50%; animation: typing 1.2s infinite ease-in-out; }
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
@media(max-width:600px) { .chat-header { padding: 8px 9px; } .messages { padding: 12px 8px; } .bubble { max-width: 86%; } .composer { padding: 8px; margin-bottom: 50px;} .search-box { width: 230px; } .modal-content { padding: 18px; } }
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
</head>
<body>
<div class="chat-app">

<header class="chat-header">
    <button class="back" onclick="window.location.href='chat.php'">‹</button>
    <div class="profile" id="ai-profile" onclick="openProfile()">🤖</div>
    <div class="header-info" onclick="openProfile()">
        <strong>AI Assistant</strong>
        <div class="status online">● Online</div>
        <div class="typing-status" id="typingStatus" style="font-size:12px; color:#2563eb; min-height:16px;"></div>
    </div>
    <div class="header-search">
        <button class="search-button" type="button" onclick="toggleSearch()">🔎</button>
        <div class="search-box" id="search-box">
            <input id="search-input" type="text" placeholder="Search messages..." autocomplete="off">
        </div>
    </div>
</header>

<main class="messages" id="messages">
    <button type="button" class="last-message-tag" id="lastMessageTag" onclick="goToLastMessage()"> ↓ </button>
    <div id="messageContainer">
        <?= $messagesMarkup ?>
        <?php if (empty($messages)): ?>
            <div class="empty"><div><strong>Start chatting with AI</strong><br><small>Send a message to begin.</small></div></div>
        <?php endif; ?>
    </div>
    <div class="typing-indicator" id="typingIndicator"><span></span><span></span><span></span></div>
</main>

<form class="composer" id="message-form">
    <textarea id="message" name="message" placeholder="Ask me anything..." rows="1"></textarea>
    <button class="send" id="send-button" type="submit">➤</button>
</form>

</div>

<!-- PROFILE MODAL -->
<div class="modal" id="profile-modal" onclick="closeProfile(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeProfile()" type="button">×</button>
        <div class="big-profile">🤖</div>
        <h2>AI Assistant</h2>
        <div class="info-row"><div class="info-label">Name</div><div class="info-value">AI Chatbot</div></div>
        <div class="info-row"><div class="info-label">Status</div><div class="info-value" id="profile-status">Online</div></div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b;">
            I'm an AI assistant. You can ask me anything or just chat.
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal" id="deleteModal" onclick="closeModal(event,'deleteModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeModal(event,'deleteModal')" type="button">×</button>
        <h3>Delete Message</h3>
        <p style="color:#64748b;">Are you sure you want to delete this message?</p>
        <button class="btn-danger" style="width:100%; padding:10px; border:0; border-radius:9px; background:#ef4444; color:#fff; font-weight:600; cursor:pointer;" onclick="confirmDelete()">Delete</button>
    </div>
</div>

<script>
/* =========================================================
   PHP DATA
========================================================= */
const CURRENT_USER = <?= json_encode($senderNumber) ?>;
let lastMessageId = <?= json_encode($lastMessageId) ?>;
let selectedDeleteId = '';
let isSending = false;
let userNearBottom = true;
let messageRequestRunning = false;

/* =========================================================
   MODALS
========================================================= */
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(event, id) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById(id).classList.remove('show');
}

/* =========================================================
   SEARCH
========================================================= */
function toggleSearch() {
    const box = document.getElementById('search-box');
    box.classList.toggle('show');
    document.getElementById('search-input').focus();
}
document.getElementById('search-input').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    document.querySelectorAll('.message-row').forEach(row => {
        row.style.display = (!query || row.innerText.toLowerCase().includes(query)) ? '' : 'none';
    });
});

/* =========================================================
   MESSAGE MENUS
========================================================= */
function closeAllMenus() {
    document.querySelectorAll('.message-menu.show').forEach(menu => menu.classList.remove('show'));
}
function toggleMenu(button) {
    const menu = button.parentElement.querySelector('.message-menu');
    const open = menu.classList.contains('show');
    closeAllMenus();
    if (!open) menu.classList.add('show');
}
document.addEventListener('click', function(event) {
    if (!event.target.closest('.message-menu') && !event.target.closest('.message-menu-btn')) closeAllMenus();
});

/* =========================================================
   COPY
========================================================= */
function copyMessage(button) {
    const bubble = button.closest('.bubble');
    const text = bubble.querySelector('.message-text');
    if (!text) return;
    const value = text.innerText || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(() => closeAllMenus()).catch(() => fallbackCopy(value));
    } else {
        fallbackCopy(value);
    }
}
function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    try { document.execCommand('copy'); } catch(e) {}
    textarea.remove();
    closeAllMenus();
}

/* =========================================================
   DELETE
========================================================= */
function deleteMessage(button) {
    const row = button.closest('.message-row');
    if (!row) return;
    selectedDeleteId = row.dataset.messageId;
    closeAllMenus();
    openModal('deleteModal');
}
function confirmDelete() {
    if (!selectedDeleteId) return;
    const body = new URLSearchParams();
    body.append('action', 'delete_ai_message');
    body.append('message_id', selectedDeleteId);
    fetch('ai_chat.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            data.deleted_ids.forEach(id => {
                const row = document.querySelector('.message-row[data-message-id="' + CSS.escape(id) + '"]');
                if (row) row.remove();
            });
            selectedDeleteId = '';
            closeModal(null, 'deleteModal');
            updateStickyDateSeparator();
        } else {
            alert(data.error || 'Could not delete message.');
        }
    })
    .catch(() => alert('Unable to delete message.'));
}

/* =========================================================
   SCROLL
========================================================= */
const messagesBox = document.getElementById('messages');
function isAtBottom() {
    if (!messagesBox) return true;
    return (messagesBox.scrollHeight - messagesBox.scrollTop - messagesBox.clientHeight) < 120;
}
messagesBox.addEventListener('scroll', function() {
    userNearBottom = isAtBottom();
    updateLastMessageTag();
    updateStickyDateSeparator();
});
function scrollToBottom(force) {
    if (!messagesBox) return;
    if (force || userNearBottom) {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }
    updateLastMessageTag();
}
function updateLastMessageTag() {
    const tag = document.getElementById('lastMessageTag');
    if (!tag) return;
    const rows = messagesBox.querySelectorAll('.message-row');
    if (!rows.length || isAtBottom()) {
        tag.classList.remove('show');
    } else {
        tag.classList.add('show');
    }
}
function goToLastMessage() {
    const rows = messagesBox.querySelectorAll('.message-row');
    if (!rows.length) return;
    const last = rows[rows.length - 1];
    last.scrollIntoView({ behavior: 'smooth', block: 'end' });
    setTimeout(() => {
        messagesBox.scrollTop = messagesBox.scrollHeight;
        userNearBottom = true;
        updateLastMessageTag();
    }, 350);
}

/* =========================================================
   STICKY DATE SEPARATOR
========================================================= */
function updateStickyDateSeparator() {
    const sticky = document.getElementById('stickyDateSeparator');
    if (!sticky) return;
    const rows = Array.from(messagesBox.querySelectorAll('.message-row')).filter(r => r.style.display !== 'none');
    if (rows.length === 0) {
        sticky.textContent = '';
        sticky.classList.add('hidden');
        return;
    }
    const boxRect = messagesBox.getBoundingClientRect();
    const triggerTop = boxRect.top + 50;
    let activeDate = rows[0].dataset.dateKey || '';
    for (let i = 0; i < rows.length; i++) {
        const rect = rows[i].getBoundingClientRect();
        if (rect.top <= triggerTop) activeDate = rows[i].dataset.dateKey || activeDate;
        else break;
    }
    if (!activeDate) {
        sticky.textContent = '';
        sticky.classList.add('hidden');
        return;
    }
    const label = getDateSeparatorLabel(activeDate);
    sticky.textContent = label;
    sticky.dataset.date = activeDate;
    sticky.classList.remove('hidden');
}
function getDateSeparatorLabel(dateKey) {
    if (!dateKey) return '';
    const parts = dateKey.split('-');
    if (parts.length !== 3) return dateKey;
    const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    if (isNaN(date.getTime())) return dateKey;
    const today = new Date(); today.setHours(0,0,0,0);
    const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
    date.setHours(0,0,0,0);
    if (date.getTime() === today.getTime()) return 'Today';
    if (date.getTime() === yesterday.getTime()) return 'Yesterday';
    const sameYear = date.getFullYear() === today.getFullYear();
    return date.toLocaleDateString(undefined, sameYear ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' });
}

/* =========================================================
   MESSAGE DOM UPDATE
========================================================= */
function appendNewMessages(html) {
    if (!html) return;
    const empty = document.querySelector('.empty');
    if (empty) empty.remove();
    const wasBottom = isAtBottom();
    document.getElementById('messageContainer').insertAdjacentHTML('beforeend', html);
    if (wasBottom) scrollToBottom(true);
    updateStickyDateSeparator();
    updateLastMessageTag();
}
function replaceAllMessages(html) {
    if (!messagesBox) return;
    document.getElementById('messageContainer').innerHTML = html || '<div class="empty"><div><strong>Start chatting with AI</strong><br><small>Send a message to begin.</small></div></div>';
    scrollToBottom(true);
    updateStickyDateSeparator();
    updateLastMessageTag();
}

/* =========================================================
   POLLING MESSAGES (for updates from other devices)
========================================================= */
function refreshMessages() {
    if (messageRequestRunning) return;
    messageRequestRunning = true;
    const url = 'ai_chat.php?ajax=messages&last_id=' + encodeURIComponent(lastMessageId || '') + '&_=' + Date.now();
    fetch(url, { method: 'GET', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.ok) return;
        if (data.mode === 'full') {
            replaceAllMessages(data.html);
        } else if (data.mode === 'new' && data.html) {
            appendNewMessages(data.html);
        }
        if (data.last_id) lastMessageId = data.last_id;
        markRead();
    })
    .catch(err => console.log('Polling error:', err))
    .finally(() => { messageRequestRunning = false; });
}

/* =========================================================
   MARK READ (for user messages)
========================================================= */
function markRead() {
    fetch('ai_chat.php?action=mark_read', { cache: 'no-store' }).catch(() => {});
}

/* =========================================================
   SEND MESSAGE
========================================================= */
document.getElementById('message-form').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isSending) return;
    const input = document.getElementById('message');
    const button = document.getElementById('send-button');
    const value = input.value.trim();
    if (value === '') return;
    isSending = true;
    button.disabled = true;
    button.textContent = '...';
    // Show typing indicator
    document.getElementById('typingIndicator').style.display = 'flex';
    const formData = new FormData();
    formData.append('action', 'send_ai_message');
    formData.append('message', value);
    fetch('ai_chat.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server responded with ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        document.getElementById('typingIndicator').style.display = 'none';
        if (!data || !data.ok) {
            alert(data.error || 'Unable to send message.');
            return;
        }
        appendNewMessages(data.html);
        if (data.last_id) lastMessageId = data.last_id;
        input.value = '';
        scrollToBottom(true);
        input.focus();
    })
    .catch(err => {
        console.error('Send error:', err);
        document.getElementById('typingIndicator').style.display = 'none';
        alert('Unable to send message. Please check the console for errors.');
    })
    .finally(() => {
        isSending = false;
        button.disabled = false;
        button.textContent = '➤';
    });
});

/* =========================================================
   PROFILE
========================================================= */
function openProfile() { openModal('profile-modal'); }
function closeProfile(event) {
    if (event && event.target !== event.currentTarget) return;
    closeModal(null, 'profile-modal');
}

/* =========================================================
   ESC KEY
========================================================= */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal(null, 'profile-modal');
        closeModal(null, 'deleteModal');
    }
});

/* =========================================================
   INITIAL
========================================================= */
refreshMessages();
setInterval(refreshMessages, 3000);
scrollToBottom(true);
updateLastMessageTag();

/* =========================================================
   BEFORE UNLOAD
========================================================= */
window.addEventListener('beforeunload', function() {
    // nothing needed
});
</script>
</body>
</html>