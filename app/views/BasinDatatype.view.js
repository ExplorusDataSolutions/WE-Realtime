var viewBasinDatatype = {
	id : 'basinDataTypeCard',
	layout : 'fit',
	items : [ {
		docked : 'top',
		xtype : 'toolbar',
		title : 'Basins and Data types',
		items : [ {
			text : 'Back',
			ui : 'back',
		}, {
			xtype : 'spacer',
		}, {
			text : 'Check updates',
			ui : 'Refresh'
		} ]
	}, {
		id : 'basinDatatypeList',
		xtype : 'carousel',
		scrollable: {
			direction: 'vertical',
			directionLock: true,
		},
		items : [ {
			id : 'basinList',
			xtype : 'WERealtime.list',
			store : null,
			itemTpl : '{i}. '
				+ '<tpl if="Status == \'deleted\'"><span class="we-deleted"></tpl>'
				+ '{Description}'
				+ '<tpl if="Status == \'deleted\'"></span></tpl>'
				+ '<span class="we-h2"> (ID: {id})</span> '
				+ '<tpl if="Status == \'new\'"> + </tpl>'
				+ '<tpl if="Status == \'changed\'"> * </tpl>'
		}, {
			id : 'dataTypeList',
			xtype : 'WERealtime.list',
			store : null,
			itemTpl : '{i}. '
				+ '<tpl if="oldDescription"><span class="we-deleted">{oldDescription}</span> -> </tpl>'
				+ '<tpl if="Status == \'deleted\'"><span class="we-deleted"></tpl>'
				+ '{Description}'
				+ '<tpl if="Status == \'deleted\'"></span></tpl>'
				+ '<span class="we-h2"> (ID: {id})</span> '
				+ '<tpl if="Status == \'new\'"> + </tpl>'
				+ '<tpl if="Status == \'changed\'"> * </tpl>'
				
				+ '<br /><span class="we-h2">Basins: [{Basins}]</span>'
		}, {
			id : 'stationList2',
			xtype : 'WERealtime.list',
			store : null,
			selectedCls : '',
			grouped : true,
			itemTpl : [
						'<span class="we-h2">{i}.</span> ',
						'<tpl if="!Status || Status == \'same\'">',
						'{Description}',
						'</tpl>',
						'<tpl if="Status == \'new\'">',
						'<span class="we-new">{Description}</span>',
						'</tpl>',
						'<tpl if="Status == \'deleted\'">',
						'<span class="we-deleted">{Description}</span>',
						'</tpl>',
						'<tpl if="Status == \'changed\'">',
						'<span class="we-deleted">{oldDescription}</span> {Description}',
						'</tpl>',
						'<br />',
						'<span class="we-h2"> - {StationId} - {Code}</span>',
						'<tpl if="Status == \'new\'"> + </tpl>',
						'<tpl if="Status == \'changed\'"> * </tpl>',
						''].join(''),
			indexBar : {
				letters : [],
				style : 'width: 80px;',
			}
		} ]
	}, {
		docked : 'bottom',
		xtype : 'toolbar',
		items : [ {
			xtype : 'spacer',
		}, {
			xtype : 'selectfield',
		}, {
			xtype : 'spacer',
		}, {
			text : 'View html page',
		} ]
	} ],
}