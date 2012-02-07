//Ext.Loader.setConfig({enabled:true});

var app = {
	launch : function() {
		var cards = Ext.create('Ext.Panel', {
			layout : 'card',
			fullscreen : true,
			cardSwitchAnimation : 'slide',

			items : [ viewMainMenu, viewHistoryList, viewStationList,
					viewStationLayerList, viewTextdataHistoryList,
					viewLayerDataList ],
		});

		// some useful references
		cards.mainMenuCard = cards.getComponent('mainMenuCard');
		cards.ingestingHistoryCard = cards.getComponent('ingestingHistoryCard');
		cards.stationListCard = cards.getComponent('stationListCard');
		cards.stationLayerListCard = cards.getComponent('stationLayerListCard');
		cards.textdataHistoryCard = cards.getComponent('textdataHistoryCard');
		cards.layerDataListCard = cards.getComponent('layerDataListCard');

		this.mainMenuCard(cards, cards.mainMenuCard);
		this.ingestingHistoryCard(cards, cards.ingestingHistoryCard);
		this.stationListCard(cards, cards.stationListCard);
		this.stationLayerListCard(cards, cards.stationLayerListCard);
		this.textdataHistoryCard(cards, cards.textdataHistoryCard);
		this.layerDataListCard(cards, cards.layerDataListCard);

		cards.setActiveItem(cards.mainMenuCard);
	},
	mainMenuCard : function(cards, card) {
		var store = Ext.getStore('WERealtime.store.mainMenu');
		var list = card.getComponent('mainMenuList');
		if (!list.getStore()) {
			list.setStore(store);
			//store.load();
		}

		list.on('select', function(obj, record, eOpts) {
			setTimeout(function() {
				if (record.get('menu') == 'View stations') {
					cards.setActiveItem(cards.stationListCard, {
						type : 'slide',
						direction : 'left'
					});
				} else if (record.get('menu') == 'Ingesting history') {
					cards.setActiveItem(cards.ingestingHistoryCard, {
						type : 'slide',
						direction : 'left'
					});
				}
			}, 100);
		});
	},
	ingestingHistoryCard : function(cards, card) {
		/*
		 * cards.mainMenuList.on('itemtap', function (obj, record, eOpts)
		 * {alert('y') setTimeout(function() {
		 * cards.setActiveItem(cards.ingestingHistoryCard, {type:'slide',
		 * direction: 'left'}); }, 100); });
		 */
		card.on('activate', function() {
			var store = Ext.getStore('WERealtime.store.ingestingHistory');
			var list = card.getComponent('historyList');
			if (!list.getStore()) {
				list.setStore(store);
				store.load();
			}
		})
		card.items.items[0].items.items[0].on('tap', function() {
			cards.setActiveItem(cards.mainMenuCard, {
				type : 'slide',
				direction : 'right'
			});
		})
	},
	/*
	 * main menu "View stations"
	 */
	stationListCard : function(cards, card) {
		card.on('activate', function() {
			var store = Ext.getStore('WERealtime.store.stationList');
			var list = card.getComponent('stationList');
			if (!list.getStore()) {
				list.setStore(store);
				store.load();
			}
		})
		var viewStationList = card.getComponent('stationList');
		viewStationList.on('itemtap', function(view, index, target, record) {
			cards.stationLayerListCard.WEData = record;
			cards.setActiveItem(cards.stationLayerListCard, {
				type : 'slide',
				direction : 'right'
			});
		})
		card.items.items[0].items.items[0].on('tap', function() {
			cards.setActiveItem(cards.mainMenuCard, {
				type : 'slide',
				direction : 'right'
			});
		})
	},
	/*
	 * View layers of a station
	 */
	stationLayerListCard : function(cards, card) {
		var store = Ext.getStore('WERealtime.store.stationLayerList');
		var list = card.getComponent('stationLayerList');

		card.on('activate', function() {
			var WEData = this.WEData, station_strid = WEData.get('strid');
			if (store.strid != station_strid) {
				store.strid = station_strid;
				list.setStore(store);
				store.load({
					params : {
						station : station_strid
					}
				});
			}
		})
		card.items.items[0].items.items[0].on('tap', function() {
			cards.setActiveItem(cards.stationListCard, {
				type : 'slide',
				direction : 'right'
			});
		})
		list.on('itemtap', function(dataView, index, dataItem, record, e) {
			var el = e.target;
			/*if (el.className == 'group-basin-infotype') {
				cards.textdataHistoryCard.WEData = {
					basin_id : el.getAttribute('basin'),
					infotype_id : el.getAttribute('infotype'),
					station_strid : card.WEData.get('strid'),
				}
				cards.setActiveItem(cards.textdataHistoryCard, {
					type : 'slide',
					direction : 'right'
				});
			}*/
			if (el.className == 'x-list-item-label') {
				cards.layerDataListCard.WEData = {
					basin_id : record.get('basin_id'),
					infotype_id : record.get('infotype_id'),
					station_strid : card.WEData.get('strid'),
					layer: record.get('field'),
				}
				cards.setActiveItem(cards.layerDataListCard, {
					type : 'slide',
					direction : 'right'
				});
			}
		})
	},
	textdataHistoryCard: function(cards, card) {
		var store = Ext.getStore('WERealtime.store.textdataHistory');
		var list = card.getComponent('textdataList');

		card.on('activate', function() {
			var WEData = this.WEData;
			// if (store.strid != station_strid) {
			list.setStore(store);
			store.load({
				params : WEData
			});
			// }*/
		})
		card.items.items[0].items.items[0].on('tap', function() {
			cards.setActiveItem(cards.stationLayerListCard, {
				type : 'slide',
				direction : 'right'
			});
		})
	},
	layerDataListCard: function(cards, card) {
		var store = Ext.getStore('WERealtime.store.layerData');
		var list = card.getComponent('layerDataList');

		card.on('activate', function() {
			var WEData = this.WEData;
			list.setStore(store);
			store.load({
				params : WEData
			});
		})
		card.items.items[0].items.items[0].on('tap', function() {
			cards.setActiveItem(cards.stationLayerListCard, {
				type : 'slide',
				direction : 'right'
			});
		})
		list.on('itemtaphold', function(dataView, index, dataItem, record) {
			if (!dataView.overlay) {
				dataView.overlay = Ext.create('Ext.Panel', {
					floating : true,
					modal : true,
					hidden : true,
					height : '80%',
					width : '80%',
					html : '',
					styleHtmlContent : true,
					scrollable : true,
					centered : true,
					items : [ {
						docked : 'top',
						xtype : 'toolbar',
						title : 'Raw text data (' + record.get('text_id') + ')',
					} ],
					masked: {
					    xtype: 'loadmask',
					    message: 'Loading...'
					}
				});
				Ext.Viewport.add(dataView.overlay);
			}
			dataView.overlay.show();
			var WEData = card.WEData;
			Ext.Ajax.request({
				url : 'index.php',
				jsonData : {
					request : 'singleTextdata',
					text_id : record.get('text_id'),
					format: 'text',
				},
				method : 'POST',
				callback : function(options, success, response) {
					if (success == true) {
						dataView.overlay.setHtml('<pre>' + response.responseText + '</pre>');
						dataView.overlay.setMasked(false);
					}
				}
			})
		})
	}
}
Ext.application(app);