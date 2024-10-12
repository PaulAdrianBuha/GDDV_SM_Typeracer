<?php

define("EMAIL_FROM", "gddvsmtp@gmail.com"); // constant

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incloure lla libreria PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

// defaults
$template = 'home';
$db_connection = 'sqlite:..\private\users.db';
//global $db = new PDO($db_connection);

$configuration = array(
    '{FEEDBACK}'          => '',
    '{LOGIN_LOGOUT_TEXT}' => 'Identificar-me',
    '{LOGIN_LOGOUT_URL}'  => '/?page=login',
    '{REGISTER_URL}'      => '/?page=register',
    '{RECOVERY_URL}'      => '/?page=recovery',
    '{SITE_NAME}'         => 'La meva pàgina',
);

// parameter processing
$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'POST') {
    $parameters = $_POST;
} else if ($method == 'GET') {
    $parameters = $_GET;
} else {
    $parameters = [];
}
 
if (isset($parameters['page'])) {
    // When user navigates to a diferent specific form page (register, login, recovery) 
    getPage($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['register'])) {
    // When user submits the register form
    postRegister($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['login'])) {
    // When user submits the login form
    postLogin($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['verifyEmail'])) {
    // When user presses the verify button from the email
    verifyAccount($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['resendVerifyEmail'])) {
    // When user tries to login without verifying or when they press the resend verification email button
    resendVerifyEmail($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['recovery'])) {
    // When user submits the account recovery form
    postRecovery($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['changePasswordConfirm'])) {
    // When user confirms the password change
    changePassword($template, $db_connection, $configuration, $parameters);
} else {
    if (isset($_COOKIE['SessionCookie'])) { // if the session is still open, directly log in the player
        $db = new PDO($db_connection);
        $sql = 'SELECT * FROM cookies c JOIN users u ON c.user_id = u.user_id WHERE c.cookie = :cookie';
        $query = $db->prepare($sql);
        $query->bindValue(':cookie', $_COOKIE["SessionCookie"]);
        $query->execute();

        $result_row = $query->fetch();
        if($result_row) {
            //$template = 'home';
            $configuration['{FEEDBACK}'] = '"Sessió" iniciada com <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar "sessió"';
            $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
        } else {
            $configuration['{FEEDBACK}'] = "<mark>ERROR: Hi ha hagut un error a la sessió</mark>";
        }
        printHtml($template, $configuration);
    } else {
        // default page view when first entering
        printHtml($template, $configuration);
    }
}

// FUNCTIONS -----------------------------------------------------------------------------------------------------------------

// process template and show output
function printHtml($template, $configuration) {
    echo getHtml($template, $configuration);
}

// retrieve the html (with replacements) given a template
function getHtml($template, $configuration) {
    $html = file_get_contents('plantilla_' . $template . '.html', true);
    $html = str_replace(array_keys($configuration), array_values($configuration), $html);
    return $html;
}

// (VIEW) Either the register, the login page, or the password recovery page. Before submitting any form
function getPage($template, $db_connection, $configuration, $parameters) {
    if ($parameters['page'] == 'register') {
        $template = 'register';
        $configuration['{REGISTER_USERNAME}'] = '';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Ja tinc un compte';

    } else if ($parameters['page'] == 'login') {
        $template = 'login';
        $configuration['{LOGIN_USERNAME}'] = '';

    } else if ($parameters['page'] == 'recovery') {
        $template = 'recovery';
        $configuration['{FEEDBACK}'] = "Introdueix el teu nom d'usuari o correu electrònic i rebràs un email per canviar la contraseña.";
        
    } else if ($parameters['page'] == 'change_password') {
        // Executed when pressing the change password button from the email
        $db = new PDO($db_connection);
        $sql = 'SELECT * FROM users WHERE (user_verification_code = :user_verification_code AND user_email = :user_email)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_verification_code', $parameters['user_verification_code']);
        $query->bindValue(':user_email', $parameters['user_email']);
        $query->execute();
        $result_row = $query->fetch();
        if ($result_row) {
            $template = 'change_password';
            $configuration['{USER_VERIFICATION_CODE}'] = $parameters['user_verification_code'];
            $configuration['{USER_EMAIL}'] = $parameters['user_email'];
        } else {
            //$template = 'home';
            $configuration['{FEEDBACK}'] = "S'ha produït un error en el procés de recuperació.";
        }
    } else if ($parameters['page'] == 'logout') {
        deleteCookieDB($db_connection);
        setcookie("SessionCookie", expires_or_options: time() - 1);
    }

    printHtml($template, $configuration);
}

