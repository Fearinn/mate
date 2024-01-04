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
 * material.inc.php
 *
 * Mate game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


/*

Example:

$this->card_types = array(
    1 => array( "card_name" => ...,
                ...
              )
);

*/

$this->suits = array(
  1 => array(
    'name' => clienttranslate('spade'),
    'nametr' => self::_('spade')
  ),
  2 => array(
    'name' => clienttranslate('heart'),
    'nametr' => self::_('heart')
  ),
  3 => array(
    'name' => clienttranslate('club'),
    'nametr' => self::_('club')
  ),
  4 => array(
    'name' => clienttranslate('diamond'),
    'nametr' => self::_('diamond')
  )
);

$this->suits_strength = array(
  // clubs > spades > hearts > diamonds
  1 => 2,
  2 => 3,
  3 => 1,
  4 => 4
);

$this->values_label = array(
  7 => '7',
  10 => '10',
  12 => clienttranslate('Q'),
  13 => clienttranslate('K'),
  14 => clienttranslate('A')
);

$this->values_strength = array(
  // A > 10 > K > Q > 7
  7 => 1,
  12 => 2,
  10 => 4,
  13 => 3,
  14 => 5,
);

$this->values_weight = array(
  // The weight is used as a multiplier for scoring
  // A = 11, K = 4, Q = 3, 7 = 7, 10 = 10
  7 => 7,
  10 => 10,
  12 => 3,
  13 => 4,
  14 => 11,
);
