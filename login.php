<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

$adminUser = 'harshmaster@4182';
$adminPass = 'examadmin2026';

$message = '';


/* ======================
   ALREADY LOGGED IN
====================== */

if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in']
) {

    header('Location: admin.php');

    exit;
}


/* ======================
   LOGIN
====================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username =
        $_POST['username'] ?? '';

    $password =
        $_POST['password'] ?? '';


    if (
        $username === $adminUser &&
        $password === $adminPass
    ) {

        $_SESSION['admin_logged_in'] = true;

        header('Location: admin.php');

        exit;
    }


    $message =
        'Invalid admin credentials.';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#2563eb"
>

<title>Admin Login</title>

<link
    rel="icon"
    type="image/png"
    href="../images/hhh%20picture.png"
>


<style>

/* ==========================================================
   RESET
========================================================== */

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}


/* ==========================================================
   BODY
========================================================== */

body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            #dbeafe 0%,
            transparent 35%
        ),
        radial-gradient(
            circle at bottom right,
            #ede9fe 0%,
            transparent 35%
        ),
        #f8fafc;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;

    color: #172033;

}


/* ==========================================================
   PAGE
========================================================== */

.page {

    width: 100%;

    max-width: 440px;

}


/* ==========================================================
   LOGIN CARD
========================================================== */

.card {

    background:
        rgba(255,255,255,.96);

    border:
        1px solid rgba(255,255,255,.8);

    border-radius: 24px;

    padding: 34px;

    box-shadow:
        0 25px 70px
        rgba(15,23,42,.12);

    backdrop-filter:
        blur(15px);

    animation:
        cardAppear .45s ease;

}


@keyframes cardAppear {

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


/* ==========================================================
   HEADER
========================================================== */

.header {

    text-align: center;

    margin-bottom: 27px;

}


.admin-icon {

    width: 72px;

    height: 72px;

    margin:
        0 auto 17px;

    border-radius: 22px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    color: white;

    font-size: 34px;

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.28);

}


.header h1 {

    font-size: 27px;

    font-weight: 800;

    color: #111827;

    margin-bottom: 8px;

}


.header p {

    font-size: 14px;

    line-height: 1.5;

    color: #64748b;

}


/* ==========================================================
   ERROR MESSAGE
========================================================== */

.alert {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 20px;

    padding: 12px 14px;

    border:
        1px solid #fca5a5;

    border-radius: 11px;

    background: #fef2f2;

    color: #b91c1c;

    font-size: 13px;

    font-weight: 600;

}


/* ==========================================================
   FIELD
========================================================== */

.field {

    margin-bottom: 19px;

}


.field label {

    display: block;

    margin-bottom: 8px;

    color: #334155;

    font-size: 13px;

    font-weight: 700;

}


/* ==========================================================
   INPUT WRAPPER
========================================================== */

.input-wrapper {

    position: relative;

}


/* ==========================================================
   INPUT ICON
========================================================== */

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


/* ==========================================================
   INPUT
========================================================== */

.field input[type="text"],
.field input[type="password"] {

    width: 100%;

    height: 48px;

    padding:
        0 14px 0 43px;

    border:
        1px solid #cbd5e1;

    border-radius: 12px;

    outline: none;

    background: #f8fafc;

    color: #172033;

    font-size: 14px;

    transition:
        border-color .2s,
        box-shadow .2s,
        background .2s;

}


.field input[type="text"]:focus,
.field input[type="password"]:focus {

    background: white;

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);

}


/* ==========================================================
   PASSWORD SHOW BUTTON
========================================================== */

.password-toggle {

    position: absolute;

    right: 7px;

    top: 7px;

    width: 34px;

    height: 34px;

    border: none;

    border-radius: 9px;

    background: transparent;

    color: #64748b;

    cursor: pointer;

    font-size: 16px;

}


.password-toggle:hover {

    background: #f1f5f9;

    color: #2563eb;

}


/* ==========================================================
   SHOW PASSWORD
========================================================== */

.show-password {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-top: -3px;

    margin-bottom: 22px;

}


.show-password input {

    width: 17px;

    height: 17px;

    cursor: pointer;

    accent-color: #2563eb;

}


.show-password label {

    margin: 0;

    color: #64748b;

    font-size: 13px;

    cursor: pointer;

}


/* ==========================================================
   BUTTONS
========================================================== */

.buttons {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

}


/* ==========================================================
   BUTTON
========================================================== */

.btn {

    height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition:
        transform .2s,
        box-shadow .2s,
        background .2s;

}


/* ==========================================================
   LOGIN BUTTON
========================================================== */

.btn-login {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.22);

}


.btn-login:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,.30);

}


.btn-login:active {

    transform:
        translateY(0);
}


/* ==========================================================
   BACK BUTTON
========================================================== */

.btn-back {

    background: #f1f5f9;

    color: #475569;

    border:
        1px solid #e2e8f0;

}


.btn-back:hover {

    background: #e2e8f0;

}


/* ==========================================================
   LOADING
========================================================== */

