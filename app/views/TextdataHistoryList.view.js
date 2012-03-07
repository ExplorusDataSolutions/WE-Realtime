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
		xtype : 'toolbar',
		docked : 'bottom',
		items : [ {
			xtype: 'spacer',
		}, {
			text : 'Parse current',
		}, {
			text : 'Parse all',
		}, {
			xtype: 'spacer',
		} ]
	}, {
		docked : 'bottom',
		xtype : 'toolbar',
		ui : 'light',
		items : [ {
			xtype: 'spacer',
		}, {
			id : 'textdataSelect',
			xtype : 'selectfield',
			store : null,
			style : 'text-align: center',
			disabled : true,
		}, {
			xtype: 'spacer',
		} ]
	} ]
}