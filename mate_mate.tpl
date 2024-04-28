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
<div id="mate_playertables">
  <!-- BEGIN playerhandblock -->
  <div class="mate_playertable whiteblock">
    <div class="mate_playertablename" style="color: #{PLAYER_COLOR}">
      {PLAYER_NAME}
    </div>
    <div class="mate_playertablecard" id="mate_playertablecard_{PLAYER_ID}"></div>
  </div>
  <!-- END playerhandblock -->
</div>

<div id="mate_history" class="whiteblock">
  <h3>{PREVIOUSLY PLAYED TRICKS}</h3>
    <div id="mate_historycards" class="mate_historycards">
    <!-- BEGIN historycardsblock -->
      <div class="mate_historycard" id="mate_historycard_{PLAYER_ID}_{NUM}" style="border-color: #{PLAYER_COLOR}"></div>
    <!-- END historycardsblock -->
  </div>
</div>

  <div id="mate_myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="mate_myhand"></div>
  </div>

  <script type="text/javascript">
    // Javascript HTML templates

    var jstpl_cardontable =
      '<div class="mate_cardontable" id="mate_cardontable_${player_id}" style="background-position:-${x}px -${y}px">\
                        </div>';

    var jstpl_cardonhistory =
      '<div class="mate_cardonhistory" id="mate_cardonhistory_${player_id}_${num}" style="background-position:-${x}px -${y}px">\
                        </div>';
  </script>

  {OVERALL_GAME_FOOTER}
</div>
