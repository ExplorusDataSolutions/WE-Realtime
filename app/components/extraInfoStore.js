Ext.define("WERealtime.extraInfoStore", {
	extend: "Ext.data.Store",

	load : function() {
		var fn = this.config.loadCondition;
		if (Ext.isFunction(fn) && fn.apply(this, arguments)) {
			this.callParent(arguments);
		}
	},
	onProxyLoad: function(operation) {
		var extraInfo = operation.getResultSet().config.message;
		
		if (extraInfo && Ext.isFunction(this.config.onProcessExtraInfo)) {
			this.config.onProcessExtraInfo(extraInfo);
		}
		
		this.superclass.onProxyLoad.call(this, operation);
	},
})