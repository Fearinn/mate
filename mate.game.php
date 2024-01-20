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
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(array(
            "trickSuit" => 11,
            "trickValue" => 12,
            "handsPlayed" => 13,
            "firstPlayer" => 14,
            "privilege" => 100,
        ));

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "mate";
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = array())
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values

        self::setGameStateInitialValue('trickSuit', 0);
        self::setGameStateInitialValue('trickValue', 0);
        self::setGameStateInitialValue('handsPlayed', 0);
        self::setGameStateInitialValue('firstPlayer', 0);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)

        self::initStat("table", "tricks_number", 0);
        self::initStat("table", "fewer_tricks_mate", 0);
        self::initStat("table", "most_tricks_mate", 0);
        self::initStat("player", "tricks_won", 0);

        // Create cards
        $cards = array();
        foreach ($this->suits as $suit_id => $suit) {
            // spade, heart, diamond, club
            foreach ($this->values_label as $value => $value_label) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'order' => 0, 'nbr' => 1);
            }
        }

        $this->cards->createCards($cards, 'deck');

        // Shuffle deck
        $this->cards->shuffle('deck');

        // Deal 10 cards to each players
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
        }

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();
        self::setGameStateValue('firstPlayer', self::getActivePlayerId());

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        $players = self::loadPlayersBasicInfos();

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        foreach ($players as $player_id => $info) {
            $sql = "SELECT card_order, card_location, card_location_arg, card_type, card_type_arg FROM card WHERE card_location_arg='$player_id' AND card_location='cardswon'";
            $result['cardswon'][$player_id] = self::getCollectionFromDb($sql);
        }

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $handsPlayed = self::getGameStateValue("handsPlayed");

        return $handsPlayed * 25;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

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

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in mate.action.php)
    */

    function playCard($card_id, $order)
    {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();

        $currentCard = $this->cards->getCard($card_id);

        $currentTrickSuit = self::getGameStateValue('trickSuit');
        $currentTrickValue = self::getGameStateValue('trickValue');
        $privilege = self::getGameStateValue('privilege');

        $hand = $this->cards->getCardsInLocation('hand', $player_id);

        if (!$currentTrickSuit || !$currentTrickValue) {
            self::setGameStateValue('trickSuit', $currentCard['type']);
            self::setGameStateValue('trickValue', $currentCard['type_arg']);
        }

        if ($currentTrickSuit && $currentTrickValue && $currentCard) {
            $same_suit = false;
            $k_in_hand = false;
            $q_in_hand = false;


            if ($currentTrickSuit != $currentCard['type'] && $currentCard['type_arg'] != $currentTrickValue) {
                throw new BgaUserException(self::_("You can't play this card now"));
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
            }

            if (($privilege == 1 || $privilege == 2) && $k_in_hand && $currentTrickValue == 13 && $currentCard['type_arg'] != 13) {
                throw new BgaUserException(self::_("You must play a K card"));
            }

            if ($privilege == 2 && $q_in_hand && $currentTrickValue == 12 && $currentCard['type_arg'] != 12) {
                throw new BgaUserException(self::_("You must play a Q card"));
            }

            if (
                $same_suit && $currentCard['type'] != $currentTrickSuit
                && !($currentTrickValue == 13 && $currentCard['type_arg'] == 13 && ($privilege == 1 || $privilege == 2))
                && !($currentTrickValue == 12 && $currentCard['type_arg'] == 12 && $privilege == 2)
            ) {
                throw new BgaUserException(self::_("You must play a card of the current suit"));
            }
        }

        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value_displayed} ${suit_displayed}'), array(
            'i18n' => array('suit_displayed', 'value_displayed'), 'card_id' => $card_id, 'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(), 'value' => $currentCard['type_arg'],
            'value_displayed' => $this->values_label[$currentCard['type_arg']], 'suit' => $currentCard['type'],
            'suit_displayed' => $this->suits[$currentCard['type']]['name']
        ));
        $sql = "UPDATE card SET card_order=$order WHERE card_id='$card_id'";
        self::DbQuery($sql);
        // Next player
        $this->gamestate->nextState('playCard');
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

    function stNewHand()
    {
        if (self::getGameStateValue('handsPlayed') > 0) {
            $players = self::loadPlayersBasicInfos();

            foreach ($players as $player_id => $player) {
                $other_player = $this->getOtherPlayer($players, $player_id);
                $this->cards->moveAllCardsInLocation('cardsontable', 'temporary', $player_id, $other_player);
                $this->cards->moveAllCardsInLocation('cardswon', 'temporary', $player_id, $other_player);
                $this->cards->moveAllCardsInLocation('hand', 'temporary', $player_id, $other_player);
            }

            foreach ($players as $player_id => $player) {
                $this->cards->moveAllCardsInLocation('temporary', 'hand', $player_id, $player_id);

                $cards = $this->cards->getPlayerHand($player_id);
                // Notify player about his cards
                self::notifyPlayer($player_id, 'newHand', clienttranslate('A new hand starts. Players exchange hands.'), array('cards' => $cards));
            }
        }

        $this->gamestate->nextState("");
    }

    function stNewRound()
    {
        $players = self::loadPlayersBasicInfos();

        $this->cards->moveAllCardsInLocation('hand', 'deck');
        $this->cards->moveAllCardsInLocation('cardswon', 'deck');
        $this->cards->moveAllCardsInLocation('cardsontable', 'deck');
        $this->cards->shuffle('deck');

        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
            self::notifyPlayer($player_id, 'newHand', clienttranslate('A new round starts. Cards are shuffled and dealt again.'), array('cards' => $cards));
        }

        $this->gamestate->nextState("");
    }

    function stNewTrick()
    {

        if ($this->cards->countCardInLocation('cardsontable') == 2) {
            $players = self::loadPlayersBasicInfos();

            foreach ($players as $player_id => $player) {
                $cards_on_table = $this->cards->getCardsInLocation('cardsontable', $player_id);
                $card = array_shift($cards_on_table);

                $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', $player_id, $player_id);

                self::notifyAllPlayers('newTrick', '', array(
                    'player_id' => $player_id,
                    'suit' => $card['type'],
                    'value' => $card['type_arg'],
                ));
            }
        }

        // Reset trick suit and trick value to 0 (= no suit and no value)

        self::setGameStateValue('trickSuit', 0);
        self::setGameStateValue('trickValue', 0);
        $this->gamestate->nextState("");
    }

    function stNextPlayer()
    {
        // Active next player OR end the trick and go to the next trick OR end the hand

        $players = self::loadPlayersBasicInfos();

        $currentTrickSuit = self::getGameStateValue('trickSuit');
        $currentTrickValue = self::getGameStateValue('trickValue');

        if ($this->cards->countCardInLocation('cardsontable') == 1 && $currentTrickSuit && $currentTrickValue) {
            $player_id = $this->getActivePlayerId();
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
            // This is the end of the trick
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value_player_id = null;
            $currentTrickSuit = self::getGameStateValue('trickSuit');
            $currentTrickValue = self::getGameStateValue('trickValue');

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

            // Active this player => he's the one who starts the next tric

            self::incStat(1, "tricks_won", $best_value_player_id);

            $players = self::loadPlayersBasicInfos();

            // Notify
            self::notifyAllPlayers('trickWin', clienttranslate('${best_player_name} wins the trick'), array(
                'best_player_id' => $best_value_player_id,
                'best_player_name' => $players[$best_value_player_id]['player_name'],
            ));

            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                $this->gamestate->changeActivePlayer($best_value_player_id);
                self::incStat(1, "tricks_number");
                self::warn('new player active - end trick');
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            self::warn('new player active - std');
            $this->gamestate->nextState('nextPlayer');
        }
    }


    function stEndHand()
    {
        $players = self::loadPlayersBasicInfos();

        $cards = $this->cards->getCardsInLocation("cardswon");
        $cards_on_table = $this->cards->getCardsInLocation("cardsontable");

        $checkmate_card = array_shift($cards_on_table);

        $winner_id = $checkmate_card['location_arg'];

        $checkmate_weight = $this->values_weight[$checkmate_card['type_arg']];

        $trick_nbr = ceil(count($cards) / 2) + 1;

        $points = $checkmate_weight * $trick_nbr;

        // Apply scores to player
        if ($points && $this->cards->countCardInLocation("hand") > 0) {

            if (!self::getStat("fewer_tricks_mate") || $trick_nbr < self::getStat("fewer_tricks_mate")) {
                self::setStat($trick_nbr, "fewer_tricks_mate");
            }

            if ($trick_nbr > self::getStat("most_tricks_mate")) {
                self::setStat($trick_nbr, "most_tricks_mate");
            }

            $sql = "UPDATE player SET player_score=player_score+$points WHERE player_id='$winner_id'";
            self::DbQuery($sql);
            self::notifyAllPlayers("points", clienttranslate('${player_name} mates with a ${card} after ${tricks} tricks, scoring ${points} points!'), array(
                'player_id' => $winner_id, 'player_name' => $players[$winner_id]['player_name'],
                'card' => $this->values_label[$checkmate_card['type_arg']],
                'tricks' => $trick_nbr,
                'points' => $points,
            ));

            $score = 0;
            $collection = self::getCollectionFromDb("SELECT player_score FROM player WHERE player_id='$winner_id'");
            foreach ($collection as $info) {
                $score = $info['player_score'];
            }

            self::notifyAllPlayers("newScores", '', array('player_id' => $winner_id, 'newScores' => $score));
        } else {
            self::notifyAllPlayers("points", clienttranslate('Hand finished with no mate!'), array());
        }

        $prev_hands_played = self::getGameStateValue('handsPlayed');
        self::setGameStateValue('handsPlayed', $prev_hands_played + 1);
        $hands_played = self::getGameStateValue('handsPlayed');
        self::warn($hands_played);

        $first_player = self::getGameStateValue('firstPlayer');
        $other_player = $this->getOtherPlayer($players, $first_player);
        self::warn($first_player);

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
            self::warn($other_player);
        }

        if ($hands_played == 3) {
            $this->gamestate->changeActivePlayer($first_player);
            self::warn($first_player);
        }

        $this->gamestate->nextState("newHand");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
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
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //


    }
}
