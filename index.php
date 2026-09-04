<?php

// Enable error reporting for debugging (remove/comment in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

date_default_timezone_set('Asia/Kolkata');

// -------------------- File paths --------------------

function getUserStorePath()
{
    return __DIR__ . '/data/users.json';
}

function getDataDir()
{
    return __DIR__ . '/data';
}

// -------------------- Load / Save users --------------------

function loadUsers()
{
    $path = getUserStorePath();

    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);

    return is_array($data) ? $data : [];
}

function saveUsers(array $users)
{
    $dir = getDataDir();

    if (!is_dir($dir)) {

        if (!mkdir($dir, 0777, true)) {
            die(
                'ERROR: Cannot create data directory. Please create it manually and set permissions 0777.'
            );
        }
    }

    if (!is_writable($dir)) {
        die(
            'ERROR: Data directory is not writable. Please set permissions 0777 on ' .
            $dir
        );
    }

    $path = getUserStorePath();

    file_put_contents(
        $path,
        json_encode($users, JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function buildUserStorageDirectory($senderNumber)
{
    $folderName = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $senderNumber
    );

    $folderName = trim($folderName, '_');

    if ($folderName === '') {
        $folderName = 'user';
    }

    $directory =
        getDataDir() . '/' . $folderName;

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}

// -------------------- Cookie helper --------------------

function getCookieValue($name)
{
    return isset($_COOKIE[$name])
        ? trim((string)$_COOKIE[$name])
        : '';
}

function setSecureCookie($name, $value, $expire = 0)
{
    $secure =
        (!empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off');

    $samesite = 'Lax';

    if ($secure) {
        $samesite = 'None';
    }

    setcookie($name, $value, [
        'expires' => $expire,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $samesite
    ]);
}

// -------------------- Main logic --------------------

$errors = [];

$mode =
    !empty($_GET['edit'])
        ? 'edit'
        : 'login';

// -------------------- Handle reset --------------------

if (
    isset($_GET['reset']) ||
    (
        isset($_POST['action']) &&
        $_POST['action'] === 'reset'
    )
) {

    unset(
        $_SESSION['sender_number'],
        $_SESSION['sender_name'],
        $_SESSION['password']
    );

    setSecureCookie(
        'php_json_chat_sender_number',
        '',
        time() - 3600
    );

    setSecureCookie(
        'php_json_chat_sender_name',
        '',
        time() - 3600
    );

    setSecureCookie(
        'php_json_chat_password',
        '',
        time() - 3600
    );

    $senderNumber =
        $senderName =
        $password = '';

    $mode = 'login';

    header('Location: index.php');

    exit;
}

// -------------------- Get credentials --------------------

$senderNumber =
    trim($_SESSION['sender_number'] ?? '');

$senderName =
    trim($_SESSION['sender_name'] ?? '');

$password =
    trim($_SESSION['password'] ?? '');

if (
    $senderNumber === '' ||
    $senderName === '' ||
    $password === ''
) {

    $senderNumber =
        preg_replace(
            '/[^0-9]/',
            '',
            getCookieValue(
                'php_json_chat_sender_number'
            )
        );

    $senderName =
        getCookieValue(
            'php_json_chat_sender_name'
        );

    $password =
        getCookieValue(
            'php_json_chat_password'
        );
}

// -------------------- Auto redirect --------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    !isset($_GET['edit']) &&
    !isset($_GET['reset'])
) {

    if (
        $senderNumber !== '' &&
        $senderName !== '' &&
        $password !== ''
    ) {

        $users = loadUsers();

        if (
            isset($users[$senderNumber]) &&
            (string)$users[$senderNumber]['password'] === $password &&
            (string)$users[$senderNumber]['name'] === $senderName
        ) {

            $_SESSION['sender_number'] =
                $senderNumber;

            $_SESSION['sender_name'] =
                $senderName;

            $_SESSION['password'] =
                $password;

            header('Location: chat.php');

            exit;
        }
    }
}

// -------------------- Process POST --------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $senderNumber =
        preg_replace(
            '/[^0-9]/',
            '',
            trim($_POST['sender_number'] ?? '')
        );

    $senderName =
        trim($_POST['sender_name'] ?? '');

    $password =
        trim($_POST['password'] ?? '');

    $action =
        trim($_POST['action'] ?? '');

    if ($action === 'reset') {

        header('Location: index.php?reset=1');

        exit;
    }

    // -------------------- UPDATE --------------------

    if (
        $action === 'update' ||
        $mode === 'edit'
    ) {

        if (
            $senderNumber === '' ||
            $senderName === '' ||
            $password === ''
        ) {

            $errors[] =
                'Please enter your mobile number, name, and password.';

        } elseif (strlen($senderNumber) !== 10) {

            $errors[] =
                'Mobile number must be exactly 10 digits.';

        } else {

            $users = loadUsers();

            $currentNumber =
                preg_replace(
                    '/[^0-9]/',
                    '',
                    $_SESSION['sender_number'] ?? ''
                );

            $currentName =
                trim(
                    $_SESSION['sender_name'] ?? ''
                );

            $currentPassword =
                trim(
                    $_SESSION['password'] ?? ''
                );

            $currentUser = null;

            $currentUserKey = null;

            foreach ($users as $key => $user) {

                if (
                    (string)($user['number'] ?? '') === $currentNumber &&
                    (string)($user['password'] ?? '') === $currentPassword &&
                    (string)($user['name'] ?? '') === $currentName
                ) {

                    $currentUser = $user;

                    $currentUserKey = $key;

                    break;
                }
            }

            if ($currentUser === null) {

                $errors[] =
                    'The current details are incorrect. Please enter your saved number, name, and password.';

            } else {

                $newNumber =
                    preg_replace(
                        '/[^0-9]/',
                        '',
                        $senderNumber
                    );

                $newName =
                    trim($senderName);

                $newPassword =
                    trim($password);

                if (
                    $newNumber === '' ||
                    $newName === '' ||
                    $newPassword === ''
                ) {

                    $errors[] =
                        'Please enter your mobile number, name, and password.';

                } elseif (strlen($newPassword) < 8) {

                    $errors[] =
                        'Password must be at least 8 characters long.';

                } elseif (strlen($newNumber) !== 10) {

                    $errors[] =
                        'New mobile number must be exactly 10 digits.';

                } else {

                    $numberExistsElsewhere =
                        false;

                    foreach ($users as $key => $user) {

                        if (
                            (string)($user['number'] ?? '') === $newNumber &&
                            $key !== $currentUserKey
                        ) {

                            $numberExistsElsewhere =
                                true;

                            break;
                        }
                    }

                    if ($numberExistsElsewhere) {

                        $errors[] =
                            'This number is already registered. Please choose another one.';

                    } else {

                        if (
                            $newNumber !== $currentNumber &&
                            isset($users[$currentNumber])
                        ) {

                            unset(
                                $users[$currentNumber]
                            );
                        }

                        $users[$newNumber] = [

                            'number' =>
                                $newNumber,

                            'name' =>
                                $newName,

                            'password' =>
                                $newPassword,

                            'time' =>
                                $currentUser['time']
                                ??
                                date('H:i:s:Y:m:d'),

                            'profile_photo' =>
                                $currentUser['profile_photo']
                                ??
                                null
                        ];

                        saveUsers($users);

                        buildUserStorageDirectory(
                            $newNumber
                        );

                        $_SESSION['sender_number'] =
                            $newNumber;

                        $_SESSION['sender_name'] =
                            $newName;

                        $_SESSION['password'] =
                            $newPassword;

                        $expire =
                            time() +
                            60 * 60 * 24 * 365;

                        setSecureCookie(
                            'php_json_chat_sender_number',
                            $newNumber,
                            $expire
                        );

                        setSecureCookie(
                            'php_json_chat_sender_name',
                            $newName,
                            $expire
                        );

                        setSecureCookie(
                            'php_json_chat_password',
                            $newPassword,
                            $expire
                        );

                        unset(
                            $_SESSION['private_receiver_number'],
                            $_SESSION['private_receiver_name'],
                            $_SESSION['group_participants'],
                            $_SESSION['chat_type']
                        );

                        header(
                            'Location: chat.php'
                        );

                        exit;
                    }
                }
            }
        }

    } else {

        // -------------------- NORMAL LOGIN / REGISTRATION --------------------

        if (
            $senderNumber === '' ||
            $senderName === '' ||
            $password === ''
        ) {

            $errors[] =
                'Please enter your mobile number, name, and password.';

        } elseif (strlen($senderNumber) !== 10) {

            $errors[] =
                'Mobile number must be exactly 10 digits.';

        } else {

            $users = loadUsers();

            $existingUser = null;

            foreach ($users as $user) {

                if (
                    (string)($user['number'] ?? '') ===
                    $senderNumber
                ) {

                    $existingUser =
                        $user;

                    break;
                }
            }

            if ($existingUser !== null) {

                $storedName =
                    (string)(
                        $existingUser['name']
                        ?? ''
                    );

                $storedPassword =
                    (string)(
                        $existingUser['password']
                        ?? ''
                    );

                if (
                    $storedPassword !==
                    $password
                ) {

                    $errors[] =
                        'The number exists, but the password is incorrect. Please enter the correct details.';

                } else {

                    buildUserStorageDirectory(
                        $senderNumber
                    );

                    $resolvedName =
                        $storedName !== ''
                            ? $storedName
                            : $senderName;

                    $_SESSION['sender_number'] =
                        $senderNumber;

                    $_SESSION['sender_name'] =
                        $resolvedName;

                    $_SESSION['password'] =
                        $password;

                    $expire =
                        time() +
                        60 * 60 * 24 * 365;

                    setSecureCookie(
                        'php_json_chat_sender_number',
                        $senderNumber,
                        $expire
                    );

                    setSecureCookie(
                        'php_json_chat_sender_name',
                        $resolvedName,
                        $expire
                    );

                    setSecureCookie(
                        'php_json_chat_password',
                        $password,
                        $expire
                    );

                    unset(
                        $_SESSION['private_receiver_number'],
                        $_SESSION['private_receiver_name'],
                        $_SESSION['group_participants'],
                        $_SESSION['chat_type']
                    );

                    header(
                        'Location: chat.php'
                    );

                    exit;
                }

            } else {

                // -------------------- NEW USER --------------------

                $users[$senderNumber] = [

                    'number' =>
                        $senderNumber,

                    'name' =>
                        $senderName,

                    'password' =>
                        $password,

                    'time' =>
                        date('H:i:s:Y:m:d')
                ];

                saveUsers($users);

                buildUserStorageDirectory(
                    $senderNumber
                );

                $_SESSION['sender_number'] =
                    $senderNumber;

                $_SESSION['sender_name'] =
                    $senderName;

                $_SESSION['password'] =
                    $password;

                $expire =
                    time() +
                    60 * 60 * 24 * 365;

                setSecureCookie(
                    'php_json_chat_sender_number',
                    $senderNumber,
                    $expire
                );

                setSecureCookie(
                    'php_json_chat_sender_name',
                    $senderName,
                    $expire
                );

                setSecureCookie(
                    'php_json_chat_password',
                    $password,
                    $expire
                );

                unset(
                    $_SESSION['private_receiver_number'],
                    $_SESSION['private_receiver_name'],
                    $_SESSION['group_participants'],
                    $_SESSION['chat_type']
                );

                header(
                    'Location: chat.php'
                );

                exit;
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<link
    rel="manifest"
    href="mainfest.json"
>
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#2563eb"
>

<title>
    <?=
        $mode === 'edit'
            ? 'Update Details'
            : 'Your Details'
    ?>
</title>

<link
    rel="icon"
    type="image/png"
    href="../images/hhh%20picture.png"
>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================================================
   BODY
========================================================= */

body {

    min-height: 100vh;

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37,99,235,.15),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 90%,
            rgba(124,58,237,.14),
            transparent 30%
        ),
        #f4f7fb;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 25px;

    color: #172033;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.page {

    width: 100%;

    max-width: 540px;

}


/* =========================================================
   CARD
========================================================= */

.box {

    width: 100%;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid rgba(255,255,255,.8);

    border-radius: 24px;

    padding:
        32px;

    box-shadow:
        0 25px 70px
        rgba(15,23,42,.12);

    animation:
        cardIn .45s ease;

}


@keyframes cardIn {

    from {

        opacity: 0;

        transform:
            translateY(20px)
            scale(.98);

    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);

    }

}


/* =========================================================
   TOP ICON
========================================================= */

.icon-box {

    width: 70px;

    height: 70px;

    margin:
        0 auto 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 21px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    color: white;

    font-size: 32px;

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.25);

}


/* =========================================================
   HEADER
========================================================= */

.header {

    text-align: center;

    margin-bottom: 28px;

}


.header h1 {

    color: #111827;

    font-size: 27px;

    font-weight: 800;

    margin-bottom: 8px;

}


.header p {

    color: #64748b;

    font-size: 14px;

    line-height: 1.5;

}


/* =========================================================
   STEP BADGE
========================================================= */

.step-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 13px;

    padding:
        6px 11px;

    border-radius: 999px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 12px;

    font-weight: 700;

}


