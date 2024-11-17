let idJoc, idJugador, phrase;
let guanyador = null;
let sabotageCharValue = null;

const textEstat = document.getElementById('estat');
const textEstat2 = document.getElementById('estat2');
const divJoc = document.getElementById('joc');
const divAreaDeJoc = document.getElementById('areaDeJoc');

const latencyPlayer = document.getElementById('latencyPlayer');
const latencyOtherPlayer = document.getElementById('latencyOtherPlayer');

const usernamePlayer = document.getElementById('usernamePlayer');
const usernameOtherPlayer = document.getElementById('usernameOtherPlayer');

const progressPlayer = document.getElementById('progressPlayer');
const progressOtherPlayer = document.getElementById('progressOtherPlayer');

const textToType = document.getElementById('textToType');
const textTypedRight = document.getElementById('textTypedRight');
const textTypedWrong = document.getElementById('textTypedWrong');
const textLeft = document.getElementById('textLeft');

const input = document.getElementById('input');
const hiddenInput = document.getElementById('hiddenInput');
const sabotageChar = document.getElementById('sabotageChar');

const userIdInput = document.getElementById('user_id');

var joc_inicialitzat = false;

// Connectar al servidor del joc
function unirseAlJoc() {
    var userId = userIdInput.value;
    fetch(`game.php?action=join&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            idJoc = data.game_id;
            idJugador = data.player_id;
            phrase = data.phrase;
            progressPlayer.max = phrase.length;
            progressOtherPlayer.max = phrase.length;
            textLeft.textContent = phrase;
            comprovarEstatDelJoc();
        });
}

function inicialitzarJoc() {
    joc_inicialitzat = true;
    fetch(`game.php?action=init_status&game_id=${idJoc}`)
        .then(response => response.json())
        .then(data => {
            usernamePlayer.innerText = data.usernamePlayer;
            usernameOtherPlayer.innerText = data.usernameOtherPlayer;
        });
}

// Comprovar l'estat del joc cada mig segon
var current_delay = 0;
var prev_delays = [0, 0, 0, 0, 0];
var game_tick = 0;

function comprovarEstatDelJoc() {
    var time1 = Date.now(); // "start timer" before fetch petition to benchmark how much time it takes to come back

    game_tick += 1;
    var stable_delay = prev_delays.reduce((sum, v) => sum + v, 0) / prev_delays.length; // average of recent delays
    var sent_delay = game_tick % 5 == 0 ? stable_delay : -1; // has the actual value of stable_delay only every 5 game ticks, else -1

    fetch(`game.php?action=status&game_id=${idJoc}&delay=${sent_delay}`)
        .then(response => response.json())
        .then(joc => {
            if (joc.error) {
                textEstat.innerText = joc.error;
                return;
            }

            guanyador = joc.winner;
            sabotageCharValue = joc.active_sabotage_char;

            // DISPLAY LATENCY
            var time2 = Date.now();
            current_delay = (time2 - time1) / 2;
            prev_delays.push(current_delay); // push_back
            prev_delays.shift(); // pop_front

            if (sent_delay != -1)
            {
                latencyPlayer.innerText = Math.round(stable_delay) + " ms";
                latencyOtherPlayer.innerText = Math.round(joc.rival_delay) + " ms";
            }
            console.log(prev_delays, " ", stable_delay, " ", latencyPlayer.innerText);

            if (joc.player1 === idJugador) {
                if (joc.player2) {
                    if (!joc_inicialitzat) inicialitzarJoc();
                    textEstat.innerText = 'Joc en curs...';
                    divJoc.style.display = 'block';
                    sabotageChar.textContent = joc.active_sabotage_char;
                    progressPlayer.value = joc.progress_player1;
                    progressOtherPlayer.value = joc.progress_player2;
                    
                    if (joc.active_sabotage_player != null) {
                        if (joc.active_sabotage_player == "DRAW") {
                            // Els dos jugadors han arribat a sabotejar gairebé al mateix temps
                            sabotageChar.style.backgroundColor = "#b3d5df";
                            textEstat2.innerText = "Has aturat el sabotatge del rival!";
                        } else if (joc.active_sabotage_player == idJugador) {
                            // Has sabotejat als altres jugadors durant 3 segons
                            sabotageChar.style.backgroundColor = "#bedfb3";
                            textEstat2.innerText = "Has sabotejat al rival!";
                        } else {
                            // Has sigut sabotejat per 3 segons
                            sabotageChar.style.backgroundColor = "#dfb3b3";
                            if (joc.active_sabotage_in_progress) {
                                input.disabled = true;
                                textEstat2.innerText = "Has sigut sabotejat!";
                            } else {
                                // Ja no estàs sabotejat (sabotage char changes)
                                input.disabled = false;
                                textEstat2.innerHTML = "&nbsp;";
                                input.focus();
                            }
                        }
                    } else {
                        if (input.disabled) {
                            // Ja no estàs sabotejat (sabotage char changes)
                            input.disabled = false;
                            textEstat2.innerHTML = "&nbsp;";
                            input.focus();
                        }
                        else {
                            // El teu rival ja no està sabotejat
                            textEstat2.innerHTML = "&nbsp;"; // (Per fer clear del "Has sabotejat al rival!")
                        }
                        sabotageChar.style.backgroundColor = "#ffffff";
                    }
                } else {
                    textEstat.innerText = 'Ets el Jugador 1. Esperant el Jugador 2...';
                }
            } else if (joc.player2 === idJugador) {
                if (!joc_inicialitzat) inicialitzarJoc();
                textEstat.innerText = 'Joc en curs...';
                divJoc.style.display = 'block';
                sabotageChar.textContent = joc.active_sabotage_char;
                progressPlayer.value = joc.progress_player2;
                progressOtherPlayer.value = joc.progress_player1;

                if (joc.active_sabotage_player != null) {
                    if (joc.active_sabotage_player == "DRAW") {
                        // Els dos jugadors han arribat a sabotejar gairebé al mateix temps
                        sabotageChar.style.backgroundColor = "#b3d5df";
                        textEstat2.innerText = "Has aturat el sabotatge del rival!";
                    } else if (joc.active_sabotage_player == idJugador) {
                         // Has sabotejat als altres jugadors durant 3 segons
                        sabotageChar.style.backgroundColor = "#bedfb3";
                        textEstat2.innerText = "Has sabotejat al rival!";
                    } else {
                         // Has sigut sabotejat per 3 segons
                        sabotageChar.style.backgroundColor = "#dfb3b3";
                        if (joc.active_sabotage_in_progress) { // si l'altre jugador ha arribat primer
                            input.disabled = true;
                            textEstat2.innerText = "Has sigut sabotejat!";
                        } else {
                            input.disabled = false;
                            textEstat2.innerHTML = "&nbsp;";
                            input.focus();
                        }
                    }
                } else {
                    if (input.disabled) {
                        // Ja no estàs sabotejat
                        input.disabled = false;
                        textEstat2.innerHTML = "&nbsp;";
                        input.focus();
                    }
                    else {
                        // El teu rival ja no està sabotejat
                        textEstat2.innerHTML = "&nbsp;"; // (Per fer clear del "Has sabotejat al rival!")
                    }
                    sabotageChar.style.backgroundColor = "#ffffff";
                }
            } else {
                textEstat.innerText = 'Espectant...';
                divJoc.style.display = 'block';
            }
            
            if (guanyador) {
                if (joc.player1 === idJugador) {
                    progressPlayer.value = joc.progress_player1;
                    progressOtherPlayer.value = joc.progress_player2;
                } else {
                    progressPlayer.value = joc.progress_player2;
                    progressOtherPlayer.value = joc.progress_player1;
                }
                input.disabled = true;
                
                if (guanyador == "DRAW") {
                    textEstat.innerText = 'Heu empatat!';
                    divAreaDeJoc.style.backgroundColor = "#b3d5df"; // blue background
                } else if (guanyador === idJugador) {
                    textEstat.innerText = 'Has guanyat!';
                    divAreaDeJoc.style.backgroundColor = "#bedfb3"; // green background
                } else {
                    textEstat.innerText = 'Has perdut!';
                    divAreaDeJoc.style.backgroundColor = "#dfb3b3"; // red background
                }
                
                return;
            }
            setTimeout(comprovarEstatDelJoc, 500);
        });
}

function handleSabotageKey() {
    fetch(`game.php?action=sabotage&game_id=${idJoc}&sabotage_char=${sabotageCharValue}`)
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                textEstat2.innerText = data.message;
            }
        });
}

input.addEventListener('input', function(event) {
    // Check if the input contains the sabotageCharValue
    if (sabotageCharValue != null && event.target.value.includes(sabotageCharValue)) {
        // Prevent the default input behavior
        event.target.value = event.target.value.replace(new RegExp(`\\${sabotageCharValue}`, 'g'), ''); // Remove sabotage char from the input
        handleSabotageKey(sabotageCharValue); // Call your method when the sabotage char is found
    }
    
    const textToTypeText = textTypedRight.textContent + textTypedWrong.textContent + textLeft.textContent;

    // Find the number of matching characters
    let text = hiddenInput.value + event.target.value;
    let matchCount = [...text].findIndex((char, i) => char !== textToTypeText[i]); // Find first non-matching (last correct character)

    // If all characters match (findIndex == -1), set matchCount to the length, otherwise keep it
    matchCount = matchCount === -1 ? text.length : matchCount;

    textTypedRight.textContent = textToTypeText.substring(0, matchCount); // from start to the last matching char
    textTypedWrong.textContent = textToTypeText.substring(matchCount, text.length); // from the last matching char to the current typed char
    textLeft.textContent = textToTypeText.substring(text.length, textToTypeText.length); // from the current typed char to the end

    if (event.target.value.substring(event.target.value.length - 1, event.target.value.length) == " ") {
        if (textTypedWrong.textContent == "") {
            hiddenInput.value += event.target.value;
            event.target.value = "";
        }
    }
    
    if (matchCount == textToTypeText.length) {
        input.disabled = true;
    }
 
    fetch(`game.php?action=type&game_id=${idJoc}&progress=${matchCount}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                textEstat2.innerText = data.error;
            }
        });
});

// Iniciar el joc unint-se
unirseAlJoc();