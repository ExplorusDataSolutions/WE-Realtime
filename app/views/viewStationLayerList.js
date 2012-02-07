var viewStationLayerList = {
	id : 'stationLayerListCard',
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
				id : 'stationLayerList',
				xtype : 'list',
				store : null,
				grouped : true,
				itemTpl : [ 'Layer {layerid}: {field}<br />',
						'<span class="we-h2">{begintime} - {endtime}</span>' ]
			} ]
}