/* =========================================================
   ERROR
========================================================= */

.error {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    margin-bottom: 20px;

    padding:
        13px 14px;

    border:
        1px solid #fecaca;

    border-radius: 12px;

    background: #fef2f2;

    color: #b91c1c;

    font-size: 13px;

    line-height: 1.45;

    font-weight: 600;

}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    margin-bottom: 18px;

}


.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #334155;

    font-size: 13px;

    font-weight: 700;

}


/* =========================================================
   INPUT WRAPPER
========================================================= */

.input-wrap {

    position: relative;

}


.input-icon {

    position: absolute;

    left: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #94a3b8;

    font-size: 17px;

    pointer-events: none;

}


/* =========================================================
   INPUT
========================================================= */

.input-wrap input {

    width: 100%;

    height: 49px;

    padding:
        0 14px 0 43px;

    border:
        1px solid #cbd5e1;

    border-radius: 12px;

    background: #f8fafc;

    color: #172033;

    font-size: 15px;

    outline: none;

    transition:
        .2s ease;

}


.input-wrap input:focus {

    background: #fff;

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);

}


/* =========================================================
   PASSWORD TOGGLE
========================================================= */

.password-toggle {

    position: absolute;

    right: 7px;

    top: 7px;

    width: 35px;

    height: 35px;

    border: none;

    border-radius: 9px;

    background: transparent;

    color: #64748b;

    cursor: pointer;

    font-size: 16px;

}


