<?php

session_start();
date_default_timezone_set('Asia/Kolkata');

// Increase limits to allow large file uploads (200 MB)
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

/* =========================================================
   PATHS & HELPERS
========================================================= */

function getDataDir()
{
    return __DIR__ . '/data';
}

function loadJson($path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}

function saveJson($path, array $data)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function safeNumber($number)
{
    return preg_replace('/[^0-9]/', '', (string)$number);
}

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function initialLetter($name)
{
    $name = trim($name);
    return $name === '' ? '?' : strtoupper(substr($name, 0, 1));
}

/* =========================================================
   CHAT PREFERENCES
========================================================= */
function getPreferencesPath($number)
{
    $number = safeNumber($number);
    return buildUserStorageDirectory($number) . '/chat_preferences.json';
}

function loadPreferences($number)
{
    $path = getPreferencesPath($number);
    $data = loadJson($path, []);

    if (!isset($data['aliases']) || !is_array($data['aliases'])) {
        $data['aliases'] = [];
    }

    return $data;
}

function savePreferences($number, array $preferences)
{
    saveJson(getPreferencesPath($number), $preferences);
}

/* =========================================================
   PRESENCE
========================================================= */

function getPresencePath()
{
    return getDataDir() . '/presence.json';
}

function loadPresence()
{
    return loadJson(getPresencePath(), []);
}

function savePresence(array $presence)
{
    saveJson(getPresencePath(), $presence);
}

function updatePresence($number, $name, $page = 'private', $conversation = '')
{
    $number = safeNumber($number);
    if ($number === '') return;
    $presence = loadPresence();
    $presence[$number] = [
        'number'       => $number,
        'name'         => $name,
        'page'         => $page,
        'conversation' => $conversation,
        'last_seen'    => time()
    ];
    savePresence($presence);
}

function getPresenceStatus($number, $page = '', $conversation = '')
{
    $number = safeNumber($number);
    if ($number === '') return 'Offline';
    $presence = loadPresence();
    if (!isset($presence[$number])) return 'Offline';
    $item = $presence[$number];
    if (time() - (int)($item['last_seen'] ?? 0) > 10) return 'Offline';
    if ($page !== '' && ($item['page'] ?? '') !== $page) return 'Offline';
    if ($conversation !== '' && ($item['conversation'] ?? '') !== $conversation) return 'Offline';
    return 'Online';
}

/* =========================================================
   TYPING
========================================================= */

function getTypingPath()
{
    return getDataDir() . '/typing.json';
}

function loadTyping()
{
    return loadJson(getTypingPath(), []);
}

function saveTyping(array $typing)
{
    saveJson(getTypingPath(), $typing);
}

function updateTypingState(array &$typing, $conversationKey, $number, $name, $isTyping)
{
    if ($conversationKey === '' || $number === '') return;
    if (!isset($typing[$conversationKey])) $typing[$conversationKey] = [];
    if (!$isTyping) {
        unset($typing[$conversationKey][$number]);
        if (empty($typing[$conversationKey])) unset($typing[$conversationKey]);
        return;
    }
    $typing[$conversationKey][$number] = [
        'number'     => $number,
        'name'       => $name,
        'updated_at' => time()
    ];
}

function getTypingStatus(array $typing, $conversationKey, $currentNumber)
{
    if ($conversationKey === '' || empty($typing[$conversationKey])) return '';
    $names = [];
    $now = time();
    foreach ($typing[$conversationKey] as $number => $entry) {
        if ((string)$number === (string)$currentNumber) continue;
        if ($now - (int)($entry['updated_at'] ?? 0) > 5) continue;
        $name = trim((string)($entry['name'] ?? ''));
        if ($name !== '') $names[] = $name;
    }
    if (empty($names)) return '';
    if (count($names) === 1) return $names[0] . ' is typing...';
    if (count($names) === 2) return $names[0] . ' and ' . $names[1] . ' are typing...';
    return count($names) . ' people are typing...';
}

/* =========================================================
   USERS
========================================================= */

function loadUsers()
{
    return loadJson(getDataDir() . '/users.json', []);
}

function getProfilePhotoPath(array $users, $number)
{
    return trim((string)($users[$number]['profile_photo'] ?? ''));
}

function buildUserStorageDirectory($number)
{
    $folderName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$number);
    $folderName = trim($folderName, '_');
    if ($folderName === '') $folderName = 'user';
    $directory = getDataDir() . '/' . $folderName;
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    return $directory;
}

function getConversationFile($a, $b)
{
    $numbers = [safeNumber($a), safeNumber($b)];
    $numbers = array_values(array_filter(array_unique($numbers)));
    sort($numbers, SORT_STRING);
    return implode('_', $numbers) . '.json';
}

/* =========================================================
   ATTACHMENT HELPERS
========================================================= */

function getUploadErrorMessage($errorCode)
{
    switch ($errorCode) {
        case UPLOAD_ERR_OK: return 'No error.';
        case UPLOAD_ERR_INI_SIZE: return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
        case UPLOAD_ERR_FORM_SIZE: return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
        case UPLOAD_ERR_PARTIAL: return 'The uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE: return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder.';
        case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION: return 'A PHP extension stopped the file upload.';
        default: return 'Unknown upload error.';
    }
}

