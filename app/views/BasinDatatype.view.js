var viewBasinDatatype = {
	id : 'basinDataTypeCard',
	layout : 'fit',
	items : [ {
		docked : 'top',
		xtype : 'toolbar',
		title : '',
		items : [ {
			text : 'Back',
			ui : 'back',
		}, {
			xtype : 'spacer',
		}, {
			text : 'Check updates',
			ui : 'Refresh'
		}, {
			text : 'Check updates',
			ui : 'Refresh',
			hidden : true,
		}, {
			text : 'Check updates',
			ui : 'Refresh',
			hidden : true,
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
			title : 'Basins',
			xtype : 'WERealtime.list',
			store : null,
			itemTpl : [
					'{i}. ',
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
					'<span class="we-deleted">{oldDescription}</span> -> {Description}',
					'</tpl>',
					'<span class="we-h2"> (ID: {id})</span> ',
					'<tpl if="Status == \'new\'"> + </tpl>',
					'<tpl if="Status == \'changed\'"> * </tpl>',
					'<br /><span class="we-h2"> - Version: {Version} - ' ]
					.join('')
		}, {
			id : 'dataTypeList',
			title : 'Data Types',
			xtype : 'WERealtime.list',
			store : null,
			itemTpl : [ '{i}. ',
					'<tpl if="Status != \'deleted\' && !oldDescription">',
					'{Description}',
					'</tpl>',
					'<tpl if="Status == \'new\'">',
					'<span class="we-new">{Description}</span>',
					'</tpl>',
					'<tpl if="Status == \'deleted\'">',
					'<span class="we-deleted">{Description}</span>',
					'</tpl>',
					'<tpl if="Status == \'changed\' && oldDescription">',
					'<span class="we-deleted">{oldDescription}</span> -> {Description}',
					'</tpl>',
					'<span class="we-h2"> (ID: {id})</span> ',
					'<tpl if="Status == \'new\'"> + </tpl>',
					'<tpl if="Status == \'changed\'"> * </tpl>',
					'<tpl if="Status == \'changed\' && oldBasins">',
					'<br /><span class="we-h2"> - Version: {Version} - ',
					'Basins: <span class="we-deleted">[{oldBasins}]</span> -> [{Basins}]</span>',
					'<tpl else>',
					'<br /><span class="we-h2"> - Version: {Version} - Basins: [{Basins}]</span>',
					'</tpl>',
					].join('')
		}, {
			id : 'stationList2',
			title : 'Stations',
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
					'<span class="we-deleted">{oldDescription}</span> -> {Description}',
					'</tpl>',
					'<br />',
					'<span class="we-h2"> - Version: {Version} - {StationId} - {Code}</span>',
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
			hidden : false,
		}, {
			xtype : 'selectfield',
			hidden : true,
		}, {
			xtype : 'selectfield',
			hidden : true,
		}, {
			xtype : 'spacer',
		} ]
	} ],
}