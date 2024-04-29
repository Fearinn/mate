<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Mate implementation : © <Matheus Gomes> <matheusgomesforwork@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * mate.action.php
 *
 * Mate main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/mate/mate/myAction.html", ...)
 *
 */


class action_mate extends APP_GameAction
{
  // Constructor: please do not modify
  public function __default()
  {
    if (self::isArg('notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
    } else {
      $this->view = "mate_mate";
      self::trace("Complete reinitialization of board game");
    }
  }

  private function checkVersion()
  {
    $clientVersion = (int) $this->getArg('gameVersion', AT_int, false);
    $this->game->checkVersion($clientVersion);
  }

  public function playCard()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $card_id = $this->getArg("id", AT_posint, true);
    $card_order = $this->getArg('order', AT_int, true);
    $this->game->playCard($card_id, $card_order);
    $this->ajaxResponse();
  }
}
