var viewApiDemo = {
	id : 'apiDemoCard',
	layout : 'vbox',
	items : [ {
		// also has a toolbar
		docked : 'top',
		xtype : 'toolbar',
		title : 'API Demo',
		/*scrollable: {
			direction: 'horizontal',
			indicators: false,
		},*/
		items : [ {
			text : 'Back',
			ui : 'back',
		}, {
			xtype : 'spacer'
		}, {
			text : 'Send',
		}  ]
	}, {
		flex  : 1,
		xtype : 'textareafield',
		label : '<span style="font-size:15px">Request</span>',
		name : 'request',
		style : 'font-size: 10px;',
		readOnly : true,
	}, {
		flex  : 2,
		xtype : 'textareafield',
		label : '<span style="font-size:15px">Response</span>',
		name : 'response',
		placeHolder : 'Tap send button to get response',
		style : 'font-size: 10px;',
		readOnly : true,
	} ]
}