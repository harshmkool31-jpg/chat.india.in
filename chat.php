<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
date_default_timezone_set('Asia/Kolkata');

ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

/* =========================================================
   PATHS & HELPERS
========================================================= */

function getDataDir() { return __DIR__ . '/data'; }
function getUserStorePath() { return getDataDir() . '/users.json'; }
function getTypingPath() { return getDataDir() . '/typing.json'; }
function getPresencePath() { return getDataDir() . '/presence.json'; }
function getGroupPhotoDir() { return getDataDir() . '/group_profiles'; }

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

/* =========================================================
   PRESENCE
========================================================= */
function loadPresence() { return loadJson(getPresencePath(), []); }
function savePresence(array $presence) { saveJson(getPresencePath(), $presence); }
function updatePresence($number, $name, $page = 'chat', $conversation = '') {
    $number = safeNumber($number);
    if ($number === '') return;
    $presence = loadPresence();
    $presence[$number] = ['number'=>$number,'name'=>$name,'page'=>$page,'conversation'=>$conversation,'last_seen'=>time()];
    savePresence($presence);
}

/* =========================================================
   TYPING
========================================================= */
function loadTyping() { return loadJson(getTypingPath(), []); }
function saveTyping(array $typing) { saveJson(getTypingPath(), $typing); }
function cleanupTyping() {
    $typing = loadTyping(); $now = time();
    foreach ($typing as $key => &$items) {
        foreach ($items as $num => &$entry) {
            if ((int)($entry['updated_at'] ?? 0) < $now - 8) unset($items[$num]);
        }
        if (empty($items)) unset($typing[$key]);
    }
    unset($items, $entry);
    saveTyping($typing);
}

/* =========================================================
   USERS
========================================================= */
function loadUsers() { return loadJson(getUserStorePath(), []); }
function saveUsers(array $users) { saveJson(getUserStorePath(), $users); }

function buildUserStorageDirectory($number) {
    $folderName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$number);
    $folderName = trim($folderName, '_');
    if ($folderName === '') $folderName = 'user';
    $directory = getDataDir() . '/' . $folderName;
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    return $directory;
}

/* =========================================================
   PROFILE PHOTO
========================================================= */
function getProfilePhotoPath(array $users, $number) {
    return trim((string)($users[$number]['profile_photo'] ?? ''));
}

