/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Mate implementation : © <Matheus Gomes> <matheusgomesforwork@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * mate.js
 *
 * Mate user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
], function (dojo, declare) {
  return declare("bgagame.mate", ebg.core.gamegui, {
    constructor: function () {
      console.log("mate constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;

      this.cardwidth = 72;
      this.cardheight = 96;
      this.historyqtd = 0;
      this.suitsstrength = {
        1: 3,
        2: 2,
        3: 4,
        4: 1,
      };
    },

    /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */

    setup: function (gamedatas) {
      console.log("Starting game setup");

      this.playerHand = new ebg.stock(); // new stock object for hand

      this.playerHand.create(
        this,
        $("myhand"),
        this.cardwidth,
        this.cardheight
      );

      this.playerHand.image_items_per_row = 13; // 13 images per row

      // Create cards types:
      for (var suit = 1; suit <= 4; suit++) {
        for (var value = 7; value <= 14; value++) {
          if (value == 7 || value == 10 || value >= 12) {
            // Build card type id
            var card_type_id = this.getCardUniqueId(suit, value);
            var suit_strength = this.suitsstrength[suit];
            var card_type_order = this.getCardUniqueId(suit_strength, value);

            if (value === 10 || value === 14) {
              card_type_order = this.getCardUniqueId(suit_strength, value + 4);
            }

            this.playerHand.addItemType(
              card_type_id,
              card_type_order,
              g_gamethemeurl + "img/cards.jpg",
              card_type_id
            );
          }
        }
      }

      // Cards in player's hand
      for (var i in this.gamedatas.hand) {
        var card = this.gamedatas.hand[i];
        var suit = card.type;
        var value = card.type_arg;
        this.playerHand.addToStockWithId(
          this.getCardUniqueId(suit, value),
          card.id
        );
      }

      // Cards played on table
      for (i in this.gamedatas.cardsontable) {
        var card = this.gamedatas.cardsontable[i];
        var suit = card.type;
        var value = card.type_arg;
        var player_id = card.location_arg;
        this.playCardOnTable(player_id, suit, value, card.id);
      }

      // Cards on history
      for (player_id in this.gamedatas.players) {
        for (key in this.gamedatas.cardswon[player_id]) {
          var card = this.gamedatas.cardswon[player_id][key];
          var order = card.card_order;
          var suit = card.card_type;
          var value = card.card_type_arg;
          this.moveCardToHistory(player_id, suit, value, order);
        }
      }

      dojo.connect(
        this.playerHand,
        "onChangeSelection",
        this,
        "onPlayerHandSelectionChanged"
      );

      // Setting up player boards
      for (player_id in this.gamedatas.players) {
        var player = this.gamedatas.players[player_id];
      }

      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      console.log("Ending game setup");
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */

        case "dummmy":
          break;
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */

        case "dummmy":
          break;
      }
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);

      if (this.isCurrentPlayerActive()) {
        switch (
          stateName
          /*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
        ) {
        }
      }
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

    // Get card unique identifier based on its suit and value
    getCardUniqueId: function (suit, value) {
      return (suit - 1) * 13 + (value - 2);
    },

    playCardOnTable: function (player_id, suit, value, card_id) {
      // player_id => direction
      dojo.place(
        this.format_block("jstpl_cardontable", {
          x: this.cardwidth * (value - 2),
          y: this.cardheight * (suit - 1),
          player_id: player_id,
        }),
        "playertablecard_" + player_id
      );

      if (player_id != this.player_id) {
        // Some opponent played a card
        // Move card from player panel
        this.placeOnObject(
          "cardontable_" + player_id,
          "overall_player_board_" + player_id
        );
      } else {
        // You played a card. If it exists in your hand, move card from there and remove
        // corresponding item

        if ($("myhand_item_" + card_id)) {
          this.placeOnObject(
            "cardontable_" + player_id,
            "myhand_item_" + card_id
          );
          this.playerHand.removeFromStockById(card_id);
        }
      }

      // In any case: move it to its final destination
      this.slideToObject(
        "cardontable_" + player_id,
        "playertablecard_" + player_id
      ).play();
    },

    moveCardToHistory: function (player_id, suit, value, order = 0) {
      this.historyqtd++;

      var trick_num = order || Math.ceil(this.historyqtd / 2);

      dojo.place(
        this.format_block("jstpl_cardonhistory", {
          x: this.cardwidth * (value - 2),
          y: this.cardheight * (suit - 1),
          player_id: player_id,
          num: trick_num,
        }),
        "historycard_" + player_id + "_" + trick_num
      );

      // In any case: move it to its final destination
      this.slideToObject(
        "cardonhistory_" + player_id + "_" + trick_num,
        "historycard_" + player_id + "_" + trick_num
      ).play();
    },

    ///////////////////////////////////////////////////
    //// Player's action

    /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

    onPlayerHandSelectionChanged: function () {
      var items = this.playerHand.getSelectedItems();
      var length = this.playerHand.count();

      if (items.length > 0) {
        var action = "playCard";
        if (this.checkAction(action, true)) {
          // Can play a card
          var card_id = items[0].id;
          this.ajaxcall(
            "/" +
              this.game_name +
              "/" +
              this.game_name +
              "/" +
              action +
              ".html",
            {
              id: card_id,
              order: 10 - length + 1,
              lock: true,
            },
            this,
            function (result) {},
            function (is_error) {}
          );

          this.playerHand.unselectAll();
        } else {
          this.playerHand.unselectAll();
        }
      }
    },

    /* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/mate/mate/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your mate.game.php file.
        
        */
    setupNotifications: function () {
      console.log("notifications subscriptions setup");
      // Example 1: standard notification handling
      // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

      // Example 2: standard notification handling + tell the user interface to wait
      //            during 3 seconds after calling the method in order to let the players
      //            see what is happening in the game.
      // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
      // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
      //
    },
    /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */

    setupNotifications: function () {
      console.log("notifications subscriptions setup");

      dojo.subscribe("playCard", this, "notif_playCard");
      dojo.subscribe("trickWin", this, "notif_trickWin");
      this.notifqueue.setSynchronous("trickWin", 1000);
      dojo.subscribe("newTrick", this, "notif_newTrick");
      dojo.subscribe("newHand", this, "notif_newHand");
      dojo.subscribe("newScores", this, "notif_newScores");
      dojo.subscribe("points", this, "notif_points");
      this.notifqueue.setSynchronous("points", 3000);
    },

    notif_newHand: function (notif) {
      this.playerHand.removeAll();

      for (var i in notif.args.cards) {
        var card = notif.args.cards[i];
        var suit = card.type;
        var value = card.type_arg;
        this.playerHand.addToStockWithId(
          this.getCardUniqueId(suit, value),
          card.id
        );
      }

      for (var player_id in this.gamedatas.players) {
        dojo.destroy("cardontable_" + player_id);

        for (var i = 0; i <= 10; i++) {
          dojo.destroy("cardonhistory_" + player_id + "_" + i);
        }
      }

      this.historyqtd = 0;
    },

    notif_playCard: function (notif) {
      // Play a card on the table
      this.playCardOnTable(
        notif.args.player_id,
        notif.args.suit,
        notif.args.value,
        notif.args.card_id
      );
    },

    notif_trickWin: function (notif) {},

    notif_newTrick: function (notif) {
      this.moveCardToHistory(
        notif.args.player_id,
        notif.args.suit,
        notif.args.value
      );

      dojo.destroy("cardontable_" + notif.args.player_id);
    },

    notif_newScores: function (notif) {
      // Update players' scores
      this.scoreCtrl[notif.args.player_id].toValue(notif.args.newScores);
    },

    notif_points: function (notif) {
      this.displayScoring(
        "playertablecard_" + notif.args.player_id,
        notif.args.player_color,
        notif.args.points
      );
    },
  });
});
