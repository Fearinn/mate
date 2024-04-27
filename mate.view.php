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
 * mate.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in mate_mate.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

require_once(APP_BASE_PATH . "view/common/game.view.php");

class view_mate_mate extends game_view
{
    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "mate";
    }

    function build_page($viewArgs)
    {
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count($players);


        /*********** Place your code below:  ************/

        $template = $this->getGameName() . "_" . $this->getGameName();

        // this will inflate our player block with actual players data
        $this->page->begin_block($template, "playerhandblock");
        foreach ($players as $player_id => $info) {
            $this->page->insert_block("playerhandblock", array(
                "PLAYER_ID" => $player_id,
                "PLAYER_NAME" => $players[$player_id]['player_name'],
                "PLAYER_COLOR" => $players[$player_id]['player_color'],
            ));
        }
        // this will make our My Hand text translatable
        $this->tpl['MY_HAND'] = $this->_("My hand");

        $this->page->begin_block($template, "historycardsblock");
        foreach ($players as $player_id => $info) {
            for ($i = 1; $i <= 10; $i++) {
                $this->page->insert_block("historycardsblock", array(
                    "PLAYER_ID" => $player_id,
                    "NUM" => $i,
                    "PLAYER_COLOR" => $players[$player_id]['player_color'],
                ));
            }
        }

        $this->tpl['PREVIOUSLY PLAYED TRICKS'] = $this->_("Previously played tricks");

        /*********** Do not change anything below this line  ************/
    }
}
