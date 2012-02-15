Ext.define("WERealtime.model.Datatype", {
    extend: "Ext.data.Model",
    config: {
    	fields: [
            {name: "id", type: "int", mapping: "datatype_id"},
            {name: "description", type: "string"},
            //{name: "update_time"},
            //{name: "version", type: "int"},
        ]
    }
});


Ext.regStore('WERealtime.store.dataTypeList', {
	model: 'WERealtime.model.Datatype',
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
});