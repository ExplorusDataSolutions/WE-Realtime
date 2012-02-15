//Ext.Loader.setConfig({enabled:true});

var app = {
	launch : function() {
		var cards = this.cards = Ext.create('Ext.Panel', {
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
	goBackEvent : function(currentCard, activatingCard) {
		var backButton = currentCard.items.items[0].items.items[0];
		var cards = this.cards;
		backButton.on('tap', function() {
			cards.setActiveItem(activatingCard, {
				type : 'slide',
				direction : 'right'
			});
		})
	},
	goForward : function(activatingCard) {
		this.cards.setActiveItem(activatingCard, {
			type : 'slide',
			direction : 'left'
		});
	},
	getOverlay : function() {
		if (!this.overlay) {
			this.overlay = Ext.create('Ext.Panel', {
				floating : true,
				modal : true,
				hidden : true,
				height : '80%',
				width : '80%',
				html : '',
				scrollable : true,
				centered : true,
				/*items : [ {
					docked : 'top',
					xtype : 'toolbar',
					title : 'Source page preview',
				} ],*/
				masked: {
				    xtype: 'loadmask',
				    message: 'Loading...'
				}
			});
			Ext.Viewport.add(this.overlay);
		}
		
		return this.overlay;
	},
	mainMenuCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.mainMenu');
		var list = card.getComponent('mainMenuList');
		
		// show main menu
		list.getStore() || list.setStore(store);
		
		list.on('select', function(obj, record) {
			setTimeout(function() {
				var menuTitle = record.get('menu');
				if (menuTitle == 'View stations') {
					me.goForward(cards.stationListCard);
				} else if (menuTitle == 'Ingesting history') {
					me.goForward(cards.ingestingHistoryCard);
				}
			}, 100);
		});
	},
	ingestingHistoryCard : function(cards, card) {
		var store = Ext.getStore('WERealtime.store.ingestingHistory');
		var list = card.getComponent('historyList');
		list.setStore(store);
		
		card.on('activate', function() {
			store.load();
		})
		this.goBackEvent(card, cards.mainMenuCard);
	},
	/*
	 * main menu "View stations"
	 */
	stationListCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.stationList');
		var list = card.getComponent('stationList');
		list.setStore(store);
		
		store.on("beforeload", function() {
			card.items.items[0].setTitle("Loading...");
		});
		var loadStation = function(records) {
			card.items.items[0].setTitle("Total " + records.length);
			var dt = Ext.Date.format(new Date, "D, M d, Y g:i:s A");
			var html = [ '<span class="we-date">Last updated: ', dt, '</span>' ]
					.join('');
			card.items.items[1].setTitle(html);
		};
		card.on('activate', function() {
			store.load(loadStation);
		})
		list.on('itemtap', function(view, index, target, record) {
			cards.stationLayerListCard.WEData = record;
			me.goForward(cards.stationLayerListCard);
		})
		this.goBackEvent(card, cards.mainMenuCard);
		var reloadButton = card.items.items[0].items.items[2];
		reloadButton.on('tap', function() {
			store.removeAll();
			store.load(loadStation);
		})
	},
	/*
	 * View layers of a station
	 */
	stationLayerListCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.stationLayerList');
		var list = card.getComponent('stationLayerList');
		list.setStore(store);

		store.on("beforeload", function() {
			card.items.items[0].setTitle("Loading...");
		});
		this.goBackEvent(card, cards.stationListCard);
		var reloadButton = card.items.items[0].items.items[2];
		reloadButton.on('tap', function() {
			store.data.each(function(record) {
				store.remove(record);
			});
			showCard();
		})
		var showCard = function() {
			var WEData = card.WEData;
			var station_strid = WEData.get('strid');
			var station_description = WEData.get('description');
			var stationStore = Ext.getStore("WERealtime.store.stationList");
			var recordIndex = stationStore.find('strid', station_strid);
			var record = recordIndex != -1 ? stationStore.getAt(recordIndex) : false;
			var dt = record ? record.get('layers_update_time') : '';
			var html = [ '<div style="line-height: 0.7em">',
					'<span class="we-h2">', station_description,
					'</span><br />', '<span class="we-date">Last updated: ',
					dt, '</span>', '</div>' ].join('');
			card.items.items[1].setTitle(html);
			var callback = function() {
				card.items.items[0].setTitle("Station layers");
				var dt = Ext.Date.format(new Date, "D, M d, Y g:i:s A");
				var html = [ '<div style="line-height: 0.7em">',
						'<span class="we-h2">', station_description,
						'</span><br />',
						'<span class="we-date">Last updated: ', dt, '</span>',
						'</div>' ].join('');
				card.items.items[1].setTitle(html);
				record.set('layers_update_time', dt);
			}
			store.load({
				params : {
					station : station_strid
				},
				addRecords : true,
				callback : callback
			});
		}
		card.on('activate', showCard);
		list.on('itemtap', function(dataView, index, dataItem, record, e) {
			var el = e.target;
			if (el.className == 'x-list-header') {
				cards.textdataHistoryCard.WEData = {
					basin_id : record.get('basin_id'),
					datatype_id : record.get('datatype_id'),
					station_strid : record.get('station_strid'),
					station_description : record.get('description'),
				}
				me.goForward(cards.textdataHistoryCard);
			}
			
			/*if (el.className == 'x-list-item-label') {
				cards.layerDataListCard.WEData = {
					basin_id : record.get('basin_id'),
					datatype_id : record.get('datatype_id'),
					station_strid : card.WEData.get('strid'),
					layer : record.get('field'),
				}
				cards.setActiveItem(cards.layerDataListCard, {
					type : 'slide',
					direction : 'right'
				});
			}*/
			return false;
		})
	},
	/*
	 * View textdata history 
	 */
	textdataHistoryCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.textdataHistory');
		var select = card.items.items[2].getComponent('textdataSelect');
		var dataPanel = card.items.items[1];
		
		//var baseUrl = 'http://www.environment.alberta.ca/apps/basins/DisplayData.aspx';
		var loadTextData = function(text_id) {
			var WEData = card.WEData;
			dataPanel.setHtml('');
			dataPanel.setMasked({
				xtype : 'loadmask',
				message : 'Loading...',
			});
			
			Ext.Ajax.request({
				url : 'index.php',
				method : 'POST',
				jsonData : {
					request : 'singleTextdata',
					basin_id : WEData.basin_id,
					datatype_id : WEData.datatype_id,
					station_strid : WEData.station_strid,
					description : WEData.station_description,
					// or
					text_id : typeof text_id == 'number' ? text_id : 0,
					format: 'json',
				},
				success : function(response, request) {
					var result = Ext.decode(response.responseText);
					if (result.Id) {
						dataPanel.setHtml([ 'Text data ID: ', result.Id,
								', Version: ', result.Version,
								', Ingest time: ', result.IngestTime,
								'<br /><br />', '<pre>',
								result.Text || 'No data available', '</pre>' ]
								.join(''));
					} else {
						dataPanel.setHtml([ '<a target="_blank" href="',
								result.Url, '">', result.Url, '</a>',
								'<br /><br />', '<pre>',
								result.Text || 'No data available', '</pre>' ]
								.join(''));
					}
					dataPanel.setMasked(false);
				}
			});
			/*
			 * var html = [ '<div style="position: fixed; width: 1000px;
			 * height: 10000px; z-index:99;"></div>', '<iframe style="width:
			 * 1000px; height: 1000px; float: left; z-index:1;" scrolling="no"
			 * src="', baseUrl, '?Type=Table&BasinID=', WEData.basin_id,
			 * '&DataType=', WEData.datatype_id, '&StationID=',
			 * WEData.station_strid, '"></iframe>' ].join('');
			 */
			//card.items.items[1].setHtml(html);
			/*
			 * Version history
			 */
			store.load({
				params : WEData,
				callback : function(records) {
					var options = [{
						text: 'Current',
						value: 0,
					}];
					for ( var i = records.length, record; record = records[--i];) {
						var ingest_time = record.get('ingest_time');
						var time_stamp = Ext.Date.parse(ingest_time,
								"Y-m-d H:i:s");
						var dt = Ext.Date.format(time_stamp,
								"M d, Y H:i:s");
						options.push({
							text : record.get('version') + ' - '
									+ record.get('new_records') + ' - '
									+ dt,
							value : record.get('id'),
						});
					}
					select.suspendEvents();
					select.setOptions(options);
					select.resumeEvents();
				}
			});
		}
		var reloadButton = card.items.items[0].items.items[2];
		reloadButton.on('tap', function() {
			dataPanel.setHtml('');
			loadTextData();
		});
		card.on('activate', loadTextData);
		
		select.on('change', function(select, newValue, oldValue) {
			loadTextData(newValue.get('value'));
		})
		
		this.goBackEvent(card, cards.stationLayerListCard);
	},
	layerDataListCard: function(cards, card) {
		var store = Ext.getStore('WERealtime.store.layerData');
		var list = card.getComponent('layerDataList');
		list.setStore(store);

		card.on('activate', function() {
			var WEData = this.WEData;
			//var 
			//var layerDescription = 
			//cardTitle = 'Data of' + station_description
			//card.items.items[0].setTitle(cardTitle);
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