.password-toggle:hover {

    background: #eef2f7;

    color: #2563eb;

}


/* =========================================================
   SHOW PASSWORD
========================================================= */

.show-password {

    display: flex;

    align-items: center;

    gap: 9px;

    margin:
        -3px 0 22px;

}


.show-password input {

    width: 17px;

    height: 17px;

    accent-color: #2563eb;

    cursor: pointer;

}


.show-password label {

    color: #64748b;

    font-size: 13px;

    cursor: pointer;

}


/* =========================================================
   BUTTON AREA
========================================================= */

.button-row {

    display: grid;

    grid-template-columns:
        1fr 1.5fr 1fr;

    gap: 10px;

    margin-top: 5px;

}


/* =========================================================
   BUTTON
========================================================= */

.button-row button {

    min-height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition:
        .2s ease;

}


/* =========================================================
   BACK
========================================================= */

.back-btn {

    background: #f1f5f9;

    color: #475569;

    border:
        1px solid #e2e8f0 !important;

}


.back-btn:hover {

    background: #e2e8f0;

}


/* =========================================================
   MAIN BUTTON
========================================================= */

.main-btn {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.20);

}


.main-btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.30);

}


/* =========================================================
   RESET
========================================================= */

.reset-btn {

    background: #fff1f2;

    color: #be123c;

    border:
        1px solid #fecdd3 !important;

}