// Delete cookie from database
function deleteCookieDB($db_connection) {
    $db = new PDO($db_connection);
    $sql = 'DELETE FROM cookies WHERE cookie = :cookie';
    $query = $db->prepare($sql);
    $query->bindValue(':cookie', $_COOKIE["SessionCookie"]);
    $query->execute();
}

// Insert session cookie into database
function addCookieDB($db_connection, $cookie, $userId) {
    $db = new PDO($db_connection);
    $sql = 'INSERT INTO cookies (cookie, user_id) VALUES (:cookie, :user_id)';
    $query = $db->prepare($sql);
    $query->bindValue(':cookie', $cookie);
    $query->bindValue(':user_id', $userId);
    try {
        $query->execute();
    } catch (Exception $e) {
        echo "Error";
    }
}

// Create session
function createSession($db_connection, $result_row) {
    $cookieValue = strval(random_int(0,99999999));
    setcookie("SessionCookie", $cookieValue, time() + (500 * 365 * 24 * 60 * 60)); // expires in 500 years
    echo $db_connection, $cookieValue, $result_row['user_id'];
    addCookieDB($db_connection, $cookieValue, $result_row['user_id']);
}

// (VIEW) Executed after submitting the registration form
// Uses plantilla_reverify
function postRegister($template, $db_connection, $configuration, $parameters) {
    if (strlen($parameters['user_password']) < 8) {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut crear el compte <b>"
                . htmlentities($parameters['user_name']) . '</b> La contrasenya ha de ser de 8 caràcters com a mínim</mark>';
    } else if ($parameters['user_password'] != $parameters['user_repeat_password']) {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: Les contrasenyes no coincideixen</mark>";
    } else {
        $template = 'reverify';
         // Generamos un codigo random para verificar que enviaremos al correo y asociamos al user en la base de datos
        $verificationcode = random_bytes(10);

        $db = new PDO($db_connection);
        $sql = 'INSERT INTO users (user_name, user_password, user_email, user_verification_code) VALUES (:user_name, :user_password, :user_email, :user_verification_code)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_name', $parameters['user_name']);
        $query->bindValue(':user_password', password_hash($parameters['user_password'], PASSWORD_BCRYPT));
        $query->bindValue(':user_email', $parameters['user_email']);
        $query->bindValue(':user_verification_code', $verificationcode);
        try {
            $query->execute();
            $configuration['{FEEDBACK}'] = 'Revisa el correu per verificar el teu compte:  <b>' . htmlentities($parameters['user_name']) . ' <br/> ' . htmlentities($parameters['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = "Torna a l'inici";
            $configuration['{LOGIN_LOGOUT_URL}'] = '/';
            $configuration['{HOME_SECOND_BUTTON_URL}'] = "/?resendVerifyEmail=true&user_verification_email=" . urlencode($parameters['user_email']);

            sendVerificationEmail($parameters['user_email'], $verificationcode);
        } catch (PDOException $e) {
            // Això no s'executarà mai (???)
            $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pugut crear el compte <b>"
            . htmlentities($parameters['user_name']) . '</b></mark>';
        }
    }
    printHtml($template, $configuration);
}

// (VIEW) Executed after submitting the login form
// Uses plantilla_home if the user is verified and plantilla_reverify if not
function postLogin($template, $db_connection, $configuration, $parameters) {
    $db = new PDO($db_connection);
    $sql = 'SELECT * FROM users WHERE (user_name = :user_name OR user_email = :user_name)';
    $query = $db->prepare($sql);
    $query->bindValue(':user_name', $parameters['user_name']);
    // $query->bindValue(':user_password', $parameters['user_password']);
    $query->execute();
    $result_row = $query->fetch();
    if ($result_row && password_verify($parameters['user_password'], $result_row['user_password'])) {
        if ($result_row['user_verified'] == 0) {
            $template = 'reverify';
            $configuration['{FEEDBACK}'] = 'Revisa el correu per verificar el teu compte:  <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = "Torna a l'inici";
            $configuration['{LOGIN_LOGOUT_URL}'] = '/';
            $configuration['{HOME_SECOND_BUTTON_URL}'] = "/?resendVerifyEmail=true&user_verification_email=" . urlencode($result_row['user_email']);
        } else {
            //$template = 'home';
            createSession($db_connection, $result_row);

            $configuration['{FEEDBACK}'] = '"Sessió" iniciada com <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar "sessió"';
            $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
        }
    } else {
        //$template = 'home';
        $configuration['{FEEDBACK}'] = '<mark>ERROR: Usuari desconegut o contrasenya incorrecta</mark>';
    }
    printHtml($template, $configuration);
}

// (VIEW) Executed after submitting the account recovery form
// Uses plantilla_recovery (loops back to it)
function postRecovery($template, $db_connection, $configuration, $parameters) {
    $template = 'recovery';
    $db = new PDO($db_connection);
    $sql = 'SELECT * FROM users WHERE (user_name = :recovery_name OR user_email = :recovery_name)';
    $query = $db->prepare($sql);
    $query->bindValue(':recovery_name', $parameters['recovery_name']);
    $query->execute();
    $result_row = $query->fetch();
    if ($result_row) {
        $configuration['{FEEDBACK}'] = 'Revisa el correu per canviar la contrasenya:  <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
    
        sendRecoveryEmail($result_row['user_email'], $result_row['user_verification_code']);
    } else {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut enviar el correu de recuperació.</mark>";
    }
    printHtml($template, $configuration);
}

// (VIEW) Executed when pressing the verify button from the email
// Uses plantilla_home, because it acts as logging into the account
function verifyAccount($template, $db_connection, $configuration, $parameters) {
    $db = new PDO($db_connection);
    $sql = 'SELECT * FROM users WHERE (user_verification_code = :user_verification_code AND user_email = :user_verification_email)';
    $query = $db->prepare($sql);
    $query->bindValue(':user_verification_code', $parameters['user_verification_code']);
    $query->bindValue(':user_verification_email', $parameters['user_verification_email']);
    $query->execute();
    $result_row = $query->fetch();
    if ($result_row) {
        // La verificación será correcta si al pulsar el botón de verificar (que enviará el código por GET) coincide con el código de verificación de la base de datos
        $sql = 'UPDATE users SET user_verified = 1 WHERE (user_email = :user_email)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_email', $result_row['user_email']);
        $query->execute();

        createSession($db_connection, $result_row);
        
        $configuration['{FEEDBACK}'] = '"Sessió" iniciada com <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar "sessió"';
        $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
            
    } else {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut verificar el compte</mark>";
    }
    printHtml($template, $configuration);
}

