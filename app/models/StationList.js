Ext.define('WERealtime.model.StationList', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "int"},
			{name: "strid", type: "string"},
			{name: "description"},
			{name: "code"},
	    ],
	}
});



Ext.regStore("WERealtime.store.stationList", {
    model: 'WERealtime.model.StationList',
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
    getGroupString : function(record) {
		return record.get('description')[0].toUpperCase();
    },
})