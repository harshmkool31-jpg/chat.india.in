<?php 
session_start(); 
date_default_timezone_set('Asia/Kolkata'); 
 
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
 
    $json = file_get_contents($path); 
    if ($json === false || trim($json) === '') { 
        return $default; 
    } 
 
    $data = json_decode($json, true); 
 
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
        json_encode( 
            $data, 
            JSON_PRETTY_PRINT | 
            JSON_UNESCAPED_UNICODE | 
            JSON_UNESCAPED_SLASHES 
        ), 
        LOCK_EX 
    ); 
} 
 
function safeNumber($number) 
{ 
    return preg_replace('/[^0-9]/', '', (string)$number); 
} 
 
function esc($value) 
{ 
    return htmlspecialchars( 
        (string)$value, 
        ENT_QUOTES, 
        'UTF-8' 
    ); 
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
 
function updatePresence( 
    $number, 
    $name, 
    $page = 'group', 
    $conversation = '' 
) { 
    $number = safeNumber($number); 
 
    if ($number === '') { 
        return; 
    } 
 
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
 
function getPresenceStatus( 
    $number, 
    $page = '', 
    $conversation = '' 
) { 
    $number = safeNumber($number); 
 
    if ($number === '') { 
        return 'Offline'; 
    } 
 
    $presence = loadPresence(); 
 
    if (!isset($presence[$number])) { 
        return 'Offline'; 
    } 
 
    $item = $presence[$number]; 
 
    if ( 
        time() - 
        (int)($item['last_seen'] ?? 0) 
        > 10 
    ) { 
        return 'Offline'; 
    } 
 
    if ( 
        $page !== '' && 
        ($item['page'] ?? '') !== $page 
    ) { 
        return 'Offline'; 
    } 
 
    if ( 
        $conversation !== '' && 
        ($item['conversation'] ?? '') !== $conversation 
    ) { 
        return 'Offline'; 
    } 
 
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
 
function updateTypingState( 
    array &$typing, 
    $conversationKey, 
    $number, 
    $name, 
    $isTyping 
) { 
    if ( 
        $conversationKey === '' || 
        $number === '' 
    ) { 
        return; 
    } 
 
    if (!isset($typing[$conversationKey])) { 
        $typing[$conversationKey] = []; 
    } 
 
    if (!$isTyping) { 
        unset($typing[$conversationKey][$number]); 
 
        if (empty($typing[$conversationKey])) { 
            unset($typing[$conversationKey]); 
        } 
 
        return; 
    } 
 
    $typing[$conversationKey][$number] = [ 
        'number'     => $number, 
        'name'       => $name, 
        'updated_at' => time() 
    ]; 
} 
 
function getTypingStatus( 
    array $typing, 
    $conversationKey, 
    $currentNumber 
) { 
    if ( 
        $conversationKey === '' || 
        empty($typing[$conversationKey]) 
    ) { 
        return ''; 
    } 
 
    $names = []; 
    $now = time(); 
 
    foreach ( 
        $typing[$conversationKey] 
        as $number => $entry 
    ) { 
        if ( 
            (string)$number === 
            (string)$currentNumber 
        ) { 
            continue; 
        } 
 
        if ( 
            $now - 
            (int)($entry['updated_at'] ?? 0) 
            > 5 
        ) { 
            continue; 
        } 
 
        $name = trim( 
            (string)($entry['name'] ?? '') 
        ); 
 
        if ($name !== '') { 
            $names[] = $name; 
        } 
    } 
 
    if (empty($names)) { 
        return ''; 
    } 
 
    if (count($names) === 1) { 
        return $names[0] . ' is typing...'; 
    } 
 
    if (count($names) === 2) { 
        return $names[0] . 
            ' and ' . 
            $names[1] . 
            ' are typing...'; 
    } 
 
    return count($names) . ' people are typing...'; 
} 
 
/* ========================================================= 
   USERS 
========================================================= */ 
 
function loadUsers() 
{ 
    return loadJson( 
        getDataDir() . '/users.json', 
        [] 
    ); 
} 
 
function getProfilePhotoPath( 
    array $users, 
    $number 
) { 
    return trim( 
        (string)( 
            $users[$number]['profile_photo'] 
            ?? '' 
        ) 
    ); 
} 
 
function buildUserStorageDirectory($number) 
{ 
    $folderName = preg_replace( 
        '/[^A-Za-z0-9._-]/', 
        '_', 
        (string)$number 
    ); 
 
    $folderName = trim( 
        $folderName, 
        '_' 
    ); 
 
    if ($folderName === '') { 
        $folderName = 'user'; 
    } 
 
    $directory = 
        getDataDir() . 
        '/' . 
        $folderName; 
 
    if (!is_dir($directory)) { 
        mkdir( 
            $directory, 
            0777, 
            true 
        ); 
    } 
 
    return $directory; 
} 
 
function buildGroupConversationFileName( 
    $senderNumber, 
    array $participantNumbers 
) { 
    $numbers = array_merge( 
        [$senderNumber], 
        $participantNumbers 
    ); 
 
    $numbers = array_unique($numbers); 
 
    $numbers = array_map( 
        'safeNumber', 
        $numbers 
    ); 
 
    $numbers = array_filter($numbers); 
 
    sort( 
        $numbers, 
        SORT_STRING 
    ); 
 
    return implode( 
        '_', 
        $numbers 
    ) . '.json'; 
} 
 
/* ========================================================= 
   ATTACHMENTS 
========================================================= */ 
 
function getAttachmentCategory( 
    $mime, 
    $fileName 
) { 
    $mime = strtolower( 
        (string)$mime 
    ); 
 
    $ext = strtolower( 
        pathinfo( 
            (string)$fileName, 
            PATHINFO_EXTENSION 
        ) 
    ); 
 
    if ( 
        strpos($mime, 'image/') === 0 
    ) { 
        return 'image'; 
    } 
 
    if ( 
        strpos($mime, 'audio/') === 0 
    ) { 
        return 'audio'; 
    } 
 
    if ( 
        strpos($mime, 'video/') === 0 
    ) { 
        return 'video'; 
    } 
 
    $image = [ 
        'jpg', 
        'jpeg', 
        'png', 
        'gif', 
        'webp', 
        'bmp', 
        'svg' 
    ]; 
 
    $audio = [ 
        'mp3', 
        'wav', 
        'ogg', 
        'm4a', 
        'aac', 
        'flac' 
    ]; 
 
    $video = [ 
        'mp4', 
        'mov', 
        'avi', 
        'mkv', 
        'webm', 
        'wmv', 
        'mpeg', 
        'mpg', 
        '3gp' 
    ]; 
 
    if ( 
        in_array( 
            $ext, 
            $image, 
            true 
        ) 
    ) { 
        return 'image'; 
    } 
 
    if ( 
        in_array( 
            $ext, 
            $audio, 
            true 
        ) 
    ) { 
        return 'audio'; 
    } 
 
    if ( 
        in_array( 
            $ext, 
            $video, 
            true 
        ) 
    ) { 
        return 'video'; 
    } 
 
    return 'document'; 
} 
 
function isAllowedAttachment( 
    $mime, 
    $fileName 
) { 
    $ext = strtolower( 
        pathinfo( 
            (string)$fileName, 
            PATHINFO_EXTENSION 
        ) 
    ); 
 
    $allowed = [ 
        'jpg', 
        'jpeg', 
        'png', 
        'gif', 
        'webp', 
        'bmp', 
        'svg', 
 
        'mp3', 
        'wav', 
        'ogg', 
        'm4a', 
        'aac', 
        'flac', 
 
        'mp4', 
        'mov', 
        'avi', 
        'mkv', 
        'webm', 
        'wmv', 
        'mpeg', 
        'mpg', 
        '3gp', 

        'pdf', 
        'doc', 
        'docx', 
        'xls', 
        'xlsx', 
        'ppt', 
        'pptx', 
        'txt', 
        'zip', 
        'rar', 
        '7z', 
        'csv', 
        'json', 
        'html',
        'css',
        'js',
        'php',
        'py',
        'php' 
    ]; 
 
    return in_array( 
        $ext, 
        $allowed, 
        true 
    ); 
} 
 
function formatFileSize($bytes) 
{ 
    $bytes = (int)$bytes; 
 
    if ( 
        $bytes >= 
        1024 * 1024 * 1024 
    ) { 
        return round( 
            $bytes / 
            (1024 * 1024 * 1024), 
            1 
        ) . ' GB'; 
    } 
 
    if ( 
        $bytes >= 
        1024 * 1024 
    ) { 
        return round( 
            $bytes / 
            (1024 * 1024), 
            1 
        ) . ' MB'; 
    } 
 
    if ( 
        $bytes >= 1024 
    ) { 
        return round( 
            $bytes / 1024, 
            1 
        ) . ' KB'; 
    } 
 
    return $bytes . ' B'; 
} 
 
function saveUploadedFile( 
    $attachment, 
    $conversationFile 
) { 
    if ( 
        !is_array($attachment) || 
        empty($attachment['tmp_name']) 
    ) { 
        return [ 
            'ok' => false, 
            'error' => 'No file selected.' 
        ]; 
    } 
 
    $error = (int)( 
        $attachment['error'] 
        ?? UPLOAD_ERR_NO_FILE 
    ); 
 
    if ( 
        $error !== UPLOAD_ERR_OK 
    ) { 
        return [ 
            'ok' => false, 
            'error' => 'Upload failed.' 
        ]; 
    } 
 
    $size = (int)( 
        $attachment['size'] 
        ?? 0 
    ); 
 
    if ($size <= 0) { 
        return [ 
            'ok' => false, 
            'error' => 'Selected file is empty.' 
        ]; 
    } 
 
    if ( 
        $size > 
        200 * 1024 * 1024 
    ) { 
        return [ 
            'ok' => false, 
            'error' => 
                'Maximum file size is 200 MB.' 
        ]; 
    } 
 
    $name = basename( 
        (string)( 
            $attachment['name'] 
            ?? 'file' 
        ) 
    ); 
 
    $mime = strtolower( 
        (string)( 
            $attachment['type'] 
            ?? '' 
        ) 
    ); 
 
    if ( 
        !isAllowedAttachment( 
            $mime, 
            $name 
        ) 
    ) { 
        return [ 
            'ok' => false, 
            'error' => 
                'This file type is not allowed.' 
        ]; 
    } 
 
    $category = 
        getAttachmentCategory( 
            $mime, 
            $name 
        ); 
 
    $folder = preg_replace( 
        '/[^A-Za-z0-9._-]/', 
        '_', 
        (string)$conversationFile 
    ); 
 
    $folder = trim( 
        $folder, 
        '_' 
    ); 
 
    if ($folder === '') { 
        $folder = 'group'; 
    } 
 
    $uploadDir = 
        getDataDir() . 
        '/attachments/' . 
        $folder; 
 
    if (!is_dir($uploadDir)) { 
        mkdir( 
            $uploadDir, 
            0777, 
            true 
        ); 
    } 
 
    $safeName = 
        time() . 
        '_' . 
        bin2hex( 
            random_bytes(3) 
        ) . 
        '_' . 
        preg_replace( 
            '/[^A-Za-z0-9._-]/', 
            '_', 
            $name 
        ); 
 
    $destination = 
        $uploadDir . 
        '/' . 
        $safeName; 
 
    if ( 
        !move_uploaded_file( 
            $attachment['tmp_name'], 
            $destination 
        ) 
    ) { 
        return [ 
            'ok' => false, 
            'error' => 
                'Could not save uploaded file.' 
        ]; 
    } 
 
    return [ 
        'ok' => true, 
        'path' => 
            'data/attachments/' . 
            $folder . 
            '/' . 
            $safeName, 
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
 
    $pattern = 
        '/((https?|ftp):\/\/[^\s<]+)/i'; 
 
    return preg_replace( 
        $pattern, 
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', 
        $escaped 
    ); 
} 
 
function renderAttachment($attachment)
{
    $path = trim((string)($attachment['path'] ?? ''));

    if ($path === '') {
        return '';
    }

    $type = trim((string)($attachment['type'] ?? 'document'));
    $name = trim((string)($attachment['name'] ?? 'file'));
    $size = formatFileSize($attachment['size'] ?? 0);

    $safePath = esc($path);
    $safeName = esc($name);

    $html = '<div class="attachment"'
        . ' data-attachment-url="' . $safePath . '"'
        . ' data-attachment-name="' . $safeName . '">';

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
        . '<a class="download-file" href="' . $safePath . '" download="'
        . $safeName . '">⬇ Download</a>'
        . '</div>';

    $html .= '</div>';

    return $html;
}

/* ========================================================= 
   MESSAGE RENDER 
========================================================= */ 
 
function renderGroupMessagesMarkup(
    array $messages,
    $senderNumber,
    $existingLastDate = null
) {
    $html = '';
    $lastDate = $existingLastDate ?? '';

    foreach ($messages as $index => $msg) {
        if (!empty($msg['deleted'])) {
            continue;
        }

        $messageId = $msg['id'] ?? ('message_' . $index);
        $messageDate = !empty($msg['date'])
            ? date('Y-m-d', strtotime($msg['date']))
            : date('Y-m-d');

        if ($messageDate !== $lastDate) {
            $dateText = date('d M Y', strtotime($messageDate));
            $html .= '<div class="date-separator">' . esc($dateText) . '</div>';
            $lastDate = $messageDate;
        }

        $messageSender = safeNumber($msg['sender_number'] ?? '');
        $messageSenderName = trim((string)($msg['sender_name'] ?? 'User'));
        $isMine = $messageSender === safeNumber($senderNumber);
        $class = $isMine ? 'mine' : '';

        $attachments = $msg['attachments'] ?? [];

        if (empty($attachments) && !empty($msg['attachment_path'])) {
            $attachments = [[
                'path' => $msg['attachment_path'],
                'type' => $msg['attachment_type'] ?? 'document',
                'name' => $msg['attachment_name'] ?? 'file',
                'size' => $msg['attachment_size'] ?? 0
            ]];
        }

        $html .= '<div class="message-row ' . $class . '" data-message-id="'
            . esc($messageId) . '" data-date-key="' . esc($messageDate) . '">';
        $html .= '<div class="bubble">';
        $html .= '<button class="message-menu-btn" onclick="toggleMessageMenu(this)" type="button">⋮</button>';
        $html .= '<div class="message-menu">';

        if (trim((string)($msg['message'] ?? '')) !== '') {
            $html .= '<button type="button" onclick="copyMessage(this)">📋 Copy</button>';
        }

        if (!empty($attachments)) {
            $html .= '<button type="button" onclick="downloadAllAttachments(this)">'
                . '⬇️ Download all</button>';
        }

        $html .= '<button type="button" class="danger" onclick="deleteMessage(this)" style="">'
            . '🗑 Delete</button>';
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

        $html .= '<div class="message-time">' . esc($msg['time'] ?? '') . '</div>';
        $html .= '</div></div>';
    }

    return $html;
}

/* ========================================================= 
   AUTHENTICATION 
========================================================= */ 
 
$senderNumber = 
    safeNumber( 
        $_SESSION['sender_number'] 
        ?? '' 
    ); 
 
$senderName = 
    trim( 
        $_SESSION['sender_name'] 
        ?? '' 
    ); 
 
if ( 
    $senderNumber === '' || 
    $senderName === '' 
) { 
    header( 
        'Location: chat.php' 
    ); 
    exit; 
} 
 
$users = loadUsers(); 
 
$senderFolder = 
    buildUserStorageDirectory( 
        $senderNumber 
    ); 
 
$conversationFile = 
    trim( 
        $_GET['conversation'] 
        ?? 
        $_SESSION['group_conversation_file'] 
        ?? 
        '' 
    ); 
 
$conversationFile = 
    basename( 
        $conversationFile 
    ); 
 
if ($conversationFile === '') { 
 
    header( 
        'Location: chat.php' 
    ); 
 
    exit; 
} 
 
$conversationPath = 
    $senderFolder . 
    '/' . 
    $conversationFile; 
 
if ( 
    !is_file($conversationPath) 
) { 
 
    header( 
        'Location: chat.php' 
    ); 
 
    exit; 
} 
 
$conversationData = 
    loadJson( 
        $conversationPath, 
        [] 
    ); 
 
if ( 
    !is_array($conversationData) || 
    ($conversationData['type'] ?? '') 
    !== 'group' 
) { 
 
    header( 
        'Location: chat.php' 
    ); 
 
    exit; 
} 
 
$groupParticipants = 
    $conversationData['participants'] 
    ?? []; 
 
if ( 
    !isset( 
        $groupParticipants[ 
            $senderNumber 
        ] 
    ) 
) { 
 
    header( 
        'Location: chat.php' 
    ); 
 
    exit; 
} 
 
$groupName = 
    trim( 
        (string)( 
            $conversationData['group_name'] 
            ?? 'Group Chat' 
        ) 
    ); 
$_SESSION['group_conversation_file'] = 
    $conversationFile; 
 
$_SESSION['group_participants'] = 
    $groupParticipants; 
 
/* ========================================================= 
   GROUP PROFILE 
========================================================= */ 
 
$groupProfile = 
    trim( 
        (string)( 
            $conversationData['group_profiles'] 
            ?? '' 
        ) 
    ); 
 
if ($groupProfile === '') { 
 
    $possibleProfiles = [ 
        getDataDir() . 
        '/group_profiles/' . 
        $conversationFile . 
        '.jpg', 
 
        getDataDir() . 
        '/group_profiles/' . 
        $conversationFile . 
        '.png', 
 
        getDataDir() . 
        '/group_profiles/' . 
        $conversationFile . 
        '.webp' 
    ]; 
 
    foreach ( 
        $possibleProfiles 
        as $profile 
    ) { 
 
        if ( 
            is_file($profile) 
        ) { 
 
            $groupProfile = 
                str_replace( 
                    __DIR__ . '/', 
                    '', 
                    $profile 
                ); 
 
            break; 
        } 
    } 
} 
 
$conversationKey = 
    $conversationFile; 
 
/* ========================================================= 
   AJAX: GET USER INFO (NEW) 
========================================================= */ 
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_user_info') {
    $number = safeNumber($_GET['number'] ?? '');
    if ($number === '') {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'name' => '']);
        exit;
    }
    $users = loadUsers();
    if (isset($users[$number])) {
        $name = $users[$number]['name'] ?? '';
        header('Content-Type: application/json');
        echo json_encode(['exists' => true, 'name' => $name]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'name' => '']);
    }
    exit;
}

