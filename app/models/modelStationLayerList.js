Ext.define('WERealtime.model.StationLayerList', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "int"},
			{name: "layerid", type: "int"},
			{name: "description"},
			{name: "field"},
			{name: "begintime"},
			{name: "endtime"},
			{name: "basin_id", type: "int"},
			{name: "infotype_id", type: "int"},
	    ],
	}
});



Ext.regStore("WERealtime.store.stationLayerList", {
	model: 'WERealtime.model.StationLayerList',
	proxy: {
		type: 'WERealtime.ajax',
		url: 'index.php',
		jsonData: {
			request: "stationLayerList",
			format: 'json'
		},
		reader: {
			type: 'json',
		}
	},
	getGroupString : function(record) {
		var basin = record.get('basin_id');
		var infotype = record.get('infotype_id');
		return [ '<span class="group-basin-infotype" basin="', basin,
				'" infotype="', infotype, '">', 'Basin ', basin,
				', Data type ', infotype, '</span>' ].join('');
	},
})