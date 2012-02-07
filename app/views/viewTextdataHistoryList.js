var viewTextdataHistoryList = {
	id : 'textdataHistoryCard',
	layout : 'fit',
	items : [
			{
				// also has a toolbar
				docked : 'top',
				xtype : 'toolbar',
				title : '',
				items : [ {
					// containing a back button that slides back to list card
					text : 'Back',
					ui : 'back',
				} ]
			},
			{
				id : 'textdataList',
				xtype : 'list',
				store : null,
				itemTpl : [ 'Version {version}<br />',
						'<span class="we-date">{update_time}</span>' ]
			} ]
}