// Sends a specific verification email to the specified email. Automatically executed after registering
function sendVerificationEmail($emailTo, $verificationcode) {
    $verificationTemplateVars = [
        "{VERIFICATION_CODE}" => urlencode($verificationcode),
        "{VERIFICATION_EMAIL}" => urlencode($emailTo), // encodifiquem el paràmetre per a que correus com exemple+1@gmail.com funcionin
        "{TIMESTAMP}" => date('l jS \of F Y h:i:s A') // afegim una variable que sigui diferent per cada correu per a que no s'amagui a partir de la 2a vegada
    ];
    sendEmail($emailTo, "Verifica el teu compte", getHtml('verification_email', $verificationTemplateVars));
}

// (VIEW) Executed when trying to log in without having verified the account beforehand or when choosing to resend the verification email.
// Is basically a page acting as barrier, the "resend verification email" option is displayed
// Uses plantilla_reverify
function resendVerifyEmail($template, $db_connection, $configuration, $parameters) {
    $template = 'reverify';
    $db = new PDO($db_connection);
    $sql = 'SELECT * FROM users WHERE (user_email = :user_verification_email)';
    $query = $db->prepare($sql);
    $query->bindValue(':user_verification_email', $parameters['user_verification_email']);
    $query->execute();
    $result_row = $query->fetch();
    if ($result_row) {
        $configuration['{FEEDBACK}'] = 'Revisa el correu per verificar el teu compte:  <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = "Torna a l'inici";
        $configuration['{LOGIN_LOGOUT_URL}'] = '/';
        $configuration['{HOME_SECOND_BUTTON_URL}'] = "/?resendVerifyEmail=true&user_verification_email=" . urlencode($result_row['user_email']);
    
        sendVerificationEmail($result_row['user_email'], $result_row['user_verification_code']);
    } else {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut verificar el compte</mark>";
    }
    printHtml($template, $configuration);
}

