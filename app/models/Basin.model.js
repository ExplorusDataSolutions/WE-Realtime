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
			type : "int"
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
			name : "update_time",
		}, {
			name : "Status",
			type : "string",
		}, {
			name : "Basins",
			convert : function(value) {
				var array = [];
				for (basin_id in value) {
					array.push(basin_id);
				}
				return array.join(', ');
			}
		}, ]
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