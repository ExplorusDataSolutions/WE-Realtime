var viewStationList = {
	id : 'stationListCard',
	layout : 'fit',
	items : [
			{
				// also has a toolbar
				docked : 'top',
				xtype : 'toolbar',
				title : '',
				/*
				 * scrollable: { direction: 'horizontal', indicators: false, },
				 */
				items : [ {
					text : 'Back',
					ui : 'back',
				}, {
					xtype : 'spacer',
				}, {
					text : 'Reload',
					ui : 'round',
				} ]
			},
			{
				docked : 'bottom',
				xtype : 'toolbar',
				title : '',
			},
			{
				id : 'stationList',
				xtype : 'WERealtime.list',
				store : null,
				grouped : true,
				indexBar : true,
				itemTpl : [
						'<span class="we-h2">{i}.</span> {Description}<br />',
						'<span class="we-h2">{id}</span>' ]
			} ]
}