{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- Mate implementation : © <Matheus Gomes> <matheusgomesforwork@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="playertables">
  <!-- BEGIN playerhandblock -->
  <div class="playertable whiteblock playertable_{DIR}">
    <div class="playertablename" style="color: #{PLAYER_COLOR}">
      {PLAYER_NAME}
    </div>
    <div class="playertablecard" id="playertablecard_{PLAYER_ID}"></div>
  </div>
  <!-- END playerhandblock -->
</div>

<div id="myhand_wrap" class="whiteblock">
  <h3>{MY_HAND}</h3>
  <div id="myhand"></div>
</div>

<script type="text/javascript">
  // Javascript HTML templates

  /*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/
</script>

{OVERALL_GAME_FOOTER}
