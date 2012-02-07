Ext.define('WERealtime.model.TextdataHistory', {
    extend: "Ext.data.Model",
    config: {
    	fields: [
            {name: "id", type: "int"},
            {name: "version", type: "string"},
            {name: "update_time"},
            {name: "basin_id"},
            {name: "infotype_id"},
            {name: "station_strid"},
            {name: "status"},
            {name: "new_records"},
            {name: "all_records"},
        ]
    }
});



Ext.regStore("WERealtime.store.textdataHistory", {
    model: 'WERealtime.model.TextdataHistory',
    proxy: {
    	type: 'WERealtime.ajax',
        url: 'index.php',
        jsonData: {
    		request: "textdataHistoryList",
    		format: 'json'
    	},
        reader: {
            type: 'json',
        }
    },
})