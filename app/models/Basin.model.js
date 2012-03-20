Ext.define("WERealtime.model.Basin", {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int",
			mapping : "Id",
		}, {
			name : "Description",
			type : "string"
		}, {
			name : "UpdateTime",
			type : "string"
		}, {
			name : "Version",
			type : "string"
		}, {
			name : "Status",
			type : "string"
		}, ]
	}
});


Ext.define("WERealtime.model.Datatype", {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int",
			mapping : "Id",
		}, {
			name : "Description",
			type : "string",
		}, {
			name : "oldDescription",
			type : "string",
		}, {
			name : "UpdateTime",
			type : "string"
		}, {
			name : "Status",
			type : "string",
		}, {
			name : "Version",
			type : "string",	// include '?'
		}, {
			name : "Basins",
			convert : function(value) {
				if (Ext.isArray(value)) {
					return value.join(', ');
				} else {
					var array = [];
					for (basin_id in value) {
						array.push(basin_id);
					}
					return array.join(', ');
				}
			}
		}, {
			name : "oldBasins",
			convert : function(value) {
				if (Ext.isArray(value)) {
					return value.join(', ');
				} else {
					var array = [];
					for (basin_id in value) {
						array.push(basin_id);
					}
					return array.join(', ');
				}
			}
		} ]
	}
});


Ext.regStore('WERealtime.store.basinList', {
	model: 'WERealtime.model.Basin',
	proxy : {
		type : 'WERealtime.ajax',
		url : 'index.php',
		jsonData : {
			request : "basinList",
			format : 'json'
		},
		reader : {
			type : 'json',
		}
	},
});
var basinListStore = Ext.getStore('WERealtime.store.basinList');
basinListStore.loadVersionList = function(select, callback) {
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
			
			Ext.isFunction(callback) && callback();
		}
	});
}
basinListStore.checkUpdates = function(callback) {
	var basinListStore = this;
	
	basinListStore.each(function(record, index, total) {
		if (record.get('Status') == 'deleted') {
			basinListStore.remove(record);
		} else {
			record.beginEdit();
			record.set('Status', 'deleted');
			record.endEdit(true);
		}
	});
	basinListStore.fireEvent('refresh');
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'checkBasinList',
			format : 'json',
		},
		method : 'POST',
		success : function(response) {
			var result = Ext.decode(response.responseText);
			
			if (Ext.isArray(result)) {
				var newRecords = [];
				for (var i = 0, len = result.length; i < len; i++) {
					var record = basinListStore.getById(result[i].id);
					if (record) {
						var old_desc = record.get('Description');
						var new_desc = result[i].name;
						var status = new_desc == old_desc ? 'same' : 'changed';
						record.beginEdit();
						record.set('Description', new_desc);
						record.set('Status', status);
						record.set('Version', '?');
						record.endEdit(true);
					} else {
						newRecords.push({
							Id : result[i].id,
							Description : result[i].name,
							Status : 'new',
							Version : '?',
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
				
				basinListStore.fireEvent('refresh');
			}
			
			Ext.isFunction(callback) && callback();
		}
	});
}
basinListStore.statistic = function() {
	var statusMap = {};
	this.each(function(record, index, total) {
		var status = record.get('Status');
		statusMap[status] ? statusMap[status]++ : statusMap[status] = 1;
	});
	return 'There are'
		+ (statusMap['new'] ? ' ' + statusMap['new'] + ' New' : '')
		+ (statusMap['deleted'] ? ' ' + statusMap['deleted'] + ' Deleted' : '')
		+ (statusMap['changed'] ? ' ' + statusMap['changed'] + ' Changed' : '')
		+ '.';
}
basinListStore.saveUpdates = function(callback) {
	var basinListStore = this;
	
	var data = [];
	basinListStore.each(function(record, index, total) {
		var status = record.get('Status');
		if (status != 'deleted') {
			data.push({
				Id: record.get('id'),
				Description: record.get('Description'),
			});
		}
	});
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'saveBasinList',
			basinList : data,
			format : 'json',
		},
		method : 'POST',
		success : function(response) {
			var result = Ext.decode(response.responseText);
			var msg = ''
				+ (result.new ? result.new + ' record(s) added' : '')
				+ (result.update ? result.update + ' record(s) updated' : '')
				+ (result.failed ? result.failed + ' record(s) failed to save' : '')
				+ ' for version ' + result.version + '.';
			alert(msg);
			
			Ext.isFunction(callback) && callback();
		}
	});
}


