Ext.define('WERealtime.model.Layer', {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int"
		}, {
			name : "layerid",
			type : "int"
		}, {
			name : "description"
		}, {
			name : "field"
		}, {
			name : "begintime"
		}, {
			name : "endtime"
		}, {
			name : "basin_id",
			type : "int"
		}, {
			name : "datatype_id",
			type : "int"
		}, ],
	}
});

Ext.define('WERealtime.model.StationLayers', {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int",
			convert : function(value, record) {
				return record.get('station_strid') + '-' + record.get('layerid');
			}
		}, {
			name : "layerid",
			type : "int"
		}, {
			name : "description",
		}, {
			name : "field"
		}, {
			name : "station_strid",
		}, {
			name : "begintime",
			convert : function(value) {
				return value && value.substr(0, 16);
			}
		}, {
			name : "endtime",
			convert : function(value) {
				return value && value.substr(0, 16);
			}
		}, {
			name : "basin_id",
			type : "int"
		}, {
			name : "datatype_id",
			type : "int"
		}, ],
	}
});

var config = {
	model : 'WERealtime.model.StationLayers',
	proxy : {
		type : 'WERealtime.ajax',
		url : 'index.php',
		jsonData : {
			request : "stationLayerList",
			format : 'json'
		},
		reader : {
			type : 'json',
			rootProperty : 'stationLayerList',
			messageProperty : 'message',
		}
	},
	grouper : function(record) {
		var basin_id = record.get('basin_id');
		var datatype_id = record.get('datatype_id');
		var basinStore = Ext.getStore('WERealtime.store.basinList');
		var dataTypeStore = Ext.getStore('WERealtime.store.dataTypeList');

		if (record = basinStore.getById(basin_id)) {
			basin_id = record.get('description');
		}
		if (record = dataTypeStore.getById(datatype_id)) {
			datatype_id = record.get('description');
		}

		return basin_id + ', ' + datatype_id;
	},
	loadCondition : function(options) {
		var station_strid = options && options.params && options.params.station;

		this.setFilters([ {
			property : 'station_strid',
			value : station_strid
		} ]);
		this.filter();

		return this.getCount() == 0;
	},
	onProcessExtraInfo : function(extraInfo) {
		var list = extraInfo.basinList;
		var store = Ext.getStore('WERealtime.store.basinList');

		store.suspendEvents();
		var reader = store.getProxy().getReader(), Model = store.getModel();
		var records = reader.extractData(list);
		for ( var i = 0, record; record = records[i]; i++) {
			records[i] = new Model(record.data, record.id, record.node);
		}
		store.add(records);
		store.resumeEvents();

		var list = extraInfo.dataTypeList;
		var store = Ext.getStore('WERealtime.store.dataTypeList');

		store.suspendEvents();
		var reader = store.getProxy().getReader(), Model = store.getModel();
		var records = reader.extractData(list);
		for ( var i = 0, record; record = records[i]; i++) {
			records[i] = new Model(record.data, record.id, record.node);
		}
		store.add(records);
		store.resumeEvents();
	}
};
var store = Ext.create("WERealtime.extraInfoStore", config);
Ext.regStore("WERealtime.store.stationLayerList", store);