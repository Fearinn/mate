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
      this.cardWidth = 72;
      this.cardHeight = 96;
      this.historyQtd = 0;
      this.suitsStrength = {
        1: 3,
        2: 2,
        3: 4,
        4: 1,
      };
    },

    setup: function (gamedatas) {
      this.playerHand = new ebg.stock();

      this.playerHand.create(
        this,
        $("mate_myhand"),
        this.cardWidth,
        this.cardHeight
      );

      this.playerHand.image_items_per_row = 13;

      for (var suit = 1; suit <= 4; suit++) {
        for (var value = 7; value <= 14; value++) {
          if (value == 7 || value == 10 || value >= 12) {
            var card_type_id = this.getCardUniqueId(suit, value);
            var suit_strength = this.suitsStrength[suit];
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

      for (player_id in this.gamedatas.players) {
        var player = this.gamedatas.players[player_id];
      }

      this.setupNotifications();
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    onEnteringState: function (stateName, args) {
      if (stateName === "playerTurn") {
        if (this.isCurrentPlayerActive()) {
          var player_id = this.player_id;
          var playableCards = args.args.playableCards[player_id];
          var hand = this.playerHand.getAllItems();

          dojo.query(".stockitem").forEach((element) => {
            var itemId = element.id.split("item_")[1];
            if (!playableCards[itemId]) {
              dojo.addClass(element, "mate_unselectable");
            }
          });
        }
      }
    },

    onLeavingState: function (stateName) {
      if (stateName === "playerTurn") {
        dojo.query(".stockitem").removeClass("mate_unselectable");
      }
    },

    onUpdateActionButtons: function (stateName, args) {
      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
        }
      }
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    getCardUniqueId: function (suit, value) {
      return (suit - 1) * 13 + (value - 2);
    },

    playCardOnTable: function (player_id, suit, value, card_id) {
      dojo.place(
        this.format_block("jstpl_cardontable", {
          x: this.cardWidth * (value - 2),
          y: this.cardHeight * (suit - 1),
          player_id: player_id,
        }),
        "mate_playertablecard_" + player_id
      );

      if (player_id != this.player_id) {
        this.placeOnObject(
          "mate_cardontable_" + player_id,
          "overall_player_board_" + player_id
        );
      } else {
        if ($("mate_myhand_item_" + card_id)) {
          this.placeOnObject(
            "mate_cardontable_" + player_id,
            "mate_myhand_item_" + card_id
          );
          this.playerHand.removeFromStockById(card_id);
        }
      }

      this.slideToObject(
        "mate_cardontable_" + player_id,
        "mate_playertablecard_" + player_id
      ).play();
    },

    moveCardToHistory: function (player_id, suit, value, order = 0) {
      this.historyQtd++;

      var trick_num = order || Math.ceil(this.historyQtd / 2);

      dojo.place(
        this.format_block("jstpl_cardonhistory", {
          x: this.cardWidth * (value - 2),
          y: this.cardHeight * (suit - 1),
          player_id: player_id,
          num: trick_num,
        }),
        "mate_historycard_" + player_id + "_" + trick_num
      );

      this.slideToObject(
        "mate_cardonhistory_" + player_id + "_" + trick_num,
        "mate_historycard_" + player_id + "_" + trick_num
      ).play();
    },

    ///////////////////////////////////////////////////
    //// Player's action

    onPlayerHandSelectionChanged: function () {
      var items = this.playerHand.getSelectedItems();
      var length = this.playerHand.count();

      if (items.length > 0) {
        var action = "playCard";
        if (this.checkAction(action)) {
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

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    setupNotifications: function () {
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
        dojo.destroy("mate_cardontable_" + player_id);

        for (var i = 0; i <= 10; i++) {
          dojo.destroy("mate_cardonhistory_" + player_id + "_" + i);
        }
      }

      this.historyQtd = 0;
    },

    notif_playCard: function (notif) {
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

      dojo.destroy("mate_cardontable_" + notif.args.player_id);
    },

    notif_newScores: function (notif) {
      this.scoreCtrl[notif.args.player_id].toValue(notif.args.newScores);
    },

    notif_points: function (notif) {
      this.displayScoring(
        "mate_playertablecard_" + notif.args.player_id,
        notif.args.player_color,
        notif.args.points
      );
    },
  });
});