function getAttachmentCategory($mime, $fileName)
{
    $mime = strtolower((string)$mime);
    $ext = strtolower(pathinfo((string)$fileName, PATHINFO_EXTENSION));
    if (strpos($mime, 'image/') === 0) return 'image';
    if (strpos($mime, 'audio/') === 0) return 'audio';
    if (strpos($mime, 'video/') === 0) return 'video';
    $image = ['jpg','jpeg','png','gif','webp','bmp','svg'];
    $audio = ['mp3','wav','ogg','m4a','aac','flac'];
    $video = ['mp4','mov','avi','mkv','webm','wmv','mpeg','mpg','3gp'];
    if (in_array($ext, $image, true)) return 'image';
    if (in_array($ext, $audio, true)) return 'audio';
    if (in_array($ext, $video, true)) return 'video';
    return 'document';
}

function isAllowedAttachment($mime, $fileName)
{
    $ext = strtolower(pathinfo((string)$fileName, PATHINFO_EXTENSION));
    $allowed = [
        'jpg','jpeg','png','gif','webp','bmp','svg',
        'mp3','wav','ogg','m4a','aac','flac',
        'mp4','mov','avi','mkv','webm','wmv','mpeg','mpg','3gp',
        'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','7z','csv','json','html','css','js','php','py','apk'
    ];
    return in_array($ext, $allowed, true);
}

function formatFileSize($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes >= 1024*1024*1024) return round($bytes/(1024*1024*1024),1).' GB';
    if ($bytes >= 1024*1024) return round($bytes/(1024*1024),1).' MB';
    if ($bytes >= 1024) return round($bytes/1024,1).' KB';
    return $bytes.' B';
}