.reset-btn:hover {

    background: #ffe4e6;

}


/* =========================================================
   SECURITY
========================================================= */

.security {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 6px;

    margin-top: 23px;

    color: #94a3b8;

    font-size: 11px;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    margin-top: 18px;

    color: #94a3b8;

    font-size: 11px;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 600px) {

    body {

        padding: 15px;

        align-items: flex-start;

        padding-top: 30px;

    }


    .box {

        padding: 25px 19px;

        border-radius: 20px;

    }


    .icon-box {

        width: 62px;

        height: 62px;

        font-size: 28px;

        border-radius: 18px;

    }


    .header h1 {

        font-size: 23px;

    }


    .header p {

        font-size: 13px;

    }


    .button-row {

        grid-template-columns: 1fr;

    }


    .button-row button {

        width: 100%;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 360px) {

    body {

        padding: 10px;

        padding-top: 20px;

    }


    .box {

        padding: 21px 15px;

    }


    .header h1 {

        font-size: 21px;

    }


    .input-wrap input {

        font-size: 14px;

    }

}

</style>

</head>


<body>


<div class="page">


<div class="box">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">


        <div class="icon-box">

            <?=
                $mode === 'edit'
                    ? '✏️'
                    : '👤'
            ?>

        </div>


        <h1>

            <?=
                $mode === 'edit'
                    ? 'Update Your Details'
                    : 'Welcome to Chat'
            ?>

        </h1>


        <p>

            <?=
                $mode === 'edit'
                    ? 'Update your mobile number, name, or password.'
                    : 'Enter your details to continue to the chat.'
            ?>

        </p>


        <?php if ($mode !== 'edit'): ?>

            <div class="step-badge" style="display: none;">

                ● Step 1 of 1

            </div>

        <?php endif; ?>


    </div>



    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if (!empty($errors)): ?>

        <div class="error">

            <span>⚠️</span>

            <span>

                <?= htmlspecialchars(
                    $errors[0],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         FORM
    ====================================================== -->

    <form method="post">


        <!-- MOBILE NUMBER -->

        <div class="form-group">

            <label for="sender_number">

                📱 Mobile Number

            </label>


            <div class="input-wrap">


                <span class="input-icon">
                    📱
                </span>


                <?php if ($mode === 'edit'): ?>

                    <!-- In edit mode, we display the current number as disabled,
                         but we still submit a hidden input with the current number.
                         The user cannot change it via this field; they can only change
                         name and password. If they want to change number, they must
                         do it through a separate flow, but we keep this simple.
                         However we still validate the hidden number length. -->
                    <input
                        type="text"
                        id="number"
                        minlength="10"
                        maxlength="10"
                        value="<?=
                            htmlspecialchars(
                                $senderNumber,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ?>"
                        disabled
                    >


                    <input
                        type="hidden"
                        name="sender_number"
                        value="<?=
                            htmlspecialchars(
                                $senderNumber,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ?>"
                    >

                <?php else: ?>

                    <input
                        type="text"
                        name="sender_number"
                        id="sender_number"
                        placeholder="Enter your mobile number"
                        minlength="10"
                        maxlength="10"
                        inputmode="numeric"
                        autocomplete="tel"
                        value="<?=
                            htmlspecialchars(
                                $senderNumber,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ?>"
                        required
                    >

                <?php endif; ?>


            </div>

        </div>



        <!-- NAME -->

        <div class="form-group">

            <label for="sender_name">

                👤 Your Name

            </label>


            <div class="input-wrap">


                <span class="input-icon">
                    👤
                </span>


                <input
                    type="text"
                    id="sender_name"
                    name="sender_name"
                    placeholder="Enter your name"
                    minlength="3"
                    autocomplete="name"
                    value="<?=
                        htmlspecialchars(
                            $senderName,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ?>"
                    required
                >


            </div>

        </div>



        <!-- PASSWORD -->

        <div class="form-group">

            <label for="password">

                🔐 Password

            </label>


            <div class="input-wrap">


                <span class="input-icon">
                    🔑
                </span>


                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Minimum 8 characters"
                    minlength="8"
                    autocomplete="current-password"
                    value="<?=
                        htmlspecialchars(
                            $password,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ?>"
                    required
                    style="padding-right: 50px;"
                >


                <button
                    type="button"
                    class="password-toggle"
                    id="passwordToggle"
                    onclick="myPassword()"
                >
                    👁
                </button>


            </div>

        </div>



        <!-- SHOW PASSWORD -->

        <div class="show-password">

            <input
                type="checkbox"
                id="checkbox"
                onclick="myPassword()"
            >


            <label for="checkbox">

                Show password

            </label>

        </div>



        <!-- =================================================
             BUTTONS
        ================================================== -->

        <div class="button-row">


            <!-- MAIN -->

            <?php if ($mode === 'edit'): ?>

                <button
                    type="submit"
                    class="main-btn"
                    name="action"
                    value="update"
                >

                    ✓ Update Details

                </button>

            <?php else: ?>

                <button
                    type="submit"
                    class="main-btn"
                    name="action"
                    value="check"
                >

                    💬 Open Chat

                </button>

            <?php endif; ?>


            <!-- RESET – shown only when in edit mode -->
            <?php if ($mode === 'edit'): ?>

                <button
                    type="submit"
                    class="reset-btn"
                    name="action"
                    value="reset"
                >
                    ↻ Reset
                </button>

            <?php endif; ?>


        </div>


    </form>



    <!-- =====================================================
         SECURITY
    ====================================================== -->

    <div class="security">

        🔒

        <span>
            Your session is protected
        </span>

    </div>


</div>




</div>



<script>

/* =========================================================
   PASSWORD SHOW / HIDE
========================================================= */

function myPassword()
{

    const password =
        document.getElementById(
            "password"
        );

    const check =
        document.getElementById(
            "checkbox"
        );

    const toggle =
        document.getElementById(
            "passwordToggle"
        );


    if (
        password.type === "password"
    ) {

        password.type = "text";

        check.checked = true;

        toggle.innerText = "🙈";

    } else {

        password.type = "password";

        check.checked = false;

        toggle.innerText = "👁";

    }

}


/* =========================================================
   NUMBER ONLY
========================================================= */

const numberInput =
    document.getElementById(
        "sender_number"
    );


if (numberInput) {

    numberInput.addEventListener(
        "input",
        function()
        {

            this.value =
                this.value.replace(
                    /\D/g,
                    ""
                );

        }
    );

}


/* =========================================================
   BACK BUTTON
========================================================= */

function goBack()
{

    window.location.href =
        '../../../Main A.html';

}


/* =========================================================
   PREVENT ACCIDENTAL DOUBLE SUBMIT
========================================================= */

const form =
    document.querySelector(
        "form"
    );


if (form) {

    form.addEventListener(
        "submit",
        function(event)
        {

            const clicked =
                document.activeElement;


            if (
                clicked &&
                clicked.name === "action" &&
                clicked.value === "reset"
            ) {

                return;

            }


            if (
                clicked &&
                clicked.value === "check"
            ) {

                clicked.disabled = true;

                clicked.innerHTML =
                    "⏳ Opening Chat...";

            }


            if (
                clicked &&
                clicked.value === "update"
            ) {

                clicked.disabled = true;

                clicked.innerHTML =
                    "⏳ Updating...";

            }

        }
    );

}

</script>


</body>

</html>