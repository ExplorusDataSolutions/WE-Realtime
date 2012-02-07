var viewLayerDataList = {
	id : 'layerDataListCard',
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
				id : 'layerDataList',
				xtype : 'list',
				store : null,
				grouped: true,
				itemTpl : [ '{value}<br />',
						'<span class="we-h2">{time} - text: {text_id}</span>' ]
			} ]
}