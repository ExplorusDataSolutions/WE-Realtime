Ext.define("WERealtime.model.Menu", {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int"
		}, {
			name : "menu",
			type : "string"
		}, {
			name : "description",
			type : "string"
		}, ]
	}
});

Ext.regStore('WERealtime.store.mainMenu', {
	model : 'WERealtime.model.Menu',
	data : [ {
		id : 1,
		menu : 'Ingesting history'
	}, {
		id : 2,
		menu : 'View stations'
	}, {
		id : 3,
		menu : 'API calls'
	}, ],
});

Ext.regStore('WERealtime.store.apiMenu', {
	model : 'WERealtime.model.Menu',
	data : [ {
		id : 31,
		menu : 'Stations with full status',
		description: [	'{',
		              	'  request: "stationList",',
		              	'  format: "json",',
		              	'}'
		              ].join('\n')
	}, {
		id : 32,
		menu : 'Ingesting history',
		description: [	'{',
		              	'  request: "ingestingVersionList",',
		              	'  format: "json",',
		              	'}'
		              ].join('\n')
	} ],
});