function saveProfilePhoto($attachment, $number) {
    if (!is_array($attachment) || empty($attachment['tmp_name'])) return ['ok'=>false,'error'=>'No photo selected.'];
    $errorCode = (int)($attachment['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) return ['ok'=>false,'error'=>'Photo upload failed.'];
    $size = (int)($attachment['size'] ?? 0);
    if ($size <= 0) return ['ok'=>false,'error'=>'The selected photo is empty.'];
    if ($size > 2*1024*1024) return ['ok'=>false,'error'=>'Image size exceeds 2MB.'];
    $extension = strtolower(pathinfo($attachment['name'] ?? 'profile.jpg', PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($extension, $allowed, true)) return ['ok'=>false,'error'=>'Only JPG, PNG, GIF and WEBP are allowed.'];
    $uploadDir = getDataDir() . '/profile_photos';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$number);
    $fileName = $safeNumber . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
    $destination = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($attachment['tmp_name'], $destination)) return ['ok'=>false,'error'=>'Unable to save profile photo.'];
    return ['ok'=>true,'path'=>'data/profile_photos/'.$fileName];
}

/* =========================================================
   GROUP PROFILE PHOTO
========================================================= */
function saveGroupPhoto($attachment, $groupFile) {
    if (!is_array($attachment) || empty($attachment['tmp_name'])) return ['ok'=>true,'path'=>''];
    $errorCode = (int)($attachment['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) return ['ok'=>true,'path'=>''];
    if ($errorCode !== UPLOAD_ERR_OK) return ['ok'=>false,'error'=>'Group profile photo upload failed.'];
    $size = (int)($attachment['size'] ?? 0);
    if ($size <= 0) return ['ok'=>false,'error'=>'The group profile photo is empty.'];
    if ($size > 5*1024*1024) return ['ok'=>false,'error'=>'Group profile photo must be 5MB or less.'];
    $extension = strtolower(pathinfo($attachment['name'] ?? 'group.jpg', PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($extension, $allowed, true)) return ['ok'=>false,'error'=>'Only JPG, PNG, GIF and WEBP are allowed.'];
    $uploadDir = getGroupPhotoDir();
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeFile = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($groupFile, PATHINFO_FILENAME));
    $fileName = $safeFile . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
    $destination = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($attachment['tmp_name'], $destination)) return ['ok'=>false,'error'=>'Unable to save group profile photo.'];
    return ['ok'=>true,'path'=>'data/group_profiles/'.$fileName];
}

/* =========================================================
   PRIVATE CONVERSATION HELPERS
========================================================= */
function buildConversationFileName($a, $b) {
    $numbers = [safeNumber($a), safeNumber($b)];
    $numbers = array_values(array_filter(array_unique($numbers)));
    sort($numbers, SORT_STRING);
    return implode('_', $numbers) . '.json';
}

function getConversationTitle(array $participants, $senderNumber) {
    $others = [];
    foreach ($participants as $number => $name) {
        if ((string)$number === (string)$senderNumber) continue;
        $name = trim((string)$name);
        $others[] = $name !== '' ? $name . ' ' : $number;
    }
    if (empty($others)) {
        return 'You'; // Self-chat
    }
    return count($others) === 1 ? $others[0] : implode(', ', $others);
}

function getConversationKey(array $chatData, $fileName) {
    $participants = $chatData['participants'] ?? [];
    $numbers = [];
    foreach ($participants as $number => $name) {
        $clean = safeNumber($number);
        if ($clean !== '') $numbers[] = $clean;
    }
    sort($numbers, SORT_STRING);
    $type = ($chatData['type'] ?? 'private') === 'group' ? 'group' : 'private';
    return $type . ':' . implode(',', $numbers);
}

function ensurePrivateConversationFiles($senderNumber,$senderName,$receiverNumber,$receiverName,$senderFolder,$receiverFolder) {
    $fileName = buildConversationFileName($senderNumber,$receiverNumber);
    foreach ([ [$senderFolder,$senderNumber,$senderName], [$receiverFolder,$receiverNumber,$receiverName] ] as $info) {
        $folder = $info[0]; $filePath = $folder.'/'.$fileName;
        $data = file_exists($filePath) ? json_decode(file_get_contents($filePath),true) : ['type'=>'private','participants'=>[],'messages'=>[]];
        if (!is_array($data)) $data = ['type'=>'private','participants'=>[],'messages'=>[]];
        $data['type'] = 'private';
        $data['participants'][$senderNumber] = $senderName;
        $data['participants'][$receiverNumber] = $receiverName;
        $data['messages'] = $data['messages'] ?? [];
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $fileName;
}

/* =========================================================
   GROUP CONVERSATION HELPERS
========================================================= */
function buildGroupConversationFileName($senderNumber, array $participantNumbers) {
    $numbers = array_merge([$senderNumber], $participantNumbers);
    $numbers = array_unique($numbers);
    $numbers = array_map('safeNumber', $numbers);
    $numbers = array_filter($numbers);
    sort($numbers, SORT_STRING);
    return implode('_', $numbers) . '.json';
}

function ensureGroupConversationFiles($senderNumber,$senderName,array $participants,$groupName='',$groupPhoto='') {
    $fileName = buildGroupConversationFileName($senderNumber, array_keys($participants));
    $allParticipants = $participants; $allParticipants[$senderNumber] = $senderName;
    foreach ($allParticipants as $number => $name) {
        $folder = buildUserStorageDirectory($number);
        $filePath = $folder.'/'.$fileName;
        $data = file_exists($filePath) ? json_decode(file_get_contents($filePath),true) : ['type'=>'group','group_name'=>$groupName,'group_profiles'=>$groupPhoto,'participants'=>[],'messages'=>[]];
        if (!is_array($data)) $data = ['type'=>'group','group_name'=>$groupName,'group_profiles'=>$groupPhoto,'participants'=>[],'messages'=>[]];
        $data['type'] = 'group';
        if (trim($groupName) !== '' || empty($data['group_name'])) $data['group_name'] = trim($groupName);
        if (trim($groupPhoto) !== '' || empty($data['group_profiles'])) $data['group_profiles'] = trim($groupPhoto);
        $data['participants'] = $allParticipants;
        $data['messages'] = $data['messages'] ?? [];
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $fileName;
}

/* =========================================================
   MARK CONVERSATION AS READ
========================================================= */
function markConversationRead($senderNumber, $conversationFile) {
    $folder = buildUserStorageDirectory($senderNumber);
    $filePath = $folder . '/' . $conversationFile;
    if (!file_exists($filePath)) {
        return ['ok' => false, 'error' => 'Conversation not found'];
    }
    $data = json_decode(file_get_contents($filePath), true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid conversation data'];
    }
    $participants = $data['participants'] ?? [];
    if (!isset($participants[$senderNumber])) {
        return ['ok' => false, 'error' => 'User not in conversation'];
    }
    $isGroup = ($data['type'] ?? 'private') === 'group';
    $messages = $data['messages'] ?? [];
    $updated = false;
    foreach ($messages as &$msg) {
        if ($isGroup) {
            if (!isset($msg['read_by']) || !in_array($senderNumber, $msg['read_by'])) {
                if (($msg['sender_number'] ?? '') !== $senderNumber) {
                    $msg['read_by'][] = $senderNumber;
                    $updated = true;
                }
            }
        } else {
            if (($msg['sender_number'] ?? '') !== $senderNumber && empty($msg['read'])) {
                $msg['read'] = true;
                $updated = true;
            }
        }
    }
    unset($msg);
    if ($updated) {
        $data['messages'] = $messages;
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return ['ok' => true, 'updated' => $updated];
}

/* =========================================================
   AUTHENTICATION
========================================================= */
$errors = [];
$senderNumber = safeNumber($_SESSION['sender_number'] ?? '');
$senderName = trim($_SESSION['sender_name'] ?? '');
$password = trim($_SESSION['password'] ?? '');
if ($senderNumber === '' || $senderName === '' || $password === '') {
    header('Location: index.php');
    exit;
}
$users = loadUsers();
$senderFolder = buildUserStorageDirectory($senderNumber);
updatePresence($senderNumber, $senderName, 'chat', '');
cleanupTyping();

/* =========================================================
   POST / AJAX HANDLING
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Presence ping
    if (isset($_POST['presence_ping'])) {
        updatePresence($senderNumber, $senderName, 'chat', '');
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]);
        exit;
    }
    // Profile photo
    if (isset($_POST['action']) && $_POST['action'] === 'profile_photo') {
        $photo = $_FILES['profile_photo'] ?? null;
        $result = saveProfilePhoto($photo, $senderNumber);
        if ($result['ok']) {
            if (!isset($users[$senderNumber])) $users[$senderNumber] = [];
            $users[$senderNumber]['profile_photo'] = $result['path'];
            saveUsers($users);
            $_SESSION['success'] = 'Profile photo updated successfully.';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        header('Location: chat.php');
        exit;
    }
    // Feedback
    if (isset($_POST['action']) && $_POST['action'] === 'feedback') {
        $feedback = trim($_POST['feedback'] ?? '');
        if ($feedback !== '') {
            $feedbackDir = getDataDir() . '/feedback';
            if (!is_dir($feedbackDir)) mkdir($feedbackDir, 0777, true);
            $feedbackFile = $feedbackDir . '/feedback.json';
            $allFeedback = file_exists($feedbackFile) ? json_decode(file_get_contents($feedbackFile),true) : [];
            if (!is_array($allFeedback)) $allFeedback = [];
            $allFeedback[] = ['sender_number'=>$senderNumber,'sender_name'=>$senderName,'feedback'=>$feedback,'time'=>date('Y-m-d H:i:s')];
            file_put_contents($feedbackFile, json_encode($allFeedback, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
            $_SESSION['success'] = 'Thank you! Your feedback has been saved.';
        }
        header('Location: chat.php');
        exit;
    }

    $chatType = $_POST['chat_type'] ?? 'private';
    if ($chatType === 'group') {
        $groupName = trim($_POST['group_name'] ?? '');
        if ($groupName === '') $errors[] = 'Please enter a group name.';
        // Get members from JSON string
        $membersJson = trim($_POST['group_members_json'] ?? '');
        $participants = [];
        if ($membersJson !== '') {
            $members = json_decode($membersJson, true);
            if (is_array($members)) {
                foreach ($members as $member) {
                    $number = safeNumber($member['number'] ?? '');
                    $name = trim($member['name'] ?? '');
                    if ($number !== '' && $name !== '' && (string)$number !== (string)$senderNumber) {
                        if (isset($users[$number])) {
                            $participants[$number] = $name;
                        }
                    }
                }
            }
        }
        if (empty($participants)) $errors[] = 'Please add at least one valid member.';
        if (empty($errors)) {
            $groupFile = buildGroupConversationFileName($senderNumber, array_keys($participants));
            $groupPhotoFile = $_FILES['group_profiles'] ?? null;
            $photoResult = saveGroupPhoto($groupPhotoFile, $groupFile);
            if (!$photoResult['ok']) $errors[] = $photoResult['error'];
            else {
                $groupPhoto = $photoResult['path'];
                if ($groupPhoto === '') {
                    $existingPhoto = '';
                    foreach ($participants as $number => $name) {
                        $existingPath = buildUserStorageDirectory($number).'/'.$groupFile;
                        if (file_exists($existingPath)) {
                            $existingData = json_decode(file_get_contents($existingPath), true);
                            if (is_array($existingData) && !empty($existingData['group_profiles'])) {
                                $existingPhoto = $existingData['group_profiles'];
                                break;
                            }
                        }
                    }
                    $groupPhoto = $existingPhoto;
                }
            }
        }
        if (empty($errors)) {
            $groupParticipants = $participants; $groupParticipants[$senderNumber] = $senderName;
            $_SESSION['chat_type'] = 'group';
            $_SESSION['group_name'] = $groupName;
            $_SESSION['group_profiles'] = $groupPhoto;
            $_SESSION['group_participants'] = $groupParticipants;
            $groupFile = ensureGroupConversationFiles($senderNumber, $senderName, $participants, $groupName, $groupPhoto);
            $_SESSION['group_conversation_file'] = $groupFile;
            header('Location: group.php?conversation='.urlencode($groupFile));
            exit;
        }
    } else {
        // Private – now we only receive receiver_number, get name from users
        $receiverNumber = safeNumber($_POST['receiver_number'] ?? '');
        if ($receiverNumber === '') {
            $errors[] = 'Please enter receiver number.';
        } elseif ((string)$receiverNumber === (string)$senderNumber) {
            $errors[] = 'You cannot start a chat with yourself.';
        } elseif (!isset($users[$receiverNumber])) {
            $errors[] = 'This receiver number does not exist.';
        } else {
            $receiverName = $users[$receiverNumber]['name'] ?? '';
            if ($receiverName === '') $receiverName = $receiverNumber;
            $_SESSION['chat_type'] = 'private';
            $_SESSION['private_receiver_number'] = $receiverNumber;
            $_SESSION['private_receiver_name'] = $receiverName;
            $receiverFolder = buildUserStorageDirectory($receiverNumber);
            ensurePrivateConversationFiles($senderNumber,$senderName,$receiverNumber,$receiverName,$senderFolder,$receiverFolder);
            header('Location: private_chat.php');
            exit;
        }
    }
}

/* =========================================================
   GET USER INFO API
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_user_info') {
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
   GET CONTACTS API
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'get_contacts') {
    $contacts = [];
    $folder = $senderFolder;
    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !preg_match('/\.json$/i', $file)) continue;
            $filePath = $folder . '/' . $file;
            $data = json_decode(file_get_contents($filePath), true);
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
   MARK READ API (GET)
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    $file = trim($_GET['file'] ?? '');
    if ($file === '') {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false, 'error'=>'No file specified']);
        exit;
    }
    $result = markConversationRead($senderNumber, $file);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

/* =========================================================
   LOAD CONVERSATIONS
========================================================= */
function getConversationList($senderNumber, $senderFolder, $users) {
    $conversationMap = [];
    if (is_dir($senderFolder)) {
        $files = scandir($senderFolder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !preg_match('/\.json$/i', $file)) continue;
            $filePath = $senderFolder.'/'.$file;
            $chatData = json_decode(file_get_contents($filePath), true);
            if (!is_array($chatData)) continue;
            if (!isset($chatData['type']) || !in_array($chatData['type'], ['private','group'], true)) continue;
            $messages = $chatData['messages'] ?? [];
            $lastMessage = !empty($messages) ? end($messages) : null;
            $unread = 0;
            $isGroup = ($chatData['type'] === 'group');
            foreach ($messages as $msg) {
                if (($msg['sender_number'] ?? '') !== $senderNumber) {
                    if ($isGroup) {
                        if (!isset($msg['read_by']) || !in_array($senderNumber, $msg['read_by'])) $unread++;
                    } else {
                        if (empty($msg['read'])) $unread++;
                    }
                }
            }
            $lastMessageId = $lastMessage ? ($lastMessage['id'] ?? '') : '';
            $lastMessageSender = $lastMessage ? ($lastMessage['sender_number'] ?? '') : '';
            $lastMessageSenderName = $lastMessage ? ($lastMessage['sender_name'] ?? '') : '';
            
            // Detect self-chat: private conversation with only the sender as participant
            $isSelf = false;
            if (!$isGroup) {
                $participants = $chatData['participants'] ?? [];
                if (count($participants) === 1 && isset($participants[$senderNumber])) {
                    $isSelf = true;
                }
            }
            
            $conversation = [
                'file' => $file,
                'participants' => $chatData['participants'] ?? [],
                'messages' => $messages,
                'last_message' => $lastMessage,
                'is_group' => $isGroup,
                'is_self' => $isSelf,
                'group_name' => trim((string)($chatData['group_name'] ?? '')),
                'group_profiles' => trim((string)($chatData['group_profiles'] ?? '')),
                'mtime' => filemtime($filePath),
                'unread' => $unread,
                'last_message_id' => $lastMessageId,
                'last_message_sender' => $lastMessageSender,
                'last_message_sender_name' => $lastMessageSenderName,
            ];
            $key = getConversationKey($chatData, $file);
            if (!isset($conversationMap[$key]) || $conversation['mtime'] > $conversationMap[$key]['mtime']) {
                $conversationMap[$key] = $conversation;
            }
        }
    }
    $list = array_values($conversationMap);
    usort($list, function($a,$b){ return $b['mtime'] <=> $a['mtime']; });
    return $list;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_conversations') {
    $conversationList = getConversationList($senderNumber, $senderFolder, $users);
    $output = [];
    foreach ($conversationList as $conv) {
        $isGroup = !empty($conv['is_group']);
        $isSelf = !empty($conv['is_self']);
        if ($isGroup) {
            $title = trim((string)($conv['group_name'] ?? ''));
            if ($title === '') $title = 'Unnamed Group';
        } elseif ($isSelf) {
            $title = 'You';
        } else {
            $title = getConversationTitle($conv['participants'] ?? [], $senderNumber);
        }
        $privatePartner = null;
        if (!$isGroup && !$isSelf) {
            foreach ($conv['participants'] ?? [] as $number => $name) {
                if ((string)$number !== (string)$senderNumber) {
                    $privatePartner = ['number'=>(string)$number,'name'=>trim((string)$name)];
                    break;
                }
            }
        }
        $lastMessage = $conv['last_message'] ?? null;
        $preview = ''; $lastTime = '';
        if (is_array($lastMessage)) {
            $preview = trim((string)($lastMessage['message'] ?? ''));
            if ($preview === '') {
                if (!empty($lastMessage['attachment'])) $preview = '📎 Attachment';
                else $preview = 'Message';
            }
            $timestamp = $lastMessage['time'] ?? $lastMessage['created_at'] ?? '';
            if ($timestamp !== '') {
                $parsed = strtotime($timestamp);
                if ($parsed) $lastTime = date('d M, H:i', $parsed);
            }
        }
        $chatUrl = $isGroup ? 'group.php' : 'private_chat.php';
        $partnerPhoto = '';
        if ($isSelf) {
            // Use sender's own photo for self-chat
            $partnerPhoto = getProfilePhotoPath($users, $senderNumber);
        } elseif (!$isGroup && $privatePartner !== null) {
            $partnerPhoto = getProfilePhotoPath($users, $privatePartner['number']);
        }
        $output[] = [
            'file' => $conv['file'],
            'title' => $title,
            'is_group' => $isGroup,
            'is_self' => $isSelf,
            'group_name' => $isGroup ? ($conv['group_name'] ?? '') : '',
            'group_profiles' => $isGroup ? ($conv['group_profiles'] ?? '') : '',
            'private_partner' => $privatePartner,
            'partner_photo' => $partnerPhoto,
            'last_message_preview' => $preview,
            'last_time' => $lastTime,
            'unread' => $conv['unread'],
            'chat_url' => $chatUrl,
            'participants' => array_keys($conv['participants'] ?? []),
            'last_message_id' => $conv['last_message_id'],
            'last_message_sender' => $conv['last_message_sender'],
            'last_message_sender_name' => $conv['last_message_sender_name'],
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'conversations'=>$output]);
    exit;
}

/* =========================================================
   NORMAL PAGE LOAD
========================================================= */
$conversationList = getConversationList($senderNumber, $senderFolder, $users);
$senderPhoto = getProfilePhotoPath($users, $senderNumber);
$success = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat • <?= esc($senderName) ?></title>
<link rel="icon" type="image/png" href="../images/hhh%20picture.png">
<link rel="manifest" href="mainfest.json">
<style>
/* =========================================================
   RESET
========================================================= */
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    min-height: 100vh;
    font-family: Inter, Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #f7f9fc, #edf3ff);
    color: #172033;
}
button, input, textarea { font-family: inherit; }
.app { width: 100%; min-height: 100vh; }

/* =========================================================
   HEADER
========================================================= */
.header {
    position: sticky;
    top: 0;
    z-index: 100;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 clamp(15px, 4vw, 40px);
    background: rgba(255,255,255,.90);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid #e8edf5;
    box-shadow: 0 5px 25px rgba(15,23,42,.05);
}
.brand {
    display: flex;
    align-items: center;
    gap: 11px;
}
.brand-logo {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    font-size: 21px;
    box-shadow: 0 7px 18px rgba(37,99,235,.20);
}
.brand-text strong { display: block; font-size: 17px; }
.brand-text span { color: #8792a5; font-size: 11px; }
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.icon-btn {
    width: 43px;
    height: 43px;
    border: 1px solid #e3e8f0;
    border-radius: 13px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 19px;
    transition: .2s ease;
}
.icon-btn:hover {
    transform: translateY(-2px);
    border-color: #bcd1ff;
    box-shadow: 0 8px 20px rgba(37,99,235,.10);
}

/* =========================================================
   MAIN
========================================================= */
.main {
    width: min(1150px, 100%);
    margin: auto;
    padding: clamp(15px, 3vw, 30px);
}

/* =========================================================
   PROFILE
========================================================= */
.profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: clamp(18px, 3vw, 28px);
    margin-bottom: 18px;
    border: 1px solid #e7ebf3;
    border-radius: 23px;
    background: rgba(255,255,255,.88);
    box-shadow: 0 12px 35px rgba(15,23,42,.06);
}
.profile-left {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}
.avatar {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    background: linear-gradient(135deg, #dbeafe, #ede9fe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-weight: 800;
    box-shadow: 0 5px 18px rgba(15,23,42,.12);
}
.profile-name { min-width: 0; }
.profile-name strong {
    display: block;
    font-size: clamp(17px, 3vw, 22px);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.profile-name span {
    display: block;
    margin-top: 4px;
    color: #7c8798;
    font-size: 12px;
}
.online {
    display: inline-flex !important;
    align-items: center;
    gap: 5px;
    color: #16a34a !important;
}
.online::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 4px rgba(34,197,94,.10);
}

/* =========================================================
   QUICK ACTIONS
========================================================= */
.quick-actions {
    display: flex;
    gap: 8px;
}
.quick-btn {
    border: 1px solid #e1e7f0;
    background: white;
    padding: 10px 13px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    color: #334155;
    transition: .2s;
}
.quick-btn:hover {
    border-color: #bfdbfe;
    color: #2563eb;
    transform: translateY(-1px);
}

/* =========================================================
   ALERT
========================================================= */
.alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 13px;
    font-size: 13px;
}
.alert.success { color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; }
.alert.error { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }

/* =========================================================
   SEARCH
========================================================= */
.search-wrapper {
    position: relative;
    margin-bottom: 17px;
}
.search-wrapper input {
    width: 100%;
    padding: 15px 17px 15px 47px;
    border: 1px solid #e0e6ef;
    border-radius: 15px;
    outline: none;
    background: white;
    font-size: 14px;
    box-shadow: 0 5px 18px rgba(15,23,42,.04);
    transition: .2s;
}
.search-wrapper input:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 4px rgba(59,130,246,.08);
}
.search-icon {
    position: absolute;
    left: 17px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

/* =========================================================
   SECTION
========================================================= */
.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.section-title h2 { margin: 0; font-size: 18px; }
.section-title span { color: #8b95a5; font-size: 12px; }

/* =========================================================
   CHAT LIST
========================================================= */
.chat-list {
    display: flex;
    flex-direction: column;
    gap: 9px;
}
.chat-item-form { display: contents; }
.chat-item {
    display: flex;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 13px;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e8edf4;
    border-radius: 17px;
    background: white;
    transition: transform .18s, box-shadow .18s, border-color .18s;
    cursor: pointer;
}
.chat-item:hover {
    transform: translateY(-2px);
    border-color: #c9dcff;
    box-shadow: 0 10px 25px rgba(37,99,235,.08);
}

/* =========================================================
   CHAT AVATAR
========================================================= */
.chat-avatar {
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    border-radius: 50%;
    object-fit: cover;
    background: linear-gradient(135deg, #e0e7ff, #dbeafe);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #2563eb;
    border: 2px solid #fff;
    box-shadow: 0 3px 12px rgba(15,23,42,.08);
}
.group-avatar {
    background: linear-gradient(135deg, #ede9fe, #dbeafe);
    color: #6366f1;
    font-size: 21px;
}

/* =========================================================
   CHAT INFO
========================================================= */
.chat-info {
    min-width: 0;
    flex: 1;
}
.chat-name-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}
.chat-name {
    font-size: 14px;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-time {
    flex: 0 0 auto;
    color: #a0a9b8;
    font-size: 10px;
}
.chat-preview {
    margin-top: 5px;
    color: #7a8597;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-badge {
    min-width: 21px;
    height: 21px;
    padding: 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    font-size: 10px;
    font-weight: 800;
}
.conversation-label {
    font-size: 9px;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 8px;
    margin-left: auto;
    white-space: nowrap;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* =========================================================
   EMPTY
========================================================= */
.empty {
    text-align: center;
    padding: 55px 20px;
    background: white;
    border: 1px dashed #d7dee9;
    border-radius: 18px;
    color: #7c8797;
}
.empty-icon { font-size: 38px; margin-bottom: 10px; }
.empty strong { display: block; color: #334155; margin-bottom: 5px; }

/* =========================================================
   FAB
========================================================= */
.fab {
    position: fixed;
    right: 25px;
    bottom: 25px;
    width: 58px;
    height: 58px;
    border: 0;
    border-radius: 18px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 12px 30px rgba(37,99,235,.30);
    z-index: 90;
    transition: .2s;
}
.fab:hover { transform: translateY(-3px) scale(1.03); }

/* =========================================================
   SIDE MENU
========================================================= */
.menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.38);
    backdrop-filter: blur(4px);
    z-index: 300;
    opacity: 0;
    pointer-events: none;
    transition: .25s;
}
.menu-overlay.active {
    opacity: 1;
    pointer-events: auto;
}
.menu-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: min(370px, 92vw);
    height: 100vh;
    background: #fff;
    z-index: 301;
    transform: translateX(100%);
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    box-shadow: -15px 0 45px rgba(15,23,42,.15);
    overflow-y: auto;
}
.menu-panel.active { transform: translateX(0); }
.menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px;
    border-bottom: 1px solid #edf0f5;
}
.menu-header strong { font-size: 20px; }
.close-menu {
    width: 39px;
    height: 39px;
    border: 1px solid #e5eaf1;
    background: white;
    border-radius: 11px;
    cursor: pointer;
    font-size: 20px;
}
.menu-profile {
    margin: 17px;
    padding: 17px;
    border-radius: 17px;
    background: linear-gradient(135deg, #eff6ff, #f5f3ff);
    display: flex;
    align-items: center;
    gap: 12px;
}
.menu-profile img, .menu-profile .avatar {
    width: 48px;
    height: 48px;
    flex-basis: 48px;
}
.menu-profile strong { display: block; }
.menu-profile span { color: #718096; font-size: 11px; }

/* =========================================================
   MENU ITEMS
========================================================= */
.menu-section { padding: 5px 12px; }
.menu-label {
    padding: 12px 10px 7px;
    color: #98a2b3;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
}
.menu-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 12px;
    border: 0;
    border-radius: 13px;
    background: transparent;
    color: #334155;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 650;
    text-align: left;
    transition: .15s;
}
.menu-item:hover {
    background: #f5f8ff;
    color: #2563eb;
}
.menu-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 17px;
}
.menu-item:hover .menu-icon { background: #e8f0ff; }
.menu-danger { color: #dc2626; }
.menu-danger:hover { background: #fef2f2; color: #dc2626; }

/* =========================================================
   MODAL
========================================================= */
.modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15,23,42,.45);
    backdrop-filter: blur(5px);
    z-index: 500;
    opacity: 0;
    pointer-events: none;
    transition: .2s;
}
.modal.active {
    opacity: 1;
    pointer-events: auto;
}
.modal-card {
    width: min(520px, 100%);
    max-height: 90vh;
    overflow-y: auto;
    padding: 23px;
    background: white;
    border-radius: 22px;
    box-shadow: 0 25px 70px rgba(15,23,42,.25);
    transform: translateY(15px) scale(.98);
    transition: .2s;
}
.modal.active .modal-card {
    transform: translateY(0) scale(1);
}
.modal-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 17px;
}
.modal-top h3 { margin: 0; font-size: 19px; }
.modal-close {
    width: 36px;
    height: 36px;
    border: 1px solid #e4e8ef;
    border-radius: 10px;
    background: white;
    cursor: pointer;
}

/* =========================================================
   FORM
========================================================= */
.form-group { margin-bottom: 13px; }
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #596579;
    font-weight: 700;
}
.form-group input, .form-group textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dce2eb;
    border-radius: 11px;
    outline: none;
    font: inherit;
    background: #fff;
}
.form-group input:focus, .form-group textarea:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(59,130,246,.08);
}
.form-group textarea { min-height: 120px; resize: vertical; }
.primary-btn {
    width: 100%;
    border: 0;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: white;
    font-weight: 800;
    cursor: pointer;
}
.photo-select {
    width: 100%;
    display: block;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: white;
    font-weight: 800;
    text-align: center;
    cursor: pointer;
}
.group-photo-preview {
    display: none;
    margin: 10px auto 15px;
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 7px 25px rgba(15,23,42,.16);
}

/* Member list and contacts styles */
.member-list {
    margin: 10px 0;
    padding: 0;
    list-style: none;
}
.member-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.member-list li .remove-member {
    color: #dc2626;
    cursor: pointer;
    font-weight: bold;
    background: none;
    border: none;
    font-size: 16px;
}
.member-list li .remove-member:hover { color: #b91c1c; }
.member-add-row {
    display: flex;
    gap: 8px;
}
.member-add-row input {
    flex: 1;
    padding: 10px;
    border: 1px solid #dce2eb;
    border-radius: 10px;
}
.member-add-row button {
    padding: 10px 16px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
}
.member-feedback {
    font-size: 12px;
    margin-top: 4px;
    min-height: 20px;
}
.member-feedback.exists { color: #16a34a; }
.member-feedback.not-exists { color: #dc2626; }

.contacts-list {
    margin: 8px 0;
    padding: 0;
    list-style: none;
    max-height: 180px;
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
.contacts-list li:last-child { border-bottom: none; }
.contacts-list li .contact-add-btn {
    padding: 4px 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
}
.contacts-list li .contact-add-btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}
.contacts-list li .contact-add-btn.added {
    background: #22c55e;
}
.contacts-list .no-contacts {
    padding: 12px;
    color: #94a3b8;
    text-align: center;
    font-style: italic;
}

/* =========================================================
   MOBILE
========================================================= */
@media (max-width:700px) {
    .header { height: 65px; }
    .brand-text span { display: none; }
    .brand-logo { width: 40px; height: 40px; }
    .profile-card { align-items: flex-start; flex-direction: column; }
    .quick-actions { width: 100%; }
    .quick-btn { flex: 1; }
    .main { padding: 13px; }
    .fab { right: 17px; bottom: 17px; }
}
@media (max-width:430px) {
    .profile-card { border-radius: 18px; }
    .chat-item { padding: 11px; }
    .chat-avatar { width: 48px; height: 48px; flex-basis: 48px; }
    .header-actions .icon-btn:first-child { display: none; }
    .menu-panel { width: 94vw; }
    .conversation-label { display: none; }
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
<div class="app">
<!-- HEADER -->
<header class="header">
    <div class="brand">
        <div class="brand-logo">💬</div>
        <div class="brand-text">
            <strong>Chat</strong>
            <span>Private & Group Messaging</span>
        </div>
    </div>
    <div class="header-actions">
        <button class="icon-btn" type="button" onclick="openFeedback()" title="Feedback">📝</button>
        <button class="icon-btn" type="button" onclick="openMenu()" title="Menu">☰</button>
    </div>
</header>

<!-- MAIN -->
<main class="main">
<?php if ($success !== ''): ?>
    <div class="alert success">✅ <?= esc($success) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert error">⚠️ <?= esc($errorMessage) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert error">⚠️ <?= esc($errors[0]) ?></div>
<?php endif; ?>

<!-- PROFILE -->
<section class="profile-card">
    <div class="profile-left">
        <?php if ($senderPhoto !== ''): ?>
            <img src="<?= esc($senderPhoto) ?>" class="avatar" alt="Profile" onclick="openProfileImage()" style="cursor:pointer;">
        <?php else: ?>
            <div class="avatar"><?= esc(strtoupper(substr($senderName !== '' ? $senderName : $senderNumber, 0, 1))) ?></div>
        <?php endif; ?>
        <div class="profile-name">
            <strong><?= esc($senderName) ?></strong>
            <span>+<?= esc($senderNumber) ?></span>
            <span class="online">Online</span>
        </div>
    </div>
    <div class="quick-actions">
        <button type="button" class="quick-btn" id="newPrivateButton">➕ Private</button>
        <button type="button" class="quick-btn" id="newGroupButton">👥 Group</button>
        <button type="button" class="quick-btn" onclick="window.location.href='ai_chat.php'">🤖 AI Chat</button>
    </div>
</section>

<!-- SEARCH -->
<div class="search-wrapper">
    <span class="search-icon">🔎</span>
    <input type="search" id="chat_search" placeholder="Search chats, names or numbers..." autocomplete="off">
</div>

<!-- RECENT CHATS -->
<div class="section-title">
    <h2>Recent chats</h2>
    <span id="chatCount"><?= count($conversationList) ?> conversations</span>
</div>

<div class="chat-list" id="chatList">
<?php if (!empty($conversationList)): ?>
<?php foreach ($conversationList as $conversation): ?>
<?php
$isGroup = !empty($conversation['is_group']);
$isSelf = !empty($conversation['is_self']);
if ($isGroup) {
    $title = trim((string)($conversation['group_name'] ?? ''));
    if ($title === '') $title = 'Unnamed Group';
} elseif ($isSelf) {
    $title = 'You';
} else {
    $title = getConversationTitle($conversation['participants'] ?? [], $senderNumber);
}
$privatePartner = null;
if (!$isGroup && !$isSelf) {
    foreach ($conversation['participants'] ?? [] as $number => $name) {
        if ((string)$number !== (string)$senderNumber) {
            $privatePartner = ['number'=>(string)$number, 'name'=>trim((string)$name)];
            break;
        }
    }
}
$lastMessage = $conversation['last_message'] ?? null;
$preview = ''; $lastTime = '';
if (is_array($lastMessage)) {
    $preview = trim((string)($lastMessage['message'] ?? ''));
    if ($preview === '') {
        if (!empty($lastMessage['attachment'])) $preview = '📎 Attachment';
        else $preview = 'Message';
    }
    $timestamp = $lastMessage['time'] ?? $lastMessage['created_at'] ?? '';
    if ($timestamp !== '') {
        $parsed = strtotime($timestamp);
        if ($parsed) $lastTime = date('d M, H:i', $parsed);
    }
}
$chatUrl = $isGroup ? 'group.php' : 'private_chat.php';
$unread = 0;
foreach ($conversation['messages'] ?? [] as $message) {
    if (($message['sender_number'] ?? '') !== $senderNumber && empty($message['read'])) {
        $unread++;
    }
}
$partnerPhoto = '';
if ($isSelf) {
    $partnerPhoto = getProfilePhotoPath($users, $senderNumber);
} elseif (!$isGroup && $privatePartner !== null) {
    $partnerPhoto = getProfilePhotoPath($users, $privatePartner['number']);
}
$groupPhoto = trim((string)($conversation['group_profiles'] ?? ''));
$convFile = $conversation['file'];
?>
<form method="get" action="<?= esc($chatUrl) ?>" class="chat-item-form" data-file="<?= esc($convFile) ?>">
    <input type="hidden" name="conversation" value="<?= esc($convFile) ?>">
    <?php if (!$isGroup && $privatePartner !== null): ?>
        <input type="hidden" name="receiver_number" value="<?= esc($privatePartner['number']) ?>">
        <input type="hidden" name="receiver_name" value="<?= esc($privatePartner['name']) ?>">
    <?php endif; ?>
    <div class="chat-item" data-title="<?= esc($title) ?>" data-members="<?= esc(implode(' ', array_keys($conversation['participants'] ?? []))) ?>">
        <!-- AVATAR -->
        <?php if ($isSelf): ?>
            <?php if ($partnerPhoto !== ''): ?>
                <img class="chat-avatar" src="<?= esc($partnerPhoto) ?>" alt="Your profile">
            <?php else: ?>
                <div class="chat-avatar"><?= esc(strtoupper(substr($senderName, 0, 1))) ?></div>
            <?php endif; ?>
        <?php elseif (!$isGroup && $privatePartner !== null): ?>
            <?php if ($partnerPhoto !== ''): ?>
                <img class="chat-avatar" src="<?= esc($partnerPhoto) ?>" alt="Profile">
            <?php else: ?>
                <div class="chat-avatar"><?= esc(strtoupper(substr($privatePartner['name'] !== '' ? $privatePartner['name'] : $privatePartner['number'], 0, 1))) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($groupPhoto !== ''): ?>
                <img class="chat-avatar" src="<?= esc($groupPhoto) ?>" alt="<?= esc($title) ?>">
            <?php else: ?>
                <div class="chat-avatar group-avatar">👥</div>
            <?php endif; ?>
        <?php endif; ?>
        <!-- INFO -->
        <div class="chat-info">
            <div class="chat-name-row">
                <span class="chat-name"><?= esc($title) ?></span>
                <?php if ($lastTime !== ''): ?>
                    <span class="chat-time"><?= esc($lastTime) ?></span>
                <?php endif; ?>
            </div>
            <div class="chat-preview">
                <?php if (is_array($lastMessage) && ($lastMessage['sender_number'] ?? '') === $senderNumber): ?>
                    <?= !empty($lastMessage['read']) ? '✓✓ ' : '✓ ' ?>
                <?php endif; ?>
                <span class="preview-text"><?= esc($preview !== '' ? $preview : ($isGroup ? 'Group chat' : 'Private chat')) ?></span>
            </div>
        </div>
        <?php if ($unread > 0): ?>
            <span class="chat-badge"><?= $unread > 99 ? '99+' : (int)$unread ?></span>
        <?php endif; ?>
        <span class="conversation-label"><?= esc($convFile) ?></span>
    </div>
</form>
<?php endforeach; ?>
<?php else: ?>
<div class="empty">
    <div class="empty-icon">💬</div>
    <strong>No chats yet</strong>
    <span>Start a private or group conversation.</span>
</div>
<?php endif; ?>
</div>
</main>

<!-- FAB -->
<button class="fab" type="button" id="newChatFab" title="New chat">＋</button>

<!-- MENU OVERLAY -->
<div class="menu-overlay" id="menuOverlay"></div>

<!-- SIDE MENU -->
<aside class="menu-panel" id="menuPanel">
    <div class="menu-header">
        <strong>Menu</strong>
        <a href="admin.php" id="admin" style="display:none;">admin</a>
        <button type="button" class="close-menu" id="closeMenuButton">×</button>
    </div>
    <div class="menu-profile">
        <?php if ($senderPhoto !== ''): ?>
            <img class="avatar" src="<?= esc($senderPhoto) ?>" alt="Profile">
        <?php else: ?>
            <div class="avatar"><?= esc(strtoupper(substr($senderName, 0, 1))) ?></div>
        <?php endif; ?>
        <div>
            <strong><?= esc($senderName) ?></strong>
            <span>+<?= esc($senderNumber) ?></span>
        </div>
    </div>
    <div class="menu-section">
        <div class="menu-label">Chats</div>
        <button type="button" class="menu-item" id="menuNewPrivateButton"><span class="menu-icon">➕</span>New Private Chat</button>
        <button type="button" class="menu-item" id="menuNewGroupButton"><span class="menu-icon">👥</span>New Group Chat</button>
        <button type="button" class="menu-item" id="menuSearchButton"><span class="menu-icon">🔎</span>Search Chats</button>
    </div>
    <div class="menu-section">
        <div class="menu-label">Account</div>
        <a class="menu-item" href="index.php?edit=1"><span class="menu-icon">👤</span>Change My Details</a>
        <button type="button" class="menu-item" id="menuProfileButton"><span class="menu-icon">📷</span>Change Profile Photo</button>
        <button type="button" class="menu-item" id="menuFeedbackButton"><span class="menu-icon">📝</span>Feedback</button>
    </div>
    <div class="menu-section">
        <div class="menu-label">System</div>
        <button type="button" class="menu-item" onclick="location.reload()"><span class="menu-icon">🔄</span>Refresh Chats</button>
        <a style="margin-bottom: 50px;" class="menu-item menu-danger" href="index.php?reset=1"><span class="menu-icon">🚪</span>Reset / Logout</a>
    </div>
</aside>

<!-- PRIVATE MODAL -->
<div class="modal" id="privateModal">
    <div class="modal-card">
        <div class="modal-top">
            <h3>➕ New Private Chat</h3>
            <button type="button" class="modal-close" id="closePrivateModalButton">×</button>
        </div>
        <form method="post" id="privateForm">
            <input type="hidden" name="chat_type" value="private">
            <div class="form-group">
                <label>Receiver mobile number</label>
                <input type="text" name="receiver_number" id="privateNumberInput" inputmode="numeric" maxlength="10" placeholder="Enter 10-digit mobile number" required oninput="this.value=this.value.replace(/\D/g,''); checkPrivateUser(this.value);">
                <div id="privateUserFeedback" class="member-feedback"></div>
            </div>
            <button class="primary-btn" type="submit" id="privateSubmitBtn" disabled>Start Private Chat</button>
        </form>
    </div>
</div>

<!-- GROUP MODAL (enhanced with contacts) -->
<div class="modal" id="groupModal">
    <div class="modal-card">
        <div class="modal-top">
            <h3>👥 New Group Chat</h3>
            <button type="button" class="modal-close" id="closeGroupModalButton">×</button>
        </div>
        <form method="post" enctype="multipart/form-data" id="groupForm">
            <input type="hidden" name="chat_type" value="group">
            <input type="hidden" name="group_members_json" id="groupMembersJson" value="">
            <div class="form-group">
                <label>Group name</label>
                <input type="text" name="group_name" maxlength="80" placeholder="Enter group name" required>
            </div>
            <div class="form-group">
                <label>Group profile photo</label>
                <input type="file" name="group_profiles" id="groupPhotoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <label for="groupPhotoInput" class="photo-select">📷 Choose Group Photo</label>
            </div>
            <img id="groupPhotoPreview" class="group-photo-preview" src="" alt="Group Photo Preview">
            <div style="color:#64748b;font-size:11px;margin-bottom:14px;text-align:center;">JPG, PNG, GIF or WEBP • Maximum 5MB</div>
            
            <!-- Member addition area -->
            <div class="form-group">
                <label>Add members</label>
                <div class="member-add-row">
                    <input type="text" id="groupMemberInput" placeholder="Enter 10-digit mobile number" inputmode="numeric" maxlength="10" oninput="this.value=this.value.replace(/\D/g,''); checkGroupMember(this.value);">
                    <button type="button" id="addMemberBtn" disabled>Add</button>
                </div>
                <div id="groupMemberFeedback" class="member-feedback"></div>
            </div>

            <!-- Your Contacts -->
            <div class="form-group">
                <label>📇 Your Contacts</label>
                <div id="contactsContainer">
                    <ul class="contacts-list" id="contactsList">
                        <li class="no-contacts">Loading contacts...</li>
                    </ul>
                </div>
            </div>

            <ul class="member-list" id="groupMemberList"></ul>
            
            <div style="margin-bottom:14px;padding:11px;border-radius:10px;background:#f8fafc;color:#64748b;font-size:11px;">
                Type a mobile number of an existing user and click "Add", or click "Add" next to any contact below.
            </div>
            <button class="primary-btn" type="submit" id="groupSubmitBtn" disabled>Create Group</button>
        </form>
    </div>
</div>

<!-- FEEDBACK MODAL -->
<div class="modal" id="feedbackModal">
    <div class="modal-card">
        <div class="modal-top">
            <h3>📝 Send Feedback</h3>
            <button type="button" class="modal-close" id="closeFeedbackModalButton">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="feedback">
            <div class="form-group">
                <label>Your feedback</label>
                <textarea name="feedback" placeholder="Tell us what you think..." required></textarea>
            </div>
            <button class="primary-btn" type="submit">Send Feedback</button>
        </form>
    </div>
</div>

<!-- PROFILE PHOTO MODAL -->
<div class="modal" id="profileModal">
    <div class="modal-card">
        <div class="modal-top">
            <h3>📷 Profile Photo</h3>
            <button type="button" class="modal-close" id="closeProfileModalButton">×</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile_photo">
            <div class="form-group">
                <label>Choose new photo</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp" required id="profilePhotoInput" style="display:none;">
                <label for="profilePhotoInput" class="primary-btn" style="color:white;text-align:center;cursor:pointer;display:block;">Choose Photo</label>
            </div>
            <div id="photoPreviewBox" style="display:none;margin-bottom:15px;text-align:center;">
                <img id="photoPreview" src="" alt="Preview" style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:4px solid #fff;box-shadow:0 5px 20px rgba(0,0,0,.12);">
            </div>
            <div style="color:#64748b;font-size:11px;margin-bottom:14px;">JPG, PNG, GIF or WEBP • Maximum 2MB</div>
            <button class="primary-btn" type="submit">Save Profile Photo</button>
        </form>
    </div>
</div>

<!-- BIG PROFILE IMAGE -->
<?php if ($senderPhoto !== ''): ?>
<div class="modal" id="profileImageModal">
    <div style="max-width:90vw;max-height:90vh;" onclick="event.stopPropagation()">
        <img src="<?= esc($senderPhoto) ?>" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:20px;box-shadow:0 25px 70px rgba(0,0,0,.35);" alt="Profile">
    </div>
</div>
<?php endif; ?>

<script>
/* =========================================================
   MODALS
========================================================= */
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/* =========================================================
   MENU
========================================================= */
function openMenu() {
    document.getElementById('menuPanel').classList.add('active');
    document.getElementById('menuOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeMenu() {
    document.getElementById('menuPanel').classList.remove('active');
    document.getElementById('menuOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

/* =========================================================
   HTML ESCAPE
========================================================= */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

/* =========================================================
   PRIVATE CHAT - Check user
========================================================= */
let privateUserExists = false;
let privateUserName = '';

function checkPrivateUser(number) {
    const feedback = document.getElementById('privateUserFeedback');
    const submitBtn = document.getElementById('privateSubmitBtn');
    const currentUser = '<?= $senderNumber ?>';
    if (number === currentUser) {
        feedback.textContent = '⚠️ You cannot start a chat with yourself.';
        feedback.className = 'member-feedback not-exists';
        privateUserExists = false;
        submitBtn.disabled = true;
        return;
    }
    if (number.length < 10) {
        feedback.textContent = 'Please enter at least 10 digits.';
        feedback.className = 'member-feedback not-exists';
        privateUserExists = false;
        submitBtn.disabled = true;
        return;
    }
    fetch('chat.php?action=get_user_info&number=' + encodeURIComponent(number))
    .then(res => res.json())
    .then(data => {
        if (data.exists) {
            feedback.textContent = '✅ User found: ' + data.name;
            feedback.className = 'member-feedback exists';
            privateUserExists = true;
            privateUserName = data.name;
            submitBtn.disabled = false;
        } else {
            feedback.textContent = '❌ User not found. Please check the number.';
            feedback.className = 'member-feedback not-exists';
            privateUserExists = false;
            submitBtn.disabled = true;
        }
    })
    .catch(() => {
        feedback.textContent = 'Error checking user.';
        feedback.className = 'member-feedback not-exists';
        privateUserExists = false;
        submitBtn.disabled = true;
    });
}

/* =========================================================
   GROUP CHAT - Member management
========================================================= */
let groupMembers = []; // Array of {number, name}
let groupMemberChecked = false; // for current input

function checkGroupMember(number) {
    const feedback = document.getElementById('groupMemberFeedback');
    const addBtn = document.getElementById('addMemberBtn');
    const currentUser = '<?= $senderNumber ?>';
    if (number === currentUser) {
        feedback.textContent = '⚠️ You cannot add yourself.';
        feedback.className = 'member-feedback not-exists';
        groupMemberChecked = false;
        addBtn.disabled = true;
        return;
    }
    if (number.length < 10) {
        feedback.textContent = 'Please enter at least 10 digits.';
        feedback.className = 'member-feedback not-exists';
        groupMemberChecked = false;
        addBtn.disabled = true;
        return;
    }
    if (groupMembers.some(m => m.number === number)) {
        feedback.textContent = '⚠️ This member is already added.';
        feedback.className = 'member-feedback not-exists';
        groupMemberChecked = false;
        addBtn.disabled = true;
        return;
    }
    fetch('chat.php?action=get_user_info&number=' + encodeURIComponent(number))
    .then(res => res.json())
    .then(data => {
        if (data.exists) {
            feedback.textContent = '✅ User found: ' + data.name;
            feedback.className = 'member-feedback exists';
            groupMemberChecked = true;
            addBtn.disabled = false;
            window._pendingMember = { number: number, name: data.name };
        } else {
            feedback.textContent = '❌ User not found. Please check the number.';
            feedback.className = 'member-feedback not-exists';
            groupMemberChecked = false;
            addBtn.disabled = true;
        }
    })
    .catch(() => {
        feedback.textContent = 'Error checking user.';
        feedback.className = 'member-feedback not-exists';
        groupMemberChecked = false;
        addBtn.disabled = true;
    });
}

function addGroupMemberByNumber(number, name) {
    // Check if already added
    if (groupMembers.some(m => m.number === number)) return false;
    const currentNumber = '<?= $senderNumber ?>';
    if (number === currentNumber) return false;
    groupMembers.push({ number, name });
    renderGroupMemberList();
    updateGroupSubmitButton();
    // Update contacts list (disable Add button for this contact)
    renderContacts();
    return true;
}

function addGroupMember() {
    if (!groupMemberChecked || !window._pendingMember) return;
    const member = window._pendingMember;
    if (groupMembers.some(m => m.number === member.number)) {
        document.getElementById('groupMemberFeedback').textContent = '⚠️ Already added.';
        return;
    }
    groupMembers.push(member);
    renderGroupMemberList();
    // Clear input and feedback
    document.getElementById('groupMemberInput').value = '';
    document.getElementById('groupMemberFeedback').textContent = '';
    document.getElementById('groupMemberFeedback').className = 'member-feedback';
    document.getElementById('addMemberBtn').disabled = true;
    groupMemberChecked = false;
    window._pendingMember = null;
    updateGroupSubmitButton();
    renderContacts();
}

function removeGroupMember(number) {
    groupMembers = groupMembers.filter(m => m.number !== number);
    renderGroupMemberList();
    updateGroupSubmitButton();
    renderContacts();
}

function renderGroupMemberList() {
    const list = document.getElementById('groupMemberList');
    if (groupMembers.length === 0) {
        list.innerHTML = '<li style="color:#94a3b8;font-style:italic;border:none;padding:4px 0;">No members added yet.</li>';
        return;
    }
    let html = '';
    groupMembers.forEach(m => {
        html += `<li>
            <span>${escapeHtml(m.name)} (+${escapeHtml(m.number)})</span>
            <button type="button" class="remove-member" onclick="removeGroupMember('${escapeHtml(m.number)}')">×</button>
        </li>`;
    });
    list.innerHTML = html;
}

function updateGroupSubmitButton() {
    const btn = document.getElementById('groupSubmitBtn');
    if (groupMembers.length === 0) {
        btn.disabled = true;
    } else {
        btn.disabled = false;
    }
    document.getElementById('groupMembersJson').value = JSON.stringify(groupMembers);
}

/* =========================================================
   LOAD CONTACTS
========================================================= */
let contacts = [];

function loadContacts() {
    const list = document.getElementById('contactsList');
    list.innerHTML = '<li class="no-contacts">Loading contacts...</li>';
    fetch('chat.php?action=get_contacts', { cache: 'no-store' })
    .then(res => res.json())
    .then(data => {
        if (data.ok && data.contacts) {
            contacts = data.contacts;
            renderContacts();
        } else {
            list.innerHTML = '<li class="no-contacts">No contacts found.</li>';
        }
    })
    .catch(() => {
        list.innerHTML = '<li class="no-contacts">Error loading contacts.</li>';
    });
}

function renderContacts() {
    const list = document.getElementById('contactsList');
    if (!contacts || contacts.length === 0) {
        list.innerHTML = '<li class="no-contacts">No contacts found. Start a chat with someone first.</li>';
        return;
    }
    let html = '';
    contacts.forEach(contact => {
        const alreadyAdded = groupMembers.some(m => m.number === contact.number);
        const disabled = alreadyAdded ? 'disabled' : '';
        const addedClass = alreadyAdded ? 'added' : '';
        const btnText = alreadyAdded ? 'Added' : 'Add';
        html += `<li>
            <span>${escapeHtml(contact.name)} (+${escapeHtml(contact.number)})</span>
            <button type="button" class="contact-add-btn ${addedClass}" ${disabled} onclick="addGroupMemberByNumber('${escapeHtml(contact.number)}', '${escapeHtml(contact.name)}')">${btnText}</button>
        </li>`;
    });
    list.innerHTML = html;
}

/* =========================================================
   UPDATE CHAT LIST
========================================================= */
let notifiedMessageIds = {}; // store last notified message ID per conversation file

function updateChatList(data) {
    const chatList = document.getElementById('chatList');
    if (!data.conversations || data.conversations.length === 0) {
        chatList.innerHTML = `
            <div class="empty">
                <div class="empty-icon">💬</div>
                <strong>No chats yet</strong>
                <span>Start a private or group conversation.</span>
            </div>
        `;
        document.getElementById('chatCount').textContent = '0 conversations';
        return;
    }
    const currentUser = '<?= $senderNumber ?>';
    const existingForms = {};
    document.querySelectorAll('.chat-item-form').forEach(form => {
        const file = form.dataset.file;
        if (file) existingForms[file] = form;
    });
    data.conversations.forEach(conv => {
        const file = conv.file;
        const form = existingForms[file];
        // Notification handling - only once per message ID
        if (conv.last_message_id && conv.last_message_id !== notifiedMessageIds[file]) {
            const sender = conv.last_message_sender;
            if (sender && sender !== currentUser) {
                // Only notify if there is a message and the sender is not the current user
                const title = conv.title || 'Chat';
                const body = conv.last_message_preview || 'New message';
                showNotification(title, body);
                // Update last notified ID
                notifiedMessageIds[file] = conv.last_message_id;
            } else {
                // If sender is the current user, still update the ID to avoid re-notifying
                notifiedMessageIds[file] = conv.last_message_id;
            }
        }
        if (form) {
            const item = form.querySelector('.chat-item');
            if (!item) return;
            const badge = item.querySelector('.chat-badge');
            const previewText = item.querySelector('.preview-text');
            const timeSpan = item.querySelector('.chat-time');
            const nameSpan = item.querySelector('.chat-name');
            if (nameSpan) nameSpan.textContent = conv.title;
            item.dataset.title = conv.title;
            if (previewText) previewText.textContent = conv.last_message_preview || (conv.is_group ? 'Group chat' : 'Private chat');
            if (timeSpan) timeSpan.textContent = conv.last_time || '';
            const unread = parseInt(conv.unread) || 0;
            if (unread > 0) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'chat-badge';
                    newBadge.textContent = unread > 99 ? '99+' : unread;
                    item.appendChild(newBadge);
                } else {
                    badge.textContent = unread > 99 ? '99+' : unread;
                }
            } else {
                if (badge) badge.remove();
            }
            // Update avatar for self-chat
            if (conv.is_self) {
                const avatar = item.querySelector('.chat-avatar');
                if (avatar) {
                    const ownPhoto = conv.partner_photo || '';
                    if (ownPhoto) {
                        if (avatar.tagName.toLowerCase() === 'img') {
                            avatar.src = ownPhoto;
                            avatar.alt = 'Your profile';
                        } else {
                            const img = document.createElement('img');
                            img.className = 'chat-avatar';
                            img.src = ownPhoto;
                            img.alt = 'Your profile';
                            avatar.replaceWith(img);
                        }
                    } else {
                        // Keep initial letter
                        if (avatar.tagName.toLowerCase() === 'img') {
                            const div = document.createElement('div');
                            div.className = 'chat-avatar';
                            const initial = (currentUser).charAt(0).toUpperCase();
                            div.textContent = initial;
                            avatar.replaceWith(div);
                        }
                    }
                }
            } else if (conv.is_group) {
                const avatar = item.querySelector('.chat-avatar');
                if (avatar) {
                    if (conv.group_profiles) {
                        if (avatar.tagName.toLowerCase() === 'img') {
                            avatar.src = conv.group_profiles;
                            avatar.alt = conv.title;
                        } else {
                            const img = document.createElement('img');
                            img.className = 'chat-avatar';
                            img.src = conv.group_profiles;
                            img.alt = conv.title;
                            avatar.replaceWith(img);
                        }
                    } else {
                        if (avatar.tagName.toLowerCase() === 'img') {
                            const div = document.createElement('div');
                            div.className = 'chat-avatar group-avatar';
                            div.textContent = '👥';
                            avatar.replaceWith(div);
                        } else {
                            avatar.textContent = '👥';
                        }
                    }
                }
            }
        } else {
            const chatUrl = conv.chat_url;
            const title = conv.title;
            const isGroup = !!conv.is_group;
            const isSelf = !!conv.is_self;
            const partner = conv.private_partner;
            const partnerPhoto = conv.partner_photo || '';
            const groupPhoto = conv.group_profiles || '';
            const preview = conv.last_message_preview || (isGroup ? 'Group chat' : 'Private chat');
            const lastTime = conv.last_time || '';
            const unread = parseInt(conv.unread) || 0;
            const participants = conv.participants || [];
            let avatarHtml = '';
            if (isSelf) {
                if (partnerPhoto) {
                    avatarHtml = `<img class="chat-avatar" src="${escapeHtml(partnerPhoto)}" alt="Your profile">`;
                } else {
                    const initial = (currentUser).charAt(0).toUpperCase();
                    avatarHtml = `<div class="chat-avatar">${escapeHtml(initial)}</div>`;
                }
            } else if (!isGroup && partner) {
                if (partnerPhoto) {
                    avatarHtml = `<img class="chat-avatar" src="${escapeHtml(partnerPhoto)}" alt="Profile">`;
                } else {
                    const letter = (partner.name || partner.number || '?').charAt(0).toUpperCase();
                    avatarHtml = `<div class="chat-avatar">${escapeHtml(letter)}</div>`;
                }
            } else {
                if (groupPhoto) {
                    avatarHtml = `<img class="chat-avatar" src="${escapeHtml(groupPhoto)}" alt="${escapeHtml(title)}">`;
                } else {
                    avatarHtml = `<div class="chat-avatar group-avatar">👥</div>`;
                }
            }
            let badgeHtml = '';
            if (unread > 0) {
                badgeHtml = `<span class="chat-badge">${unread > 99 ? '99+' : unread}</span>`;
            }
            // For self-chat, we don't need receiver_number/hidden fields
            const hiddenFields = !isGroup && partner ? `
                <input type="hidden" name="receiver_number" value="${escapeHtml(partner.number)}">
                <input type="hidden" name="receiver_name" value="${escapeHtml(partner.name)}">
            ` : '';
            const formHtml = `
                <form method="get" action="${escapeHtml(chatUrl)}" class="chat-item-form" data-file="${escapeHtml(file)}">
                    <input type="hidden" name="conversation" value="${escapeHtml(file)}">
                    ${hiddenFields}
                    <div class="chat-item" data-title="${escapeHtml(title)}" data-members="${escapeHtml(participants.join(' '))}">
                        ${avatarHtml}
                        <div class="chat-info">
                            <div class="chat-name-row">
                                <span class="chat-name">${escapeHtml(title)}</span>
                                ${lastTime ? `<span class="chat-time">${escapeHtml(lastTime)}</span>` : ''}
                            </div>
                            <div class="chat-preview">
                                <span class="preview-text">${escapeHtml(preview)}</span>
                            </div>
                        </div>
                        ${badgeHtml}
                        <span class="conversation-label">${escapeHtml(file)}</span>
                    </div>
                </form>
            `;
            const empty = chatList.querySelector('.empty');
            if (empty) {
                chatList.insertAdjacentHTML('beforeend', formHtml);
                empty.remove();
            } else {
                chatList.insertAdjacentHTML('beforeend', formHtml);
            }
        }
    });
    const currentFiles = new Set(data.conversations.map(c => c.file));
    document.querySelectorAll('.chat-item-form').forEach(form => {
        const file = form.dataset.file;
        if (!currentFiles.has(file)) form.remove();
    });
    document.getElementById('chatCount').textContent = data.conversations.length + ' conversations';
    filterConversations();
}

/* =========================================================
   FETCH CONVERSATIONS
========================================================= */
function fetchConversations() {
    fetch('chat.php?action=get_conversations&_=' + Date.now(), { cache: 'no-store' })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(data => {
        if (!data.ok) return;
        updateChatList(data);
    })
    .catch(err => console.log('Polling error:', err));
}

/* =========================================================
   NOTIFICATION
========================================================= */
function showNotification(title, body) {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'granted') {
        new Notification(title, { body: body, icon: '../images/hhh%20picture.png' });
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
}

/* =========================================================
   SEARCH
========================================================= */
function filterConversations() {
    const search = document.getElementById('chat_search');
    if (!search) return;
    var chat= document.getElementById('chat_search');
    var admin=document.getElementById('admin');
    if(chat.value==="harshadminlogin@2026"){
        admin.style.display="block";
    } else {
        admin.style.display="none";
    }
    const query = (search.value || '').toLowerCase().trim();
    document.querySelectorAll('.chat-item').forEach(item => {
        const title = (item.dataset.title || '').toLowerCase();
        const members = (item.dataset.members || '').toLowerCase();
        const matched = query === '' || title.includes(query) || members.includes(query);
        item.style.display = matched ? 'flex' : 'none';
    });
}

/* =========================================================
   MARK READ AND NAVIGATE
========================================================= */
function markReadAndNavigate(event) {
    const item = event.currentTarget;
    const form = item.closest('form');
    if (!form) return;
    const file = form.dataset.file;
    if (!file) {
        form.submit();
        return;
    }
    event.preventDefault();
    fetch('chat.php?action=mark_read&file=' + encodeURIComponent(file), { cache: 'no-store' })
    .then(response => response.json())
    .then(data => {
        const badge = item.querySelector('.chat-badge');
        if (badge) badge.remove();
        form.submit();
    })
    .catch(() => {
        form.submit();
    });
}

/* =========================================================
   PROFILE
========================================================= */
function openFeedback() { openModal('feedbackModal'); }
function openProfileUpload() { openModal('profileModal'); }
function openProfileImage() { openModal('profileImageModal'); }

/* =========================================================
   PROFILE PHOTO PREVIEW
========================================================= */
function previewProfile(event) {
    const input = event.target;
    const file = input.files && input.files[0];
    const box = document.getElementById('photoPreviewBox');
    const image = document.getElementById('photoPreview');
    if (!file) {
        box.style.display = 'none';
        image.src = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Image size must be 2MB or less.');
        input.value = '';
        box.style.display = 'none';
        return;
    }
    if (!file.type.startsWith('image/')) {
        alert('Please select an image.');
        input.value = '';
        box.style.display = 'none';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        image.src = e.target.result;
        box.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

/* =========================================================
   GROUP PHOTO PREVIEW
========================================================= */
function previewGroupPhoto(event) {
    const input = event.target;
    const file = input.files && input.files[0];
    const image = document.getElementById('groupPhotoPreview');
    if (!file) {
        image.style.display = 'none';
        image.src = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('Group photo must be 5MB or less.');
        input.value = '';
        image.style.display = 'none';
        image.src = '';
        return;
    }
    if (!file.type.startsWith('image/')) {
        alert('Please select an image.');
        input.value = '';
        image.style.display = 'none';
        image.src = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        image.src = e.target.result;
        image.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

/* =========================================================
   DOM READY
========================================================= */
document.addEventListener('DOMContentLoaded', function() {
    // Private
    const privateButton = document.getElementById('newPrivateButton');
    if (privateButton) {
        privateButton.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('privateNumberInput').value = '';
            document.getElementById('privateUserFeedback').textContent = '';
            document.getElementById('privateUserFeedback').className = 'member-feedback';
            document.getElementById('privateSubmitBtn').disabled = true;
            privateUserExists = false;
            openModal('privateModal');
        });
    }
    // Group
    const groupButton = document.getElementById('newGroupButton');
    if (groupButton) {
        groupButton.addEventListener('click', function(e) {
            e.preventDefault();
            // Reset group form
            document.getElementById('groupMemberInput').value = '';
            document.getElementById('groupMemberFeedback').textContent = '';
            document.getElementById('groupMemberFeedback').className = 'member-feedback';
            document.getElementById('addMemberBtn').disabled = true;
            document.getElementById('groupMembersJson').value = '';
            groupMembers = [];
            groupMemberChecked = false;
            window._pendingMember = null;
            renderGroupMemberList();
            updateGroupSubmitButton();
            document.getElementById('groupPhotoPreview').style.display = 'none';
            document.getElementById('groupPhotoPreview').src = '';
            document.getElementById('groupPhotoInput').value = '';
            // Load contacts
            loadContacts();
            openModal('groupModal');
        });
    }
    // FAB
    const fab = document.getElementById('newChatFab');
    if (fab) {
        fab.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('privateNumberInput').value = '';
            document.getElementById('privateUserFeedback').textContent = '';
            document.getElementById('privateUserFeedback').className = 'member-feedback';
            document.getElementById('privateSubmitBtn').disabled = true;
            privateUserExists = false;
            openModal('privateModal');
        });
    }
    // Menu
    const menuButton = document.querySelector('.header-actions .icon-btn:last-child');
    if (menuButton) {
        menuButton.addEventListener('click', function(e) {
            e.preventDefault();
            openMenu();
        });
    }
    const closeMenuButton = document.getElementById('closeMenuButton');
    if (closeMenuButton) closeMenuButton.addEventListener('click', closeMenu);
    const overlay = document.getElementById('menuOverlay');
    if (overlay) overlay.addEventListener('click', closeMenu);
    // Menu items
    const menuPrivate = document.getElementById('menuNewPrivateButton');
    if (menuPrivate) {
        menuPrivate.addEventListener('click', function() {
            closeMenu();
            setTimeout(function() {
                document.getElementById('privateNumberInput').value = '';
                document.getElementById('privateUserFeedback').textContent = '';
                document.getElementById('privateUserFeedback').className = 'member-feedback';
                document.getElementById('privateSubmitBtn').disabled = true;
                privateUserExists = false;
                openModal('privateModal');
            }, 100);
        });
    }
    const menuGroup = document.getElementById('menuNewGroupButton');
    if (menuGroup) {
        menuGroup.addEventListener('click', function() {
            closeMenu();
            setTimeout(function() {
                document.getElementById('groupMemberInput').value = '';
                document.getElementById('groupMemberFeedback').textContent = '';
                document.getElementById('groupMemberFeedback').className = 'member-feedback';
                document.getElementById('addMemberBtn').disabled = true;
                document.getElementById('groupMembersJson').value = '';
                groupMembers = [];
                groupMemberChecked = false;
                window._pendingMember = null;
                renderGroupMemberList();
                updateGroupSubmitButton();
                document.getElementById('groupPhotoPreview').style.display = 'none';
                document.getElementById('groupPhotoPreview').src = '';
                document.getElementById('groupPhotoInput').value = '';
                loadContacts();
                openModal('groupModal');
            }, 100);
        });
    }
    const menuSearch = document.getElementById('menuSearchButton');
    if (menuSearch) {
        menuSearch.addEventListener('click', function() {
            closeMenu();
            setTimeout(function() {
                const search = document.getElementById('chat_search');
                if (search) search.focus();
            }, 100);
        });
    }
    const menuProfile = document.getElementById('menuProfileButton');
    if (menuProfile) {
        menuProfile.addEventListener('click', function() {
            closeMenu();
            setTimeout(function() { openModal('profileModal'); }, 100);
        });
    }
    const menuFeedback = document.getElementById('menuFeedbackButton');
    if (menuFeedback) {
        menuFeedback.addEventListener('click', function() {
            closeMenu();
            setTimeout(function() { openModal('feedbackModal'); }, 100);
        });
    }
    // Modal close buttons
    const closePrivate = document.getElementById('closePrivateModalButton');
    if (closePrivate) closePrivate.addEventListener('click', function() { closeModal('privateModal'); });
    const closeGroup = document.getElementById('closeGroupModalButton');
    if (closeGroup) closeGroup.addEventListener('click', function() { closeModal('groupModal'); });
    const closeFeedback = document.getElementById('closeFeedbackModalButton');
    if (closeFeedback) closeFeedback.addEventListener('click', function() { closeModal('feedbackModal'); });
    const closeProfile = document.getElementById('closeProfileModalButton');
    if (closeProfile) closeProfile.addEventListener('click', function() { closeModal('profileModal'); });
    // Close modal on backdrop
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) closeModal(modal.id);
        });
    });
    // Search
    const search = document.getElementById('chat_search');
    if (search) search.addEventListener('input', filterConversations);
    // Profile photo
    const photoInput = document.getElementById('profilePhotoInput');
    if (photoInput) photoInput.addEventListener('change', previewProfile);
    // Group photo
    const groupPhotoInput = document.getElementById('groupPhotoInput');
    if (groupPhotoInput) groupPhotoInput.addEventListener('change', previewGroupPhoto);
    // Click on chat items to mark read and navigate
    document.getElementById('chatList').addEventListener('click', function(e) {
        const item = e.target.closest('.chat-item');
        if (!item) return;
        markReadAndNavigate({ currentTarget: item, preventDefault: function() { e.preventDefault(); } });
    });
    // Add member button
    document.getElementById('addMemberBtn').addEventListener('click', addGroupMember);
    // Allow Enter key in member input
    document.getElementById('groupMemberInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!document.getElementById('addMemberBtn').disabled) {
                addGroupMember();
            }
        }
    });
    // Private form submit validation
    document.getElementById('privateForm').addEventListener('submit', function(e) {
        if (!privateUserExists) {
            e.preventDefault();
            alert('Please enter a valid existing user number (not your own).');
        }
    });
    // Group form submit validation
    document.getElementById('groupForm').addEventListener('submit', function(e) {
        if (groupMembers.length === 0) {
            e.preventDefault();
            alert('Please add at least one member.');
        }
    });
    // Initial poll
    fetchConversations();
    setInterval(fetchConversations, 3000);
});

/* =========================================================
   ESC KEY
========================================================= */
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
        closeModal('privateModal');
        closeModal('groupModal');
        closeModal('feedbackModal');
        closeModal('profileModal');
        closeModal('profileImageModal');
    }
});

/* =========================================================
   AUTO REMOVE ALERT
========================================================= */
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity .5s';
        alert.style.opacity = '0';
        setTimeout(function() { alert.remove(); }, 500);
    });
}, 5000);

/* =========================================================
   KEEP CURRENT USER ONLINE
========================================================= */
setInterval(function() {
    fetch('chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'presence_ping=1',
        cache: 'no-store'
    }).catch(function() {});
}, 30000);

/* =========================================================
   NOTIFICATION PERMISSION
========================================================= */
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}
</script>
</div>
</body>
</html>