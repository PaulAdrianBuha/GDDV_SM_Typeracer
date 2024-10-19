<?php
session_start();

// Connectar a la base de dades SQLite
try {
    $db = new PDO('sqlite:../private/games.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connexió amb la base de dades fallida: ' . $e->getMessage()]);
    exit();
}

$accio = isset($_GET['action']) ? $_GET['action'] : '';

switch ($accio) {
    case 'join':
        if (!isset($_SESSION['player_id'])) {
            $_SESSION['player_id'] = uniqid();
        }

        $player_id = $_SESSION['player_id'];
        $game_id = null;
        $phrase = null;

        // Intentar unir-se a un joc existent on player2 sigui null
        $stmt = $db->prepare('SELECT games.game_id, phrases.phrase_id, phrases.phrase FROM games LEFT JOIN phrases ON games.phrase_id = phrases.phrase_id WHERE player2 IS NULL LIMIT 1');
        $stmt->execute();
        $joc_existent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($joc_existent) {
            // Unir-se al joc existent com a player2
            $game_id = $joc_existent['game_id'];
            $phrase = $joc_existent['phrase'];
            
            $stmt = $db->prepare('UPDATE games SET player2 = :player_id WHERE game_id = :game_id');
            $stmt->bindValue(':player_id', $player_id);
            $stmt->bindValue(':game_id', $game_id);
            $stmt->execute();
        } else {
            // Crear un nou joc com a player1
            $stmt = $db->prepare('SELECT phrase_id, phrase FROM phrases');
            $stmt->execute();
            $phrases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $phrase_object = $phrases[rand(0, count($phrases) - 1)];
            $phrase_id = $phrase_object['phrase_id'];
            $phrase = $phrase_object['phrase'];

            $game_id = uniqid();
            $stmt = $db->prepare('INSERT INTO games (game_id, player1, phrase_id, previous_sabotage_start_time) VALUES (:game_id, :player_id, :phrase_id, :previous_sabotage_start_time)');
            $stmt->bindValue(':game_id', $game_id);
            $stmt->bindValue(':player_id', $player_id);
            $stmt->bindValue(':phrase_id', $phrase_id);
            $stmt->bindValue(':previous_sabotage_start_time', time());
            $stmt->execute();
        }

        echo json_encode(['game_id' => $game_id, 'player_id' => $player_id, 'phrase' => $phrase]);
        break;

    case 'status':
        $game_id = $_GET['game_id'];
        $stmt = $db->prepare('SELECT games.*, sabotages.sabotage_char as active_sabotage_char FROM games LEFT JOIN sabotages ON games.active_sabotage_id = sabotages.sabotage_id WHERE game_id = :game_id');
        $stmt->bindValue(':game_id', $game_id);
        $stmt->execute();
        $joc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$joc) {
            echo json_encode(['error' => 'Joc no trobat']);
        } else {
            // TEST PURPOSES ONLY
            $player_id = $_SESSION['player_id'];
            // Comprovar si cal generar un nou sabotatge
            if ($joc['player1'] && $joc['player2'] && !$joc['winner'] && $player_id == $joc['player1']) {
                
                // Temps actual
                $current_time = time();

                // Cada 15 segons un sabotatge
                $timeBetweenSabotages = 10;
                if ($current_time > ($joc['previous_sabotage_start_time'] + $timeBetweenSabotages)) {
                    $stmt = $db->prepare('SELECT * FROM sabotages');
                    $stmt->execute();
                    $sabotages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $sabotage_object = $sabotages[rand(0, count($sabotages) - 1)]; // TODO: Mirar si estamos haciendo esto bien. Cada player hara su fetch y generara un rand diferente
                    $sabotage_id = $sabotage_object['sabotage_id'];
                    $sabotage_char = $sabotage_object['sabotage_char'];
                    
                    $stmt = $db->prepare('UPDATE games SET active_sabotage_id = :active_sabotage_id,
                        active_sabotage_start_time = :active_sabotage_start_time,
                        active_sabotage_player = :active_sabotage_player,
                        active_sabotage_done_time = :active_sabotage_done_time,
                        previous_sabotage_start_time = :previous_sabotage_start_time 
                        WHERE game_id = :game_id'
                    );
                    $stmt->bindValue(':active_sabotage_id', $sabotage_id);
                    $stmt->bindValue(':active_sabotage_start_time', $current_time);
                    $stmt->bindValue(':active_sabotage_player', null);
                    $stmt->bindValue(':active_sabotage_done_time', null);
                    $stmt->bindValue(':previous_sabotage_start_time', $joc['active_sabotage_start_time']);
                    $stmt->bindValue(':game_id', $game_id);
                    $stmt->execute();

                    $joc['active_sabotage_id'] = $sabotage_id;
                    $joc['active_sabotage_char'] = $sabotage_char;
                    $joc['active_sabotage_start_time'] = $current_time;
                    $joc['active_sabotage_player'] = null;
                    $joc['active_sabotage_done_time'] = null;
                    $joc['previous_sabotage_start_time'] = $joc['active_sabotage_start_time'];
                    
                }
            }
            
            echo json_encode([
                'player1' => $joc['player1'],
                'player2' => $joc['player2'],
                'winner' => $joc['winner'],
                'progress_player1' => $joc['progress_player1'],
                'progress_player2' => $joc['progress_player2'],
                'active_sabotage_char' => $joc['active_sabotage_char']
            ]);
        }
        break;
    case 'type':
        $game_id = $_GET['game_id'];
        $player_id = $_SESSION['player_id'];
        $progress = $_GET['progress'];

        $stmt = $db->prepare('SELECT games.*, phrases.phrase FROM games LEFT JOIN phrases ON games.phrase_id = phrases.phrase_id WHERE game_id = :game_id LIMIT 1');
        $stmt->bindValue(':game_id', $game_id);
        $stmt->execute();
        $joc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$joc || $joc['winner']) {
            echo json_encode(['error' => 'Joc finalitzat o no trobat']);
            break;
        }

        /* TODO APLICAR A TYPE
        if (!$joc['circle_visible']) {
            echo json_encode(['error' => 'No hi ha cap cercle per fer clic']);
            break;
        }

        // Comprovar si algú ja ha fet clic al cercle
        if ($joc['next_circle_time'] !== null && $joc['next_circle_time'] > time()) {
            echo json_encode(['error' => 'El cercle ja ha estat clicat']);
            break;
        }
        */

        // Determinar quin jugador ha escrit text
        if ($joc['player1'] === $player_id) {
            $stmt = $db->prepare('UPDATE games SET progress_player1 = :progress_player1 WHERE game_id = :game_id');
            $stmt->bindValue(':game_id', $game_id);
            $stmt->bindValue(':progress_player1', $progress);
            $stmt->execute();
            $joc['progress_player1'] = $progress; // Actualitzar l'array $joc
        } elseif ($joc['player2'] === $player_id) {
            $stmt = $db->prepare('UPDATE games SET progress_player2 = :progress_player2 WHERE game_id = :game_id');
            $stmt->bindValue(':game_id', $game_id);
            $stmt->bindValue(':progress_player2', $progress);
            $stmt->execute();
            $joc['progress_player2'] = $progress; // Actualitzar l'array $joc
        } else {
            echo json_encode(['error' => 'Jugador invàlid']);
            break;
        }

        // TODO Comprovar si hi ha un guanyador, fer servir llargada paraula game i comparar-la amb llargada player
        if (strlen($joc['phrase']) == $joc['progress_player1']) {
            $stmt = $db->prepare('UPDATE games SET winner = :player_id WHERE game_id = :game_id');
            $stmt->bindValue(':player_id', $joc['player1']);
            $stmt->bindValue(':game_id', $game_id);
            $stmt->execute();
        } elseif (strlen($joc['phrase']) == $joc['progress_player2']) {
            $stmt = $db->prepare('UPDATE games SET winner = :player_id WHERE game_id = :game_id');
            $stmt->bindValue(':player_id', $joc['player2']);
            $stmt->bindValue(':game_id', $game_id);
            $stmt->execute();
        }

        echo json_encode(['success' => true]);
        break;
}
