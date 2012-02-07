var viewStationList = {
	id : 'stationListCard',
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
				id : 'stationList',
				xtype : 'list',
				store : null,
				grouped : true,
				indexBar : true,
				itemTpl : [ '{description}<br />',
						'<span class="we-h2">{serial} - {strid}</span>' ]
			} ]
}