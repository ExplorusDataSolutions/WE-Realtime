var viewMainMenu = {
	id: 'mainMenuCard',
	layout: 'fit',
	items: [{
		// main top toolbar
		docked : 'top',
		xtype: 'toolbar',
		title: 'WE-Realtime Ingesting Tool' // will get added once loaded
	},{
		// the list itself, gets bound to the store programmatically once it's loaded
		id: 'mainMenuList',
		xtype: 'WERealtime.list',
		store: null,
		itemTpl: '{menu}{description}',
	}],
}