function saveUploadedFile($attachment, $conversationFile)
{
    if (!is_array($attachment) || empty($attachment['tmp_name'])) {
        return ['ok' => false, 'error' => 'No file selected.'];
    }
    $error = (int)($attachment['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => getUploadErrorMessage($error)];
    }
    $size = (int)($attachment['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Selected file is empty.'];
    }
    if ($size > 200*1024*1024) {
        return ['ok' => false, 'error' => 'Maximum file size is 200 MB.'];
    }
    $name = basename((string)($attachment['name'] ?? 'file'));
    $mime = strtolower((string)($attachment['type'] ?? ''));
    if (!isAllowedAttachment($mime, $name)) {
        return ['ok' => false, 'error' => 'This file type is not allowed.'];
    }
    $category = getAttachmentCategory($mime, $name);
    $folder = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$conversationFile);
    $folder = trim($folder, '_');
    if ($folder === '') $folder = 'private';
    $uploadDir = getDataDir() . '/attachments/' . $folder;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeName = time() . '_' . bin2hex(random_bytes(3)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $destination = $uploadDir . '/' . $safeName;
    if (!move_uploaded_file($attachment['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'Could not save uploaded file.'];
    }
    return [
        'ok' => true,
        'path' => 'data/attachments/' . $folder . '/' . $safeName,
        'type' => $category,
        'name' => $name,
        'size' => filesize($destination),
        'mime' => $mime,
        'stored_name' => $safeName,
        'stored_folder' => $folder
    ];
}

function linkifyText($text)
{
    $escaped = esc($text);
    $pattern = '/((https?|ftp):\/\/[^\s<]+)/i';
    return preg_replace($pattern, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $escaped);
}

function renderAttachment($attachment)
{
    $path = trim((string)($attachment['path'] ?? ''));
    if ($path === '') return '';
    $type = trim((string)($attachment['type'] ?? 'document'));
    $name = trim((string)($attachment['name'] ?? 'file'));
    $size = formatFileSize($attachment['size'] ?? 0);
    $safePath = esc($path);
    $safeName = esc($name);
    $html = '<div class="attachment" data-attachment-url="' . $safePath . '" data-attachment-name="' . $safeName . '">';
    if ($type === 'image') {
        $html .= '<a href="' . $safePath . '" target="_blank" rel="noopener">'
            . '<img src="' . $safePath . '" alt="' . $safeName . '">'
            . '</a>';
    } elseif ($type === 'audio') {
        $html .= '<audio controls preload="metadata" src="' . $safePath . '"></audio>';
    } elseif ($type === 'video') {
        $html .= '<video controls preload="metadata" src="' . $safePath . '"></video>';
    } else {
        $html .= '<div class="document">'
            . '<span class="document-icon">📄</span>'
            . '<a target="_blank" href="' . $safePath . '" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><span class="document-name">' . $safeName . '</span></a>'
            . '</div>';
    }
    $html .= '<div class="attachment-actions">'
        . '<div class="attachment-meta">'
        . '<span class="file-name">' . $safeName . '</span>'
        . '<span class="file-size">' . esc($size) . '</span>'
        . '</div>'
        . '<a class="download-file" href="' . $safePath . '" download="' . $safeName . '">⬇ Download</a>'
        . '</div>';
    $html .= '</div>';
    return $html;
}

/* =========================================================
   MESSAGE RENDER
========================================================= */

function renderPrivateMessagesMarkup(array $messages, $senderNumber, $existingLastDate = null)
{
    $html = '';
    $lastDate = $existingLastDate ?? '';
    foreach ($messages as $index => $msg) {
        if (!empty($msg['deleted'])) continue;
        $messageId = $msg['id'] ?? ('message_' . $index);
        // Use 'date' field if available, else derive from 'time'
        if (isset($msg['date']) && !empty($msg['date'])) {
            $messageDate = $msg['date'];
        } elseif (!empty($msg['time'])) {
            $messageDate = date('Y-m-d', strtotime($msg['time']));
        } else {
            $messageDate = date('Y-m-d');
        }
        if ($messageDate !== $lastDate) {
            $dateText = date('d M Y', strtotime($messageDate));
            $html .= '<div class="date-separator">' . esc($dateText) . '</div>';
            $lastDate = $messageDate;
        }
        
        $messageSender = safeNumber($msg['sender_number'] ?? '');
        $messageSenderName = trim((string)($msg['sender_name'] ?? 'User'));
        $isMine = $messageSender === safeNumber($senderNumber);
        $class = $isMine ? 'mine' : '';

        // Determine attachments array
        $attachments = [];
        if (!empty($msg['attachments']) && is_array($msg['attachments'])) {
            $attachments = $msg['attachments'];
        } elseif (!empty($msg['file_path'])) {
            // Fallback for old message format
            $attachments = [[
                'path' => $msg['file_path'],
                'type' => $msg['type'] ?? 'document',
                'name' => $msg['file_name'] ?? 'file',
                'size' => $msg['file_size'] ?? 0
            ]];
        }

        $html .= '<div class="message-row ' . $class . '" data-message-id="' . esc($messageId) . '" data-date-key="' . esc($messageDate) . '">';
        $html .= '<div class="bubble">';
        $html .= '<button class="message-menu-btn" onclick="toggleMenu(this)" type="button">⋮</button>';
        $html .= '<div class="message-menu">';
        if (trim((string)($msg['message'] ?? '')) !== '') {
            $html .= '<button type="button" onclick="copyMessage(this)">📋 Copy</button>';
        }
        if (!empty($attachments)) {
            $html .= '<button type="button" onclick="downloadAllAttachments(this)">⬇️ Download all</button>';
        }
        $html .= '<button type="button" class="danger" onclick="deleteMessage(this)">🗑 Delete</button>';
        $html .= '</div>';
        if (!$isMine) {
            $html .= '<div class="sender-name">' . esc($messageSenderName) . '</div>';
        }
        $text = trim((string)($msg['message'] ?? ''));
        if ($text !== '') {
            $html .= '<div class="message-text">' . nl2br(linkifyText($text)) . '</div>';
        }
        foreach ($attachments as $attachment) {
            $html .= renderAttachment($attachment);
        }
        $readState = !empty($msg['read']);
        $readTick = '';
        if ($isMine) {
            $readTick = '<span class="message-ticks ' . ($readState ? 'read' : 'sent') . '" data-read-status="' . ($readState ? 'read' : 'sent') . '" title="' . ($readState ? 'Seen' : 'Sent') . '">' . ($readState ? '✓✓' : '✓') . '</span>';
        }
        $displayTime = $msg['time'] ?? '';
        if (empty($displayTime) && !empty($msg['time'])) {
            $displayTime = date('H:i', strtotime($msg['time']));
        }
        $html .= '<div class="message-time">' . esc($displayTime) . $readTick . '</div>';
        $html .= '</div></div>';
    }
    return $html;
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
$chatPreferences = loadPreferences($senderNumber);

$receiverNumber = safeNumber(
    $_GET['receiver_number'] ?? $_POST['receiver_number'] ?? $_SESSION['private_receiver_number'] ?? ''
);
$receiverName = trim(
    $_GET['receiver_name'] ?? $_POST['receiver_name'] ?? $_SESSION['private_receiver_name'] ?? ''
);

if ($receiverNumber === '') {
    header('Location: chat.php');
    exit;
}

// Check if this is a self-chat
$isSelfChat = ((string)$receiverNumber === (string)$senderNumber);

if ($isSelfChat) {
    // For self-chat, use sender's own name and photo
    $receiverName = 'You';
    // Keep receiverNumber as is (same as sender)
} else {
    // Normal private chat with another user
    if (isset($users[$receiverNumber])) {
        $receiverName = trim($users[$receiverNumber]['name'] ?? '') ?: $receiverName ?: $receiverNumber;
    } else {
        $receiverName = $receiverName ?: $receiverNumber;
    }
    if (!empty($chatPreferences['aliases'][$receiverNumber])) {
        $receiverName = trim((string)$chatPreferences['aliases'][$receiverNumber]);
    }
}

$_SESSION['private_receiver_number'] = $receiverNumber;
$_SESSION['private_receiver_name'] = $receiverName;

$conversationFileName = getConversationFile($senderNumber, $receiverNumber);
$senderFolder = buildUserStorageDirectory($senderNumber);
$receiverFolder = buildUserStorageDirectory($receiverNumber);

$conversationFile = $senderFolder . '/' . $conversationFileName;
$receiverFile = $receiverFolder . '/' . $conversationFileName;

// Ensure both copies exist
if (!file_exists($conversationFile)) {
    $default = ['type' => 'private', 'participants' => [], 'messages' => []];
    saveJson($conversationFile, $default);
}
if (!file_exists($receiverFile)) {
    $default = ['type' => 'private', 'participants' => [], 'messages' => []];
    saveJson($receiverFile, $default);
}

// Ensure participants are set
$conversationData = loadJson($conversationFile, []);
$conversationData['type'] = 'private';
$conversationData['participants'][$senderNumber] = $senderName;
if ($isSelfChat) {
    // For self-chat, only one participant (yourself) is needed, but we can keep both entries same
    $conversationData['participants'][$receiverNumber] = $senderName;
} else {
    $conversationData['participants'][$receiverNumber] = $receiverName;
}
saveJson($conversationFile, $conversationData);

$receiverData = loadJson($receiverFile, []);
$receiverData['type'] = 'private';
$receiverData['participants'][$senderNumber] = $senderName;
if ($isSelfChat) {
    $receiverData['participants'][$receiverNumber] = $senderName;
} else {
    $receiverData['participants'][$receiverNumber] = $receiverName;
}
saveJson($receiverFile, $receiverData);

$conversationKey = basename($conversationFileName, '.json');

updatePresence($senderNumber, $senderName, 'private', $conversationKey);

/* =========================================================
   AJAX HANDLERS
========================================================= */

if (isset($_GET['ajax'])) {
    $ajaxAction = $_GET['ajax'];

    // Presence
    if ($ajaxAction === 'presence') {
        updatePresence($senderNumber, $senderName, 'private', $conversationKey);
        $statuses = [];
        foreach ([$senderNumber, $receiverNumber] as $num) {
            $statuses[$num] = getPresenceStatus($num, 'private', $conversationKey);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'statuses' => $statuses]);
        exit;
    }

    // Typing set
    if ($ajaxAction === 'typing') {
        $isTyping = ($_GET['typing'] ?? '0') === '1';
        $typing = loadTyping();
        updateTypingState($typing, $conversationKey, $senderNumber, $senderName, $isTyping);
        saveTyping($typing);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Typing status
    if ($ajaxAction === 'typing_status') {
        $typing = loadTyping();
        $status = getTypingStatus($typing, $conversationKey, $senderNumber);
        header('Content-Type: application/json');
        echo json_encode(['status' => $status]);
        exit;
    }

    // Read receipts/status for messages sent by the current user.
    if ($ajaxAction === 'read_status') {
        $freshData = loadJson($conversationFile, []);
        $status = [];
        foreach (($freshData['messages'] ?? []) as $msg) {
            $id = (string)($msg['id'] ?? '');
            if ($id === '') continue;
            if (safeNumber($msg['sender_number'] ?? '') === $senderNumber) {
                $status[$id] = !empty($msg['read']);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'status' => $status]);
        exit;
    }

    // Messages (polling)
    if ($ajaxAction === 'messages') {
        $freshData = loadJson($conversationFile, []);
        $messages = $freshData['messages'] ?? [];
        $lastId = trim($_GET['last_id'] ?? '');

        if ($lastId === '') {
            $html = renderPrivateMessagesMarkup($messages, $senderNumber);
            $lastId = !empty($messages) ? end($messages)['id'] ?? '' : '';
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'mode' => 'full', 'html' => $html, 'last_id' => $lastId]);
            exit;
        }

        $lastIndex = -1;
        foreach ($messages as $index => $msg) {
            if ((string)($msg['id'] ?? '') === (string)$lastId) {
                $lastIndex = $index;
                break;
            }
        }
        if ($lastIndex === -1) {
            $html = renderPrivateMessagesMarkup($messages, $senderNumber);
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
        $newHtml = renderPrivateMessagesMarkup($newMessages, $senderNumber, $previousDate);
        $lastId = !empty($messages) ? end($messages)['id'] ?? '' : '';
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'mode' => 'new', 'html' => $newHtml, 'last_id' => $lastId]);
        exit;
    }
}

/* =========================================================
   POST HANDLERS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Send message
    if ($action === 'rename_receiver') {
        $targetNumber = safeNumber($_POST['receiver_number'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');

        if ($targetNumber === '' || $newName === '') {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'error'=>'Enter a valid receiver name.']);
            exit;
        }

        if ($targetNumber === $senderNumber) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'error'=>'You cannot rename yourself here.']);
            exit;
        }

        $prefs = loadPreferences($senderNumber);
        $prefs['aliases'][$targetNumber] = $newName;
        savePreferences($senderNumber, $prefs);

        $conv=getConversationFile($senderNumber,$targetNumber);
        $myFile=buildUserStorageDirectory($senderNumber).'/'.$conv;
        $myData=loadJson($myFile,[]);
        if(is_array($myData)){
            $myData['participants'][$targetNumber]=$newName;
            if(!empty($myData['messages'])&&is_array($myData['messages'])){
                foreach($myData['messages'] as &$msg){
                    if(safeNumber($msg['receiver_number']??'')===$targetNumber)$msg['receiver_name']=$newName;
                    if(safeNumber($msg['sender_number']??'')===$targetNumber)$msg['sender_name']=$newName;
                }
                unset($msg);
            }
            saveJson($myFile,$myData);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok'=>true,'name'=>$newName]);
        exit;
    }

    if ($action === 'send_message') {
        $text = trim((string)($_POST['message'] ?? ''));
        $uploaded = $_FILES['attachment'] ?? null;

        $fileResults = [];
        $hasFiles = false;

        // Process uploaded files
        if (is_array($uploaded) && isset($uploaded['name']) && is_array($uploaded['name'])) {
            $count = count($uploaded['name']);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $uploaded['name'][$i] ?? '',
                    'type' => $uploaded['type'][$i] ?? '',
                    'tmp_name' => $uploaded['tmp_name'][$i] ?? '',
                    'error' => $uploaded['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $uploaded['size'][$i] ?? 0
                ];
                if (!empty($file['name'])) {
                    $result = saveUploadedFile($file, $conversationFileName);
                    if (!$result['ok']) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'error' => $result['error']]);
                        exit;
                    }
                    $fileResults[] = $result;
                    $hasFiles = true;
                }
            }
        } elseif (is_array($uploaded) && !empty($uploaded['name'])) {
            $file = $uploaded;
            if (!empty($file['name'])) {
                $result = saveUploadedFile($file, $conversationFileName);
                if (!$result['ok']) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => $result['error']]);
                    exit;
                }
                $fileResults[] = $result;
                $hasFiles = true;
            }
        }

        if ($text === '' && !$hasFiles) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Message is empty.']);
            exit;
        }

        $now = time();
        $date = date('Y-m-d', $now);
        $time = date('H:i:s', $now);

        $newMessage = [
            'id' => uniqid('msg_', true),
            'sender_number' => $senderNumber,
            'sender_name' => $senderName,
            'receiver_number' => $receiverNumber,
            'receiver_name' => $receiverName,
            'message' => $text,
            'time' => $time,
            'date' => $date,
            'read' => false,
            'attachments' => []
        ];

        if ($hasFiles) {
            foreach ($fileResults as $result) {
                $newMessage['attachments'][] = $result;
            }
        }

        // Save to sender
        $senderData = loadJson($conversationFile, []);
        $senderData['type'] = 'private';
        $senderData['participants'][$senderNumber] = $senderName;
        if ($isSelfChat) {
            $senderData['participants'][$receiverNumber] = $senderName;
        } else {
            $senderData['participants'][$receiverNumber] = $receiverName;
        }
        $senderData['messages'][] = $newMessage;
        saveJson($conversationFile, $senderData);

        // Save to receiver
        $receiverData = loadJson($receiverFile, []);
        $receiverData['type'] = 'private';
        $receiverData['participants'][$senderNumber] = $senderName;
        if ($isSelfChat) {
            $receiverData['participants'][$receiverNumber] = $senderName;
        } else {
            $receiverData['participants'][$receiverNumber] = $receiverName;
        }
        $receiverData['messages'][] = $newMessage;
        saveJson($receiverFile, $receiverData);

        // Stop typing
        $typing = loadTyping();
        updateTypingState($typing, $conversationKey, $senderNumber, $senderName, false);
        saveTyping($typing);

        // Return only the new message HTML for display
        $previousDate = null;
        $allMessages = $senderData['messages'] ?? [];
        if (count($allMessages) > 1) {
            $lastOld = $allMessages[count($allMessages) - 2];
            if (!empty($lastOld['date'])) {
                $previousDate = $lastOld['date'];
            } elseif (!empty($lastOld['time'])) {
                $previousDate = date('Y-m-d', strtotime($lastOld['time']));
            }
        }
        $newHtml = renderPrivateMessagesMarkup([$newMessage], $senderNumber, $previousDate);

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'html' => $newHtml,
            'last_id' => $newMessage['id']
        ]);
        exit;
    }

    // Delete message
    if ($action === 'delete_message') {
        $id = (string)($_POST['message_id'] ?? '');
        if ($id === '') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid message.']);
            exit;
        }

        $deleted = false;
        foreach ([$conversationFile, $receiverFile] as $file) {
            $data = loadJson($file, []);
            $messages = $data['messages'] ?? [];
            foreach ($messages as $key => $msg) {
                if ((string)($msg['id'] ?? '') === $id) {
                    unset($messages[$key]);
                    $deleted = true;
                    break;
                }
            }
            $data['messages'] = array_values($messages);
            saveJson($file, $data);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => $deleted]);
        exit;
    }

    // Mark read
    if ($action === 'mark_read') {
        foreach ([$conversationFile, $receiverFile] as $file) {
            $data = loadJson($file, []);
            $changed = false;
            if (!isset($data['messages']) || !is_array($data['messages'])) {
                $data['messages'] = [];
            }
            foreach ($data['messages'] as &$msg) {
                if ((string)($msg['receiver_number'] ?? '') === $senderNumber && empty($msg['read'])) {
                    $msg['read'] = true;
                    $msg['read_at'] = date('Y-m-d H:i:s');
                    $changed = true;
                }
            }
            unset($msg);
            if ($changed) saveJson($file, $data);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

/* =========================================================
   LOAD DATA FOR PAGE
========================================================= */

$conversationData = loadJson($conversationFile, []);
$messages = $conversationData['messages'] ?? [];
$messagesMarkup = renderPrivateMessagesMarkup($messages, $senderNumber);

$senderPhoto = getProfilePhotoPath($users, $senderNumber);
if ($isSelfChat) {
    // Use sender's own photo for the "receiver" (yourself)
    $receiverPhoto = $senderPhoto;
} else {
    $receiverPhoto = getProfilePhotoPath($users, $receiverNumber);
}

$typing = loadTyping();
$typingText = getTypingStatus($typing, $conversationKey, $senderNumber);

$initialStatuses = [];
foreach ([$senderNumber, $receiverNumber] as $num) {
    $initialStatuses[$num] = getPresenceStatus($num, 'private', $conversationKey);
}

$lastMessageId = !empty($messages) ? end($messages)['id'] ?? '' : '';

updatePresence($senderNumber, $senderName, 'private', $conversationKey);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($isSelfChat ? 'You' : $receiverName) ?> - Chat</title>
<link rel="icon" type="image/png" href="../images/hhh%20picture.png">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; width: 100%; height: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #eef2f7; overflow: hidden; }
.chat-app { width: 100%; height: 100vh; display: flex; flex-direction: column; background: #fff; }
.chat-header { height: 72px; min-height: 72px; position: fixed; width: 100%; display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e6eaf0; box-shadow: 0 2px 12px rgba(0,0,0,.04); z-index: 20; }
.back { width: 42px; height: 42px; border: 0; background: #f3f6fa; border-radius: 50%; font-size: 21px; cursor: pointer; transition: background 0.2s; }
.back:hover { background: #e2e8f0; }
.profile { width: 46px; height: 46px; min-width: 46px; border-radius: 50%; overflow: hidden; background: #dbeafe; display: flex; justify-content: center; align-items: center; font-weight: 700; color: #2563eb; cursor: pointer; position: relative; }
.profile img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
.header-info { flex: 1; min-width: 0; cursor: pointer; }
.header-info strong { display: flex; align-items: center; gap: 8px; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status { font-size: 12px; color: #64748b; margin-top: 3px; }
.status.online { color: #16a34a; }
.status.typing { color: #2563eb; font-weight: 600; }
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
.sender-name { font-size: 12px; display: none; font-weight: 600; color: #2563eb; margin-bottom: 3px; }
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
.attachment { margin-top: 2px; }
.attachment img { max-width: 340px; max-height: 350px; display: block; border-radius: 11px; cursor: pointer; }
.attachment video { max-width: 340px; max-height: 350px; border-radius: 11px; }
.attachment audio { width: 280px; max-width: 100%; }
.document { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-decoration: none; color: #111827; }
.document-icon { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: #e2e8f0; border-radius: 9px; font-size: 19px; }
.document-name { max-width: 220px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 13px; }
.attachment-actions { margin-top: 6px; }
.download-file { display: inline-flex; align-items: center; justify-content: center; padding: 7px 10px; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; font-size: 12px; font-weight: 600; }
.download-file:hover { background: #1d4ed8; }
.attachment-meta { display: flex; justify-content: space-between; gap: 10px; margin-top: 5px; font-size: 11px; color: #64748b; }
.file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-size { flex-shrink: 0; }
.composer { display: flex; align-items: flex-end; gap: 8px; padding: 10px 14px; background: #fff; border-top: 1px solid #e5e7eb; }
.attach-label { width: 42px; height: 42px; display: flex; justify-content: center; align-items: center; background: #f1f5f9; border-radius: 50%; cursor: pointer; font-size: 20px; transition: background 0.2s; }
.attach-label:hover { background: #e2e8f0; }
#attachment { display: none; }
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
.member-list { max-height: 250px; overflow-y: auto; margin-top: 8px; }
.member-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.member-item:last-child { border-bottom: 0; }
.member-item .avatar { width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #2563eb; overflow: hidden; }
.member-item .avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.member-item .info { flex: 1; }
.member-item .info strong { display: block; font-size: 14px; }
.member-item .info span { font-size: 12px; color: #64748b; }
.member-item .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.member-item .status-dot.online { background: #22c55e; }
.member-item .add-btn { padding: 4px 12px; border: 0; border-radius: 20px; background: #2563eb; color: #fff; font-size: 12px; cursor: pointer; transition: background 0.2s; }
.member-item .add-btn:hover { background: #1d4ed8; }
.member-item .add-btn:disabled { opacity: 0.5; cursor: default; }
.rename-section{margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb}
.rename-section input{width:100%;padding:11px 12px;border:1px solid #d7dee8;border-radius:10px;margin:8px 0;outline:none;font-size:14px}
.rename-section input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.10)}
.rename-result{min-height:18px;margin-top:7px;font-size:12px}
@media(max-width:600px) { .chat-header { padding: 8px 9px; } .messages { padding: 12px 8px; } .bubble { max-width: 86%; } .attachment img { max-width: 220px; } .attachment video { max-width: 220px; } .composer { padding: 8px; margin-bottom: 50px;} .search-box { width: 230px; } .modal-content { padding: 18px; } }
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
    <div class="profile" id="receiver-profile" onclick="openProfile()">
        <?php if ($receiverPhoto !== ''): ?>
            <img src="<?= esc($receiverPhoto) ?>" alt="" onclick="event.stopPropagation(); openBigImage('<?= esc($receiverPhoto) ?>')">
        <?php else: ?>
            <?= esc(initialLetter($isSelfChat ? $senderName : $receiverName)) ?>
        <?php endif; ?>
    </div>
    <div class="header-info" onclick="openProfile()">
        <strong><?= esc($isSelfChat ? 'You' : $receiverName) ?></strong>
        <div class="status" id="member-status">Checking status...</div>
        <div class="typing-status" id="typingStatus" style="font-size:12px; color:#2563eb; min-height:16px;"><?= esc($typingText) ?></div>
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
  
    <div>
    <?= $messagesMarkup ?>
     <?php if (empty($messages)): ?>
        <div class="empty"><div><strong>No messages yet</strong><br><small>Start the conversation.</small></div></div>
    <?php endif; ?>
    </div>
</main>

<form class="composer" id="message-form" enctype="multipart/form-data">
    <label class="attach-label" for="attachment" id="attachment-label">📎</label>
    <input type="file" multiple id="attachment" name="attachment[]" accept="image/*,audio/*,video/*,.pdf,.doc, .docx, .xls, .xlsx, .ppt, .pptx, .txt, .zip, .rar, .7z, .csv, .json, .html, .css,.js,.apk,.py,.php">
    <textarea id="message" name="message" placeholder="Type a message..." rows="1"></textarea>
    <button class="send" id="send-button" type="submit">➤</button>
</form>

</div>

<!-- PROFILE MODAL -->
<div class="modal" id="profile-modal" onclick="closeProfile(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeProfile()" type="button">×</button>
        <div class="big-profile">
            <?php if ($receiverPhoto !== ''): ?>
                <img src="<?= esc($receiverPhoto) ?>" alt="" onclick="openBigImage('<?= esc($receiverPhoto) ?>')">
            <?php else: ?>
                <?= esc(initialLetter($isSelfChat ? $senderName : $receiverName)) ?>
            <?php endif; ?>
        </div>
        <h2><?= esc($isSelfChat ? 'You' : $receiverName) ?></h2>
        <div class="info-row"><div class="info-label">Name</div><div class="info-value"><?= esc($isSelfChat ? $senderName : $receiverName) ?></div></div>
        <div class="info-row"><div class="info-label">Number</div><div class="info-value">+<?= esc($receiverNumber) ?></div></div>
        <div class="info-row"><div class="info-label">Status</div><div class="info-value" id="profile-status">Checking...</div></div>
        <?php if (!$isSelfChat): ?>
        <div class="rename-section">
            <div class="info-label">Rename receiver for you</div>
            <input type="text" id="receiverNameInput" value="<?= esc($receiverName) ?>" maxlength="100" autocomplete="off" placeholder="Enter receiver name">
            <button type="button" class="download-file" onclick="renameReceiver()">Save Name</button>
            <div id="renameReceiverResult" class="rename-result"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- BIG IMAGE MODAL -->
<div class="modal" id="bigImageModal" onclick="closeModal(event,'bigImageModal')">
    <div style="max-width:95vw; max-height:95vh; text-align:center;" onclick="event.stopPropagation()">
        <img id="bigImage" src="" alt="" style="max-width:90vw; max-height:85vh; object-fit:contain; border-radius:16px;">
        <br>
        <button onclick="closeModal(event,'bigImageModal')" style="margin-top:10px; width:40px; height:40px; border:0; border-radius:50%; cursor:pointer; font-size:20px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.2);">×</button>
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
const CONVERSATION_FILE = <?= json_encode($conversationFileName) ?>;
const CURRENT_USER = <?= json_encode($senderNumber) ?>;
const RECEIVER_NUMBER = <?= json_encode($receiverNumber) ?>;
const RECEIVER_NAME = <?= json_encode($isSelfChat ? 'You' : $receiverName) ?>;
const IS_SELF_CHAT = <?= $isSelfChat ? 'true' : 'false' ?>;
let lastMessageId = <?= json_encode($lastMessageId) ?>;
let typingTimer = null;
let selectedDeleteId = '';
let isSending = false;
let userNearBottom = true;
let messageRequestRunning = false;
let readStatusRequestRunning = false;

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
    body.append('action', 'delete_message');
    body.append('message_id', selectedDeleteId);
    fetch('private_chat.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            const row = document.querySelector('.message-row[data-message-id="' + CSS.escape(selectedDeleteId) + '"]');
            if (row) row.remove();
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
   DOWNLOAD ALL ATTACHMENTS
========================================================= */
function downloadAllAttachments(button) {
    const row = button.closest('.message-row');
    if (!row) return;
    const attachments = row.querySelectorAll('.attachment');
    attachments.forEach((att, index) => {
        const url = att.dataset.attachmentUrl || '';
        const name = att.dataset.attachmentName || ('file-' + (index + 1));
        if (!url) return;
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = url;
            link.download = name;
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            link.remove();
        }, index * 250);
    });
    closeAllMenus();
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
    messagesBox.insertAdjacentHTML('beforeend', html);
    if (wasBottom) scrollToBottom(true);
    updateStickyDateSeparator();
    updateLastMessageTag();
}
function replaceAllMessages(html) {
    if (!messagesBox) return;
    messagesBox.innerHTML = html || '<div class="empty"><div><strong>No messages yet</strong><br><small>Start the conversation.</small></div></div>';
    scrollToBottom(true);
    updateStickyDateSeparator();
    updateLastMessageTag();
}

/* =========================================================
   POLLING MESSAGES
========================================================= */
function refreshMessages() {
    if (messageRequestRunning) return;
    messageRequestRunning = true;
    const url = 'private_chat.php?ajax=messages&last_id=' + encodeURIComponent(lastMessageId || '') + '&_=' + Date.now();
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
        refreshReadStatus();
    })
    .catch(err => console.log('Polling error:', err))
    .finally(() => { messageRequestRunning = false; });
}

/* =========================================================
   TYPING
========================================================= */
function sendTypingState(value) {
    const url = 'private_chat.php?ajax=typing&typing=' + (value ? '1' : '0');
    fetch(url, { method: 'GET', cache: 'no-store' }).catch(() => {});
}
const messageInput = document.getElementById('message');
messageInput.addEventListener('input', function() {
    if (this.value.trim() !== '') {
        sendTypingState(true);
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => sendTypingState(false), 2500);
    } else {
        sendTypingState(false);
    }
});
messageInput.addEventListener('blur', function() { sendTypingState(false); });
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('message-form').requestSubmit();
    }
});

function refreshTypingStatus() {
    fetch('private_chat.php?ajax=typing_status&_=' + Date.now(), { method: 'GET', cache: 'no-store' })
    .then(res => res.json())
    .then(data => {
        const statusEl = document.getElementById('typingStatus');
        if (statusEl) statusEl.textContent = data.status || '';
    })
    .catch(() => {});
}

/* =========================================================
   PRESENCE
========================================================= */
function refreshPresence() {
    fetch('private_chat.php?ajax=presence&_=' + Date.now(), { method: 'GET', cache: 'no-store' })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.ok || !data.statuses) return;
        const theirStatus = data.statuses[RECEIVER_NUMBER] || 'Offline';
        const statusEl = document.getElementById('member-status');
        const profileStatus = document.getElementById('profile-status');
        if (statusEl) {
            if (theirStatus === 'Online') {
                statusEl.textContent = '● Online';
                statusEl.className = 'status online';
            } else {
                statusEl.textContent = 'Offline';
                statusEl.className = 'status';
            }
        }
        if (profileStatus) {
            profileStatus.textContent = theirStatus === 'Online' ? '● Online' : 'Offline';
        }
    })
    .catch(() => {});
}

/* =========================================================
   MARK READ
========================================================= */
function markRead() {
    const body = new URLSearchParams();
    body.append('action', 'mark_read');
    fetch('private_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    }).catch(() => {});
}


/* =========================================================
   READ RECEIPTS / DOUBLE TICK
========================================================= */
function refreshReadStatus() {
    if (readStatusRequestRunning) return;
    readStatusRequestRunning = true;

    fetch('private_chat.php?ajax=read_status&_=' + Date.now(), {
        method: 'GET',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.ok || !data.status) return;

        document.querySelectorAll('.message-row.mine[data-message-id]').forEach(row => {
            const id = row.dataset.messageId;
            const tick = row.querySelector('.message-ticks');
            if (!tick || !Object.prototype.hasOwnProperty.call(data.status, id)) return;

            const isRead = !!data.status[id];
            tick.textContent = isRead ? '✓✓' : '✓';
            tick.className = 'message-ticks ' + (isRead ? 'read' : 'sent');
            tick.dataset.readStatus = isRead ? 'read' : 'sent';
            tick.title = isRead ? 'Seen' : 'Sent';
        });
    })
    .catch(() => {})
    .finally(() => { readStatusRequestRunning = false; });
}


/* ========================================================= 
   ATTACHMENT PREVIEW 
========================================================= */ 
 
document 
    .getElementById( 
        'attachment' 
    ) 
    .addEventListener( 
        'change', 
        function() { 
 
            const files = 
                this.files; 
 
            if ( 
                !files || 
                files.length === 0 
            ) { 
                return; 
            } 
 
            let names = []; 
 
            for ( 
                let i = 0; 
                i < files.length; 
                i++ 
            ) { 
 
                names.push( 
                    files[i].name 
                ); 
            } 
 
            const preview = 
                document.createElement( 
                    'div' 
                ); 
 
            preview.style.cssText = 
                'font-size:12px;' + 
                'color:#64748b;' + 
                'padding:4px 10px;' + 
                'background:#f1f5f9;' + 
                'border-radius:10px;' + 
                'margin-bottom:6px;'; 
 
            preview.textContent = 
                '📎 ' + 
                names.join(', '); 
 
            const composer = 
                document.querySelector( 
                    '.composer' 
                ); 
 
            const existing = 
                document.getElementById( 
                    'filePreview' 
                ); 
 
            if (existing) { 
                existing.remove(); 
            } 
 
            preview.id = 
                'filePreview'; 
 
            composer.parentNode.insertBefore( 
                preview, 
                composer 
            ); 
        } 
    ); 
 
    
/* =========================================================
   SEND MESSAGE
========================================================= */
document.getElementById('message-form').addEventListener('submit', function(e) {
    e.preventDefault();
    if (isSending) return;
    const form = this;
    const input = document.getElementById('message');
    const button = document.getElementById('send-button');
    const value = input.value.trim();
    const files = document.getElementById('attachment').files;
    if (value === '' && (!files || files.length === 0)) return;
    isSending = true;
    button.disabled = true;
    button.textContent = '...';
    sendTypingState(false);
    const formData = new FormData(form);
    formData.append('action', 'send_message');
    formData.append('receiver_number', RECEIVER_NUMBER);
    formData.append('receiver_name', RECEIVER_NAME);
    fetch('private_chat.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.ok) {
            alert(data.error || 'Unable to send message.');
            return;
        }
        appendNewMessages(data.html);
        if (data.last_id) lastMessageId = data.last_id;
        input.value = '';
        document.getElementById('attachment').value = '';
        scrollToBottom(true);
        input.focus();
    })
    .catch(err => {
        console.log(err);
        alert('Unable to send message.');
    })
    .finally(() => {
        isSending = false;
        button.disabled = false;
        button.textContent = '➤';
    });
});

/* =========================================================
   RENAME RECEIVER
========================================================= */
function renameReceiver(){
    if (IS_SELF_CHAT) {
        alert('You cannot rename yourself.');
        return;
    }
    const input=document.getElementById('receiverNameInput');
    const result=document.getElementById('renameReceiverResult');
    if(!input||!result)return;
    const newName=input.value.trim();
    if(!newName){result.textContent='Enter a receiver name.';result.style.color='#dc2626';return;}

    const body=new URLSearchParams();
    body.append('action','rename_receiver');
    body.append('receiver_number',RECEIVER_NUMBER);
    body.append('new_name',newName);

    fetch('private_chat.php',{
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
        body:body.toString()
    }).then(res=>res.json()).then(data=>{
        if(!data.ok){
            result.textContent=data.error||'Unable to rename receiver.';
            result.style.color='#dc2626';
            return;
        }
        const header=document.querySelector('.header-info strong');
        if(header)header.textContent=data.name;
        const title=document.querySelector('#profile-modal h2');
        if(title)title.textContent=data.name;
        const rows=document.querySelectorAll('#profile-modal .info-row .info-value');
        if(rows.length)rows[0].textContent=data.name;
        result.textContent='Receiver name updated.';
        result.style.color='#16a34a';
        input.value=data.name;
    }).catch(()=>{
        result.textContent='Unable to rename receiver.';
        result.style.color='#dc2626';
    });
}

/* =========================================================
   PROFILE
========================================================= */
function openProfile() {
    openModal('profile-modal');
    refreshPresence();
}
function closeProfile(event) {
    if (event && event.target !== event.currentTarget) return;
    closeModal(null, 'profile-modal');
}

/* =========================================================
   BIG IMAGE
========================================================= */
function openBigImage(src) {
    document.getElementById('bigImage').src = src;
    openModal('bigImageModal');
}

/* =========================================================
   ESC KEY
========================================================= */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal(null, 'bigImageModal');
        closeModal(null, 'profile-modal');
        closeModal(null, 'deleteModal');
    }
});

/* =========================================================
   INITIAL
========================================================= */
refreshMessages();
refreshPresence();
refreshTypingStatus();
refreshReadStatus();
setInterval(refreshMessages, 1500);
setInterval(refreshPresence, 2000);
setInterval(refreshTypingStatus, 1000);
setInterval(refreshReadStatus, 1000);
scrollToBottom(true);
updateLastMessageTag();


/* =========================================================
   BEFORE UNLOAD
========================================================= */
window.addEventListener('beforeunload', function() {
    sendTypingState(false);
});
</script>
</body>
</html>