<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Mate implementation : © <Your name here> <Your email address here>
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

        // Set current trick suit to zero (= no trick suit)
        self::setGameStateInitialValue('trickSuit', 0);

        self::setGameStateInitialValue('trickValue', 0);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // Create cards
        $cards = array();
        foreach ($this->suits as $suit_id => $suit) {
            // spade, heart, diamond, club
            foreach ($this->values_label as $value => $value_label) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'nbr' => 1);
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

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');


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
        // TODO: compute and return the game progression

        return 0;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in mate.action.php)
    */

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

    function playCard($card_id)
    {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        $currentCard = $this->cards->getCard($card_id);
        $currentTrickSuit = self::getGameStateValue('trickSuit');
        $currentTrickValue = self::getGameStateValue('trickValue');

        if (!$currentTrickSuit || !$currentTrickValue) {
            self::setGameStateValue('trickSuit', $currentCard['type']);
            self::setGameStateValue('trickValue', $currentCard['type_arg']);
        }

        if ($currentTrickSuit && $currentCard && $currentTrickSuit != $currentCard['type'] && $currentCard['type_arg'] != $currentTrickValue)
            throw new BgaVisibleSystemException("You can't play this card now");

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value_displayed} ${suit_displayed}'), array(
            'i18n' => array('suit_displayed', 'value_displayed'), 'card_id' => $card_id, 'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(), 'value' => $currentCard['type_arg'],
            'value_displayed' => $this->values_label[$currentCard['type_arg']], 'suit' => $currentCard['type'],
            'suit_displayed' => $this->suits[$currentCard['type']]['name']
        ));

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
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            $this->cards->moveAllCardsInLocation('cardsontable', 'hand', $player_id, $player_id);
        }


        foreach ($players as $player_id => $player) {
            $cards = $this->cards->getPlayerHand($player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array('cards' => $cards));
        }

        $this->gamestate->nextState("");
    }

    function stNewTrick()
    {
        // New trick: active the player who wins the last trick
        // Reset trick suit and trick value to 0 (= no suit and no value)
        self::setGameStateValue('trickSuit', 0);
        self::setGameStateValue('trickValue', 0);
        $this->gamestate->nextState("");
    }

    function stNextPlayer()
    {
        // Active next player OR end the trick and go to the next trick OR end the hand
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

            $players = self::loadPlayersBasicInfos();

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($best_value_player_id);

            // Notify
            foreach ($players as $player_id => $player) {
                $cards_on_table = $this->cards->getCardsInLocation('cardsontable', $player_id);
                $card = array_shift($cards_on_table);

                $this->cards->moveAllCardsInLocation('cardsontable', 'temporary', $player_id, $player_id);

                self::notifyAllPlayers('trickWin', clienttranslate('${best_player_name} wins the trick'), array(
                    'best_player_id' => $best_value_player_id,
                    'best_player_name' => $players[$best_value_player_id]['player_name'],
                    'player_id' => $player_id,
                    'suit' => $card['type'],
                    'value' => $card['type_arg'],
                ));
            }
            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }


    function stEndHand()
    {

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