Ext.regStore('WERealtime.store.datatypeList', {
	model: 'WERealtime.model.Datatype',
	proxy : {
		type : 'WERealtime.ajax',
		url : 'index.php',
		jsonData : {
			request : "datatypeList",
			format : 'json'
		},
		reader : {
			type : 'json',
		}
	},
});
var dataTypeListStore = Ext.getStore('WERealtime.store.datatypeList');
dataTypeListStore.loadVersionList = function(select) {
	select.disable();
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'datatypeVersionList',
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
dataTypeListStore.checkUpdates = function(callback) {
	var dataTypeListStore = this;
	
	dataTypeListStore.each(function(record, index, total) {
		if (record.get('Status') == 'deleted') {
			dataTypeListStore.remove(record);
		} else {
			record.beginEdit();
			record.set('Status', 'deleted');
			record.endEdit(true);
		}
	});
	dataTypeListStore.fireEvent('refresh');
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'checkDatatypeList',
			format : 'json',
		},
		method : 'POST',
		timeout : 1000 * 1000,
		success : function(response) {
			// 0: {id:1, name:River Flows and Levels, basins:[2, 3, 8, 12, 11, 4, 10, 1, 7]}
			// 1: {id:3, name:Lakes and Reservoirs Levels, basins:[2, 3, 8, 11, 4, 10, 1, 7]}
			var result = Ext.decode(response.responseText);
			
			if (Ext.isArray(result)) {
				var newRecords = [];
				for (var i = 0, len = result.length; i < len; i++) {
					var row = result[i];
					var record = dataTypeListStore.getById(row.id);
					if (record) {
						var rawData = record.raw;
						
						var old_desc = record.get('Description');
						var new_desc = row.name;
						var old_basins = record.get('Basins');
						var new_basins = row.basins;
						new_basins.sort(function(a, b) {return a - b});
						new_basins = new_basins.join(', ');
						
						rawData.oldDescription = new_desc != old_desc ? old_desc : '';
						rawData.oldBasins = old_basins != new_basins ? record.raw.Basins : null;
						var status = new_desc == old_desc && old_basins == new_basins ? 'same' : 'changed';
						rawData.Description = new_desc;
						rawData.Status = status;
						rawData.Basins = row.basins;
						
						rawData.id = rawData.Id;
						rawData.Version = '?';
						record.setData(rawData);
					} else {
						newRecords.push({
							Id : result[i].id,
							Description : result[i].name,
							Status : 'new',
							Version : '?',
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
				
				dataTypeListStore.fireEvent('refresh');
			}
			
			Ext.isFunction(callback) && callback(result);
		}
	});
}
dataTypeListStore.statistic = function() {
	var statusMap = {};
	this.each(function(record, index, total) {
		var status = record.get('Status');
		statusMap[status] ? statusMap[status]++ : statusMap[status] = 1;
	});
	return 'There are'
		+ (statusMap['new'] ? ' ' + statusMap['new'] + ' New' : '')
		+ (statusMap['deleted'] ? ' ' + statusMap['deleted'] + ' Deleted' : '')
		+ (statusMap['changed'] ? ' ' + statusMap['changed'] + ' Changed' : '')
		+ '.';
}
dataTypeListStore.saveUpdates = function() {
	var dataTypeListStore = this;
	
	var data = [];
	dataTypeListStore.each(function(record, index, total) {
		var status = record.get('Status');
		if (status != 'deleted') {
			data.push(record.raw);
		}
	});
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'saveDatatypeList',
			dataTypeList : data,
			format : 'json',
		},
		method : 'POST',
		success : function(response) {
			var result = Ext.decode(response.responseText);
			if (result.message) {
				alert(result.message);
				dataTypeListStore.load();
			} else {
				alert(response.responseText);
			}
		}
	})
}