/* ========================================================= 
   AJAX: CONTACTS 
========================================================= */ 
if (isset($_GET['ajax']) && $_GET['ajax'] === 'contacts') {
    $contacts = [];
    $folder = $senderFolder;
    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !preg_match('/\.json$/i', $file)) continue;
            $filePath = $folder . '/' . $file;
            $data = loadJson($filePath, []);
            if (!is_array($data)) continue;
            $participants = $data['participants'] ?? [];
            foreach ($participants as $number => $name) {
                $number = safeNumber($number);
                if ($number === '' || (string)$number === (string)$senderNumber) continue;
                $name = trim((string)$name);
                if ($name === '') $name = $number;
                if (!isset($contacts[$number])) {
                    $contacts[$number] = ['number' => $number, 'name' => $name];
                }
            }
        }
    }
    $contactList = array_values($contacts);
    usort($contactList, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'contacts' => $contactList]);
    exit;
}

/* ========================================================= 
   AJAX: PRESENCE 
========================================================= */ 
 
if ( 
    isset($_GET['ajax']) && 
    $_GET['ajax'] === 'presence' 
) { 
 
    updatePresence( 
        $senderNumber, 
        $senderName, 
        'group', 
        $conversationKey 
    ); 
 
    $statuses = []; 
 
    foreach ( 
        $groupParticipants 
        as $number => $memberName 
    ) { 
 
        $number = 
            safeNumber($number); 
 
        $statuses[$number] = 
            getPresenceStatus( 
                $number, 
                'group', 
                $conversationKey 
            ); 
    } 
 
    header( 
        'Content-Type: application/json' 
    ); 
 
    echo json_encode([ 
        'ok' => true, 
        'statuses' => $statuses 
    ]); 
 
    exit; 
} 
 
/* ========================================================= 
   AJAX: TYPING 
========================================================= */ 
 
if ( 
    isset($_GET['ajax']) && 
    $_GET['ajax'] === 'typing' 
) { 
 
    $typing = 
        loadTyping(); 
 
    $isTyping = 
        ($_GET['typing'] ?? '0') 
        === '1'; 
 
    updateTypingState( 
        $typing, 
        $conversationKey, 
        $senderNumber, 
        $senderName, 
        $isTyping 
    ); 
 
    saveTyping($typing); 
 
    header( 
        'Content-Type: application/json' 
    ); 
 
    echo json_encode([ 
        'ok' => true 
    ]); 
 
    exit; 
} 
 
/* ========================================================= 
   AJAX: TYPING STATUS 
========================================================= */ 
 
if ( 
    isset($_GET['ajax']) && 
    $_GET['ajax'] === 'typing_status' 
) { 
 
    $typing = 
        loadTyping(); 
 
    $status = 
        getTypingStatus( 
            $typing, 
            $conversationKey, 
            $senderNumber 
        ); 
 
    header( 
        'Content-Type: application/json' 
    ); 
 
    echo json_encode([ 
        'status' => $status 
    ]); 
 
    exit; 
} 
 
/* ========================================================= 
   AJAX: GROUP STATE 
========================================================= */ 
 
if ( 
    isset($_GET['ajax']) && 
    $_GET['ajax'] === 'group_state' 
) { 
 
    $freshData = 
        loadJson( 
            $conversationPath, 
            [] 
        ); 
 
    if ( 
        !is_array($freshData) 
    ) { 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => false, 
            'error' => 
                'Could not load group data.' 
        ]); 
 
        exit; 
    } 
 
    $freshParticipants = 
        $freshData['participants'] 
        ?? []; 
 
    $freshGroupName = 
        trim( 
            (string)( 
                $freshData['group_name'] 
                ?? 'Group Chat' 
            ) 
        ); 
 
    $freshProfile = 
        trim( 
            (string)( 
                $freshData['group_profiles'] 
                ?? '' 
            ) 
        ); 
 
    header( 
        'Content-Type: application/json' 
    ); 
 
    echo json_encode([ 
        'ok' => true, 
        'group_name' => 
            $freshGroupName, 
        'group_profiles' => 
            $freshProfile, 
        'participants' => 
            $freshParticipants, 
        'member_count' => 
            count( 
                $freshParticipants 
            ) 
    ]); 
 
    exit; 
} 
 
/* ========================================================= 
   AJAX: MESSAGES 
========================================================= */ 
 
if ( 
    isset($_GET['ajax']) && 
    $_GET['ajax'] === 'messages' 
) { 
 
    $freshConversationData = 
        loadJson( 
            $conversationPath, 
            [] 
        ); 
 
    if ( 
        !is_array( 
            $freshConversationData 
        ) 
    ) { 
 
        $freshConversationData = [ 
            'type' => 
                'group', 
            'participants' => 
                $groupParticipants, 
            'messages' => 
                [] 
        ]; 
    } 
 
    $allMessages = 
        $freshConversationData['messages'] 
        ?? []; 
 
    $lastId = 
        trim( 
            $_GET['last_id'] 
            ?? '' 
        ); 
 
    if ($lastId === '') { 
 
        $html = 
            renderGroupMessagesMarkup( 
                $allMessages, 
                $senderNumber 
            ); 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'mode' => 'full', 
            'html' => $html, 
            'last_id' => 
                !empty($allMessages) 
                ? 
                ( 
                    end($allMessages)['id'] 
                    ?? '' 
                ) 
                : 
                '' 
        ]); 
 
        exit; 
    } 
 
    $lastIndex = -1; 
 
    foreach ( 
        $allMessages 
        as $index => $message 
    ) { 
 
        if ( 
            (string)( 
                $message['id'] 
                ?? '' 
            ) 
            === 
            (string)$lastId 
        ) { 
 
            $lastIndex = 
                $index; 
 
            break; 
        } 
    } 
 
    if ($lastIndex === -1) { 
 
        $html = 
            renderGroupMessagesMarkup( 
                $allMessages, 
                $senderNumber 
            ); 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'mode' => 'full', 
            'html' => $html, 
            'last_id' => 
                !empty($allMessages) 
                ? 
                ( 
                    end($allMessages)['id'] 
                    ?? '' 
                ) 
                : 
                '' 
        ]); 
 
        exit; 
    } 
 
    $newMessages = 
        array_slice( 
            $allMessages, 
            $lastIndex + 1 
        ); 
 
    $previousDate = null; 
 
    if ($lastIndex >= 0) { 
 
        $previous = 
            $allMessages[ 
                $lastIndex 
            ]; 
 
        if ( 
            !empty( 
                $previous['date'] 
            ) 
        ) { 
 
            $previousDate = 
                date( 
                    'Y-m-d', 
                    strtotime( 
                        $previous['date'] 
                    ) 
                ); 
        } 
    } 
 
    $newHtml = 
        renderGroupMessagesMarkup( 
            $newMessages, 
            $senderNumber, 
            $previousDate 
        ); 
 
    header( 
        'Content-Type: application/json' 
    ); 
 
    echo json_encode([ 
        'ok' => true, 
        'mode' => 'new', 
        'html' => $newHtml, 
        'last_id' => 
            !empty($allMessages) 
            ? 
            ( 
                end($allMessages)['id'] 
                ?? '' 
            ) 
            : 
            '' 
    ]); 
 
    exit; 
} 
 
/* ========================================================= 
   POST HANDLERS 
========================================================= */ 
 
