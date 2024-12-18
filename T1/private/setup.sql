CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    user_name VARCHAR(63),
    user_password VARCHAR(255),
    user_email VARCHAR(255) UNIQUE,
    user_verified INTEGER NOT NULL DEFAULT 0,
    user_verification_code VARCHAR(255)
);

CREATE UNIQUE INDEX user_name_UNIQUE ON users (user_name ASC);

CREATE TABLE IF NOT EXISTS cookies (
    cookie varchar(255) PRIMARY KEY,
    user_id INTEGER
);

CREATE TABLE IF NOT EXISTS user_secrets (
    secret_id INTEGER PRIMARY KEY,
    user_id INTEGER,
    secret_text varchar(255),
    secret_creation_time INTEGER
);


CREATE TABLE IF NOT EXISTS games (
    game_id TEXT PRIMARY KEY,
    player1 TEXT,
    player2 TEXT,
    winner TEXT,
    win_time_p1 FLOAT DEFAULT NULL, -- Time when the player 1 wins
    win_time_p2 FLOAT DEFAULT NULL, -- Time when the player 2 wins
    progress_player1 INTEGER DEFAULT 0,
    progress_player2 INTEGER DEFAULT 0,
    delay_player1 INTEGER DEFAULT 0, -- Stable latency of player 1 in ms (time it takes for their petition to come back to them)
    delay_player2 INTEGER DEFAULT 0, -- Stable latency of player 2 in ms (time it takes for their petition to come back to them)
    phrase_id TEXT, -- Phrase id for the game
    active_sabotage_id TEXT DEFAULT NULL, -- Current sabotage id
    active_sabotage_start_time FLOAT DEFAULT NULL, -- Sabotage start time in s
    active_sabotage_player TEXT DEFAULT NULL, -- Player who sabotages the other player
    active_sabotage_done_time_p1 FLOAT DEFAULT NULL, -- Time in s when the player 1 does their sabotage
    active_sabotage_done_time_p2 FLOAT DEFAULT NULL, -- Time in s when the player 2 does their sabotage
    active_sabotage_done_time FLOAT DEFAULT NULL -- Time in s when the sabotage is actually processed and takes effect
);

CREATE TABLE IF NOT EXISTS phrases (
    phrase_id TEXT PRIMARY KEY,
    phrase TEXT
);

INSERT INTO phrases (phrase_id, phrase) VALUES
    ("1", "I would ask you all to consider this carefully and to give me any suggestions that may occur to you. In the meantime I warn everybody to be upon his or her guard. So far the murderer has had an easy task, since his victims have been unsuspicious. From now on, it is our task to suspect each and every one amongst us. Forewarned is forearmed. Take no risks and be alert to danger. That is all."),
    ("2", "Danger is a topic you're often asked about as a sprinter. The common perception is that the final 200 meters of a Tour stage are the most dangerous and the most frightening. That might be half right, but while it may hold true that the final few seconds of a bike race are fastest and apparently chaotic, at 70 kph and 200 heartbeats per minute, there's simply no time for anxiety. Adrenaline, yes. Instinct, sure. But fear, no."),
    ("3", "Pray your love is deep for me. I'ma make you go weak for me. Make you wait a whole week for me. I see you watchin', I know you want it, I know you need it."),
    ("4", "The man who lies to himself and listens to his own lie comes to a point that he cannot distinguish the truth within him, or around him, and so loses all respect for himself and for others."),
    ("5", "If I can't find the cure, I'll fix you with my love. No matter what you know, I'll fix you with my love. And if you say you're okay, I'm gonna heal you anyway. Promise I'll always be there, promise I'll be the cure."),
    ("6", "It's a fallen situation when all eyes are turned in and a love isn't flowing the way it could have been. You brought it all on but it feels so wrong. You brought it all on. I don't believe this song."),
    ("7", "Although the United Nations does not have the power to enforce decisions or compel nations to take military action, the ability to compel member nations to impose economic sanctions against countries guilty of violating security orders gives it significant power in the world stage."),
    ("8", "She did not tell them to clean up their lives, or go and sin no more. She did not tell them they were the blessed of the earth, its inheriting meek, or its glory-bound pure. She told them that the only grace they could have is the grace they could imagine. That if they could not see it, they could not have it."),
    ("9", "Focus your attention on the Now and tell me what problem you have at this moment. I am not getting any answer because it is impossible to have a problem when your attention is fully in the Now. A situation that needs to be either dealt with or accepted - yes."),
    ("10","Every night you cry yourself to sleep, thinking 'Why does this happen to me?' Why does every moment have to be so hard? Hard to believe that it's not over tonight, just give me one more chance to make it right. I may not make it through the night, I won't go home without you.");

CREATE TABLE IF NOT EXISTS sabotages (
    sabotage_id TEXT PRIMARY KEY, 
    sabotage_char TEXT(1)
);

INSERT INTO sabotages (sabotage_id, sabotage_char) VALUES
    ("1", "$"),
    ("2", "@"),
    ("3", "="),
    ("4", "¬"),
    ("5", "¿"),
    ("6", "|");