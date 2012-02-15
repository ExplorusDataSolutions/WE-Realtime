Ext.define("WERealtime.model.Menu", {
	extend : "Ext.data.Model",
	config : {
		fields : [ {
			name : "id",
			type : "int"
		}, {
			name : "menu",
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
		menu : 'Menu 1'
	}, {
		id : 4,
		menu : 'Menu 2'
	}, {
		id : 5,
		menu : 'Menu 3'
	}, {
		id : 6,
		menu : 'More...'
	}, ],
});