CREATE TABLE IF NOT EXISTS games (
    game_id TEXT PRIMARY KEY,
    player1 TEXT,
    player2 TEXT,
    points_player1 INTEGER DEFAULT 0,
    points_player2 INTEGER DEFAULT 0,
    circle_x INTEGER DEFAULT NULL,
    circle_y INTEGER DEFAULT NULL,
    circle_visible INTEGER DEFAULT 0,
    next_circle_time INTEGER DEFAULT NULL,
    winner TEXT,
    progress_player1 INTEGER DEFAULT 0,
    progress_player2 INTEGER DEFAULT 0
);