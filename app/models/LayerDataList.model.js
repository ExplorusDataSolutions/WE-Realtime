Ext.define('WERealtime.model.LayerData', {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "string",
			mapping : "time"
		}, {
			name : "date",
			type : "string",
			mapping : "time",
			convert : function(value, record) {
				return value.substr(0, 10);
			}
		}, {
			name : "time",
			type : "string",
			mapping : "time",
			convert : function(value, record) {
				return value.substr(11, 5);
			}
		}, {
			name : "value",
			type : "string"
		}, {
			name : "text_id",
			type : "int"
		}, ]
	}
});

Ext.regStore("WERealtime.store.layerData", {
	model : 'WERealtime.model.LayerData',
	proxy : {
		type : 'WERealtime.ajax',
		url : 'index.php',
		jsonData : {
			request : "layerDataList",
			format : 'json'
		},
		reader : {
			type : 'json',
		}
	},
	sorters : [ {
		property : 'time',
		direction : 'DESC'
	} ],
	groupDir : 'DESC',
	grouper : 'date',
})