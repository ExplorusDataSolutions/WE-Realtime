Ext.define('WERealtime.model.IngestingHistory', {
    extend: "Ext.data.Model",
    config: {
    	fields: [
            {name: "id", type: "int", mapping: 'version'},
            {name: "version", type: "string"},
            {name: "start_time"},
            {name: "end_time"},
            {name: "total"},
            {name: "final_status"},
            {name: "status_time"}
        ]
    }
});



Ext.regStore("WERealtime.store.ingestingHistory", {
    model: 'WERealtime.model.IngestingHistory',
    proxy: {
    	type: 'WERealtime.ajax',
        url: 'index.php',
        jsonData: {
    		request: "ingestingVersionList",
    		format: 'json'
    	},
        reader: {
            type: 'json',
        }
    },
})