// Sends a specific email for recovering your password
function sendRecoveryEmail($emailTo, $verificationcode) {
    $recoveryTemplateVars = [
        "{VERIFICATION_CODE}" => urlencode($verificationcode),
        "{EMAIL}" => urlencode($emailTo), // encodifiquem el paràmetre per a que correus com exemple+1@gmail.com funcionin
        "{TIMESTAMP}" => date('l jS \of F Y h:i:s A') // afegim una variable que sigui diferent per cada correu per a que no s'amagui a partir de la 2a vegada
    ];
    sendEmail($emailTo, "Recupera el teu compte", getHtml('recovery_email', $recoveryTemplateVars));
}

// Generic funtion for sending emails
function sendEmail($emailTo, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Configuracio SMTP
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;               // Mostrar sortida(Desactivar en producció)
        $mail->isSMTP();                                        // Activar enviament SMTP
        $mail->Host  = 'smtp.gmail.com';                        // Servidor SMTP
        $mail->SMTPAuth  = true;                                // Identificacio SMTP
        $mail->Username  = 'gddvsmtp@gmail.com';                // Usuari SMTP
        $mail->Password  = 'pyzgmvqmbtjetzow';	                // Contrasenya SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port  = 587;
        $mail->setFrom('gddvsmtp@gmail.com','GDDV SMTP');       // Remitent del correu

        // Destinataris
        $mail->addAddress($emailTo);                            // Email i nom del destinatari

        // Contingut del correu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body  = $message;
        $mail->AltBody = $message;
        $mail->send();
    } catch (Exception $e) {
        echo "El missatge no s'ha pogut enviar. Mailer Error: {$mail->ErrorInfo}";
    }
}

function changePassword($template, $db_connection, $configuration, $parameters) {
    if (strlen($parameters['user_password']) < 8) {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut canviar la contrasenya. La contrasenya ha de ser de 8 caràcters com a mínim.</mark>";
    } else if ( $parameters['user_recover_password'] != $parameters['user_repeat_password']) {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: Les contrasenyes no coincideixen</mark>";
    } else {
        $db = new PDO($db_connection);
        $sql = 'UPDATE users SET user_password = :user_recover_password WHERE (user_email = :user_email AND user_verification_code = :user_verification_code)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_recover_password', password_hash($parameters['user_recover_password'], PASSWORD_BCRYPT));
        $query->bindValue(':user_email', $parameters['user_email']);
        $query->bindValue(':user_verification_code', $parameters['user_verification_code']);
        try {
            $query->execute();

            $configuration['{FEEDBACK}'] = "S'ha canviat la contrasenya exitosament";
        } catch (PDOException $e) {
            $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut canviar la contrasenya</mark>";
        }
    }
    printHtml($template, $configuration);
}