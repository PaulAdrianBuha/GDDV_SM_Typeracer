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
        $stmt = $db->prepare('SELECT games.game_id, phrases.phrase_id, phrases.phrase FROM games LEFT JOIN phrases ON games.phrase_id = phrases.phrase_id WHERE games.player2 IS NULL LIMIT 1');
        $stmt->execute();
        $joc_existent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($joc_existent) {
            // Unir-se al joc existent com a player2
            $game_id = $joc_existent['game_id'];
            $phrase = $joc_existent['phrase'];
            
            $stmt = $db->prepare('UPDATE games SET player2 = :player_id,
                    active_sabotage_start_time = :active_sabotage_start_time
                    WHERE game_id = :game_id');
            $stmt->bindValue(':player_id', $player_id);
            $stmt->bindValue(':game_id', $game_id);
            $stmt->bindValue(':active_sabotage_start_time', microtime(true)); // the game clock starts, in 15s players will be able to sabotage
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
            $stmt = $db->prepare('INSERT INTO games (game_id, player1, phrase_id) VALUES (:game_id, :player_id, :phrase_id)');
            $stmt->bindValue(':game_id', $game_id);
            $stmt->bindValue(':player_id', $player_id);
            $stmt->bindValue(':phrase_id', $phrase_id);
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

        $sabotageChanged = false;
        $current_time = microtime(true);
        $prev_time = $joc['previous_sabotage_start_time'];

        if (!$joc) {
            echo json_encode(['error' => 'Joc no trobat']);
        } else {
            $player_id = $_SESSION['player_id'];

            $wait_for_other_player_time = 1;

            // Manage who wins first --------------------------------------------------------------------------------------------------------------------------
            $win_diff_to_draw_time = 2;
            if ($joc['winner'] == null) // If the game hasn't chosen a winner yet
            {
                if ($joc['win_time_p1'] != null && $joc['win_time_p2'] != null)
                {
                    $stmt = $db->prepare('UPDATE games SET winner = :winner,
                        win_time_p1 = :win_time_p1,
                        win_time_p2 = :win_time_p2
                        WHERE game_id = :game_id'
                    );
                    $stmt->bindValue(':win_time_p1', null);
                    $stmt->bindValue(':win_time_p2', null);
                    $stmt->bindValue(':game_id', $game_id);

                    if (abs($joc['win_time_p2'] - $joc['win_time_p1']) < $win_diff_to_draw_time) 
                    {
                        $stmt->bindValue(':winner', "DRAW");
                        $joc['winner'] = "DRAW";
                    }
                    else
                    {
                        if ($joc['win_time_p2'] > $joc['win_time_p1'])
                        {
                            $stmt->bindValue(':winner', $joc['player1']);
                            $joc['winner'] = $joc['player1'];
                        }
                        else //($joc['win_time_p2'] < $joc['win_time_p1'])
                        {
                            $stmt->bindValue(':winner', $joc['player2']);
                            $joc['winner'] = $joc['player2'];
                        }
                    } 

                    $stmt->execute();
                }
                elseif ($joc['win_time_p1'] == null && $joc['win_time_p2'] != null) // Check if p2 already sabotaged but not enough time elapsed to determine if the p1 also sabotages
                {
                    if ($current_time > $joc['win_time_p2'] + $wait_for_other_player_time) //TODO: Testear esto
                    {
                        $stmt = $db->prepare('UPDATE games SET winner = :winner,
                            win_time_p2 = :win_time_p2
                            WHERE game_id = :game_id'
                        );
                        $stmt->bindValue(':winner', $joc['player2']);
                        $stmt->bindValue(':win_time_p2', null);
                        $stmt->bindValue(':game_id', $game_id);
                        $stmt->execute();

                        $joc['winner'] = $joc['player2'];
                    }
                }
                elseif ($joc['win_time_p1'] != null && $joc['win_time_p2'] == null) // Check if p1 already sabotaged but not enough time elapsed to determine if the p2 also sabotages
                {
                    if ($current_time > $joc['win_time_p1'] + $wait_for_other_player_time) //TODO: Testear esto
                    {
                        $stmt = $db->prepare('UPDATE games SET winner = :winner,
                            win_time_p1 = :win_time_p1
                            WHERE game_id = :game_id'
                        );
                        $stmt->bindValue(':winner', $joc['player1']);
                        $stmt->bindValue(':win_time_p1', null);
                        $stmt->bindValue(':game_id', $game_id);
                        $stmt->execute();

                        $joc['winner'] = $joc['player1'];
                    }
                }
            }

            // Manage who sabotages first --------------------------------------------------------------------------------------------------------------------------
            $sabotage_diff_to_draw_time = 1;
            if ($joc['active_sabotage_done_time'] == null) // If the game hasn't chosen a sabotage winner yet
            {
                if ($joc['active_sabotage_done_time_p1'] != null && $joc['active_sabotage_done_time_p2'] != null)
                {
                    $stmt = $db->prepare('UPDATE games SET active_sabotage_player = :active_sabotage_player,
                        active_sabotage_done_time_p1 = :active_sabotage_done_time_p1,
                        active_sabotage_done_time_p2 = :active_sabotage_done_time_p2,
                        active_sabotage_done_time = :active_sabotage_done_time
                        WHERE game_id = :game_id'
                    );
                    $stmt->bindValue(':active_sabotage_done_time', $current_time);
                    $stmt->bindValue(':active_sabotage_done_time_p1', null);
                    $stmt->bindValue(':active_sabotage_done_time_p2', null);

                    $stmt->bindValue(':game_id', $game_id);

                    if (abs($joc['active_sabotage_done_time_p2'] - $joc['active_sabotage_done_time_p1']) < $sabotage_diff_to_draw_time) 
                    {
                        $stmt->bindValue(':active_sabotage_player', "DRAW");
                        $joc['active_sabotage_player'] = "DRAW";
                    }
                    else
                    {
                        if ($joc['active_sabotage_done_time_p2'] > $joc['active_sabotage_done_time_p1'])
                        {
                            $stmt->bindValue(':active_sabotage_player', $joc['player1']);
                            $joc['active_sabotage_player'] = $joc['player1'];
                        }
                        else //($joc['active_sabotage_done_time_p2'] < $joc['active_sabotage_done_time_p1'])
                        {
                            $stmt->bindValue(':active_sabotage_player', $joc['player2']);
                            $joc['active_sabotage_player'] = $joc['player2'];
                        }
                    } 
                    $joc['active_sabotage_done_time'] = $current_time;

                    $stmt->execute();
                }
                elseif ($joc['active_sabotage_done_time_p1'] == null && $joc['active_sabotage_done_time_p2'] != null) // Check if p2 already sabotaged but not enough time elapsed to determine if the p1 also sabotages
                {
                    if ($current_time > $joc['active_sabotage_done_time_p2'] + $wait_for_other_player_time) //TODO: Testear esto
                    {
                        $stmt = $db->prepare('UPDATE games SET active_sabotage_player = :active_sabotage_player,
                            active_sabotage_done_time = :active_sabotage_done_time,
                            active_sabotage_done_time_p2 = :active_sabotage_done_time_p2
                            WHERE game_id = :game_id'
                        );
                        $stmt->bindValue(':active_sabotage_player', $joc['player2']);
                        $stmt->bindValue(':active_sabotage_done_time', $current_time);
                        $stmt->bindValue(':active_sabotage_done_time_p2', null);
                        $stmt->bindValue(':game_id', $game_id);
                        $stmt->execute();

                        $joc['active_sabotage_player'] = $joc['player2'];
                        $joc['active_sabotage_done_time'] = $current_time;
                    }
                }
                elseif ($joc['active_sabotage_done_time_p1'] != null && $joc['active_sabotage_done_time_p2'] == null) // Check if p1 already sabotaged but not enough time elapsed to determine if the p2 also sabotages
                {
                    if ($current_time > $joc['active_sabotage_done_time_p1'] + $wait_for_other_player_time) //TODO: Testear esto
                    {
                        $stmt = $db->prepare('UPDATE games SET active_sabotage_player = :active_sabotage_player,
                            active_sabotage_done_time = :active_sabotage_done_time,
                            active_sabotage_done_time_p1 = :active_sabotage_done_time_p1
                            WHERE game_id = :game_id'
                        );
                        $stmt->bindValue(':active_sabotage_player', $joc['player1']);
                        $stmt->bindValue(':active_sabotage_done_time', $current_time);
                        $stmt->bindValue(':active_sabotage_done_time_p1', null);
                        $stmt->bindValue(':game_id', $game_id);
                        $stmt->execute();

                        $joc['active_sabotage_player'] = $joc['player1'];
                        $joc['active_sabotage_done_time'] = $current_time;
                    }
                }
            }
            
            // Update sabotage char --------------------------------------------------------------------------------------------------------------------------------
            // Only one of the players generates a new symbol
            if ($joc['player1'] && $joc['player2'] && !$joc['winner'] && $player_id == $joc['player1']) {
                // Every 10 seconds a new sabotage char is generated
                $timeBetweenSabotages = max(10, $joc['active_sabotage_done_time'] + 3 - $joc['active_sabotage_start_time']);
                if ($current_time > ($joc['active_sabotage_start_time'] + $timeBetweenSabotages)) {
                    $stmt = $db->prepare('SELECT * FROM sabotages'); // get list of sabotage symbols
                    $stmt->execute();
                    $sabotages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $sabotage_object = $sabotages[rand(0, count($sabotages) - 1)]; // choose one at random
                    $sabotage_id = $sabotage_object['sabotage_id'];
                    $sabotage_char = $sabotage_object['sabotage_char'];
                    
                    $stmt = $db->prepare('UPDATE games SET active_sabotage_id = :active_sabotage_id,
                        active_sabotage_player = :active_sabotage_player,
                        active_sabotage_done_time = :active_sabotage_done_time,
                        active_sabotage_start_time = :active_sabotage_start_time,
                        previous_sabotage_start_time = :previous_sabotage_start_time 
                        WHERE game_id = :game_id'
                    );
                    $stmt->bindValue(':active_sabotage_id', $sabotage_id);
                    $stmt->bindValue(':active_sabotage_start_time', $current_time);
                    $stmt->bindValue(':previous_sabotage_start_time', $joc['active_sabotage_start_time']); // about to be previous
                    $stmt->bindValue(':active_sabotage_player', null);
                    $stmt->bindValue(':active_sabotage_done_time', null);
                    $stmt->bindValue(':game_id', $game_id);
                    $stmt->execute();

                    $joc['active_sabotage_id'] = $sabotage_id;
                    $joc['active_sabotage_char'] = $sabotage_char;
                    $joc['active_sabotage_start_time'] = $current_time;
                    $joc['previous_sabotage_start_time'] = $joc['active_sabotage_start_time'];
                    $joc['active_sabotage_player'] = null;
                    $joc['active_sabotage_done_time'] = null;
                    
                    $sabotageChanged = true;
                }
            }

            // This disables the ability to sabotage after sabotaging until the symbol changes again
            if ($joc['active_sabotage_done_time'] != null && !($joc['active_sabotage_done_time'] + 3 > microtime(true)))
            {
                $stmt = $db->prepare('UPDATE games SET active_sabotage_id = :active_sabotage_id,
                        active_sabotage_player = :active_sabotage_player
                        WHERE game_id = :game_id'
                );
                $stmt->bindValue(':active_sabotage_id', null);
                $stmt->bindValue(':active_sabotage_player', null);
                $stmt->bindValue(':game_id', $game_id);
                $stmt->execute();

                $joc['active_sabotage_id'] = null;
                $joc['active_sabotage_char'] = null;
                $joc['active_sabotage_player'] = null;
            }
            
            echo json_encode([
                'player1' => $joc['player1'],
                'player2' => $joc['player2'],
                'winner' => $joc['winner'],
                'progress_player1' => $joc['progress_player1'],
                'progress_player2' => $joc['progress_player2'],
                'sabotage_debug1' => $sabotageChanged,
                'sabotage_debug2' => $current_time,
                'sabotage_debug3' => $prev_time,
                'active_sabotage_char' => $joc['active_sabotage_char'],
                'active_sabotage_player' => $joc['active_sabotage_player'],
                'active_sabotage_in_progress' => $joc['active_sabotage_done_time'] != null
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

        // Comprovar si hi ha un guanyador, fer servir llargada paraula game i comparar-la amb llargada player
        if (strlen($joc['phrase']) == $joc['progress_player1']) {
            $stmt = $db->prepare('UPDATE games SET win_time_p1 = :win_time_p1 WHERE game_id = :game_id');
            $stmt->bindValue(':win_time_p1', microtime(true));
            $stmt->bindValue(':game_id', $game_id);
            $stmt->execute();
        } elseif (strlen($joc['phrase']) == $joc['progress_player2']) {
            $stmt = $db->prepare('UPDATE games SET win_time_p2 = :win_time_p2 WHERE game_id = :game_id');
            $stmt->bindValue(':win_time_p2', microtime(true));
            $stmt->bindValue(':game_id', $game_id);
            $stmt->execute();
        }
        break;

    case 'sabotage':
        $game_id = $_GET['game_id'];
        $player_id = $_SESSION['player_id'];
        $sabotage_char = $_GET['sabotage_char'];

        $stmt = $db->prepare('SELECT games.*, sabotages.sabotage_char as active_sabotage_char FROM games LEFT JOIN sabotages ON games.active_sabotage_id = sabotages.sabotage_id WHERE game_id = :game_id');
        $stmt->bindValue(':game_id', $game_id);
        $stmt->execute();
        $joc = $stmt->fetch(PDO::FETCH_ASSOC);

        
        if (($joc['player1'] === $player_id && $joc['active_sabotage_done_time_p1'] != null) ||
            ($joc['player2'] === $player_id && $joc['active_sabotage_done_time_p2'] != null))
        {
            echo json_encode(['message' => 'El sabotage ja s\'havia fet']);
            break;
        }

        if ($joc['active_sabotage_char'] == null) {
            echo json_encode(['message' => 'No hi ha cap sabotatge actiu']);
            break;
        }

        if ($joc['active_sabotage_char'] != $sabotage_char) {
            echo json_encode(['message' => 'Tecla de sabotatge caducada',
                    'log_active_sabotage_char' => $joc['active_sabotage_char'],
                    'log_sabotage_char' => $sabotage_char
                ]);
            break;
        }

        if ($joc['player1'] === $player_id)
        {
            $stmt = $db->prepare('UPDATE games SET
                            active_sabotage_done_time_p1 = :active_sabotage_done_time_p1
                            WHERE game_id = :game_id');
            $stmt->bindValue(':active_sabotage_done_time_p1', microtime(true));
        }
        elseif ($joc['player2'] === $player_id)
        {
            $stmt = $db->prepare('UPDATE games SET
                            active_sabotage_done_time_p2 = :active_sabotage_done_time_p2
                            WHERE game_id = :game_id');
            $stmt->bindValue(':active_sabotage_done_time_p2', microtime(true));
        }
        //$stmt->bindValue(':active_sabotage_player', $player_id);
        $stmt->bindValue(':game_id', $game_id);
        $stmt->execute();
        break;
}
