Ext.define('WERealtime.model.Station', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "int"},
			{name: "strid", type: "string"},
			{name: "description"},
			{name: "code"},
			{name: "layers_update_time"},
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
		return record.get('description')[0].toUpperCase();
    },
    loadCondition: function() {
    	return this.getData().length == 0;
    }
})
Ext.regStore("WERealtime.store.stationList", store);