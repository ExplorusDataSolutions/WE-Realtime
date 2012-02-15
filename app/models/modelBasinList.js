Ext.define("WERealtime.model.Basin", {
    extend: "Ext.data.Model",
    config: {
    	//idProperty: 'basin_id',
    	fields: [
            {name: "id", type: "int"},
            {name: "description", type: "string"},
            {name: "update_time"},
            {name: "version", type: "int"},
        ]
    }
});


Ext.regStore('WERealtime.store.basinList', {
	model: 'WERealtime.model.Basin',
	proxy : {
		type : 'WERealtime.ajax',
		url : 'index.php',
		jsonData : {
			request : "stationLayerList",
			station : "ABEE",
			format : 'json'
		},
		reader : {
			type : 'json',
			rootProperty : 'basinList',
		}
	},
});