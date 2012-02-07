Ext.define('WERealtime.ajax', {
	extend: "Ext.data.proxy.Ajax",
	alias: 'proxy.WERealtime.ajax',
	doRequest: function(operation, callback, scope) {
 		var writer  = this.getWriter(),
			request = this.buildRequest(operation);

		request.setConfig({
			headers       : this.getHeaders(),
			timeout       : this.getTimeout(),
			method        : this.getMethod(request),
			callback      : this.createRequestCallback(request, operation, callback, scope),
			scope         : this
		});


		//request = writer.write(request);

		Ext.Ajax.request(request.getCurrentConfig());

		return request;
    },
	getMethod: function(request) {
		return 'POST';
	},
	buildRequest: function(operation) {
		var me = this,
			params = Ext.applyIf(operation.getParams() || {}, me.getExtraParams() || {}),
			request;

		params = Ext.applyIf(params, me.getParams(operation));
		params = Ext.applyIf(params, me.config.jsonData || {});
		request = Ext.create('Ext.data.Request', {
			//params   : {},
			jsonData : params,
			action   : operation.getAction(),
			records  : operation.getRecords(),
			url      : operation.getUrl(),
			operation: operation,
			proxy    : me
		});

		request.setUrl(me.buildUrl(request));
		operation.setRequest(request);

		return request;
	},
	buildUrl: function(request) {
		return this.getUrl(request);
	}
})