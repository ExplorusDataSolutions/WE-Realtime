//Ext.Loader.setConfig({enabled:true});

var app = {
	launch : function() {
		var app = this;
		var cards = this.cards = Ext.create('Ext.Panel', {
			layout : 'card',
			fullscreen : true,
			cardSwitchAnimation : 'slide',

			items : [ viewMainMenu, viewHistoryList, viewStationList,
					viewStationLayerList, viewTextdataHistoryList,
					viewLayerDataList, viewApiList, viewApiDemo, viewBasinDatatype ],
		});

		// some useful references
		cards.mainMenuCard = cards.getComponent('mainMenuCard');
		cards.ingestingHistoryCard = cards.getComponent('ingestingHistoryCard');
		cards.basinDataTypeCard = cards.getComponent('basinDataTypeCard');
		cards.stationListCard = cards.getComponent('stationListCard');
		cards.stationLayerListCard = cards.getComponent('stationLayerListCard');
		cards.textdataHistoryCard = cards.getComponent('textdataHistoryCard');
		cards.layerDataListCard = cards.getComponent('layerDataListCard');
		cards.apiListCard = cards.getComponent('apiListCard');
		cards.apiDemoCard = cards.getComponent('apiDemoCard');

		this.mainMenuCard(cards, cards.mainMenuCard);
		this.ingestingHistoryCard(cards, cards.ingestingHistoryCard);
		this.basinDataTypeCard(cards, cards.basinDataTypeCard);
		this.stationListCard(cards, cards.stationListCard);
		this.stationLayerListCard(cards, cards.stationLayerListCard);
		this.textdataHistoryCard(cards, cards.textdataHistoryCard);
		this.layerDataListCard(cards, cards.layerDataListCard);
	},
	/**
	 * Some frequently used methods
	 */
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
	/**
	 * Cards and their events
	 */
	mainMenuCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.mainMenu');
		var list = card.getComponent('mainMenuList');
		
		// show main menu, and once only
		list.getStore() || list.setStore(store);
		
		list.on('itemtap', function(view, index, target, record) {
			setTimeout(function() {
				var menuTitle = record.get('menu');
				// We can add more "if" here to add new screens
				switch (menuTitle) {
				case 'API calls':
					me.initApiListCard(cards, cards.apiListCard);
					me.goForward(cards.apiListCard);
					break;
				case 'View basins and data types':
					me.goForward(cards.basinDataTypeCard);
					break;
				case 'View stations':
					me.goForward(cards.stationListCard);
					break;
				case 'Ingesting history':
					me.goForward(cards.ingestingHistoryCard);
					break;
				case 'Check stations updates':
					//me.initApiListCard(cards, cards.apiListCard);
					//me.goForward(cards.apiListCard);
					break
				}
			}, 100);
		});
	},
	ingestingHistoryCard : function(cards, card) {
		var store = Ext.getStore('WERealtime.store.ingestingHistory');
		var list = card.getComponent('historyList');
		var btn = card.items.items[2].items.items[1];
		list.setStore(store);
		
		card.on('activate', function() {
			store.load(function(records) {
				var last_record;
				if (records.length) {
					last_record = records[0];
				}
				
				if (last_record && last_record.get('end_time') == '') {
					btn.setHtml('Stop current ingesting');
				} else {
					btn.setHtml('Start new ingesting');
				}
			});
		})
		
		btn.on('tap', function() {
			var last_version = store.getAt(0);
			
			if (last_version && last_version.get('end_time') == '') {
				Ext.Ajax.request({
					url: 'index.php',
					method: 'POST',
					jsonData: {
						request: 'stopCurrentIngesting',
						version: last_version.get('version'),
						format: 'json',
					},
					success: function(response, options) {
						alert(response.responseText)
					}
				});
			}
		});
		
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
			
			if (el.className == 'x-list-item-label') {
				cards.layerDataListCard.WEData = {
					basin_id : record.get('basin_id'),
					datatype_id : record.get('datatype_id'),
					station_strid : card.WEData.get('strid'),
					layer : record.get('field'),
				}
				me.goForward(cards.layerDataListCard);
			}
			return false;
		})
	},
	/*
	 * View textdata history 
	 */
	textdataHistoryCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.textdataHistory');
		var select = card.items.items[3].getComponent('textdataSelect');
		var dataPanel = card.items.items[1];
		var parseCurrent = card.items.items[2].items.items[1];
		var parseAll = card.items.items[2].items.items[2];
		
		//var baseUrl = 'http://www.environment.alberta.ca/apps/basins/DisplayData.aspx';
		var loadVersionList = function(WEData) {
			select.disable();
			store.load({
				params : WEData,
				callback : function(records) {
					var options = [{
						text: WEData.station_strid + ' - Real time data',
						value: 0,
					}];
					for ( var i = records.length, record; record = records[--i];) {
						var ingest_time = record.get('ingest_time');
						var time_stamp = Ext.Date.parse(ingest_time,
								"Y-m-d H:i:s");
						var dt = Ext.Date.format(time_stamp,
								"M d, Y H:i:s");
						options.push({
							text : WEData.station_strid + ' - '
									+ record.get('version') + ' - '
									+ record.get('id') + ' - '
									+ record.get('new_records') + '/'
									+ record.get('all_records'),
							value : record.get('id'),
						});
					}
					select.suspendEvents();
					select.setOptions(options);
					select.resumeEvents();
					select.enable();
				}
			});
		}
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
			loadVersionList(WEData);
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
		
		parseCurrent.on('tap', function() {
			var WEData = card.WEData;
			
			Ext.Ajax.request({
				url : 'index.php',
				method : 'POST',
				jsonData : {
					request : 'parseTextdata',
					text_id : select.getValue(),
					station_strid : WEData.station_strid,
					format : 'json',
				},
				success : function(response, options) {
					var result = Ext.decode(response.responseText);
					var message = Ext.String.format(
							'{0} records parsed, {1} records updated',
							result.AllRecords, result.NewRecords
						);
					alert(message);
					loadVersionList(WEData);
				}
			})
		})
		
		parseAll.on('tap', function() {
			var WEData = card.WEData;
			
			Ext.Ajax.request({
				url : 'index.php',
				method : 'POST',
				jsonData : {
					request : 'parseTextdataHistory',
					basin_id : WEData.basin_id,
					datatype_id : WEData.datatype_id,
					station_strid : WEData.station_strid,
					format : 'json',
				},
				success : function(response, options) {
					var result = Ext.decode(response.responseText);
					var message = Ext.String.format('{0} versions parsed', result.Versions);
					alert(message);
					loadVersionList(WEData);
				}
			})
		})
	},
	layerDataListCard: function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.layerData');
		var list = card.getComponent('layerDataList');
		list.setStore(store);
		
		this.goBackEvent(card, cards.stationLayerListCard);
		
		card.on('activate', function() {
			var WEData = this.WEData;
			store.load({
				params : WEData
			});
		})
		
		list.on('itemtaphold', function(dataView, index, dataItem, record) {
			var overlay = me.getOverlay();
			overlay.show();
			
			var WEData = card.WEData;
			Ext.Ajax.request({
				url : 'index.php',
				jsonData : {
					request : 'singleTextdata',
					text_id : record.get('text_id'),
					format: 'json',
				},
				method : 'POST',
				callback : function(options, success, response) {
					var result = Ext.decode(response.responseText);
					if (success == true) {
						overlay.setHtml('<pre>' + result.Text + '</pre>');
						overlay.setMasked(false);
					}
				}
			})
		})
	},
	initApiListCard : function(cards, card) {
		var me = this;
		var store = Ext.getStore('WERealtime.store.apiMenu');
		var list = card.getComponent('apiList');
		var back = card.items.items[0].items.items[0];
		
		this.goBackEvent(card, cards.mainMenuCard);
		
		card.on('activate', function() {
			list.getStore() || list.setStore(store);
		});
		
		list.on('itemtap', function(view, index, target, record) {
			cards.apiDemoCard.WEData = record;
			me.goForward(cards.apiDemoCard);
		});
		
		this.initApiDemoCard(cards, cards.apiDemoCard);
	},
	initApiDemoCard: function(cards, card) {
		var me = this;
		var apiTextBox = card.items.items[1];
		var send = card.items.items[0].items.items[2];
		var result = card.items.items[2];
		
		this.goBackEvent(card, cards.apiListCard);
		
		card.on('activate', function() {
			var WEData = card.WEData;
			var apiText = WEData.get('description');
			
			apiTextBox.setValue(apiText);
			result.setValue('');
		});
		
		send.on('tap', function() {
			var WEData = card.WEData;
			var apiText = WEData.get('description');
			var jsonData = Ext.decode(apiText);
			
			var el = result.getEl();
			var height = el.getHeight();
			var textarea = el.query('textarea')[0];
			textarea.style.height = height + 'px';
			
			Ext.Ajax.request({
				url : 'index.php',
				jsonData : jsonData,
				method : 'POST',
				success : function(response, opts) {
					result.setValue(response.responseText);
				}
			})
		})
	},
	basinDataTypeCard: function(cards, card) {
		var me = this;
		var carousels = card.items.items[1];
		var basinList = card.items.items[1].items.items[1];
		var basinListStore = Ext.getStore('WERealtime.store.basinList');
		var dataTypeList = card.items.items[1].items.items[2];
		var dataTypeListStore = Ext.getStore('WERealtime.store.datatypeList');
		var stationList = card.items.items[1].items.items[3];
		var stationListStore = Ext.getStore('WERealtime.store.stationList2');
		var selectedBasinId, selectedDatatypeId;
		var indexBar = stationList.getIndexBar();
		
		var select = card.items.items[2].items.items[1];
		var btnViewPage = card.items.items[2].items.items[3];
		
		var backButton = card.items.items[0].items.items[0];
		backButton.on('tap', function() {
			if (carousels.getActiveItem().id == 'basinList') {
				cards.setActiveItem(cards.mainMenuCard, {
					type : 'slide',
					direction : 'right'
				});
			} else {
				carousels.previous();
			}
		});
		var checkButton = card.items.items[0].items.items[2];
		checkButton.on('tap', function() {
			if (carousels.getActiveItem().id == 'basinList') {
				Ext.Ajax.request({
					url : 'index.php',
					jsonData : {
						request : 'checkBasinList',
						format : 'json',
					},
					method : 'POST',
					success : function(response) {
						var result = Ext.decode(response.responseText);
						
						basinListStore.each(function(record, index, total) {
							record.set('Status', 'deleted');
						});
						
						if (Ext.isArray(result)) {
							var newRecords = [];
							for (var i = 0, len = result.length; i < len; i++) {
								var record = basinListStore.getById(result[i].id);
								if (record) {
									var old_desc = record.get('Description');
									var new_desc = result[i].name;
									var status = new_desc == old_desc ? 'same' : 'changed';
									record.set('Description', new_desc);
									record.set('Status', status);
								} else {
									newRecords.push({
										Id : result[i].id,
										Description : result[i].name,
										Status : 'new',
									});
								}
							}
							
							basinListStore.suspendEvents();
							var reader = basinListStore.getProxy().getReader();
							var Model = basinListStore.getModel();
							var records = reader.extractData(newRecords);
							for ( var i = 0, record; record = records[i]; i++) {
								records[i] = new Model(record.data, record.id, record.node);
							}
							basinListStore.add(records);
							basinListStore.resumeEvents();
						}
						
						basinList.refresh();
					}
				});
			} else if (carousels.getActiveItem().id == 'dataTypeList') {
				Ext.Ajax.request({
					url : 'index.php',
					jsonData : {
						request : 'checkDatatypeList',
						format : 'json',
					},
					method : 'POST',
					timeout : 1000 * 1000,
					success : function(response) {
						var result = Ext.decode(response.responseText);
						
						dataTypeListStore.each(function(record, index, total) {
							record.set('Status', 'deleted');
						});
						
						if (Ext.isArray(result)) {
							var newRecords = [];
							for (var i = 0, len = result.length; i < len; i++) {
								var record = dataTypeListStore.getById(result[i].id);
								if (record) {
									var old_desc = record.get('Description');
									var new_desc = result[i].name;
									var old_basins = record.get('Basins');
									var new_basins = result[i].basins;
									new_basins.sort(function(a, b) {return a - b});
									new_basins = new_basins.join(', ');
									if (new_desc != old_desc) {
										record.set('oldDescription', old_desc);
									}
									var status = new_desc == old_desc && old_basins == new_basins ? 'same' : 'changed';
									record.set('Description', new_desc);
									record.set('Status', status);
								} else {
									newRecords.push({
										Id : result[i].id,
										Description : result[i].name,
										Status : 'new',
									});
								}
							}
							
							dataTypeListStore.suspendEvents();
							var reader = dataTypeListStore.getProxy().getReader();
							var Model = dataTypeListStore.getModel();
							var records = reader.extractData(newRecords);
							for ( var i = 0, record; record = records[i]; i++) {
								records[i] = new Model(record.data, record.id, record.node);
							}
							dataTypeListStore.add(records);
							dataTypeListStore.resumeEvents();
						}
						
						dataTypeList.refresh();
					}
				});
			} else if (carousels.getActiveItem().id == 'stationList2') {
				basinListStore.sort('id');
				dataTypeListStore.sort('id');
				basinListStore.each(function(record) {
					var basin_id = record.get('id');
					dataTypeListStore.each(function(record) {
						var datatype_id = record.get('id');
						if (record.raw.Basins[basin_id]) {
							stationListStore.checkUpdates(basin_id, datatype_id, function() {
								stationList.refresh();
								var group, groups = stationListStore.getGroups();
								var groupName = basin_id + '.' + datatype_id;
								var status, info = {total:0};
								for (var i = 0, len = groups.length; i < len; i++) {
									group = groups[i];
									if (group.name == groupName) {
										for (var j = 0, len2 = group.children.length; j < len2; j++) {
											status = group.children[j].get('Status');
											info[status] = info[status] ? info[status] + 1 : 1;
											status != 'deleted' && info.total++;
										}
										var letters = indexBar.getLetters();
										for (var j = 0, len2 = letters.length; j < len2; j++) {
											if (letters[j].indexOf(basin_id + '.' + datatype_id) == 0) {
												letters[j] = group.name
													+ ' (' + info.total + ' '
													+ (info['new'] ? '+' + info['new'] : '')
													+ (info['deleted'] ? '-' + info.deleted : '')
													+ (info['changed'] ? '*' + info.changed : '')
													+ ')';
												break;
											}
										}
										indexBar.setLetters([]);
										indexBar.setLetters(letters);
										break;
									}
								}
							});
							//return false;
						}
					});
					return false;
				});
			}
		});
		
		var loadVersionList = function() {
			select.disable();
			Ext.Ajax.request({
				url : 'index.php',
				jsonData : {
					request : 'basinVersionList',
					format : 'json',
				},
				success : function(response, options) {
					var result = Ext.decode(response.responseText);
					
					var options = [];
					for ( var i = result.length, row; row = result[--i];) {
						var time_stamp = Ext.Date.parse(row.update_time,
								"Y-m-d H:i:s");
						var dt = Ext.Date.format(time_stamp,
								"M d, Y");
						options.push({
							text : 'Version: ' + row.version + ' - ' + dt,
							value : row.version,
						});
					}
					select.suspendEvents();
					select.setOptions(options);
					select.resumeEvents();
					select.enable();
				}
			});
		}
		card.on('activate', function() {
			basinList.getStore() || basinList.setStore(basinListStore);
			basinListStore.load();
			
			dataTypeList.getStore() || dataTypeList.setStore(dataTypeListStore);
			dataTypeListStore.load();
			
			stationList.getStore() || stationList.setStore(stationListStore);
			stationListStore.load(function(records, operation, success) {
				var groups = stationListStore.getGroups();
				var letters = [];
				var len1 = 0, len2 = 0, n1, n2;
				/*for (var i = 0, len = groups.length; i < len; i++) {
					n1 = groups[i].name;
					n2 = groups[i].children.length.toString();
					len1 = Math.max(len1, n1.length);
					len2 = Math.max(len2, n2.length);
				}*/
				for (var i = 0, len = groups.length; i < len; i++) {
					n1 = groups[i].name;
					//n1 = n1 + Ext.String.repeat('&nbsp;', len1 - n1.length + 1);
					n2 = ' (' + groups[i].children.length.toString() + ')';
					//n2 = n2 + Ext.String.repeat('&nbsp;', len2 - n2.length);
					letters.push(n1 + n2);
				}
				indexBar.setLetters(letters);
			});
			
			loadVersionList();
		});
		
		basinList.on('itemtap', function(view, index, target, record) {
			selectedBasinId = record.get('id');
			dataTypeListStore.clearFilter();
			dataTypeListStore.filter(function(item) {
				return (', ' + item.get('Basins')　+ ', ').indexOf(', ' + selectedBasinId + ', ') != -1;
			})
			carousels.next();
		});
		
		dataTypeList.on('itemtap', function(view, index, target, record) {
			if (!selectedBasinId) {
				return false;
			}
			selectedDatatypeId = record.get('id');
			stationListStore.clearFilter();
			stationListStore.filter(function(item) {
				return item.get('BasinId') == selectedBasinId
					&& item.get('DatatypeId') == selectedDatatypeId;
			})
			carousels.next();
		});
		
		var stationsBaseUrl = 'http://www.environment.alberta.ca/apps/basins/Map.aspx';
		btnViewPage.on('tap', function() {
			if (selectedBasinId && selectedDatatypeId) {
				window.open(stationsBaseUrl + '?Basin=' + selectedBasinId + '&DataType=' + selectedDatatypeId);
			} else {
				alert('Please specify both a Basin and a Datatype first')
			}
		});
		/*carousels.on('activate', function(container, item, toIndex, fromIndex, eOpts) {
			alert(toIndex+','+fromIndex)
		})*/
		
		select.on('change', function(select, newValue, oldValue) {
			basinListStore.load({
				params: {
					version: newValue.get('value')
				}
			});
		})
	}
}
Ext.application(app);