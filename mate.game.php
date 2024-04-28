<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Mate implementation : © <Matheus Gomes> <matheusgomesforwork@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * mate.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */


require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');


class Mate extends Table
{
    function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels(array(
            "trickSuit" => 11,
            "trickValue" => 12,
            "handsPlayed" => 13,
            "firstPlayer" => 14,
            "privilege" => 100,
        ));

        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName()
    {
        return "mate";
    }

    protected function setupNewGame($players, $options = array())
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        $this->DbQuery($sql);
        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        $this->setGameStateInitialValue('trickSuit', 0);
        $this->setGameStateInitialValue('trickValue', 0);
        $this->setGameStateInitialValue('handsPlayed', 0);
        $this->setGameStateInitialValue('firstPlayer', 0);

        $this->initStat("table", "tricks_number", 0);
        $this->initStat("table", "fewer_tricks_mate", 0);
        $this->initStat("table", "most_tricks_mate", 0);
        $this->initStat("player", "tricks_won", 0);

        $cards = array();
        foreach ($this->suits as $suit_id => $suit) {
            foreach ($this->values_label as $value => $value_label) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'order' => 0, 'nbr' => 1);
            }
        }

        $this->cards->createCards($cards, 'deck');

        $this->cards->shuffle('deck');

        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
        }

        $this->activeNextPlayer();
        $this->setGameStateValue('firstPlayer', $this->getActivePlayerId());

        /************ End of the game initialization *****/
    }

    protected function getAllDatas()
    {
        $result = array();

        $current_player_id = $this->getCurrentPlayerId();

        $players = $this->loadPlayersBasicInfos();

        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = $this->getCollectionFromDb($sql);

        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);

        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        foreach ($players as $player_id => $info) {
            $sql = "SELECT card_order, card_location, card_location_arg, card_type, card_type_arg FROM card WHERE card_location_arg='$player_id' AND card_location='cardswon'";
            $result['cardswon'][$player_id] = $this->getCollectionFromDb($sql);
        }

        return $result;
    }

    function getGameProgression()
    {
        $handsPlayed = $this->getGameStateValue("handsPlayed");

        return $handsPlayed * 25;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    

    function cantPlayCard($card_id, $player_id)
    {
        $currentCard = $this->cards->getCard($card_id);

        $currentTrickSuit = $this->getGameStateValue('trickSuit');
        $currentTrickValue = $this->getGameStateValue('trickValue');
        $privilege = $this->getGameStateValue('privilege');

        $this->warn($currentTrickSuit);
        $this->warn($currentTrickValue);

        $hand = $this->cards->getCardsInLocation('hand', $player_id);

        if (!$currentTrickSuit || !$currentTrickValue) {
            return false;
        }

        if ($currentTrickSuit && $currentTrickValue && $currentCard) {
            $same_suit = false;
            $k_in_hand = false;
            $q_in_hand = false;


            if ($currentTrickSuit != $currentCard['type'] && $currentCard['type_arg'] != $currentTrickValue) {
                return $this->_("You can't play this card now");
            }

            foreach ($hand as $card) {
                if ($currentTrickSuit == $card['type']) {
                    $same_suit = true;
                }

                if ($card['type_arg'] == 12) {
                    $q_in_hand = true;
                }

                if ($card['type_arg'] == 13) {
                    $k_in_hand = true;
                }

                if ($same_suit && $q_in_hand && $k_in_hand) {
                    break;
                }
            }

            if (($privilege == 1 || $privilege == 2) && $k_in_hand && $currentTrickValue == 13 && $currentCard['type_arg'] != 13) {
                return $this->_("You must play a K card");
            }

            if ($privilege == 2 && $q_in_hand && $currentTrickValue == 12 && $currentCard['type_arg'] != 12) {
                return $this->_("You must play a Q card");
            }

            if (
                $same_suit && $currentCard['type'] != $currentTrickSuit
                && !($currentTrickValue == 13 && $currentCard['type_arg'] == 13 && ($privilege == 1 || $privilege == 2))
                && !($currentTrickValue == 12 && $currentCard['type_arg'] == 12 && $privilege == 2)
            ) {
                return $this->_("You must play a card of the current suit");
            }
        }

        return false;
    }

    function getPlayableCards()
    {
        $playable_cards = array();
        foreach ($this->loadPlayersBasicInfos() as $player_id => $player) {
            $playable_cards[$player_id] = null;

            $hand = $this->cards->getCardsInLocation("hand", $player_id);

            foreach ($hand as $card_id => $card) {
                if (!$this->cantPlayCard($card_id, $player_id)) {
                    $playable_cards[$player_id][$card_id] = $card_id;
                }
            }
        }

        return $playable_cards;
    }

    function getOtherPlayer($players, $player_id)
    {
        $filtered_players = array_filter($players, fn ($other_player) =>
        $player_id != $other_player, ARRAY_FILTER_USE_KEY);
        $ids = array_keys($filtered_players);

        return array_shift($ids);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 

    function playCard($card_id, $order)
    {
        $this->checkAction("playCard");
        $player_id = $this->getActivePlayerId();

        $currentCard = $this->cards->getCard($card_id);

        $currentTrickSuit = $this->getGameStateValue('trickSuit');
        $currentTrickValue = $this->getGameStateValue('trickValue');
        $privilege = $this->getGameStateValue('privilege');

        $cant_play = $this->cantPlayCard($card_id, $player_id);
        if ($cant_play) {
            throw new BgaUserException($cant_play);
        }

        if (!$currentTrickSuit || !$currentTrickValue) {
            $this->setGameStateValue('trickSuit', $currentCard['type']);
            $this->setGameStateValue('trickValue', $currentCard['type_arg']);
        }

        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        $this->notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value_displayed} ${suit_displayed}'), array(
            'i18n' => array('suit_displayed', 'value_displayed'),
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'value' => $currentCard['type_arg'],
            'value_displayed' => $this->values_label[$currentCard['type_arg']],
            'suit' => $currentCard['type'],
            'suit_displayed' => $this->suits[$currentCard['type']]['name']
        ));

        $this->DbQuery("UPDATE card SET card_order=$order WHERE card_id='$card_id'");

        $this->gamestate->nextState('playCard');
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    function argPlayerTurn()
    {
        return array("playableCards" => $this->getPlayableCards());
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////


    function stNewHand()
    {
        if ($this->getGameStateValue('handsPlayed') > 0) {
            $players = $this->loadPlayersBasicInfos();

            foreach ($players as $player_id => $player) {
                $other_player = $this->getOtherPlayer($players, $player_id);
                $this->cards->moveAllCardsInLocation('cardsontable', 'temporary', $player_id, $other_player);
                $this->cards->moveAllCardsInLocation('cardswon', 'temporary', $player_id, $other_player);
                $this->cards->moveAllCardsInLocation('hand', 'temporary', $player_id, $other_player);
            }

            foreach ($players as $player_id => $player) {
                $this->cards->moveAllCardsInLocation('temporary', 'hand', $player_id, $player_id);

                $cards = $this->cards->getPlayerHand($player_id);
                $this->notifyPlayer($player_id, 'newHand', clienttranslate('A new hand starts. Players exchange hands.'), array('cards' => $cards));
            }
        }

        $this->gamestate->nextState("");
    }

    function stNewRound()
    {
        $players = $this->loadPlayersBasicInfos();

        $this->cards->moveAllCardsInLocation('hand', 'deck');
        $this->cards->moveAllCardsInLocation('cardswon', 'deck');
        $this->cards->moveAllCardsInLocation('cardsontable', 'deck');
        $this->cards->shuffle('deck');

        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
            $this->notifyPlayer($player_id, 'newHand', clienttranslate('A new round starts. Cards are shuffled and dealt again.'), array('cards' => $cards));
        }

        $this->gamestate->nextState("");
    }

    function stNewTrick()
    {

        if ($this->cards->countCardInLocation('cardsontable') == 2) {
            $players = $this->loadPlayersBasicInfos();

            foreach ($players as $player_id => $player) {
                $cards_on_table = $this->cards->getCardsInLocation('cardsontable', $player_id);
                $card = array_shift($cards_on_table);

                $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', $player_id, $player_id);

                $this->notifyAllPlayers('newTrick', '', array(
                    'player_id' => $player_id,
                    'suit' => $card['type'],
                    'value' => $card['type_arg'],
                ));
            }
        }

        $this->setGameStateValue('trickSuit', 0);
        $this->setGameStateValue('trickValue', 0);
        $this->gamestate->nextState("");
    }

    function stNextPlayer()
    {
        $players = $this->loadPlayersBasicInfos();

        $currentTrickSuit = $this->getGameStateValue('trickSuit');
        $currentTrickValue = $this->getGameStateValue('trickValue');

        $player_id = $this->getActivePlayerId();
        $this->giveExtraTime($player_id);

        if ($this->cards->countCardInLocation('cardsontable') == 1 && $currentTrickSuit && $currentTrickValue) {
            $next_player = $this->getOtherPlayer($players, $player_id);

            $can_play = false;
            $cards = $this->cards->getCardsInLocation('hand', $next_player);

            foreach ($cards as $card) {
                if ($card['type_arg'] == $currentTrickValue || $card['type'] == $currentTrickSuit) {
                    $can_play = true;
                    break;
                }
            }

            if (!$can_play) {
                $this->gamestate->nextState('endHand');
                return;
            }
        }

        if ($this->cards->countCardInLocation('cardsontable') == 2) {
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value_player_id = null;
            $currentTrickSuit = $this->getGameStateValue('trickSuit');
            $currentTrickValue = $this->getGameStateValue('trickValue');

            $same_suit = true;
            foreach ($cards_on_table as $card) {
                if ($card['type'] != $currentTrickSuit) $same_suit = false;
            }

            if ($same_suit) {
                foreach ($cards_on_table as $card) {
                    $card_value_strength = $this->values_strength[$card['type_arg']];
                    $current_strength = $this->values_strength[$currentTrickValue];

                    if (($best_value_player_id === null || $card_value_strength > $current_strength) && $card_value_strength >= $current_strength) {
                        $best_value_player_id = $card['location_arg'];
                    }
                }
            } else {

                foreach ($cards_on_table as $card) {
                    $card_suit_strength = $this->suits_strength[$card['type']];
                    $current_strength = $this->suits_strength[$currentTrickSuit];

                    if (
                        ($best_value_player_id === null || $card_suit_strength >
                            $current_strength)  && $card_suit_strength >= $current_strength
                    ) {
                        $best_value_player_id = $card['location_arg'];
                    }
                }
            }

            $this->incStat(1, "tricks_won", $best_value_player_id);

            $players = $this->loadPlayersBasicInfos();

            $this->notifyAllPlayers('trickWin', clienttranslate('${best_player_name} wins the trick'), array(
                'best_player_id' => $best_value_player_id,
                'best_player_name' => $players[$best_value_player_id]['player_name'],
            ));

            if ($this->cards->countCardInLocation('hand') == 0) {
                $this->gamestate->nextState("endHand");
            } else {
                $this->gamestate->changeActivePlayer($best_value_player_id);
                $this->incStat(1, "tricks_number");
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState('nextPlayer');
        }
    }


    function stEndHand()
    {
        $players = $this->loadPlayersBasicInfos();

        $cards = $this->cards->getCardsInLocation("cardswon");
        $cards_on_table = $this->cards->getCardsInLocation("cardsontable");

        $checkmate_card = array_shift($cards_on_table);

        $winner_id = $checkmate_card['location_arg'];

        $checkmate_weight = $this->values_weight[$checkmate_card['type_arg']];

        $trick_nbr = ceil(count($cards) / 2) + 1;

        $points = $checkmate_weight * $trick_nbr;

        if ($points && $this->cards->countCardInLocation("hand") > 0) {

            if (!$this->getStat("fewer_tricks_mate") || $trick_nbr < $this->getStat("fewer_tricks_mate")) {
                $this->setStat($trick_nbr, "fewer_tricks_mate");
            }

            if ($trick_nbr > $this->getStat("most_tricks_mate")) {
                $this->setStat($trick_nbr, "most_tricks_mate");
            }

            $sql = "UPDATE player SET player_score=player_score+$points WHERE player_id='$winner_id'";
            $this->DbQuery($sql);
            $this->notifyAllPlayers("points", clienttranslate('${player_name} mates with a ${card} after ${tricks} tricks, scoring ${points} points!'), array(
                'player_id' => $winner_id, 'player_name' => $players[$winner_id]['player_name'],
                'player_color' => $players[$winner_id]['player_color'],
                'card' => $this->values_label[$checkmate_card['type_arg']],
                'tricks' => $trick_nbr,
                'points' => $points,
            ));

            $score = 0;
            $collection = $this->getCollectionFromDb("SELECT player_score FROM player WHERE player_id='$winner_id'");
            foreach ($collection as $info) {
                $score = $info['player_score'];
            }

            $this->notifyAllPlayers("newScores", '', array('player_id' => $winner_id, 'newScores' => $score));
        } else {
            $this->notifyAllPlayers("points", clienttranslate('Hand finished with no mate!'), array());
        }

        $prev_hands_played = $this->getGameStateValue('handsPlayed');
        $this->setGameStateValue('handsPlayed', $prev_hands_played + 1);
        $hands_played = $this->getGameStateValue('handsPlayed');
        $first_player = $this->getGameStateValue('firstPlayer');
        $other_player = $this->getOtherPlayer($players, $first_player);

        if ($hands_played == 2) {
            $this->gamestate->changeActivePlayer($other_player);
            $this->gamestate->nextState('newRound');
            return;
        }

        if ($hands_played == 4) {
            $this->gamestate->nextState('endGame');
            return;
        }

        if ($hands_played == 1) {
            $this->gamestate->changeActivePlayer($other_player);
        }

        if ($hands_played == 3) {
            $this->gamestate->changeActivePlayer($first_player);
        }

        $this->gamestate->nextState("newHand");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            if ($statename === "playerTurn") {
                $order = 10 - $this->cards->countCardInLocation('hand', $active_player) + 1;
                $cards = $this->cards->getCardsInLocation('hand', $active_player);

                $currentTrickSuit = $this->getGameStateValue('trickSuit');
                $currentTrickValue = $this->getGameStateValue('trickValue');

                foreach ($cards as $card_id => $card) {
                    if (!$currentTrickSuit || $card['type'] == $currentTrickSuit || $card['type_arg'] == $currentTrickValue) {
                        try {
                            $this->playCard($card_id, $order);
                        } catch (Exception $e) {
                        }
                    }
                }
            } else {
                $this->gamestate->nextState("zombiePass");
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');

            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //


    }
}