if ( 
    $_SERVER['REQUEST_METHOD'] 
    === 'POST' 
) { 
 
    $action = 
        $_POST['action'] 
        ?? ''; 
 
    /* ===================================================== 
       SEND MESSAGE 
    ===================================================== */ 
 
    if ( 
        $action === 'send' 
    ) { 
 
        $existingData = 
            loadJson( 
                $conversationPath, 
                [] 
            ); 
 
        if ( 
            !is_array($existingData) 
        ) { 
 
            $existingData = [ 
                'type' => 
                    'group', 
                'group_name' => 
                    $groupName, 
                'participants' => 
                    $groupParticipants, 
                'group_profiles' => 
                    $groupProfile, 
                'messages' => 
                    [] 
            ]; 
        } 
 
        $currentParticipants = 
            $existingData['participants'] 
            ?? $groupParticipants; 
 
        $currentGroupName = 
            trim( 
                (string)( 
                    $existingData['group_name'] 
                    ?? $groupName 
                ) 
            ); 
 
        $currentGroupProfile = 
            trim( 
                (string)( 
                    $existingData['group_profiles'] 
                    ?? $groupProfile 
                ) 
            ); 
 
        $message = 
            trim( 
                $_POST['message'] 
                ?? '' 
            ); 
 
        $attachments = []; 
 
        if ( 
            isset( 
                $_FILES['attachment'] 
            ) && 
            is_array( 
                $_FILES['attachment']['name'] 
            ) 
        ) { 
 
            $count = 
                count( 
                    $_FILES['attachment']['name'] 
                ); 
 
            for ( 
                $i = 0; 
                $i < $count; 
                $i++ 
            ) { 
 
                if ( 
                    empty( 
                        $_FILES['attachment']['name'][$i] 
                    ) 
                ) { 
                    continue; 
                } 
 
                $single = [ 
                    'name' => 
                        $_FILES['attachment']['name'][$i], 
 
                    'type' => 
                        $_FILES['attachment']['type'][$i], 
 
                    'tmp_name' => 
                        $_FILES['attachment']['tmp_name'][$i], 
 
                    'error' => 
                        $_FILES['attachment']['error'][$i], 
 
                    'size' => 
                        $_FILES['attachment']['size'][$i] 
                ]; 
 
                $result = 
                    saveUploadedFile( 
                        $single, 
                        $conversationFile 
                    ); 
 
                if ( 
                    !$result['ok'] 
                ) { 
 
                    header( 
                        'Content-Type: application/json' 
                    ); 
 
                    echo json_encode([ 
                        'ok' => false, 
                        'error' => 
                            $result['error'] 
                    ]); 
 
                    exit; 
                } 
 
                $attachments[] = 
                    $result; 
            } 
        } 
 
        if ( 
            $message === '' && 
            empty($attachments) 
        ) { 
 
            header( 
                'Content-Type: application/json' 
            ); 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Message is empty.' 
            ]); 
 
            exit; 
        } 
 
        $existingMessages = 
            $existingData['messages'] 
            ?? []; 
 
        $newMessage = [ 
            'id' => 
                uniqid( 
                    'msg_', 
                    true 
                ), 
 
            'sender_number' => 
                $senderNumber, 
 
            'sender_name' => 
                $senderName, 
 
            'message' => 
                $message, 
 
            'time' => 
                date('H:i:s'), 
 
            'date' => 
                date('Y-m-d'), 
 
            'attachments' => 
                $attachments 
        ]; 
 
        foreach ( 
            $currentParticipants 
            as $memberNumber => $memberName 
        ) { 
 
            $memberNumber = 
                safeNumber( 
                    $memberNumber 
                ); 
 
            if ( 
                $memberNumber === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $memberNumber 
                ); 
 
            $memberFile = 
                $memberFolder . 
                '/' . 
                $conversationFile; 
 
            $memberData = 
                loadJson( 
                    $memberFile, 
                    [] 
                ); 
 
            if ( 
                !is_array( 
                    $memberData 
                ) 
            ) { 
 
                $memberData = [ 
                    'type' => 
                        'group', 
                    'participants' => 
                        $currentParticipants, 
                    'messages' => 
                        [] 
                ]; 
            } 
 
            $memberData['type'] = 
                'group'; 
 
            $memberData['group_name'] = 
                $currentGroupName; 
 
            $memberData['participants'] = 
                $currentParticipants; 
 
            $memberData['group_profiles'] = 
                $currentGroupProfile; 
 
            $memberData['messages'] = 
                $memberData['messages'] 
                ?? []; 
 
            $memberData['messages'][] = 
                $newMessage; 
 
            saveJson( 
                $memberFile, 
                $memberData 
            ); 
        } 
 
        $existingData['type'] = 
            'group'; 
 
        $existingData['group_name'] = 
            $currentGroupName; 
 
        $existingData['participants'] = 
            $currentParticipants; 
 
        $existingData['group_profiles'] = 
            $currentGroupProfile; 
 
        $existingData['messages'] = 
            $existingMessages; 
 
        $existingData['messages'][] = 
            $newMessage; 
 
        saveJson( 
            $conversationPath, 
            $existingData 
        ); 
 
        $typing = 
            loadTyping(); 
 
        updateTypingState( 
            $typing, 
            $conversationKey, 
            $senderNumber, 
            $senderName, 
            false 
        ); 
 
        saveTyping($typing); 
 
        $previousDate = null; 
 
        if ( 
            !empty( 
                $existingMessages 
            ) 
        ) { 
 
            $last = 
                end( 
                    $existingMessages 
                ); 
 
            if ( 
                !empty( 
                    $last['date'] 
                ) 
            ) { 
 
                $previousDate = 
                    date( 
                        'Y-m-d', 
                        strtotime( 
                            $last['date'] 
                        ) 
                    ); 
            } 
        } 
 
        $newHtml = 
            renderGroupMessagesMarkup( 
                [$newMessage], 
                $senderNumber, 
                $previousDate 
            ); 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'html' => 
                $newHtml, 
            'last_id' => 
                $newMessage['id'] 
        ]); 
 
        exit; 
    } 
 
    /* ===================================================== 
       DELETE MESSAGE 
    ===================================================== */ 
 
    if ( 
        $action === 'delete_message' 
    ) { 
 
        $deleteId = 
            trim( 
                $_POST['message_id'] 
                ?? '' 
            ); 
 
        if ( 
            $deleteId === '' 
        ) { 
 
            header( 
                'Content-Type: application/json' 
            ); 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Invalid message.' 
            ]); 
 
            exit; 
        } 
 
        foreach ( 
            $groupParticipants 
            as $memberNumber => $memberName 
        ) { 
 
            $memberNumber = 
                safeNumber( 
                    $memberNumber 
                ); 
 
            if ( 
                $memberNumber === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $memberNumber 
                ); 
 
            $memberFile = 
                $memberFolder . 
                '/' . 
                $conversationFile; 
 
            $memberData = 
                loadJson( 
                    $memberFile, 
                    [] 
                ); 
 
            $memberMessages = 
                $memberData['messages'] 
                ?? []; 
 
            $memberData['messages'] = 
                array_values( 
                    array_filter( 
                        $memberMessages, 
                        function ($m) 
                        use ($deleteId) { 
 
                            return 
                                (string)( 
                                    $m['id'] 
                                    ?? '' 
                                ) 
                                !== 
                                (string)$deleteId; 
                        } 
                    ) 
                ); 
 
            saveJson( 
                $memberFile, 
                $memberData 
            ); 
        } 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'deleted_id' => 
                $deleteId 
        ]); 
 
        exit; 
    } 
 
    /* ===================================================== 
       ADD MEMBER 
    ===================================================== */ 
 
    if ( 
        $action === 'add_member' 
    ) { 
 
        $memberName = 
            trim( 
                $_POST['member_name'] 
                ?? '' 
            ); 
 
        $memberNumber = 
            safeNumber( 
                $_POST['member_number'] 
                ?? '' 
            ); 
 
        if ( 
            $memberName === '' || 
            $memberNumber === '' 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Enter member name and number.' 
            ]); 
 
            exit; 
        } 
 
        if ( 
            !isset( 
                $users[$memberNumber] 
            ) 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'This number is not registered.' 
            ]); 
 
            exit; 
        } 
 
        if ( 
            isset( 
                $groupParticipants[ 
                    $memberNumber 
                ] 
            ) 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Member already exists.' 
            ]); 
 
            exit; 
        } 
 
        $groupParticipants[ 
            $memberNumber 
        ] = 
            $users[ 
                $memberNumber 
            ]['name'] 
            ?? 
            $memberName; 
 
        $newFile = 
            buildGroupConversationFileName( 
                $senderNumber, 
                array_keys( 
                    $groupParticipants 
                ) 
            ); 
 
        $latestData = 
            loadJson( 
                $conversationPath, 
                [] 
            ); 
 
        $oldMessages = 
            $latestData['messages'] 
            ?? []; 
 
        foreach ( 
            $groupParticipants 
            as $number => $memberNameValue 
        ) { 
 
            $number = 
                safeNumber( 
                    $number 
                ); 
 
            if ( 
                $number === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $number 
                ); 
 
            $newPath = 
                $memberFolder . 
                '/' . 
                $newFile; 
 
            $newData = [ 
                'type' => 
                    'group', 
 
                'group_name' => 
                    $groupName, 
 
                'group_profiles' => 
                    $groupProfile, 
 
                'participants' => 
                    $groupParticipants, 
 
                'messages' => 
                    $oldMessages 
            ]; 
 
            saveJson( 
                $newPath, 
                $newData 
            ); 
        } 
 
        foreach ( 
            $groupParticipants 
            as $number => $memberNameValue 
        ) { 
 
            $number = 
                safeNumber( 
                    $number 
                ); 
 
            if ( 
                $number === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $number 
                ); 
 
            $oldPath = 
                $memberFolder . 
                '/' . 
                $conversationFile; 
 
            if ( 
                is_file($oldPath) && 
                basename($oldPath) 
                !== 
                $newFile 
            ) { 
 
                @unlink( 
                    $oldPath 
                ); 
            } 
        } 
 
        $_SESSION[ 
            'group_conversation_file' 
        ] = 
            $newFile; 
 
        $_SESSION[ 
            'group_participants' 
        ] = 
            $groupParticipants; 
 
        header( 
            'Content-Type: application/json' 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'conversation' => 
                $newFile, 
            'group_name' => 
                $groupName 
        ]); 
 
        exit; 
    } 
 
    /* ===================================================== 
       UPDATE MEMBER NAME (RENAME) 
    ===================================================== */ 
    if ($action === 'update_member_name') { 
        $targetNumber = safeNumber($_POST['member_number'] ?? ''); 
        $newName = trim($_POST['new_name'] ?? ''); 
 
        if ($targetNumber === '' || $newName === '') { 
            echo json_encode(['ok' => false, 'error' => 'Invalid data.']); 
            exit; 
        } 
 
        // Check if member exists 
        if (!isset($groupParticipants[$targetNumber])) { 
            echo json_encode(['ok' => false, 'error' => 'Member not found.']); 
            exit; 
        } 
 
        // Update all copies for every participant 
        foreach ($groupParticipants as $number => $name) { 
            $number = safeNumber($number); 
            if ($number === '') continue; 
            $memberFolder = buildUserStorageDirectory($number); 
            $memberFile = $memberFolder . '/' . $conversationFile; 
            $memberData = loadJson($memberFile, []); 
            if (is_array($memberData) && isset($memberData['participants'][$targetNumber])) { 
                $memberData['participants'][$targetNumber] = $newName; 
                saveJson($memberFile, $memberData); 
            } 
        } 
 
        // Also update the current file 
        $conversationData = loadJson($conversationPath, []); 
        if (is_array($conversationData) && isset($conversationData['participants'][$targetNumber])) { 
            $conversationData['participants'][$targetNumber] = $newName; 
            saveJson($conversationPath, $conversationData); 
        } 
 
        echo json_encode(['ok' => true, 'new_name' => $newName]); 
        exit; 
    } 
 
    /* ===================================================== 
       UPDATE MULTIPLE MEMBER NAMES (BATCH RENAME) 
    ===================================================== */ 
    if ($action === 'update_member_names') { 
        $updates = $_POST['updates'] ?? []; 
        if (!is_array($updates) || empty($updates)) { 
            echo json_encode(['ok' => false, 'error' => 'No updates provided.']); 
            exit; 
        } 
 
        $changed = false; 
        foreach ($updates as $number => $newName) { 
            $number = safeNumber($number); 
            $newName = trim($newName); 
            if ($number === '' || $newName === '') continue; 
            if (!isset($groupParticipants[$number])) continue; 
            if ($groupParticipants[$number] === $newName) continue; 
 
            // Update all copies for every participant 
            foreach ($groupParticipants as $memberNumber => $memberName) { 
                $memberNumber = safeNumber($memberNumber); 
                if ($memberNumber === '') continue; 
                $memberFolder = buildUserStorageDirectory($memberNumber); 
                $memberFile = $memberFolder . '/' . $conversationFile; 
                $memberData = loadJson($memberFile, []); 
                if (is_array($memberData) && isset($memberData['participants'][$number])) { 
                    $memberData['participants'][$number] = $newName; 
                    saveJson($memberFile, $memberData); 
                } 
            } 
 
            // Also update the current file 
            $conversationData = loadJson($conversationPath, []); 
            if (is_array($conversationData) && isset($conversationData['participants'][$number])) { 
                $conversationData['participants'][$number] = $newName; 
                saveJson($conversationPath, $conversationData); 
            } 
 
            $changed = true; 
        } 
 
        if (!$changed) { 
            echo json_encode(['ok' => false, 'error' => 'No changes were made.']); 
            exit; 
        } 
 
        // Reload participants from the main file 
        $freshData = loadJson($conversationPath, []); 
        $groupParticipants = $freshData['participants'] ?? $groupParticipants; 
        $_SESSION['group_participants'] = $groupParticipants; 
 
        echo json_encode(['ok' => true]); 
        exit; 
    } 
 
    /* ===================================================== 
       GROUP PROFILE 
    ===================================================== */ 
 
    if ( 
        $action === 'group_profile' 
    ) { 
 
        if (isset($_POST['remove']) && $_POST['remove'] === '1') { 
            foreach ( 
                $groupParticipants 
                as $number => $memberNameValue 
            ) { 
                $number = safeNumber($number); 
                if ($number === '') continue; 
                $memberFolder = buildUserStorageDirectory($number); 
                $memberFile = $memberFolder . '/' . $conversationFile; 
                $memberData = loadJson($memberFile, []); 
                if (is_array($memberData)) { 
                    unset($memberData['group_profiles']); 
                    saveJson($memberFile, $memberData); 
                } 
            } 
            $conversationData = loadJson($conversationPath, []); 
            unset($conversationData['group_profiles']); 
            saveJson($conversationPath, $conversationData); 
            echo json_encode(['ok' => true, 'profile' => '']); 
            exit; 
        } 
 
        if ( 
            empty( 
                $_FILES['group_profile'] 
            ) || 
            ( 
                $_FILES[ 
                    'group_profile' 
                ]['error'] 
                ?? 
                UPLOAD_ERR_NO_FILE 
            ) 
            !== 
            UPLOAD_ERR_OK 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Please select an image.' 
            ]); 
 
            exit; 
        } 
 
        $file = 
            $_FILES[ 
                'group_profile' 
            ]; 
 
        if ( 
            (int)$file['size'] 
            > 
            5 * 1024 * 1024 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Group profile image must be under 5 MB.' 
            ]); 
 
            exit; 
        } 
 
        $ext = 
            strtolower( 
                pathinfo( 
                    $file['name'], 
                    PATHINFO_EXTENSION 
                ) 
            ); 
 
        $allowed = [ 
            'jpg', 
            'jpeg', 
            'png', 
            'gif', 
            'webp' 
        ]; 
 
        if ( 
            !in_array( 
                $ext, 
                $allowed, 
                true 
            ) 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Invalid profile image.' 
            ]); 
 
            exit; 
        } 
 
        $profileDir = 
            getDataDir() . 
            '/group_profiles'; 
 
        if ( 
            !is_dir( 
                $profileDir 
            ) 
        ) { 
 
            mkdir( 
                $profileDir, 
                0777, 
                true 
            ); 
        } 
 
        $newProfileName = 
            $conversationFile . 
            '_' . 
            time() . 
            '_' . 
            bin2hex( 
                random_bytes(3) 
            ) . 
            '.' . 
            $ext; 
 
        $destination = 
            $profileDir . 
            '/' . 
            $newProfileName; 
 
        if ( 
            !move_uploaded_file( 
                $file['tmp_name'], 
                $destination 
            ) 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Could not save group image.' 
            ]); 
 
            exit; 
        } 
 
        $groupProfile = 
            'data/group_profiles/' . 
            $newProfileName; 
 
        foreach ( 
            $groupParticipants 
            as $number => $memberNameValue 
        ) { 
 
            $number = 
                safeNumber( 
                    $number 
                ); 
 
            if ( 
                $number === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $number 
                ); 
 
            $memberFile = 
                $memberFolder . 
                '/' . 
                $conversationFile; 
 
            $memberData = 
                loadJson( 
                    $memberFile, 
                    [] 
                ); 
 
            $memberData['type'] = 
                'group'; 
 
            $memberData['group_name'] = 
                $groupName; 
 
            $memberData['group_profiles'] = 
                $groupProfile; 
 
            $memberData['participants'] = 
                $groupParticipants; 
 
            $memberData['messages'] = 
                $memberData['messages'] 
                ?? []; 
 
            saveJson( 
                $memberFile, 
                $memberData 
            ); 
        } 
 
        $conversationData = 
            loadJson( 
                $conversationPath, 
                [] 
            ); 
 
        $conversationData[ 
            'group_profiles' 
        ] = 
            $groupProfile; 
 
        saveJson( 
            $conversationPath, 
            $conversationData 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'profile' => 
                $groupProfile 
        ]); 
 
        exit; 
    } 
 
    /* ===================================================== 
       UPDATE GROUP NAME 
    ===================================================== */ 
 
    if ( 
        $action === 'update_group_name' 
    ) { 
 
        $newGroupName = 
            trim( 
                $_POST['group_name'] 
                ?? '' 
            ); 
 
        if ( 
            $newGroupName === '' 
        ) { 
 
            echo json_encode([ 
                'ok' => false, 
                'error' => 
                    'Group name cannot be empty.' 
            ]); 
 
            exit; 
        } 
 
        $groupName = 
            $newGroupName; 
 
        foreach ( 
            $groupParticipants 
            as $number => $memberName 
        ) { 
 
            $number = 
                safeNumber( 
                    $number 
                ); 
 
            if ( 
                $number === '' 
            ) { 
                continue; 
            } 
 
            $memberFolder = 
                buildUserStorageDirectory( 
                    $number 
                ); 
 
            $memberFile = 
                $memberFolder . 
                '/' . 
                $conversationFile; 
 
            $memberData = 
                loadJson( 
                    $memberFile, 
                    [] 
                ); 
 
            if ( 
                !is_array( 
                    $memberData 
                ) 
            ) { 
 
                $memberData = [ 
                    'type' => 
                        'group', 
                    'participants' => 
                        $groupParticipants, 
                    'messages' => 
                        [] 
                ]; 
            } 
 
            $memberData['type'] = 
                'group'; 
 
            $memberData['group_name'] = 
                $groupName; 
 
            $memberData['participants'] = 
                $groupParticipants; 
 
            $memberData['group_profiles'] = 
                $groupProfile; 
 
            $memberData['messages'] = 
                $memberData['messages'] 
                ?? []; 
 
            saveJson( 
                $memberFile, 
                $memberData 
            ); 
        } 
 
        $freshCurrentData = 
            loadJson( 
                $conversationPath, 
                [] 
            ); 
 
        if ( 
            !is_array( 
                $freshCurrentData 
            ) 
        ) { 
 
            $freshCurrentData = []; 
        } 
 
        $freshCurrentData[ 
            'type' 
        ] = 
            'group'; 
 
        $freshCurrentData[ 
            'group_name' 
        ] = 
            $groupName; 
 
        $freshCurrentData[ 
            'participants' 
        ] = 
            $groupParticipants; 
 
        $freshCurrentData[ 
            'group_profiles' 
        ] = 
            $groupProfile; 
 
        $freshCurrentData[ 
            'messages' 
        ] = 
            $freshCurrentData[ 
                'messages' 
            ] 
            ?? []; 
 
        saveJson( 
            $conversationPath, 
            $freshCurrentData 
        ); 
 
        echo json_encode([ 
            'ok' => true, 
            'group_name' => 
                $groupName 
        ]); 
 
        exit; 
    } 
} 
 
