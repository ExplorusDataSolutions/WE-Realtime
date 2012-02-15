var viewTextdataHistoryList = {
	id : 'textdataHistoryCard',
	layout : 'fit',
	items : [ {
		// also has a toolbar
		docked : 'top',
		xtype : 'toolbar',
		title : 'Text data',
		items : [ {
			// containing a back button that slides back to list card
			text : 'Back',
			ui : 'back',
		}, {
			xtype : 'spacer'
		}, {
			text : 'Refresh',
			ui : 'round'
		} ]
	}, {
		xtype : 'panel',
		scrollable : true,
		html : '',
	}, {
		docked : 'bottom',
		xtype : 'toolbar',
		items : [ {
			flex : 2,
			id : 'textdataSelect',
			xtype : 'selectfield',
			store : null,
		} ]
	} ]
}