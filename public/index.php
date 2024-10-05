<?php

define("EMAIL_FROM", "gddvsmtp@gmail.com");

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
$configuration = array(
    '{FEEDBACK}'          => '',
    '{LOGIN_LOGOUT_TEXT}' => 'Identificar-me',
    '{LOGIN_LOGOUT_URL}'  => '/?page=login',
    '{REGISTER_URL}'      => '/?page=register',
    '{SITE_NAME}'         => 'La meva pàgina'
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
    // When user navigates to specific diferent page 
    getPage($template, $configuration, $parameters);
} else if (isset($parameters['register'])) {
    // When user submits register form
    postRegister($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['login'])) {
    // When user submits login form
    postLogin($template, $db_connection, $configuration, $parameters);
} else if (isset($parameters['verifyEmail'])) {
    verifyAccount($template, $db_connection, $configuration, $parameters);
} else {
    // default page view when first entering
    printHtml($template, $configuration);
}

// FUNCTIONS -----------------------------------------------------------------------------------------------------------------

// process template and show output
function printHtml($template, $configuration) {
    echo getHtml($template, $configuration);
}

function getHtml($template, $configuration) {
    $html = file_get_contents('plantilla_' . $template . '.html', true);
    $html = str_replace(array_keys($configuration), array_values($configuration), $html);
    return $html;
}

function getPage($template, $configuration, $parameters) {
    if ($parameters['page'] == 'register') {
        $template = 'register';
        $configuration['{REGISTER_USERNAME}'] = '';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Ja tinc un compte';
    } else if ($parameters['page'] == 'login') {
        $template = 'login';
        $configuration['{LOGIN_USERNAME}'] = '';
    }
    printHtml($template, $configuration);
}

function postRegister($template, $db_connection, $configuration, $parameters) {
    if (strlen($parameters['user_password']) < 8) {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut crear el compte <b>"
                . htmlentities($parameters['user_name']) . '</b> La contrasenya ha de ser de 8 caràcters com a mínim</mark>';
    } else {
        // Generamos un codigo random para verificar que enviaremos al correo y asociamos al user en la base de datos
        $verificationcode = strval(random_int(0, 9999999));

        $db = new PDO($db_connection);
        // TODO Hacer SELECT EXISTS user con email o password y dar un error menos generico, antes de hacer el INSERT
        $sql = 'INSERT INTO users (user_name, user_password, user_email, user_verification_code) VALUES (:user_name, :user_password, :user_email, :user_verification_code)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_name', $parameters['user_name']);
        $query->bindValue(':user_password', password_hash($parameters['user_password'], PASSWORD_BCRYPT));
        $query->bindValue(':user_email', $parameters['user_email']);
        $query->bindValue(':user_verification_code', $verificationcode);
        try {
            $query->execute();
            // TODO NO DEJAR INICIAR HASTA QUE VERIFIQUE LA CUENTA, MOSTRAR ALGUNA TEMPLATE DE plantilla_feedback.html MAYBE?
            $configuration['{FEEDBACK}'] = 'Creat el compte <b>' . htmlentities($parameters['user_name']) . ' <br/> ' . htmlentities($parameters['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar sessió';
            
            sendVerificationEmail($parameters['user_email'], $verificationcode);
        } catch (PDOException $e) {
             // Això no s'executarà mai (???)
             $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pugut crear el compte <b>"
             . htmlentities($parameters['user_name']) . '</b></mark>';
        }
    }
    printHtml($template, $configuration);
}

function postLogin($template, $db_connection, $configuration, $parameters) {
    // When user submits login form
    $db = new PDO($db_connection);
    // TODO CHECK USER IS VERIFIED
    $sql = 'SELECT * FROM users WHERE (user_name = :user_name OR user_email = :user_name)';
    $query = $db->prepare($sql);
    $query->bindValue(':user_name', $parameters['user_name']);
    // $query->bindValue(':user_password', $parameters['user_password']);
    $query->execute();
    $result_row = $query->fetch();
    if ($result_row && password_verify($parameters['user_password'], $result_row['user_password'])) {
        $configuration['{FEEDBACK}'] = '"Sessió" iniciada com <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar "sessió"';
        $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
    } else {
        $configuration['{FEEDBACK}'] = '<mark>ERROR: Usuari desconegut o contrasenya incorrecta</mark>';
    }
    printHtml($template, $configuration);
}

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
        // gestionar error???

        $configuration['{FEEDBACK}'] = '"Sessió" iniciada com <b>' . htmlentities($result_row['user_name']) . ' <br/> ' . htmlentities($result_row['user_email']) . '</b>';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar "sessió"';
        $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
    } else {
        $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pogut verificar el compte</mark>";
    }
    printHtml($template, $configuration);
}

function sendVerificationEmail($emailTo, $verificationcode) {
    $verificationTemplateVars = [
        "{VERIFICATION_CODE}" => $verificationcode,
        "{VERIFICATION_EMAIL}" => urlencode($emailTo) // encodifiquem el paràmetre per a que correus com exemple+1@gmail.com funcionin
    ];
    sendEmail($emailTo, "Verifica el teu compte", getHtml('verification_email', $verificationTemplateVars));
}

function sendRecoveryEmail($emailTo) {
    sendEmail($emailTo, "Recupera el teu compte", getHtml('recovery_email', []));
}

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
        echo 'El missatge s\'ha enviat';
    } catch (Exception $e) {
        echo "El missatge no s'ha enviat. Mailer Error: {$mail->ErrorInfo}";
    }
}