/* ========================================================= 
   INITIAL DATA 
========================================================= */ 
 
$conversationData = 
    loadJson( 
        $conversationPath, 
        [] 
    ); 
 
$messages = 
    $conversationData[ 
        'messages' 
    ] 
    ?? []; 
 
$messagesMarkup = 
    renderGroupMessagesMarkup( 
        $messages, 
        $senderNumber 
    ); 
 
$typing = 
    loadTyping(); 
 
$typingText = 
    getTypingStatus( 
        $typing, 
        $conversationKey, 
        $senderNumber 
    ); 
 
$initialStatuses = []; 
 
foreach ( 
    $groupParticipants 
    as $number => $memberName 
) { 
 
    $number = 
        safeNumber($number); 
 
    $initialStatuses[$number] = 
        getPresenceStatus( 
            $number, 
            'group', 
            $conversationKey 
        ); 
} 
 
$lastMessageId = ''; 
 
if ( 
    !empty($messages) 
) { 
 
    $lastMessage = 
        end($messages); 
 
    $lastMessageId = 
        $lastMessage['id'] 
        ?? ''; 
} 
 
updatePresence( 
    $senderNumber, 
    $senderName, 
    'group', 
    $conversationKey 
); 
 
?> 
 
<!DOCTYPE html> 
 
<html lang="en"> 
<head> 
 
<meta charset="UTF-8"> 
 
<meta 
name="viewport" 
content="width=device-width, initial-scale=1.0" 
 
> 
 
<title><?= esc($groupName) ?></title> 
 
<link 
    rel="icon" 
    type="image/png" 
    href="../images/hhh%20picture.png" 
> 
 
<style> 
 
* { 
    box-sizing: border-box; 
} 
 
html, 
body { 
    margin: 0; 
    width: 100%; 
    height: 100%; 
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
    background: #eef2f7; 
    overflow: hidden; 
} 
 
.chat-app { 
    width: 100%; 
    height: 100vh; 
    display: flex; 
    flex-direction: column; 
    background: #fff; 
} 
 
.chat-header { 
    height: 72px; 
    min-height: 72px; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 10px 16px; 
    background: #fff; 
    border-bottom: 1px solid #e6eaf0; 
    box-shadow: 0 2px 12px rgba(0,0,0,.04); 
    z-index: 20; 
    position: fixed;
    width: 100%;
} 
 
.back { 
    width: 42px; 
    height: 42px; 
    border: 0; 
    background: #f3f6fa; 
    border-radius: 50%; 
    font-size: 21px; 
    cursor: pointer; 
    transition: background 0.2s; 
} 
.back:hover { 
    background: #e2e8f0; 
} 
 
.profile { 
    width: 46px; 
    height: 46px; 
    min-width: 46px; 
    border-radius: 50%; 
    overflow: hidden; 
    background: #dbeafe; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    font-weight: 700; 
    color: #2563eb; 
    cursor: pointer; 
    position: relative; 
} 
.profile img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
} 
 
.header-info { 
    flex: 1; 
    min-width: 0; 
    cursor: pointer; 
} 
.header-info strong { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    font-size: 16px; 
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
} 
.header-info strong .edit-icon { 
    font-size: 14px; 
    opacity: 0.5; 
    transition: opacity 0.2s; 
} 
.header-info strong:hover .edit-icon { 
    opacity: 1; 
} 
.status { 
    font-size: 12px; 
    color: #64748b; 
    margin-top: 3px; 
} 
.status.online { 
    color: #16a34a; 
} 
.status.typing { 
    color: #2563eb; 
    font-weight: 600; 
} 
 
/* ---------- MENU ---------- */ 
.menu-wrapper { 
    position: relative; 
} 
.menu-btn { 
    width: 42px; 
    height: 42px; 
    border: 0; 
    border-radius: 50%; 
    background: #f3f6fa; 
    font-size: 24px; 
    cursor: pointer; 
    transition: background 0.2s; 
} 
.menu-btn:hover { 
    background: #e2e8f0; 
} 
.dropdown-menu { 
    position: absolute; 
    right: 0; 
    top: 50px; 
    min-width: 200px; 
    background: #fff; 
    border: 1px solid #e2e8f0; 
    border-radius: 14px; 
    box-shadow: 0 15px 40px rgba(0,0,0,.15); 
    display: none; 
    z-index: 200; 
    overflow: hidden; 
    padding: 8px 0; 
} 
.dropdown-menu.show { 
    display: block; 
} 
.dropdown-menu .menu-item { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 12px 18px; 
    border: 0; 
    background: transparent; 
    width: 100%; 
    text-align: left; 
    font-size: 14px; 
    cursor: pointer; 
    transition: background 0.15s; 
    color: #1e293b; 
} 
.dropdown-menu .menu-item:hover { 
    background: #f1f5f9; 
} 
.dropdown-menu .menu-item .icon { 
    font-size: 18px; 
} 
 
.header-search { 
    position: relative; 
} 
.search-button { 
    width: 40px; 
    height: 40px; 
    border: 0; 
    border-radius: 50%; 
    background: #f1f5f9; 
    cursor: pointer; 
    font-size: 18px; 
    transition: background 0.2s; 
} 
.search-button:hover { 
    background: #e2e8f0; 
} 
.search-box { 
    display: none; 
    position: absolute; 
    right: 0; 
    top: 48px; 
    width: 280px; 
    padding: 12px; 
    background: #fff; 
    border: 1px solid #e2e8f0; 
    border-radius: 12px; 
    box-shadow: 0 12px 30px rgba(0,0,0,.15); 
    z-index: 100; 
} 
.search-box.show { 
    display: block; 
} 
.search-box input { 
    width: 100%; 
    padding: 10px 12px; 
    border: 1px solid #dbe2ea; 
    border-radius: 9px; 
    outline: none; 
    font-size: 14px; 
} 
 
.messages { 
    position: relative;
    flex: 1; 
    overflow-y: auto; 
    padding: 20px clamp(10px,5vw,80px); 
    background: radial-gradient( 
        circle at top, 
        #f8fbff, 
        #edf2f7 
    ); 
} 
 
.date-separator { 
    position: sticky; 
    top: 60px; 
    z-index: 5; 
    width: max-content; 
    margin: 12px auto; 
    padding: 6px 16px; 
    border-radius: 20px; 
    background: rgba(255,255,255,.92); 
    backdrop-filter: blur(4px); 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 2px 8px rgba(15,23,42,.08); 
    color: #64748b; 
    font-size: 11px; 
    font-weight: 600; 
} 
 
.message-row { 
    display: flex; 
    margin-bottom: 10px; 
} 
.message-row.mine { 
    justify-content: flex-end; 
} 
 
.bubble { 
    position: relative; 
    max-width: min(75%,520px); 
    padding: 9px 38px 9px 12px; 
    border-radius: 15px; 
    background: #fff; 
    border: 1px solid #e3e8ef; 
    box-shadow: 0 2px 8px rgba(15,23,42,.05); 
} 
.mine .bubble { 
    background: #dbeafe; 
    border-color: #bfdbfe; 
} 
 
.sender-name { 
    font-size: 12px; 
    font-weight: 600; 
    color: #2563eb; 
    margin-bottom: 3px; 
} 
 
.message-text { 
    white-space: pre-wrap; 
    word-break: break-word; 
    font-size: 14px; 
    line-height: 1.45; 
} 
.message-text a { 
    color: #2563eb; 
    text-decoration: underline; 
} 
 
.message-time { 
    font-size: 10px; 
    color: #64748b; 
    margin-top: 5px; 
    text-align: right; 
} 
 
.message-menu-btn { 
    position: absolute; 
    right: 5px; 
    top: 5px; 
    width: 26px; 
    height: 26px; 
    border: 0; 
    background: transparent; 
    border-radius: 50%; 
    cursor: pointer; 
    color: #64748b; 
    font-size: 17px; 
} 
.message-menu-btn:hover { 
    background: rgba(0,0,0,.06); 
} 
 
.message-menu { 
    position: absolute; 
    right: 7px; 
    top: 34px; 
    width: 180px; 
    background: #fff; 
    border: 1px solid #e2e8f0; 
    border-radius: 10px; 
    box-shadow: 0 12px 30px rgba(15,23,42,.18); 
    display: none; 
    z-index: 50; 
    overflow: hidden; 
} 
.message-menu.show { 
    display: block; 
} 
.message-menu button { 
    width: 100%; 
    padding: 10px 14px; 
    text-align: left; 
    border: 0; 
    background: #fff; 
    cursor: pointer; 
    font-size: 13px; 
    transition: background 0.15s; 
} 
.message-menu button:hover { 
    background: #f1f5f9; 
} 
.message-menu .danger { 
    color: #dc2626; 
} 
 
.attachment { 
    margin-top: 2px; 
} 
.attachment img { 
    max-width: 300px; 
    max-height: 350px; 
    display: block; 
    border-radius: 11px; 
    cursor: pointer; 
} 
.attachment video { 
    max-width: 340px; 
    max-height: 350px; 
    border-radius: 11px; 
} 
.attachment audio { 
    width: 280px; 
    max-width: 100%; 
} 
.document { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    padding: 10px; 
    border-radius: 10px; 
    background: #f8fafc; 
    border: 1px solid #e2e8f0; 
    text-decoration: none; 
    color: #111827; 
} 
.document-icon { 
    width: 38px; 
    height: 38px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    background: #e2e8f0; 
    border-radius: 9px; 
    font-size: 19px; 
} 
.document-name { 
    max-width: 220px; 
    overflow: hidden; 
    white-space: nowrap; 
    text-overflow: ellipsis; 
    font-size: 13px; 
} 
.attachment-actions {
    margin-top: 6px;
}

.download-file {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    border-radius: 8px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.download-file:hover {
    background: #1d4ed8;
}


.attachment-meta { 
    display: flex; 
    justify-content: space-between; 
    gap: 10px; 
    margin-top: 5px; 
    font-size: 11px; 
    color: #64748b; 
} 
.file-name { 
    overflow: hidden; 
    text-overflow: ellipsis; 
    white-space: nowrap; 
} 
.file-size { 
    flex-shrink: 0; 
} 

/* Last message tag */
.last-message-tag {
    position: sticky;
    left: 90%;
    bottom: 18px;
    z-index: 15;
    display: none;
    border: 1px solid #dbe2ea;
    background: rgba(255,255,255,.96);
    color: #2563eb;
    border-radius: 22px;
    padding: 9px 14px;
    box-shadow: 0 6px 20px rgba(15,23,42,.16);
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    top: 90%;
}
.last-message-tag.show {
    display: block;
}
.last-message-tag:hover {
    background: #eff6ff;
}
 
.composer { 
    display: flex; 
    align-items: flex-end; 
    gap: 8px; 
    padding: 10px 14px; 
    background: #fff; 
    border-top: 1px solid #e5e7eb; 
} 
.attach-label { 
    width: 42px; 
    height: 42px; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    background: #f1f5f9; 
    border-radius: 50%; 
    cursor: pointer; 
    font-size: 20px; 
    transition: background 0.2s; 
} 
.attach-label:hover { 
    background: #e2e8f0; 
} 
#attachment { 
    display: none; 
} 
#message { 
    flex: 1; 
    field-sizing:content;
    resize: none; 
    min-height: 42px; 
    max-height: 130px; 
    padding: 11px 14px; 
    border: 1px solid #d8dee8; 
    border-radius: 22px; 
    outline: none; 
    font: inherit; 
    transition: border-color 0.2s; 
} 
#message:focus { 
    border-color: #2563eb; 
} 
.send { 
    width: 44px; 
    height: 44px; 
    border: 0; 
    border-radius: 50%; 
    background: #2563eb; 
    color: #fff; 
    font-size: 18px; 
    cursor: pointer; 
    transition: background 0.2s; 
} 
.send:hover { 
    background: #1d4ed8; 
} 
.send:disabled { 
    opacity: .55; 
    cursor: not-allowed; 
} 
 
.empty { 
    height: 100%; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    color: #64748b; 
    text-align: center; 
} 
 
.modal { 
    display: none; 
    position: fixed; 
    inset: 0; 
    background: rgba(0,0,0,.65); 
    backdrop-filter: blur(4px); 
    z-index: 1000; 
    align-items: center; 
    justify-content: center; 
    padding: 20px; 
} 
.modal.show { 
    display: flex; 
} 
.modal-content { 
    position: relative; 
    background: #fff; 
    border-radius: 20px; 
    max-width: 520px; 
    width: 100%; 
    padding: 28px; 
    max-height: 90vh; 
    overflow-y: auto; 
    box-shadow: 0 20px 60px rgba(0,0,0,.3); 
} 
.modal-close { 
    position: absolute; 
    right: 12px; 
    top: 12px; 
    width: 36px; 
    height: 36px; 
    border: 0; 
    border-radius: 50%; 
    background: #f1f5f9; 
    cursor: pointer; 
    font-size: 20px; 
    transition: background 0.2s; 
} 
.modal-close:hover { 
    background: #e2e8f0; 
} 
 
.big-profile { 
    width: 200px; 
    height: 200px; 
    max-width: 80vw; 
    max-height: 70vh; 
    margin: 10px auto; 
    border-radius: 50%; 
    overflow: hidden; 
    background: #dbeafe; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 80px; 
    color: #2563eb; 
    border: 4px solid #e2e8f0; 
} 
.big-profile img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
    cursor: pointer; 
} 
 
.info-row { 
    padding: 9px 0; 
    border-bottom: 1px solid #e5e7eb; 
} 
.info-label { 
    color: #64748b; 
    font-size: 12px; 
} 
.info-value { 
    font-size: 14px; 
    margin-top: 3px; 
    word-break: break-word; 
} 
 
.member-list { 
    max-height: 250px; 
    overflow-y: auto; 
    margin-top: 8px; 
} 
.member-item { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 8px 0; 
    border-bottom: 1px solid #f1f5f9; 
} 
.member-item:last-child { 
    border-bottom: 0; 
} 
.member-item .avatar { 
    width: 36px; 
    height: 36px; 
    border-radius: 50%; 
    background: #e2e8f0; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 700; 
    color: #2563eb; 
    overflow: hidden; 
} 
.member-item .avatar img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
    border-radius: 50%; 
} 
.member-item .info { 
    flex: 1; 
} 
.member-item .info input { 
    width: 100%; 
    padding: 4px 8px; 
    border: 1px solid #dbe2ea; 
    border-radius: 6px; 
    font-size: 14px; 
    background: transparent; 
    transition: border-color 0.2s, background 0.2s; 
} 
.member-item .info input:focus { 
    border-color: #2563eb; 
    background: #fff; 
    outline: none; 
} 
.member-item .info span { 
    font-size: 12px; 
    color: #64748b; 
    display: block; 
    margin-top: 2px; 
} 
.member-item .status-dot { 
    width: 10px; 
    height: 10px; 
    border-radius: 50%; 
    background: #94a3b8; 
    flex-shrink: 0; 
} 
.member-item .status-dot.online { 
    background: #22c55e; 
} 
.member-item .rename-btn { 
    background: transparent; 
    border: none; 
    cursor: pointer; 
    font-size: 14px; 
    color: #2563eb; 
    padding: 0 6px; 
} 
.member-item .rename-btn:hover { 
    color: #1d4ed8; 
} 
 
