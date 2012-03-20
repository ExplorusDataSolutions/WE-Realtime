Ext.define('WERealtime.model.Station', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "string", mapping : "Id"},
			{name: "Description"},
			{name: "Code"},
			{name: "Datatypes"},
			{name: "BasinId"},
			{name: "DatatypeId"},
			{name: "layers_update_time"},
			{name: "Status"}
	    ],
	}
});


Ext.define('WERealtime.model.Station2', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "string", mapping : "Id2"},
			{name: "StationId", mapping : "Id"},
			{name: "oldDescription"},
			{name: "Description"},
			{name: "Code"},
			{name: "Datatypes"},
			{name: "BasinId"},
			{name: "DatatypeId"},
			{name: "layers_update_time"},
			{name: "Status"},
			{name: "Version"}
	    ],
	}
});


var store = Ext.create("WERealtime.extraInfoStore", {
    model: 'WERealtime.model.Station',
    proxy: {
    	type: 'WERealtime.ajax',
        url: 'index.php',
        jsonData: {
    		request: "stationList",
    		format: 'json'
    	},
        reader: {
            type: 'json',
        }
    },
    sorters: 'description',
    grouper : function(record) {
		return record.get('Description')[0].toUpperCase();
    },
    loadCondition: function() {
    	return this.getData().length == 0;
    }
})
Ext.regStore("WERealtime.store.stationList", store);


Ext.regStore('WERealtime.store.stationList2', {
//var stationListStore = Ext.create("WERealtime.extraInfoStore", {
	model: 'WERealtime.model.Station2',
	proxy: {
		type: 'WERealtime.ajax',
		url: 'index.php',
		jsonData: {
			request: "stationListWithStatus",
			format: 'json'
		},
		reader: {
			type: 'json',
		}
	},
	sorters: 'Description',
	grouper : {
		groupFn : function(record) {
			var basin_id = record.get('BasinId');
			var datatype_id = record.get('DatatypeId');
			var stationsBaseUrl = 'http://www.environment.alberta.ca/apps/basins/Map.aspx'
				+ '?Basin=' + basin_id + '&DataType=' + datatype_id;
			
			return basin_id + '.' + datatype_id
				+ ' <a target="_blank" href="' + stationsBaseUrl + '">&gt;&gt;</a>';
		},
		sorterFn : function(r1, r2) {
			var basin = 0 + r1.get('BasinId') - r2.get('BasinId');
			var type = 0 + r1.get('DatatypeId') - r2.get('DatatypeId');
			
			return basin == 0 ? type : basin;
		}
	},
	//loadCondition: function() {
	//	return true;
	//}
})
//Ext.regStore("WERealtime.store.stationList2", stationListStore);
var stationListStore = Ext.getStore('WERealtime.store.stationList2');

stationListStore.getGroup = function(basin_id, datatype_id) {
	var m, group = null, groups = this.getGroups();
	for (var i = 0, len = groups.length; i < len; i++) {
		group = groups[i];
		m = group.name.match(/(\d+)\.(\d+) /);
		if (m[1] == basin_id && m[2] == datatype_id) {
			return group;
		}
	}
	return null;
}
stationListStore.loadVersionList = function(select) {
	select.disable();
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'stationVersionList',
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
stationListStore.checkUpdates = function(basin_id, datatype_id, callback) {
	var stationListStore = this;
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'checkStationList',
			basin_id : basin_id,
			datatype_id : datatype_id,
			format : 'json',
		},
		method : 'POST',
		timeout : 1000 * 1000,
		success : function(response) {
			var result = Ext.decode(response.responseText);
			
			if (Ext.isArray(result)) {
				var newRecords = [];
				for (var i = 0, len = result.length; i < len; i++) {
					var row = result[i];
					var id = row.id + '_' + basin_id + '_' + datatype_id;
					var record = stationListStore.getById(id);
					if (record) {
						var rawData = record.raw;
						
						var old_desc = record.get('Description');
						var new_desc = row.name;
						var status = new_desc == old_desc ? 'same' : 'changed';
						
						if (status == 'changed') {
							rawData.oldDescription = old_desc;
						}
						rawData.Description = new_desc;
						rawData.Status = status;
						rawData.Code = row.code;
						rawData.Version = '?';
						
						rawData.id = id;
						rawData.StationId = row.id;
						record.setData(rawData);
					} else {
						newRecords.push({
							Id2 : id,
							Id : row.id,
							Description : row.name,
							Status : 'new',
							Code : result[i].code,
							BasinId : basin_id,
							DatatypeId : datatype_id,
							Version : '?',
						});
					}
				}
				
				stationListStore.suspendEvents();
				var reader = stationListStore.getProxy().getReader();
				var Model = stationListStore.getModel();
				var records = reader.extractData(newRecords);
				for ( var i = 0, record; record = records[i]; i++) {
					records[i] = new Model(record.data, record.id, record.node);
				}
				stationListStore.add(records);
				stationListStore.resumeEvents();
				
				stationListStore.fireEvent('refresh');
			} else {
				// This is possible, for basin(8).datatype(10), basin(8).datatype(12) etc
				// No stations
			}
			
			Ext.isFunction(callback) && callback();
		}
	});
}
stationListStore.statistic = function() {
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
stationListStore.saveUpdates = function(callback) {
	var stationListStore = this;
	
	var data = [];
	stationListStore.each(function(record, index, total) {
		var status = record.get('Status');
		if (status != 'deleted') {
			data.push({
				Id: record.get('StationId'),
				Description: record.get('Description'),
				Code: record.get('Code'),
				BasinId: record.get('BasinId'),
				DatatypeId: record.get('DatatypeId'),
			});
		}
	});
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'saveStationList',
			stationList : data,
			format : 'json',
		},
		method : 'POST',
		success : function(response) {
			try {
				var result = Ext.decode(response.responseText);
			} catch (e) {
				;
			}
			/*var msg = ''
				+ (result.new ? result.new + ' records added' : '')
				+ (result.update ? result.update + ' records updated' : '')
				+ (result.failed ? result.failed + ' records failed to save' : '')
				+ ' for version ' + result.version + '.';
			alert(msg);*/
			Ext.isFunction(callback) && callback();
		}
	});
}
