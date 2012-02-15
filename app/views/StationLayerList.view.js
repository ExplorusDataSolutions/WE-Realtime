var viewStationLayerList = {
	id : 'stationLayerListCard',
	layout : 'vbox',
	items : [
			{
				docked : 'top',
				xtype : 'toolbar',
				title : 'Station layers',
				// scrollable : {
				// direction : 'horizontal',
				// },
				items : [ {
					text : 'Back',
					ui : 'back',
				}, {
					xtype : 'spacer',
				}, {
					text : 'Reload',
					ui : 'round'
				} ]
			},
			{
				docked : 'bottom',
				xtype : 'toolbar',
				title : '',
			},
			{
				// html: '',
				// flex: 1,
				// height: 'auto',
				// style : 'text-align: center; padding: 5px',
				// }, {
				id : 'stationLayerList',
				flex : 2,
				xtype : 'list',
				store : null,
				grouped : true,
				itemTpl : [ '<tpl if="layerid == 0">', 'No layers found',
						'<tpl else>', '{field}<br />',
						'<span class="we-h2">ID: </span>',
						'<span class="we-date">{layerid}, </span> ',
						'<span class="we-h2">Range: </span> ',
						'<span class="we-date">{begintime} - {endtime}</span>',
						'</tpl>', ]
			} ]
}