/* Contacts list for adding members */ 
.contacts-list { 
    margin: 8px 0; 
    padding: 0; 
    list-style: none; 
    max-height: 200px; 
    overflow-y: auto; 
    border: 1px solid #e5eaf0; 
    border-radius: 10px; 
} 
.contacts-list li { 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    padding: 8px 12px; 
    border-bottom: 1px solid #f1f5f9; 
    font-size: 13px; 
} 
.contacts-list li:last-child { 
    border-bottom: none; 
} 
.contacts-list .contact-add-btn { 
    padding: 4px 12px; 
    background: #2563eb; 
    color: white; 
    border: none; 
    border-radius: 8px; 
    cursor: pointer; 
    font-size: 12px; 
} 
.contacts-list .contact-add-btn:disabled { 
    background: #94a3b8; 
    cursor: not-allowed; 
} 
.contacts-list .contact-add-btn.added { 
    background: #22c55e; 
} 
.contacts-list .no-contacts { 
    padding: 12px; 
    color: #94a3b8; 
    text-align: center; 
    font-style: italic; 
} 
 

.add-member-input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    outline: none;
    font-size: 15px;
    margin: 8px 0 10px;
}
.add-member-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}
.add-member-help {
    display: block;
    margin: 0 0 10px;
    color: #64748b;
    font-size: 12px;
}
.add-member-result {
    min-height: 20px;
    margin: 4px 0 10px;
    font-size: 13px;
}
.add-member-result.success { color: #16a34a; }
.add-member-result.error { color: #dc2626; }
.add-member-submit {
    width: 100%;
    border: 0;
    border-radius: 9px;
    padding: 11px 14px;
    background: #2563eb;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.add-member-submit:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.user-search-input { 
    width: 100%; 
    padding: 10px; 
    border: 1px solid #dbe2ea; 
    border-radius: 9px; 
    outline: none; 
    margin-bottom: 10px; 
    font-size: 14px; 
} 
 
.profile-preview { 
    margin: 10px 0; 
    text-align: center; 
} 
.profile-preview img { 
    max-width: 120px; 
    max-height: 120px; 
    border-radius: 50%; 
    border: 3px solid #e2e8f0; 
} 
 
.btn-group { 
    display: flex; 
    gap: 8px; 
    margin-top: 10px; 
} 
.btn-group button { 
    flex: 1; 
    padding: 10px; 
    border: 0; 
    border-radius: 9px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: background 0.2s; 
} 
.btn-primary { 
    background: #2563eb; 
    color: #fff; 
    padding: 6px 10px;
    border-radius: 8px;
} 
.btn-primary:hover { 
    background: #1d4ed8; 
} 
.btn-danger { 
    background: #ef4444; 
    color: #fff; 
} 
.btn-danger:hover { 
    background: #dc2626; 
} 
 
/* Save changes button for members */ 
.save-members-btn { 
    width: 100%; 
    padding: 12px; 
    background: #16a34a; 
    color: #fff; 
    border: none; 
    border-radius: 9px; 
    font-weight: 600; 
    cursor: pointer; 
    margin-top: 12px; 
    transition: background 0.2s; 
} 
.save-members-btn:hover { 
    background: #15803d; 
} 
.save-members-btn:disabled { 
    opacity: 0.6; 
    cursor: not-allowed; 
} 
 
@media(max-width:600px) { 
    .chat-header { 
        padding: 8px 9px; 
    } 
    .search-button{
        display: none;
    }
    .messages { 
        padding: 12px 8px; 
    } 
    .bubble { 
        max-width: 86%; 
    } 
    .attachment img { 
        max-width: 220px; 
    } 
    .attachment video { 
        max-width: 220px; 
    } 
    .composer { 
        padding: 8px; 
        margin-bottom: 50px;
    } 
    .search-box { 
        width: 230px; 
       
    } 
    .modal-content { 
        padding: 18px; 
    } 
} 
 
/* Custom scrollbar */ 
::-webkit-scrollbar { 
    width: 5px; 
} 
::-webkit-scrollbar-track { 
    background: transparent; 
} 
::-webkit-scrollbar-thumb { 
    background: #cbd5e1; 
    border-radius: 10px; 
} 
::-webkit-scrollbar-thumb:hover { 
    background: #94a3b8; 
} 
 
</style> 
 
</head> 
 
<body> 
 
<div class="chat-app"> 
 
<header class="chat-header"> 
 
<button 
    class="back" 
    onclick="window.location.href='chat.php'" 
> 
    ‹ 
</button> 
 
<div 
    class="profile" 
    id="group-profile" 
    onclick="openGroupInfo()" 
> 
 
    <?php if ($groupProfile !== ''): ?> 
 
        <img 
            src="<?= esc($groupProfile) ?>" 
            alt="Group" 
        > 
 
    <?php else: ?> 
 
        <?= esc( 
            strtoupper( 
                substr( 
                    $groupName, 
                    0, 
                    1 
                ) 
            ) 
        ) ?> 
 
    <?php endif; ?> 
 
</div> 
 
<div 
    class="header-info" 
    onclick="openGroupInfo()" 
> 
 
    <strong id="headerGroupName"> 
        <?= esc($groupName) ?> 
        <span class="edit-icon">✎</span> 
    </strong> 
 
    <div 
        class="status" 
        id="group-status" 
    > 
        <?= count($groupParticipants) ?> 
        members 
    </div> 
 
    <div 
        class="typing-status" 
        id="typingStatus" 
        style=" 
            font-size:12px; 
            color:#2563eb; 
            min-height:16px; 
        " 
    > 
        <?= esc($typingText) ?> 
    </div> 
 
</div> 
 
<div class="header-search"> 
 
    <button 
        class="search-button" 
        type="button" 
        onclick="toggleSearch()" 
    > 
        🔎 
    </button> 
 
    <div 
        class="search-box" 
        id="search-box" 
    > 
 
        <input 
            id="search-input" 
            type="text" 
            placeholder="Search messages..." 
            autocomplete="off" 
        > 
 
    </div> 
 
</div> 
 
<!-- MENU --> 
<div class="menu-wrapper"> 
    <button class="menu-btn" type="button" onclick="toggleMenu()"> 
        ☰ 
    </button> 
    <div class="dropdown-menu" id="dropdownMenu"> 
        <button class="menu-item" onclick="openMembersModal()"> 
            <span class="icon">👥</span> Members 
        </button> 
        <button class="menu-item" onclick="openGroupInfo()"> 
            <span class="icon">ℹ️</span> Group Info 
        </button> 
        <button class="menu-item" onclick="openAddMember()"> 
            <span class="icon">➕</span> Add Member 
        </button> 
        <button class="menu-item" onclick="focusSearch()"> 
            <span class="icon">🔎</span> Search Messages 
        </button> 
    </div> 
</div> 
 
</header> 
 
<main 
    class="messages" 
    id="messages" 
> 

<!-- Last message tag (down button) -->
<button type="button" class="last-message-tag" id="lastMessageTag" onclick="goToLastMessage()">↓ </button>
 <div>
<?= $messagesMarkup ?> 
 
<?php if (empty($messages)): ?> 
 
    <div class="empty"> 
 
        <div> 
            <strong>No messages yet</strong> 
            <br> 
            <small> 
                Start the group conversation. 
            </small> 
        </div> 
 
    </div> 
 
<?php endif; ?> 
 </div>
</main> 
 
<form 
    class="composer" 
    id="message-form" 
    enctype="multipart/form-data" 
> 
 
<label 
    class="attach-label" 
    for="attachment" 
> 
    📎 
</label> 
 
<input 
    type="file" 
    multiple 
    id="attachment" 
    name="attachment[]" 
    accept=" 
        image/*, 
        audio/*, 
        video/*, 
        .pdf, 
        .doc, 
        .docx, 
        .xls, 
        .xlsx, 
        .ppt, 
        .pptx, 
        .txt, 
        .zip, 
        .rar, 
        .7z, 
        .csv, 
        .json, 
        .html, 
        .css,
        .js,
        .apk,
        .py,
        .php
    " 
> 
 
<textarea 
    id="message" 
    name="message" 
    placeholder="Type a message..." 
    rows="1" 
></textarea> 
 
<button 
    class="send" 
    id="send-button" 
    type="submit" 
> 
    ➤ 
</button> 
 
</form> 
 
</div> 
 
<!-- MEMBERS MODAL --> 
 
<div 
    class="modal" 
    id="membersModal" 
> 
 
<div 
    class="modal-content" 
    onclick="event.stopPropagation()" 
> 
 
    <button 
        class="modal-close" 
        onclick="closeModal(event,'membersModal')" 
    > 
        × 
    </button> 
 
    <h3 style="margin-top:0;">Group Members</h3> 
 
    <input 
        type="text" 
        id="memberSearchInput" 
        class="user-search-input" 
        placeholder="Search members..." 
        oninput="filterMembers(this.value)" 
    > 
 
    <div 
        class="member-list" 
        id="memberList" 
    > 
 
        <?php foreach ( 
            $groupParticipants 
            as $number => $memberName 
        ): ?> 
 
            <?php 
 
            $number = 
                safeNumber($number); 
 
            $photo = 
                getProfilePhotoPath( 
                    $users, 
                    $number 
                ); 
 
            $status = 
                $initialStatuses[ 
                    $number 
                ] 
                ?? 
                'Offline'; 
?> 
 
            <div 
                class="member-item" 
                data-number="<?= esc($number) ?>" 
                data-search="<?= esc( 
                    strtolower( 
                        $memberName . 
                        ' ' . 
                        $number 
                    ) 
                ) ?>" 
            > 
 
                <div class="avatar"> 
 
                    <?php if ($photo !== ''): ?> 
 
                        <img 
                            src="<?= esc($photo) ?>" 
                            alt="" 
                        > 
 
                    <?php else: ?> 
 
                        <?= esc( 
                            strtoupper( 
                                substr( 
                                    $memberName, 
                                    0, 
                                    1 
                                ) 
                            ) 
                        ) ?> 
 
                    <?php endif; ?> 
 
                </div> 
 
                <div class="info"> 
 
                    <input 
                        type="text" 
                        class="member-name-input" 
                        value="<?= esc($memberName) ?>" 
                        data-number="<?= esc($number) ?>" 
                        placeholder="Name" 
                    > 
 
                    <span> 
                        +<?= esc($number) ?> 
                    </span> 
 
                </div> 
 
                <span 
                    class="status-dot <?= $status === 'Online' ? 'online' : '' ?>" 
                    id="modal-status-<?= esc($number) ?>" 
                ></span> 
 
            </div> 
 
        <?php endforeach; ?> 
 
    </div> 
 
    <button 
        type="button" 
        class="save-members-btn" 
        id="saveMembersBtn" 
        onclick="saveMemberNames()" 
    > 
        💾 Save Changes 
    </button> 
 
    <hr style="margin:15px 0;"> 
 
    <h4>Add Member from Contacts</h4> 
 
    <div id="contactsContainer"> 
        <ul class="contacts-list" id="contactsList"> 
            <li class="no-contacts">Loading contacts...</li> 
        </ul> 
    </div> 
 
</div> 
 
</div> 
 

<!-- ADD MEMBER INPUT MODAL -->
<div class="modal" id="addMemberModal">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button
            class="modal-close"
            type="button"
            onclick="closeModal(event,'addMemberModal')"
        >×</button>

        <h3 style="margin-top:0;">Add Member</h3>

        <label for="addMemberNumberInput"
               style="display:block;font-weight:600;margin-bottom:4px;">
            Mobile number
        </label>

        <input
            type="text"
            id="addMemberNumberInput"
            class="add-member-input"
            inputmode="numeric"
            autocomplete="off"
            maxlength="10"
            placeholder="Enter 10-digit mobile number"
            oninput="handleAddMemberNumberInput(this)"
        >

        <span class="add-member-help">
            Type the registered 10-digit mobile number.
        </span>

        <div id="addMemberResult" class="add-member-result"></div>

        <button
            type="button"
            id="addMemberSubmit"
            class="add-member-submit"
            onclick="submitAddMember()"
            disabled
        >
            Add Member
        </button>
    </div>
</div>

<!-- GROUP INFO MODAL --> 
 
<div 
    class="modal" 
    id="groupInfoModal" 
> 
 
<div 
    class="modal-content" 
    onclick="event.stopPropagation()" 
> 
 
    <button 
        class="modal-close" 
        onclick="closeModal(event,'groupInfoModal')" 
    > 
        × 
    </button> 
 
    <div class="big-profile"> 
 
        <?php if ($groupProfile !== ''): ?> 
 
            <img 
                id="groupInfoImage" 
                src="<?= esc($groupProfile) ?>" 
                alt="Group" 
                onclick="openBigImage(this.src)" 
            > 
 
        <?php else: ?> 
 
            <span id="groupInfoInitial"> 
                <?= esc( 
                    strtoupper( 
                        substr( 
                            $groupName, 
                            0, 
                            1 
                        ) 
                    ) 
                ) ?> 
            </span> 
 
        <?php endif; ?> 
 
    </div> 
 
    <div class="info-row"> 
 
        <div class="info-label"> 
            Group Name 
        </div> 
 
        <div 
            class="info-value" 
            id="groupNameDisplay" 
        > 
            <?= esc($groupName) ?> 
        </div> 
 
    </div> 
 
    <div class="info-row"> 
 
        <div class="info-label"> 
            Members 
        </div> 
 
        <div 
            class="info-value" 
            id="groupMemberCount" 
        > 
            <?= count($groupParticipants) ?> 
        </div> 
 
    </div> 
<div class="info-row"> 
 
        <div class="info-label"> 
            Your Number 
        </div> 
 
        <div class="info-value"> 
            +<?= esc($senderNumber) ?> 
        </div> 
 
    </div> 
 
     
 
        <div 
            style=" 
                margin-top:15px; 
                padding-top:15px; 
                border-top:1px solid #e5e7eb; 
            " 
        > 
 
            <h4>Edit Group Name</h4> 
 
            <form id="groupNameForm"> 
 
                <input 
                    type="text" 
                    name="group_name" 
                    id="groupNameInput" 
                    style=" 
                        width:100%; 
                        padding:10px; 
                        border:1px solid #dbe2ea; 
                        border-radius:9px; 
                        margin-bottom:8px; 
                    " 
                    value="<?= esc($groupName) ?>" 
                    required 
                > 
 
                <button 
                    type="submit" 
                    class="btn-primary" 
                    style="width:100%;" 
                > 
                    Update Group Name 
                </button> 
 
            </form> 
 
            <h4 style="margin-top:15px;"> 
                Change Group Profile 
            </h4> 
 
            <form 
                id="groupProfileForm" 
                enctype="multipart/form-data" 
            > 
 
                <input 
                    type="file" 
                    name="group_profile" 
                    id="groupProfileInput" 
                    style=" 
                        width:100%; 
                        padding:10px; 
                        border:1px solid #dbe2ea; 
                        border-radius:9px; 
                        margin-bottom:8px; 
                        display:none; 
                    " 
                    accept="image/*" 
                > 
                <label for="groupProfileInput" style=" 
                  width: 100%; 
                  padding: 10px; 
                  border: 1px solid #dbe2ea; 
                  border-radius: 9px; 
                  margin-bottom: 8px; 
                  position: relative; 
                  background: #2563eb; 
                  color: white; 
                  display: flex; 
                  justify-content: center; 
                  align-items: center; 
                  cursor: pointer; 
                "> 
                    Choose Photo 
                </label> 
 
                <div id="profilePreview" class="profile-preview" style="display:none;"> 
                    <img id="profilePreviewImg" src="#" alt="Preview"> 
                </div> 
 
                <div class="btn-group"> 
                    <button type="submit" class="btn-primary"> 
                        Upload 
                    </button> 
                    <button type="button" class="btn-danger" onclick="removeProfilePhoto()"> 
                        Remove 
                    </button> 
                </div> 
 
            </form> 
 
        </div> 
 
     
 
</div> 
 
 
</div> 
 
<!-- BIG IMAGE --> 
 
<div 
    class="modal" 
    id="bigImageModal" 
> 
 
<div 
    style=" 
        max-width:95vw; 
        max-height:95vh; 
        text-align:center; 
    " 
    onclick="event.stopPropagation()" 
> 
 
    <img 
        id="bigImage" 
        src="" 
        alt="" 
        style=" 
            max-width:90vw; 
            max-height:85vh; 
            object-fit:contain; 
            border-radius:16px; 
        " 
    > 
 
    <br> 
 
    <button 
        onclick="closeModal(event,'bigImageModal')" 
        style=" 
            margin-top:10px; 
            width:40px; 
            height:40px; 
            border:0; 
            border-radius:50%; 
            cursor:pointer; 
            font-size:20px; 
            background:#fff; 
            box-shadow:0 4px 12px rgba(0,0,0,.2); 
        " 
    > 
        × 
    </button> 
 
</div> 
 
</div> 
 
<!-- DELETE MODAL --> 
 
<div 
    class="modal" 
    id="deleteModal" 
> 
 
<div 
    class="modal-content" 
    onclick="event.stopPropagation()" 
> 
 
    <button 
        class="modal-close" 
        onclick="closeModal(event,'deleteModal')" 
    > 
        × 
    </button> 
 
    <h3> 
        Delete Message 
    </h3> 
 
    <p style="color:#64748b;"> 
        Are you sure you want to delete this message? 
    </p> 
 
    <button 
        class="btn-danger" 
        style="    width: 100%;
    padding: 10px;
    border: 0;
    border-radius: 9px;
    background: #ef4444;
    color: #fff;
    font-weight: 600;
    cursor: pointer" 
        onclick="confirmDelete()" 
    > 
        Delete 
    </button> 
 
</div> 
 
</div> 
 
<script> 
 
/* ========================================================= 
   PHP DATA 
========================================================= */ 
 
const GROUP_FILE = 
    <?= json_encode($conversationFile) ?>; 
 
const CURRENT_USER = 
    <?= json_encode($senderNumber) ?>; 
 
let GROUP_NAME = 
    <?= json_encode($groupName) ?>; 
 
let MEMBERS = 
    <?= json_encode($groupParticipants) ?>; 
 
const USERS = 
    <?= json_encode($users) ?>; 
 
let lastMessageId = 
    <?= json_encode($lastMessageId) ?>; 
 
let typingTimer = null; 
 
let selectedDeleteId = ''; 
 
let isSending = false; 
 
let userNearBottom = true; 
 
let messageRequestRunning = false; 
 
let groupStateRequestRunning = false; 
 
 
/* ========================================================= 
   MODALS 
========================================================= */ 
 
function openModal(id) { 
 
    const modal = 
        document.getElementById(id); 
 
    if (modal) { 
        modal.classList.add('show'); 
    } 
} 
 
function closeModal(event, id) { 
 
    if ( 
        event && 
        event.target !== 
        event.currentTarget 
    ) { 
        return; 
    } 
 
    const modal = 
        document.getElementById(id); 
 
    if (modal) { 
        modal.classList.remove('show'); 
    } 
} 
 
 
/* ========================================================= 
   MENU 
========================================================= */ 
 
function toggleMenu() { 
    const menu = document.getElementById('dropdownMenu'); 
    menu.classList.toggle('show'); 
} 
 
// Close menu when clicking outside 
document.addEventListener('click', function(e) { 
    const menu = document.getElementById('dropdownMenu'); 
    const btn = document.querySelector('.menu-btn'); 
    if (!menu.contains(e.target) && !btn.contains(e.target)) { 
        menu.classList.remove('show'); 
    } 
}); 
 
/* ========================================================= 
   SEARCH 
========================================================= */ 
 
function toggleSearch() { 
 
    const box = 
        document.getElementById( 
            'search-box' 
        ); 
 
    box.classList.toggle( 
        'show' 
    ); 
 
    document 
        .getElementById( 
            'search-input' 
        ) 
        .focus(); 
} 

function focusSearch() { 
    toggleMenu(); 
    setTimeout(function() { 
        const box = document.getElementById('search-box'); 
        if (!box.classList.contains('show')) { 
            box.classList.add('show'); 
        } 
        document.getElementById('search-input').focus(); 
    }, 100); 
} 
 
document 
    .getElementById( 
        'search-input' 
    ) 
    .addEventListener( 
        'input', 
        function () { 
 
            const query = 
                this.value 
                    .toLowerCase() 
                    .trim(); 
 
            document 
                .querySelectorAll( 
                    '.message-row' 
                ) 
                .forEach( 
                    function(row) { 
 
                        if (!query) { 
 
                            row.style.display = 
                                ''; 
 
                            return; 
                        } 
 
                        row.style.display = 
                            row.innerText 
                                .toLowerCase() 
                                .includes(query) 
                                ? 
                                '' 
                                : 
                                'none'; 
                    } 
                ); 
        } 
    ); 
 
 
/* ========================================================= 
   MESSAGE MENUS 
========================================================= */ 
 
function closeAllMenus() { 
 
    document 
        .querySelectorAll( 
            '.message-menu.show' 
        ) 
        .forEach( 
            function(menu) { 
 
                menu.classList.remove( 
                    'show' 
                ); 
            } 
        ); 
} 
 
function toggleMessageMenu(button) { 
 
    const menu = 
        button.parentElement 
            .querySelector( 
                '.message-menu' 
            ); 
 
    const open = 
        menu.classList.contains( 
            'show' 
        ); 
 
    closeAllMenus(); 
 
    if (!open) { 
        menu.classList.add( 
            'show' 
        ); 
    } 
} 
 
document.addEventListener( 
    'click', 
    function(event) { 
 
        if ( 
            !event.target.closest( 
                '.message-menu' 
            ) && 
            !event.target.closest( 
                '.message-menu-btn' 
            ) 
        ) { 
 
            closeAllMenus(); 
        } 
    } 
); 
 
 
/* ========================================================= 
   COPY 
========================================================= */ 
 
function copyMessage(button) { 
 
    const bubble = 
        button.closest( 
            '.bubble' 
        ); 
 
    const text = 
        bubble.querySelector( 
            '.message-text' 
        ); 
 
    if (!text) { 
        return; 
    } 
 
    const value = 
        text.innerText || ''; 
 
    if ( 
        navigator.clipboard && 
        navigator.clipboard.writeText 
    ) { 
 
        navigator.clipboard 
            .writeText(value) 
            .then( 
                function() { 
                    closeAllMenus(); 
                } 
            ) 
            .catch( 
                function() { 
                    fallbackCopy(value); 
                } 
            ); 
 
    } else { 
 
        fallbackCopy(value); 
    } 
} 
 
function fallbackCopy(text) { 
 
    const textarea = 
        document.createElement( 
            'textarea' 
        ); 
 
    textarea.value = 
        text; 
 
    document.body.appendChild( 
        textarea 
    ); 
 
    textarea.select(); 
 
    try { 
        document.execCommand( 
            'copy' 
        ); 
    } catch(e) {} 
 
    textarea.remove(); 
 
    closeAllMenus(); 
} 
 
 
/* =========================================================
   DELETE
========================================================= */ 
 
function deleteMessage(button) { 
 
    const row = 
        button.closest( 
            '.message-row' 
        ); 
 
    if (!row) { 
        return; 
    } 
 
    selectedDeleteId = 
        row.dataset.messageId; 
 
    closeAllMenus(); 
 
    openModal( 
        'deleteModal' 
    ); 
} 
 
function confirmDelete() { 
 
    if (!selectedDeleteId) { 
        return; 
    } 
 
    const body = 
        new URLSearchParams(); 
 
    body.append( 
        'action', 
        'delete_message' 
    ); 
 
    body.append( 
        'message_id', 
        selectedDeleteId 
    ); 
 
    fetch( 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ), 
        { 
            method: 'POST', 
 
            headers: { 
                'X-Requested-With': 
                    'XMLHttpRequest', 
 
                'Content-Type': 
                    'application/x-www-form-urlencoded' 
            }, 
 
            body: 
                body.toString() 
        } 
    ) 
    .then( 
        function(response) { 
            return response.json(); 
        } 
    ) 
    .then( 
        function(data) { 
 
            if (data.ok) { 
 
                const row = 
                    document.querySelector( 
                        '.message-row[data-message-id="' + 
                        CSS.escape( 
                            selectedDeleteId 
                        ) + 
                        '"]' 
                    ); 
 
                if (row) { 
                    row.remove(); 
                } 
 
                selectedDeleteId = 
                    ''; 
 
                document 
                    .getElementById( 
                        'deleteModal' 
                    ) 
                    .classList.remove( 
                        'show' 
                    ); 
 
            } else { 
 
                alert( 
                    data.error || 
                    'Could not delete message.' 
                ); 
            } 
        } 
    ) 
    .catch( 
        function() { 
 
            alert( 
                'Unable to delete message.' 
            ); 
        } 
    ); 
} 
 
 
/* ========================================================= 
   DOWNLOAD 
========================================================= */ 
 
function downloadAllAttachments(button) {
    const row = button.closest('.message-row');

    if (!row) {
        return;
    }

    const attachments = row.querySelectorAll('.attachment');

    attachments.forEach(function (attachment, index) {
        const url = attachment.dataset.attachmentUrl || '';
        const name = attachment.dataset.attachmentName || ('file-' + (index + 1));

        if (!url) {
            return;
        }

        setTimeout(function () {
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
 
const messagesBox = 
    document.getElementById( 
        'messages' 
    ); 
 
function isAtBottom() { 
 
    if (!messagesBox) { 
        return true; 
    } 
 
    return ( 
        messagesBox.scrollHeight - 
        messagesBox.scrollTop - 
        messagesBox.clientHeight 
    ) < 120; 
} 
 
messagesBox.addEventListener( 
    'scroll', 
    function() { 
 
        userNearBottom = 
            isAtBottom(); 
        updateLastMessageTag();
    } 
); 
 
function scrollToBottom(force) { 
 
    if (!messagesBox) { 
        return; 
    } 
 
    if ( 
        force || 
        userNearBottom 
    ) { 
 
        messagesBox.scrollTop = 
            messagesBox.scrollHeight; 
    } 
    updateLastMessageTag();
} 

/* ========================================================= 
   LAST MESSAGE TAG (Down Button) 
========================================================= */ 
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
   MESSAGE DOM UPDATE 
========================================================= */ 
 
function appendNewMessages(html) { 
 
    if (!html) { 
        return; 
    } 
 
    const empty = 
        document.querySelector( 
            '.empty' 
        ); 
 
    if (empty) { 
        empty.remove(); 
    } 
 
    const wasBottom = 
        isAtBottom(); 
 
    messagesBox.insertAdjacentHTML( 
        'beforeend', 
        html 
    ); 
 
    if (wasBottom) { 
        scrollToBottom(true); 
    } 
    updateLastMessageTag();
} 
 
function replaceAllMessages(html) { 
 
    if (!messagesBox) { 
        return; 
    } 
 
    messagesBox.innerHTML = 
        html || 
        '<div class="empty">' + 
        '<div>' + 
        '<strong>No messages yet</strong>' + 
        '<br>' + 
        '<small>Start the group conversation.</small>' + 
        '</div>' + 
        '</div>'; 
 
    scrollToBottom(true); 
    updateLastMessageTag();
} 
 
 
/* ========================================================= 
   POLLING MESSAGES 
========================================================= */ 
 
function refreshMessages() { 
 
    if ( 
        messageRequestRunning 
    ) { 
        return; 
    } 
 
    messageRequestRunning = 
        true; 
 
    const url = 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ) + 
        '&ajax=messages&last_id=' + 
        encodeURIComponent( 
            lastMessageId || '' 
        ); 
 
    fetch( 
        url, 
        { 
            method: 'GET', 
            cache: 'no-store', 
            headers: { 
                'X-Requested-With': 
                    'XMLHttpRequest' 
            } 
        } 
    ) 
    .then( 
        function(response) { 
            return response.json(); 
        } 
    ) 
    .then( 
        function(data) { 
 
            if ( 
                !data || 
                !data.ok 
            ) { 
                return; 
            } 
 
            if ( 
                data.mode === 
                'full' 
            ) { 
 
                replaceAllMessages( 
                    data.html 
                ); 
 
            } else if ( 
                data.mode === 
                'new' && 
                data.html 
            ) { 
 
                appendNewMessages( 
                    data.html 
                ); 
            } 
 
            if ( 
                data.last_id 
            ) { 
 
                lastMessageId = 
                    data.last_id; 
            } 
        } 
    ) 
    .catch( 
        function(error) { 
 
            console.log( 
                'Message polling error:', 
                error 
            ); 
        } 
    ) 
    .finally( 
        function() { 
 
            messageRequestRunning = 
                false; 
        } 
    ); 
} 
 
 
/* ========================================================= 
   GROUP STATE POLLING 
========================================================= */ 
 
function refreshGroupState() { 
 
    if ( 
        groupStateRequestRunning 
    ) { 
        return; 
    } 
 
    groupStateRequestRunning = 
        true; 
 
    const url = 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ) + 
        '&ajax=group_state'; 
 
    fetch( 
        url, 
        { 
            method: 'GET', 
            cache: 'no-store', 
            headers: { 
                'X-Requested-With': 
                    'XMLHttpRequest' 
            } 
        } 
    ) 
    .then( 
        function(response) { 
            return response.json(); 
        } 
    ) 
    .then( 
        function(data) { 
 
            if ( 
                !data || 
                !data.ok 
            ) { 
                return; 
            } 
 
            if ( 
                typeof data.group_name 
                !== 'undefined' 
            ) { 
 
                GROUP_NAME = 
                    data.group_name; 
 
                const headerName = 
                    document.getElementById( 
                        'headerGroupName' 
                    ); 
 
                if (headerName) { 
 
                    headerName.textContent = 
                        data.group_name; 
                } 
 
                document.title = 
                    data.group_name; 
 
                const display = 
                    document.getElementById( 
                        'groupNameDisplay' 
                    ); 
 
                if (display) { 
 
                    display.textContent = 
                        data.group_name; 
                } 
 
                const input = 
                    document.getElementById( 
                        'groupNameInput' 
                    ); 
 
                if ( 
                    input && 
                    document.activeElement 
                    !== input 
                ) { 
 
                    input.value = 
                        data.group_name; 
                } 
            } 
 
            if ( 
                typeof data.member_count 
                !== 'undefined' 
            ) { 
 
                const status = 
                    document.getElementById( 
                        'group-status' 
                    ); 
 
                if (status) { 
 
                    status.textContent = 
                        data.member_count + 
                        ' members'; 
                } 
 
                const count = 
                    document.getElementById( 
                        'groupMemberCount' 
                    ); 
 
                if (count) { 
 
                    count.textContent = 
                        data.member_count; 
                } 
            } 
 
            if ( 
                typeof data.group_profiles 
                !== 'undefined' 
            ) { 
                const profileUrl = data.group_profiles ? data.group_profiles + '?v=' + Date.now() : ''; 
                const profile = document.getElementById('group-profile'); 
                if (profile) { 
                    let img = profile.querySelector('img'); 
                    if (profileUrl) { 
                        if (!img) { 
                            profile.innerHTML = '<img alt="Group">'; 
                            img = profile.querySelector('img'); 
                        } 
                        img.src = profileUrl; 
                    } else { 
                        const initial = GROUP_NAME.charAt(0).toUpperCase(); 
                        profile.innerHTML = initial; 
                    } 
                } 
                const infoImage = document.getElementById('groupInfoImage'); 
                const bigProfile = document.querySelector('#groupInfoModal .big-profile'); 
                if (infoImage) { 
                    if (profileUrl) { 
                        infoImage.src = profileUrl; 
                    } else { 
                        const initial = GROUP_NAME.charAt(0).toUpperCase(); 
                        bigProfile.innerHTML = '<span id="groupInfoInitial">' + initial + '</span>'; 
                    } 
                } else if (bigProfile) { 
                    if (profileUrl) { 
                        bigProfile.innerHTML = '<img id="groupInfoImage" src="' + profileUrl + '" alt="Group" onclick="openBigImage(this.src)">'; 
                    } else { 
                        const initial = GROUP_NAME.charAt(0).toUpperCase(); 
                        bigProfile.innerHTML = '<span id="groupInfoInitial">' + initial + '</span>'; 
                    } 
                } 
            } 
 
            if ( 
                data.participants && 
                JSON.stringify( 
                    MEMBERS 
                ) !== 
                JSON.stringify( 
                    data.participants 
                ) 
            ) { 
 
                MEMBERS = 
                    data.participants; 
 
                rebuildMemberList( 
                    data.participants 
                ); 
            } 
        } 
    ) 
    .catch( 
        function(error) { 
 
            console.log( 
                'Group state error:', 
                error 
            ); 
        } 
    ) 
    .finally( 
        function() { 
 
            groupStateRequestRunning = 
                false; 
        } 
    ); 
} 
 
 
/* ========================================================= 
   REBUILD MEMBER LIST (with input fields) 
========================================================= */ 
 
function rebuildMemberList( 
    participants 
) { 
 
    const list = 
        document.getElementById( 
            'memberList' 
        ); 
 
    if (!list) { 
        return; 
    } 
 
    list.innerHTML = ''; 
 
    Object.keys( 
        participants 
    ).forEach( 
        function(number) { 
 
            const name = 
                participants[ 
                    number 
                ]; 
 
            const user = 
                USERS[number] 
                || {}; 
 
            const photo = 
                user.profile_photo 
                || ''; 
 
            const item = 
                document.createElement( 
                    'div' 
                ); 
 
            item.className = 
                'member-item'; 
 
            item.dataset.number = 
                number; 
 
            item.dataset.search = 
                ( 
                    name + 
                    ' ' + 
                    number 
                ).toLowerCase(); 
 
            let avatar = 
                ''; 
 
            if (photo) { 
 
                avatar = 
                    '<img src="' + 
                    escapeHtml(photo) + 
                    '" alt="">'; 
 
            } else { 
 
                avatar = 
                    escapeHtml( 
                        String(name) 
                            .charAt(0) 
                            .toUpperCase() 
                    ); 
            } 
 
            item.innerHTML = 
                '<div class="avatar">' + 
                avatar + 
                '</div>' + 
 
                '<div class="info">' + 
                '<input type="text" class="member-name-input" value="' + 
                escapeHtml(name) + 
                '" data-number="' + 
                escapeHtml(number) + 
                '" placeholder="Name">' + 
                '<span>+' + 
                escapeHtml(number) + 
                '</span>' + 
                '</div>' + 
 
                '<span class="status-dot" ' + 
                'id="modal-status-' + 
                escapeHtml(number) + 
                '"></span>'; 
 
            list.appendChild( 
                item 
            ); 
        } 
    ); 
 
    refreshPresence(); 
} 
 
function escapeHtml(value) { 
 
    const div = 
        document.createElement( 
            'div' 
        ); 
 
    div.textContent = 
        String(value); 
 
    return div.innerHTML; 
} 
 
 
/* ========================================================= 
   TYPING 
========================================================= */ 
 
function sendTypingState(value) { 
 
    const url = 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ) + 
        '&ajax=typing&typing=' + 
        ( 
            value 
            ? '1' 
            : '0' 
        ); 
 
    fetch( 
        url, 
        { 
            method: 'GET', 
            cache: 'no-store' 
        } 
    ) 
    .catch( 
        function() {} 
    ); 
} 
 
function typingStart() { 
 
    sendTypingState(true); 
 
    clearTimeout( 
        typingTimer 
    ); 
 
    typingTimer = 
        setTimeout( 
            function() { 
 
                sendTypingState( 
                    false 
                ); 
 
            }, 
            2500 
        ); 
} 
 
function typingStop() { 
 
    clearTimeout( 
        typingTimer 
    ); 
 
    sendTypingState( 
        false 
    ); 
} 
 
function refreshTypingStatus() { 
 
    const url = 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ) + 
        '&ajax=typing_status'; 
 
    fetch( 
        url, 
        { 
            method: 'GET', 
            cache: 'no-store' 
        } 
    ) 
    .then( 
        function(response) { 
            return response.json(); 
        } 
    ) 
    .then( 
        function(data) { 
 
            const status = 
                document.getElementById( 
                    'typingStatus' 
                ); 
 
            if (status) { 
 
                status.textContent = 
                    data.status || 
                    ''; 
            } 
        } 
    ) 
    .catch( 
        function() {} 
    ); 
} 
 
 
/* ========================================================= 
   PRESENCE 
========================================================= */ 
 
function refreshPresence() { 
 
    const url = 
        'group.php?conversation=' + 
        encodeURIComponent( 
            GROUP_FILE 
        ) + 
        '&ajax=presence'; 
 
    fetch( 
        url, 
        { 
            method: 'GET', 
            cache: 'no-store' 
        } 
    ) 
    .then( 
        function(response) { 
            return response.json(); 
        } 
    ) 
    .then( 
        function(data) { 
 
            if ( 
                !data || 
                !data.statuses 
            ) { 
                return; 
            } 
 
            Object.keys( 
                data.statuses 
            ).forEach( 
                function(number) { 
 
                    const status = 
                        data.statuses[ 
                            number 
                        ]; 
 
                    const dot = 
                        document.getElementById( 
                            'modal-status-' + 
                            number 
                        ); 
 
                    if (dot) { 
 
                        dot.classList.toggle( 
                            'online', 
                            status === 
                            'Online' 
                        ); 
                    } 
                } 
            ); 
        } 
    ) 
    .catch( 
        function() {} 
    ); 
} 
 
 
/* ========================================================= 
   MESSAGE INPUT 
========================================================= */ 
 
const messageInput = 
    document.getElementById( 
        'message' 
    ); 
 
messageInput.addEventListener( 
    'input', 
    function() { 
 
        if ( 
            this.value.trim() 
            !== '' 
        ) { 
 
            typingStart(); 
 
        } else { 
 
            typingStop(); 
        } 
    } 
); 
 
messageInput.addEventListener( 
    'blur', 
    typingStop 
); 
 
messageInput.addEventListener( 
    'keydown', 
    function(event) { 
 
        if ( 
            event.key === 'Enter' && 
            !event.shiftKey 
        ) { 
 
            event.preventDefault(); 
 
            document 
                .getElementById( 
                    'message-form' 
                ) 
                .requestSubmit(); 
        } 
    } 
); 
 
 
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
 
document 
    .getElementById( 
        'message-form' 
    ) 
    .addEventListener( 
        'submit', 
        function(event) { 
 
            event.preventDefault(); 
 
            if (isSending) { 
                return; 
            } 
 
            const form = 
                this; 
 
            const input = 
                document.getElementById( 
                    'message' 
                ); 
 
            const button = 
                document.getElementById( 
                    'send-button' 
                ); 
 
            const value = 
                input.value.trim(); 
 
            const files = 
                document.getElementById( 
                    'attachment' 
                ).files; 
 
            if ( 
                value === '' && 
                ( 
                    !files || 
                    files.length === 0 
                ) 
            ) { 
                return; 
            } 
 
            isSending = 
                true; 
 
            button.disabled = 
                true; 
 
            button.textContent = 
                '...'; 
 
            typingStop(); 
 
            const formData = 
                new FormData(form); 
 
            formData.append( 
                'action', 
                'send' 
            ); 
 
            fetch( 
                'group.php?conversation=' + 
                encodeURIComponent( 
                    GROUP_FILE 
                ), 
                { 
                    method: 'POST', 
 
                    headers: { 
                        'X-Requested-With': 
                            'XMLHttpRequest' 
                    }, 
 
                    body: 
                        formData 
                } 
            ) 
            .then( 
                function(response) { 
                    return response.json(); 
                } 
            ) 
            .then( 
                function(data) { 
 
                    if ( 
                        !data || 
                        !data.ok 
                    ) { 
 
                        alert( 
                            data.error || 
                            'Unable to send message.' 
                        ); 
 
                        return; 
                    } 
 
                    appendNewMessages( 
                        data.html 
                    ); 
 
                    if ( 
                        data.last_id 
                    ) { 
 
                        lastMessageId = 
                            data.last_id; 
                    } 
 
                    input.value = 
                        ''; 
 
                    document 
                        .getElementById( 
                            'attachment' 
                        ) 
                        .value = 
                        ''; 
 
                    const preview = 
                        document.getElementById( 
                            'filePreview' 
                        ); 
 
                    if (preview) { 
                        preview.remove(); 
                    } 
 
                    scrollToBottom( 
                        true 
                    ); 
 
                    input.focus(); 
                } 
            ) 
            .catch( 
                function(error) { 
 
                    console.log( 
                        error 
                    ); 
 
                    alert( 
                        'Unable to send message.' 
                    ); 
                } 
            ) 
            .finally( 
                function() { 
 
                    isSending = 
                        false; 
 
                    button.disabled = 
                        false; 
 
                    button.textContent = 
                        '➤'; 
                } 
            ); 
        } 
    ); 
 
 
/* ========================================================= 
   MEMBERS MODAL – with editable name inputs and save 
========================================================= */ 
 
function openMembersModal() { 
    openModal('membersModal'); 
    refreshGroupState(); 
    refreshPresence(); 
    loadContacts(); 
} 

/* ========================================================= 
   SAVE MEMBER NAMES (BATCH) 
========================================================= */ 
 
function saveMemberNames() { 
    const inputs = document.querySelectorAll('.member-name-input'); 
    const updates = {}; 
    let hasChanges = false; 
 
    inputs.forEach(function(input) { 
        const number = input.dataset.number; 
        const newName = input.value.trim(); 
        if (newName === '') { 
            alert('Member name cannot be empty for number ' + number); 
            input.focus(); 
            return; 
        } 
        // Compare with current MEMBERS (the original list) 
        if (MEMBERS[number] && MEMBERS[number] !== newName) { 
            updates[number] = newName; 
            hasChanges = true; 
        } 
    }); 
 
    if (!hasChanges) { 
        alert('No changes to save.'); 
        return; 
    } 
 
    const btn = document.getElementById('saveMembersBtn'); 
    btn.disabled = true; 
    btn.textContent = 'Saving...'; 
 
    const formData = new FormData(); 
    formData.append('action', 'update_member_names'); 
    // Send updates as JSON in a single field 
    formData.append('updates', JSON.stringify(updates)); 
 
    fetch('group.php?conversation=' + encodeURIComponent(GROUP_FILE), { 
        method: 'POST', 
        headers: { 
            'X-Requested-With': 'XMLHttpRequest' 
        }, 
        body: formData 
    }) 
    .then(res => res.json()) 
    .then(data => { 
        if (!data.ok) { 
            alert(data.error || 'Unable to save changes.'); 
            return; 
        } 
        // Update local MEMBERS 
        for (const [num, newName] of Object.entries(updates)) { 
            MEMBERS[num] = newName; 
        } 
        // Update UI (header, etc.) – refresh group state will handle it 
        refreshGroupState(); 
        // Update the input values to reflect saved names (in case they were trimmed) 
        Object.keys(updates).forEach(num => { 
            const input = document.querySelector(`.member-name-input[data-number="${num}"]`); 
            if (input) input.value = updates[num]; 
        }); 
        alert('Member names updated successfully.'); 
    }) 
    .catch(() => { 
        alert('Unable to save changes.'); 
    }) 
    .finally(() => { 
        btn.disabled = false; 
        btn.textContent = '💾 Save Changes'; 
    }); 
} 
 
/* ========================================================= 
   ADD MEMBER – Quick prompt (NEW) 
========================================================= */ 
function openAddMember() {
    toggleMenu();

    const modal = document.getElementById('addMemberModal');
    const input = document.getElementById('addMemberNumberInput');
    const result = document.getElementById('addMemberResult');
    const submit = document.getElementById('addMemberSubmit');

    if (!modal || !input || !result || !submit) {
        alert('Add Member form is not available.');
        return;
    }

    input.value = '';
    result.textContent = '';
    result.className = 'add-member-result';
    submit.disabled = true;
    submit.textContent = 'Add Member';
    delete submit.dataset.number;
    delete submit.dataset.name;

    openModal('addMemberModal');

    setTimeout(function () {
        input.focus();
    }, 100);
}

function handleAddMemberNumberInput(input) {
    input.value = input.value.replace(/\D/g, '').slice(0, 10);

    const number = input.value;
    const result = document.getElementById('addMemberResult');
    const submit = document.getElementById('addMemberSubmit');

    if (!result || !submit) return;

    result.textContent = '';
    result.className = 'add-member-result';
    submit.disabled = true;
    delete submit.dataset.number;
    delete submit.dataset.name;

    if (number.length === 0) {
        return;
    }

    if (number.length < 10) {
        result.textContent =
            'Enter ' + (10 - number.length) + ' more digit(s).';
        result.className = 'add-member-result error';
        return;
    }

    if (Object.keys(MEMBERS).includes(number)) {
        result.textContent = 'This member is already in the group.';
        result.className = 'add-member-result error';
        return;
    }

    result.textContent = 'Checking registered number...';

    fetch(
        'group.php?ajax=get_user_info&number=' +
        encodeURIComponent(number),
        { cache: 'no-store' }
    )
    .then(function (res) {
        return res.json();
    })
    .then(function (data) {
        const currentInput =
            document.getElementById('addMemberNumberInput');

        if (!currentInput || currentInput.value !== number) {
            return;
        }

        if (!data.exists) {
            result.textContent =
                'User with this number is not registered.';
            result.className = 'add-member-result error';
            submit.disabled = true;
            return;
        }

        const name = data.name || number;

        result.textContent = 'Found: ' + name;
        result.className = 'add-member-result success';

        submit.disabled = false;
        submit.dataset.number = number;
        submit.dataset.name = name;
    })
    .catch(function () {
        result.textContent = 'Error checking this number.';
        result.className = 'add-member-result error';
        submit.disabled = true;
    });
}

function submitAddMember() {
    const input = document.getElementById('addMemberNumberInput');
    const submit = document.getElementById('addMemberSubmit');
    const result = document.getElementById('addMemberResult');

    if (!input || !submit || !result) return;

    const number = input.value.trim();

    if (!/^\d{10}$/.test(number)) {
        result.textContent = 'Please enter a valid 10-digit number.';
        result.className = 'add-member-result error';
        return;
    }

    if (Object.keys(MEMBERS).includes(number)) {
        result.textContent = 'This member is already in the group.';
        result.className = 'add-member-result error';
        return;
    }

    const name = submit.dataset.name || number;

    submit.disabled = true;
    submit.textContent = 'Adding...';
    result.textContent = 'Adding member...';
    result.className = 'add-member-result';

    const formData = new FormData();
    formData.append('action', 'add_member');
    formData.append('member_number', number);
    formData.append('member_name', name);

    fetch(
        'group.php?conversation=' +
        encodeURIComponent(GROUP_FILE),
        {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }
    )
    .then(function (res) {
        return res.json();
    })
    .then(function (data) {
        if (!data.ok) {
            result.textContent =
                data.error || 'Unable to add member.';
            result.className = 'add-member-result error';
            submit.disabled = false;
            submit.textContent = 'Add Member';
            return;
        }

        window.location.href =
            'group.php?conversation=' +
            encodeURIComponent(data.conversation);
    })
    .catch(function () {
        result.textContent = 'Unable to add member.';
        result.className = 'add-member-result error';
        submit.disabled = false;
        submit.textContent = 'Add Member';
    });
}

function filterMembers(query) { 
 
    query = 
        String( 
            query || '' 
        ) 
        .toLowerCase() 
        .trim(); 
 
    document 
        .querySelectorAll( 
            '.member-item' 
        ) 
        .forEach( 
            function(item) { 
 
                const search = 
                    ( 
                        item.dataset.search 
                        || '' 
                    ).toLowerCase(); 
 
                item.style.display = 
                    ( 
                        !query || 
                        search.includes( 
                            query 
                        ) 
                    ) 
                    ? 
                    '' 
                    : 
                    'none'; 
            } 
        ); 
} 
 
/* ========================================================= 
   LOAD CONTACTS (for adding members) 
========================================================= */ 
 
function loadContacts() { 
    const list = document.getElementById('contactsList'); 
    list.innerHTML = '<li class="no-contacts">Loading contacts...</li>'; 
 
    fetch('group.php?ajax=contacts', { cache: 'no-store' }) 
    .then(res => res.json()) 
    .then(data => { 
        if (data.ok && data.contacts) { 
            renderContacts(data.contacts); 
        } else { 
            list.innerHTML = '<li class="no-contacts">Could not load contacts.</li>'; 
        } 
    }) 
    .catch(() => { 
        list.innerHTML = '<li class="no-contacts">Error loading contacts.</li>'; 
    }); 
} 
 
function renderContacts(contacts) { 
    const list = document.getElementById('contactsList'); 
    if (!contacts || contacts.length === 0) { 
        list.innerHTML = '<li class="no-contacts">No contacts found. Start a chat with someone first.</li>'; 
        return; 
    } 
 
    const currentMembers = Object.keys(MEMBERS); 
    let html = ''; 
    contacts.forEach(contact => { 
        const alreadyAdded = currentMembers.includes(contact.number); 
        const disabled = alreadyAdded ? 'disabled' : ''; 
        const btnClass = alreadyAdded ? 'added' : ''; 
        const btnText = alreadyAdded ? 'Added' : 'Add'; 
        html += `<li> 
            <span>${escapeHtml(contact.name)} (+${escapeHtml(contact.number)})</span> 
            <button type="button" class="contact-add-btn ${btnClass}" ${disabled} 
                onclick="addContactToGroup('${escapeHtml(contact.number)}', '${escapeHtml(contact.name)}')"> 
                ${btnText} 
            </button> 
        </li>`; 
    }); 
    list.innerHTML = html; 
} 
 
function addContactToGroup(number, name) { 
    // Check again if already a member 
    if (Object.keys(MEMBERS).includes(number)) { 
        alert('Already a member.'); 
        return; 
    } 
 
    const formData = new FormData(); 
    formData.append('action', 'add_member'); 
    formData.append('member_number', number); 
    formData.append('member_name', name); 
 
    fetch('group.php?conversation=' + encodeURIComponent(GROUP_FILE), { 
        method: 'POST', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
        body: formData 
    }) 
    .then(res => res.json()) 
    .then(data => { 
        if (!data.ok) { 
            alert(data.error || 'Unable to add member.'); 
            return; 
        } 
        // Reload the page with new conversation file 
        window.location.href = 'group.php?conversation=' + encodeURIComponent(data.conversation); 
    }) 
    .catch(() => alert('Unable to add member.')); 
} 
 
/* ========================================================= 
   GROUP INFO 
========================================================= */ 
 
function openGroupInfo() { 
 
    refreshGroupState(); 
 
    openModal( 
        'groupInfoModal' 
    ); 
} 
 
function openBigImage(src) { 
 
    document.getElementById( 
        'bigImage' 
    ).src = 
        src; 
 
    openModal( 
        'bigImageModal' 
    ); 
} 
 
 
/* ========================================================= 
   GROUP NAME UPDATE 
========================================================= */ 
 
const groupNameForm = 
    document.getElementById( 
        'groupNameForm' 
    ); 
 
if (groupNameForm) { 
 
    groupNameForm.addEventListener( 
        'submit', 
        function(event) { 
 
            event.preventDefault(); 
 
            const input = 
                document.getElementById( 
                    'groupNameInput' 
                ); 
 
            const newName = 
                input.value.trim(); 
 
            if (!newName) { 
 
                alert( 
                    'Group name cannot be empty.' 
                ); 
 
                return; 
            } 
 
            const formData = 
                new FormData(); 
 
            formData.append( 
                'action', 
                'update_group_name' 
            ); 
 
            formData.append( 
                'group_name', 
                newName 
            ); 
 
            fetch( 
                'group.php?conversation=' + 
                encodeURIComponent( 
                    GROUP_FILE 
                ), 
                { 
                    method: 'POST', 
 
                    headers: { 
                        'X-Requested-With': 
                            'XMLHttpRequest' 
                    }, 
 
                    body: 
                        formData 
                } 
            ) 
            .then( 
                function(response) { 
                    return response.json(); 
                } 
            ) 
            .then( 
                function(data) { 
 
                    if ( 
                        !data.ok 
                    ) { 
 
                        alert( 
                            data.error || 
                            'Unable to update group name.' 
                        ); 
 
                        return; 
                    } 
 
                    GROUP_NAME = 
                        data.group_name; 
 
                    document 
                        .getElementById( 
                            'groupNameDisplay' 
                        ) 
                        .textContent = 
                        data.group_name; 
 
                    document 
                        .getElementById( 
                            'headerGroupName' 
                        ) 
                        .textContent = 
                        data.group_name; 
 
                    document.title = 
                        data.group_name; 
 
                    alert( 
                        'Group name updated successfully.' 
                    ); 
                } 
            ) 
            .catch( 
                function() { 
 
                    alert( 
                        'Unable to update group name.' 
                    ); 
                } 
            ); 
        } 
    ); 
} 
 
 
/* ========================================================= 
   GROUP PROFILE 
========================================================= */ 
 
document.getElementById('groupProfileInput').addEventListener('change', function(e) { 
    const file = e.target.files[0]; 
    const preview = document.getElementById('profilePreview'); 
    const img = document.getElementById('profilePreviewImg'); 
    if (file) { 
        const reader = new FileReader(); 
        reader.onload = function(ev) { 
            img.src = ev.target.result; 
            preview.style.display = 'block'; 
        } 
        reader.readAsDataURL(file); 
    } else { 
        preview.style.display = 'none'; 
    } 
}); 
 
function removeProfilePhoto() { 
    if (!confirm('Remove group profile picture?')) return; 
    const formData = new FormData(); 
    formData.append('action', 'group_profile'); 
    formData.append('remove', '1'); 
    fetch('group.php?conversation=' + encodeURIComponent(GROUP_FILE), { 
        method: 'POST', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
        body: formData 
    }) 
    .then(res => res.json()) 
    .then(data => { 
        if (!data.ok) { 
            alert(data.error || 'Unable to remove profile.'); 
            return; 
        } 
        const profile = document.getElementById('group-profile'); 
        const initial = GROUP_NAME.charAt(0).toUpperCase(); 
        profile.innerHTML = initial; 
        const bigProfile = document.querySelector('#groupInfoModal .big-profile'); 
        bigProfile.innerHTML = '<span id="groupInfoInitial">' + initial + '</span>'; 
        alert('Profile picture removed.'); 
        closeModal(event, 'groupInfoModal'); 
    }) 
    .catch(() => alert('Unable to remove profile.')); 
} 
 
const groupProfileForm = 
    document.getElementById( 
        'groupProfileForm' 
    ); 
 
if (groupProfileForm) { 
 
    groupProfileForm.addEventListener( 
        'submit', 
        function(event) { 
 
            event.preventDefault(); 
 
            const fileInput = document.getElementById('groupProfileInput'); 
            if (!fileInput.files || fileInput.files.length === 0) { 
                alert('Please select an image.'); 
                return; 
            } 
 
            const formData = 
                new FormData( 
                    groupProfileForm 
                ); 
 
            formData.append( 
                'action', 
                'group_profile' 
            ); 
 
            fetch(
                'group.php?conversation=' + 
                encodeURIComponent( 
                    GROUP_FILE 
                ), 
                { 
                    method: 'POST', 
 
                    headers: { 
                        'X-Requested-With': 
                            'XMLHttpRequest' 
                    }, 
 
                    body: 
                        formData 
                } 
            ) 
            .then( 
                function(response) { 
                    return response.json(); 
                } 
            ) 
            .then( 
                function(data) { 
 
                    if ( 
                        !data.ok 
                    ) { 
 
                        alert( 
                            data.error || 
                            'Unable to update group profile.' 
                        ); 
 
                        return; 
                    } 
 
                    const imageUrl = 
                        data.profile + 
                        '?v=' + 
                        Date.now(); 
 
                    const profile = 
                        document.getElementById( 
                            'group-profile' 
                        ); 
 
                    if (profile) { 
 
                        let img = 
                            profile.querySelector( 
                                'img' 
                            ); 
 
                        if (!img) { 
 
                            profile.innerHTML = 
                                '<img alt="Group">'; 
 
                            img = 
                                profile.querySelector( 
                                    'img' 
                                ); 
                        } 
 
                        img.src = 
                            imageUrl; 
                    } 
 
                    const infoImage = 
                        document.getElementById( 
                            'groupInfoImage' 
                        ); 
 
                    if (infoImage) { 
 
                        infoImage.src = 
                            imageUrl; 
 
                    } else { 
 
                        const bigProfile = 
                            document.querySelector( 
                                '#groupInfoModal .big-profile' 
                            ); 
 
                        if (bigProfile) { 
 
                            bigProfile.innerHTML = 
                                '<img id="groupInfoImage" ' + 
                                'src="' + 
                                imageUrl + 
                                '" ' + 
                                'alt="Group" ' + 
                                'onclick="openBigImage(this.src)">'; 
                        } 
                    } 
 
                    alert( 
                        'Group profile updated.' 
                    ); 
                    document.getElementById('profilePreview').style.display = 'none'; 
                    fileInput.value = ''; 
                } 
            ) 
            .catch( 
                function() { 
 
                    alert( 
                        'Unable to update group profile.' 
                    ); 
                } 
            ); 
        } 
    ); 
} 
 
 
/* ========================================================= 
   SEARCH MESSAGES 
========================================================= */ 
 
function searchMessages(query) { 
 
    query = 
        String( 
            query || '' 
        ) 
        .toLowerCase() 
        .trim(); 
 
    const rows = 
        document.querySelectorAll( 
            '.message-row' 
        ); 
 
    let found = 
        false; 
 
    rows.forEach( 
        function(row) { 
 
            const text = 
                ( 
                    row.textContent 
                    || '' 
                ).toLowerCase(); 
 
            const show = 
                !query || 
                text.includes( 
                    query 
                ); 
 
            row.style.display = 
                show 
                ? '' 
                : 'none'; 
 
            if ( 
                show && 
                query 
            ) { 
                found = true; 
            } 
        } 
    ); 
} 
 
 
/* ========================================================= 
   MODAL OUTSIDE CLICK 
========================================================= */ 
 
document 
    .querySelectorAll( 
        '.modal' 
    ) 
    .forEach( 
        function(modal) { 
 
            modal.addEventListener( 
                'click', 
                function(event) { 
 
                    if ( 
                        event.target === 
                        modal 
                    ) { 
 
                        modal.classList.remove( 
                            'show' 
                        ); 
                    } 
                } 
            ); 
        } 
    ); 
 
 
/* ========================================================= 
   INITIAL SCROLL 
========================================================= */ 
 
setTimeout( 
    function() { 
        scrollToBottom(true); 
    }, 
    50 
); 
 
 
/* ========================================================= 
   START POLLING 
========================================================= */ 
 
refreshMessages(); 
 
setInterval( 
    refreshMessages, 
    1500 
); 
 
refreshGroupState(); 
 
setInterval( 
    refreshGroupState, 
    2000 
); 
 
refreshTypingStatus(); 
 
setInterval( 
    refreshTypingStatus, 
    1000 
); 
 
refreshPresence(); 
 
setInterval( 
    refreshPresence, 
    2000 
); 
 
 
/* ========================================================= 
   BEFORE UNLOAD 
========================================================= */ 
 
window.addEventListener( 
    'beforeunload', 
    function() { 
 
        sendTypingState( 
            false 
        ); 
    } 
); 
 
 
/* ========================================================= 
   MEMBER NUMBER 
========================================================= */ 
 
const memberNumber = 
    document.getElementById( 
        'memberNumber' 
    ); 
 
if (memberNumber) { 
 
    memberNumber.addEventListener( 
        'input', 
        function() { 
 
            this.value = 
                this.value.replace( 
                    /\D/g, 
                    '' 
                ); 
        } 
    ); 
} 
 
</script> 
 
</body> 
</html>