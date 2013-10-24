//Ext.Loader.setConfig({enabled:true});

Ext.Ajax.on('requestexception', function(conn, response, options) {
	console.log('Server error: [' + response.status + '] ' + response.statusText);
})

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
		
		var timer = null, times = 0;
		var updateIngestingStatus = function() {
			Ext.Ajax.request({
				url : 'index.php',
				method : 'POST',
				jsonData : {
					request	: 'ingestingVersionList',
					start	: 0,
					limit	: 1,
					format	: 'json',
				},
				success : function(response, options) {
					var result = Ext.decode(response.responseText);
					if (result.length && !result[0].end_time) {
						var record = store.getAt(0);
						if (record.get('final_status') == result[0].final_status) {
							if (++times > 3) {
								record.set('unexpected_stop', ' seems stopped');
								clearInterval(timer);
								btn.setText('Start ingesting');
							}
						} else {
							times = 0;
							if (record) {
								record.beginEdit();
								record.set('final_status', result[0].final_status);
								record.set('total', result[0].total);
								record.endEdit();
							}
						}
					} else {
						clearInterval(timer);
						loadVersionList();
					}
				}
			})
		}
		var loadVersionList = function(again) {
			store.load(function(records) {
				var latest_version = store.getAt(0);
				
				if (latest_version && latest_version.get('end_time') == '') {
					btn.setText('Stop current ingesting');
					timer = setInterval(updateIngestingStatus, 5000);
				} else {
					btn.setText('Start ingesting');
					if (again === true) {
						setTimeout(function() {
							loadVersionList(true);
						}, 5000);
					}
				}
			});
		}
		card.on('activate', loadVersionList);
		
		btn.on('tap', function(btn, e) {
			if (btn.getText() == 'Start ingesting') {
				Ext.Ajax.request({
					url: 'index.php',	// ignore_user_abort() in php
					method : 'POST',
					jsonData : {
						request : 'startIngesting',
						url : window.location + 'start_ingesting.php',
					}
				});
				loadVersionList(true);
			}
			if (btn.getText() == 'Stop current ingesting') {
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
			var station_strid = WEData.get('id');
			var station_description = WEData.get('Description');
			var stationStore = Ext.getStore("WERealtime.store.stationList");
			var recordIndex = stationStore.find('id', station_strid);
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
					station_strid : card.WEData.get('id'),
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
		var toolbar = card.items.items[0];
		var carousels = card.items.items[1];
		var basinList = card.items.items[1].items.items[1];
		var basinListStore = Ext.getStore('WERealtime.store.basinList');
		var dataTypeList = card.items.items[1].items.items[2];
		var dataTypeListStore = Ext.getStore('WERealtime.store.datatypeList');
		var stationList = card.items.items[1].items.items[3];
		var stationListStore = Ext.getStore('WERealtime.store.stationList2');
		var selectedBasinId, selectedDatatypeId;
		var indexBar = stationList.getIndexBar();
		
		var selVersion = {
			basinList : card.items.items[2].items.items[1],
			dataTypeList : card.items.items[2].items.items[2],
			stationList2 : card.items.items[2].items.items[3],
		}
		var btnViewPage = card.items.items[2].items.items[3];
		var btnChkUpt = {
			basinList : card.items.items[0].items.items[2],
			dataTypeList : card.items.items[0].items.items[3],
			stationList2 : card.items.items[0].items.items[4],
		}

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
		var indexBarLetters = function(records, operation, success) {
			var groups = stationListStore.getGroups();
			var letters = [];
			var len1 = 0, len2 = 0, n1, n2, m, group, info, status;
			for (var i = 0, len = groups.length; i < len; i++) {
				group = groups[i];
				
				info = {};
				for (var j = 0, jlen = group.children.length; j < jlen; j++) {
					status = group.children[j].get('Status');
					info[status] ? info[status]++ : info[status] = 1;
				}
				
				m = groups[i].name.match(/(\d+)\.(\d+) /);
				n1 = m[1] + '.' + m[2];
				n2 = ' (' + group.children.length.toString()
					+ (info.same == group.children.length ? '' : ' ')
					+ (info['new'] ? '+' + info['new'] : '')
					+ (info.deleted ? '-' + info.deleted : '')
					+ (info.changed ? '*' + info.changed : '')
					+ ')';
				letters.push(n1 + n2);
			}
			indexBar.setLetters(letters);
		}
		
		var showPage = function(page) {
			toolbar.setTitle(page.title);
			
			switch (page.id) {
			case 'basinList':
				if (!basinList.getStore()) {
					basinList.setStore(basinListStore);
					basinListStore.loadVersionList(selVersion.basinList, function() {
						basinListStore.load();
					});
				}
				break;
			case 'dataTypeList':
				if (!dataTypeList.getStore()) {
					dataTypeList.setStore(dataTypeListStore);
					dataTypeListStore.load();
					dataTypeListStore.loadVersionList(selVersion.dataTypeList);
				}
				break;
			case 'stationList2':
				if (!stationList.getStore()) {
					stationList.setStore(stationListStore);
					stationListStore.load({
						//params: params,
						callback: indexBarLetters
					});
					stationListStore.loadVersionList(selVersion.stationList2);
				}
				break;
			}
		}
		card.on('activate', function() {
			showPage(carousels.getComponent('basinList'));
		});
		carousels.on('activeitemchange', function(container, value, oldValue) {
			showPage(value);
			for (var i in btnChkUpt) btnChkUpt[i].hide();
			btnChkUpt[value.id].show();
			for (var i in selVersion) selVersion[i].hide();
			selVersion[value.id].show();
		});
		
		/*
		 * basins
		 */
		selVersion.basinList.on('change', function(select, newValue, oldValue) {
			basinListStore.load({params : {version : newValue.get('value')}});
		});
		basinList.on('itemtap', function(view, index, target, record) {
			selectedBasinId = record.get('id');
			dataTypeListStore.clearFilter();
			dataTypeListStore.filter(function(item) {
				return (', ' + item.get('Basins')　+ ', ').indexOf(', ' + selectedBasinId + ', ') != -1;
			})
			carousels.next();
		});
		var basinActionSheet = Ext.create('Ext.ActionSheet', {
			items: [ {
				text: 'Release changes',
				ui  : 'decline',
				handler : function() {
					basinActionSheet.hide();
					btnChkUpt.basinList.setText('Check Updates');
					basinListStore.load();
				}
			}, {
				text: 'Save as new version',
				ui  : 'confirm',
				handler : function() {
					basinActionSheet.hide();
					basinListStore.saveUpdates(function() {
						basinListStore.load();
						basinListStore.loadVersionList(selVersion.basinList);
						btnChkUpt.basinList.setText('Check Updates');
					});
				}
			}, {
				text: 'Cancel',
				handler : function() {
					basinActionSheet.hide();
				}
			} ]
		});
		Ext.Viewport.add(basinActionSheet);
		btnChkUpt.basinList.on('tap', function() {
			var btn = btnChkUpt.basinList;
			if (btn.getText() == 'Action') {
				basinActionSheet.show();
			} else {
				btn.disable();
				
				basinList.mask();
				basinListStore.checkUpdates(function() {
					basinList.unmask();
					btn.enable();
					
					var statusMap = {};
					basinListStore.each(function(record, index, total) {
						var status = record.get('Status');
						if (status != 'same') {
							statusMap[status] ? statusMap[status]++ : statusMap[status] = 1;
						}
					});
					if (Ext.encode(statusMap) == '{}') {
						if (confirm('No changes to save. Save as new version anyway?')) {
							basinListStore.saveUpdates(function() {
								basinListStore.load();
								basinListStore.loadVersionList(selVersion.basinList);
							});
						} else {
							btn.setText('Action');
						}
					} else {
						if (confirm(basinListStore.statistic() + ' Save changes?')) {
							basinListStore.saveUpdates(function() {
								basinListStore.load();
								basinListStore.loadVersionList(selVersion.basinList);
							});
						} else {
							btn.setText('Action');
						}
					}
				});
			}
		});
		/*
		 * data types
		 */
		selVersion.dataTypeList.on('change', function(select, newValue, oldValue) {
			dataTypeListStore.load({params : {version : newValue.get('value')}});
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
		var datatypeActionSheet = Ext.create('Ext.ActionSheet', {
			items: [ {
				text: 'Release changes',
				ui  : 'decline',
				handler : function() {
					datatypeActionSheet.hide();
					btnChkUpt.dataTypeList.setText('Check Updates');
					dataTypeListStore.load();
				}
			}, {
				text: 'Save as new version',
				ui  : 'confirm',
				handler : function() {
					datatypeActionSheet.hide();
					basinListStore.saveUpdates(function() {
						dataTypeListStore.load();
						dataTypeListStore.loadVersionList(selVersion.datatypeList);
						btnChkUpt.datatypeList.setText('Check Updates');
					});
				}
			}, {
				text: 'Cancel',
				handler : function() {
					datatypeActionSheet.hide();
				}
			} ]
		});
		Ext.Viewport.add(datatypeActionSheet);
		btnChkUpt.dataTypeList.on('tap', function() {
			var btn = btnChkUpt.dataTypeList;
			if (btn.getText() == 'Action') {
				datatypeActionSheet.show();
			} else {
				btn.disable();
				dataTypeList.mask();
				
				dataTypeListStore.checkUpdates(function(result) {
					dataTypeList.unmask();
					btn.enable();
					
					if (result.message) {
						alert(result.message);
						dataTypeListStore.load();
					} else {
						var statusMap = {};
						dataTypeListStore.each(function(record, index, total) {
							var status = record.get('Status');
							if (status != 'same') {
								statusMap[status] ? statusMap[status]++ : statusMap[status] = 1;
							}
						});
						if (Ext.encode(statusMap) == '{}') {
							if (confirm('No changes to save. Save as new version anyway?')) {
								dataTypeListStore.saveUpdates(function() {
									dataTypeListStore.load();
									dataTypeListStore.loadVersionList(selVersion.basinList);
								});
							} else {
								btn.setText('Action');
							}
						} else {
							if (confirm(dataTypeListStore.statistic() + ' Save changes?')) {
								dataTypeListStore.saveUpdates(function() {
									dataTypeListStore.load();
									dataTypeListStore.loadVersionList(selVersion.basinList);
								});
							} else {
								btn.setText('Action');
							}
						}
					}
				});
			}
		});
		/*
		 * stations
		 */
		selVersion.stationList2.on('change', function(select, newValue, oldValue) {
			stationListStore.load({
				params : {version : newValue.get('value')},
				callback: indexBarLetters
			});
		});
		
		var stationActionSheet = Ext.create('Ext.ActionSheet', {
			items: [ {
				text: 'Release changes',
				ui  : 'decline',
				handler : function() {
					stationActionSheet.hide();
					btnChkUpt.stationList2.setText('Check Updates');
					stationListStore.load(indexBarLetters);
				}
			}, {
				text: 'Save as new version',
				ui  : 'confirm',
				handler : function() {
					stationActionSheet.hide();
					stationListStore.saveUpdates(function() {
						stationListStore.load(indexBarLetters);
						stationListStore.loadVersionList(selVersion.stationList2);
					});
					btnChkUpt.stationList2.setText('Check Updates');
				}
			}, {
				text: 'Cancel',
				handler : function() {
					stationActionSheet.hide();
				}
			} ]
		});
		Ext.Viewport.add(stationActionSheet);
		btnChkUpt.stationList2.on('tap', function() {
			var btn = btnChkUpt.stationList2;
			if (btn.getText() == 'Action') {
				stationActionSheet.show();
			} else {
				btnChkUpt.stationList2.disable();
				
				basinListStore.sort('id');
				dataTypeListStore.sort('id');
				
				var paramsList = [];
				basinListStore.each(function(record) {
					var basin_id = record.get('id');
					dataTypeListStore.each(function(record) {
						var datatype_id = record.get('id');
						if (record.raw.Basins[basin_id]) {
							paramsList.push([basin_id, datatype_id]);
						}
					})
				});
				
				stationListStore.each(function(record) {
					if (record.get('Status') == 'deleted') {
						stationListStore.remove(record);
					} else {
						record.beginEdit();
						record.set('Status', 'deleted');
						record.endEdit(true);
					}
				});
				
				setTimeout(function() {
					var callee = arguments.callee;
					var p = paramsList.shift();
					var basin_id = p[0];
					var datatype_id = p[1];
					
					stationListStore.checkUpdates(basin_id, datatype_id, function() {
						var group = stationListStore.getGroup(basin_id, datatype_id);
						if (group) {
							var status, info = {total:0};
							for (var i = 0, len = group.children.length; i < len; i++) {
								status = group.children[i].get('Status');
								info[status] = info[status] ? info[status] + 1 : 1;
								status != 'deleted' && info.total++;
							}
							var letters = indexBar.getLetters();
							for (var i = 0, len = letters.length; i < len; i++) {
								if (letters[i].indexOf(basin_id + '.' + datatype_id + ' ') == 0) {
									letters[i] = basin_id + '.' + datatype_id
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
							stationList.onIndex(indexBar, basin_id + '.' + datatype_id);
						}
						if (paramsList.length) {
							setTimeout(callee, 1000);
						} else {
							btn.enable();
							
							var statusMap = {};
							stationListStore.each(function(record, index, total) {
								var status = record.get('Status');
								statusMap[status] ? statusMap[status]++ : statusMap[status] = 1;
							});
							if (statusMap.same == stationListStore.length) {
								if (confirm('No changes to save. Save as new version anyway?')) {
									stationListStore.saveUpdates();
								} else {
									btn.setText('Action');
								}
							} else {
								if (confirm(stationListStore.statistic() + ' Save changes?')) {
									stationListStore.saveUpdates(function() {
										stationListStore.load(indexBarLetters);
										stationListStore.loadVersionList(selVersion.stationList2);
									});
								} else {
									btn.setText('Action');
								}
							}
						}
					});
				}, 100);
			}
		});
	}
}
Ext.application(app);