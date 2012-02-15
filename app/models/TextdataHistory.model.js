Ext.define('WERealtime.model.TextdataHistory', {
    extend: "Ext.data.Model",
    config: {
    	fields: [
            {name: "id", type: "int", mapping: 'Id'},
            {name: "version", type: "int", mapping: 'Version'},
            {name: "ingest_time", mapping: 'IngestTime'},
            {name: "basin_id"},
            {name: "infotype_id"},
            {name: "station_strid"},
            {name: "Status"},
            {name: "new_records", mapping: "NewRecords"},
            {name: "all_records", mapping: "AllRecords"},
            {name: "text_id", mapping: "text_id"}
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