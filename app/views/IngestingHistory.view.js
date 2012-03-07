var viewHistoryList = {
	id : 'ingestingHistoryCard',
	layout : 'fit',
	/*
	 * tabBar: { // the detail card contains two tabs: address and map docked:
	 * 'top', ui: 'light', layout: { pack: 'center' } },
	 */
	items : [
			{
				// also has a toolbar
				docked : 'top',
				xtype : 'toolbar',
				title : 'Ingesting history',
				items : [ {
					// containing a back button that slides back to list card
					text : 'Back',
					ui : 'back',
				} ]
			},
			{
				id : 'historyList',
				xtype : 'list',
				store : null,
				itemTpl : [
						'<tpl if="total">',
						'Version {version}, Total {total}<br />',
						'<span class="we-date">Final status:</span> <span class="we-h2">{final_status}</span><br />',
						'<span class="we-date">{start_time} -- {end_time}</span>',
						'<tpl else>',
						'Version {version}, Ingesting...<br />',
						'<span class="we-date">Current status:</span> <span class="we-h2">{final_status}</span><br />',
						'<span class="we-date">{start_time} -- Soon</span>',
						'</tpl>' ]
			}, {
				// also has a toolbar
				docked : 'bottom',
				xtype : 'toolbar',
				items : [ {
					xtype: 'spacer',
				}, {
					text : '',
				}, {
					xtype: 'spacer',
				} ]
			}, ]
}