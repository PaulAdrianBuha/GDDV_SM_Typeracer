<?php

define("EMAIL_FROM", "gddvsmtp@gmail.com");

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
} else {
    // default page view when first entering
    printHtml($template, $configuration);
}

// FUNCTIONS -----------------------------------------------------------------------------------------------------------------

// process template and show output
function printHtml($template, $configuration) {
    $html = file_get_contents('plantilla_' . $template . '.html', true);
    $html = str_replace(array_keys($configuration), array_values($configuration), $html);
    echo $html;
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
        $db = new PDO($db_connection);
        // TODO Hacer SELECT EXISTS user con email o password y dar un error menos generico, antes de hacer el INSERT
        $sql = 'INSERT INTO users (user_name, user_password, user_email) VALUES (:user_name, :user_password, :user_email)';
        $query = $db->prepare($sql);
        $query->bindValue(':user_name', $parameters['user_name']);
        $query->bindValue(':user_password', password_hash($parameters['user_password'], PASSWORD_BCRYPT));
        $query->bindValue(':user_email', $parameters['user_email']);
        try {
            $query->execute();

            $configuration['{FEEDBACK}'] = 'Creat el compte <b>' . htmlentities($parameters['user_name']) . ' <br/> ' . htmlentities($parameters['user_email']) . '</b>';
            $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar sessió';
            
            sendVerificationEmail($parameters['user_email']);
        } catch (PDOException $e) {
             // Això no s'executarà mai (???)
             $configuration['{FEEDBACK}'] = "<mark>ERROR: No s'ha pugut crear el compte <b>"
             . htmlentities($parameters['user_name']) . '</b></mark>';
        }
    }
    printHtml($template, $configuration);
}

function sendVerificationEmail($emailTo) {
    sendEmail($emailTo, "Verifica el teu compte", "Verifica el teu compte blabla");
}

function sendRecoveryEmail($emailTo) {
    sendEmail($emailTo, "Recupera el teu compte", "Recupera el teu compte blabla");
}

function sendEmail($emailTo, $subject, $message) {
    $headers = "From: " . EMAIL_FROM;
    if (mail($emailTo, $subject, $message, $headers)){
        echo "Ha ido bien" . $emailTo . " - " . $subject . " - " . $message . " - " . $headers;
    } else {
        echo "Ha ido mal";
    }
}

function postLogin($template, $db_connection, $configuration, $parameters) {
    // When user submits login form
    $db = new PDO($db_connection);
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