.btn.loading {

    pointer-events: none;

    opacity: .7;

}


.spinner {

    display: inline-block;

    width: 15px;

    height: 15px;

    border:
        2px solid
        rgba(255,255,255,.5);

    border-top-color: white;

    border-radius: 50%;

    animation:
        spin .7s linear infinite;

    vertical-align: middle;

    margin-right: 6px;

}


@keyframes spin {

    to {

        transform:
            rotate(360deg);

    }

}


/* ==========================================================
   SECURITY NOTE
========================================================== */

.security {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    margin-top: 21px;

    color: #94a3b8;

    font-size: 11px;

}


/* ==========================================================
   FOOTER
========================================================== */

.footer {

    text-align: center;

    margin-top: 18px;

    color: #94a3b8;

    font-size: 11px;

}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 500px) {

    body {

        padding: 14px;

    }


    .card {

        padding: 26px 20px;

        border-radius: 20px;

    }


    .admin-icon {

        width: 62px;

        height: 62px;

        border-radius: 18px;

        font-size: 29px;

    }


    .header h1 {

        font-size: 23px;

    }


    .header p {

        font-size: 13px;

    }


    .buttons {

        grid-template-columns: 1fr;

    }


    .btn {

        width: 100%;

    }

}


/* ==========================================================
   VERY SMALL SCREEN
========================================================== */

@media (max-width: 330px) {

    .card {

        padding:
            22px 15px;

    }

    .header h1 {

        font-size: 21px;

    }

}

</style>

</head>


<body>


<div class="page">


    <div class="card">


        <!-- ==================================================
             HEADER
        ================================================== -->

        <section class="header">


            <div class="admin-icon">
                🔐
            </div>


            <h1>
                Admin Login
            </h1>


            <p>
                Sign in to access your
                chat administration panel.
            </p>


        </section>



        <!-- ==================================================
             ERROR
        ================================================== -->

        <?php if ($message): ?>

            <div class="alert">

                ⚠️

                <span>
                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

            </div>

        <?php endif; ?>



        <!-- ==================================================
             LOGIN FORM
        ================================================== -->

        <form
            method="post"
            id="loginForm"
            autocomplete="on"
        >


            <!-- USERNAME -->

            <div class="field">

                <label for="username">
                    Username
                </label>


                <div class="input-wrapper">


                    <span class="input-icon">
                        👤
                    </span>


                    <input
                        id="username"
                        name="username"
                        type="text"
                        placeholder="Enter admin username"
                        autocomplete="username"
                        required
                        autofocus
                    >


                </div>

            </div>



            <!-- PASSWORD -->

            <div class="field">

                <label for="password">
                    Password
                </label>


                <div class="input-wrapper">


                    <span class="input-icon">
                        🔑
                    </span>


                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Enter admin password"
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        aria-label="Show password"
                        onclick="togglePassword()"
                    >
                        👁
                    </button>


                </div>

            </div>



            <!-- SHOW PASSWORD -->

            <div class="show-password">

                <input
                    type="checkbox"
                    id="showPassword"
                    onclick="togglePassword()"
                >

                <label
                    for="showPassword"
                >
                    Show password
                </label>

            </div>



            <!-- BUTTONS -->

            <div class="buttons">


                <button
                    type="submit"
                    class="btn btn-login"
                    id="loginButton"
                >
                    🔓 Login
                </button>


                <button
                    type="button"
                    class="btn btn-back"
                    onclick="goBack()"
                >
                    ← Back
                </button>


            </div>


        </form>



        <!-- ==================================================
             SECURITY
        ================================================== -->

        <div class="security">

            🔒

            <span>
                Secure administrator access
            </span>

        </div>


    </div>



    <div class="footer">

        Chat Administration Panel

    </div>


</div>



<script>

/* ==========================================================
   PASSWORD TOGGLE
========================================================== */

function togglePassword() {

    const password =
        document.getElementById(
            'password'
        );

    const checkbox =
        document.getElementById(
            'showPassword'
        );

    const toggle =
        document.getElementById(
            'passwordToggle'
        );


    if (
        password.type === 'password'
    ) {

        password.type = 'text';

        checkbox.checked = true;

        toggle.innerText = '🙈';

        toggle.setAttribute(
            'aria-label',
            'Hide password'
        );

    } else {

        password.type = 'password';

        checkbox.checked = false;

        toggle.innerText = '👁';

        toggle.setAttribute(
            'aria-label',
            'Show password'
        );
    }
}


/* ==========================================================
   BACK
========================================================== */

function goBack() {

    window.location.href =
        'chat.php';
}


/* ==========================================================
   LOGIN LOADING
========================================================== */

document
    .getElementById('loginForm')
    .addEventListener(
        'submit',
        function() {

            const button =
                document.getElementById(
                    'loginButton'
                );


            button.classList.add(
                'loading'
            );


            button.innerHTML = `

                <span class="spinner"></span>

                Logging in...

            `;

        }
    );

</script>


</body>

</html>