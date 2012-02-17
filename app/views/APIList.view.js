var viewApiList = {
	id: 'apiListCard',
	layout: 'fit',
	items : [ {
		// main top toolbar
		docked : 'top',
		xtype : 'toolbar',
		title : 'API calls', // will get added once loaded{
		items : [ {
			text : 'Back',
			ui : 'back',
		} ]
	}, {
		// the list itself, gets bound to the store programmatically once it's loaded
		id: 'apiList',
		xtype: 'WERealtime.list',
		store: null,
		itemTpl: '{i}. {menu}',
	}],
}