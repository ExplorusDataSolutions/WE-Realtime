Ext.define('WERealtime.model.Station', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "string", mapping : "Id"},
			{name: "oldDescription"},
			{name: "Description"},
			{name: "Code"},
			{name: "Datatypes"},
			{name: "BasinId"},
			{name: "DatatypeId"},
			{name: "layers_update_time"},
			{name: "Status"}
	    ],
	}
});


Ext.define('WERealtime.model.Station2', {
	extend: "Ext.data.Model",
	config: {
		fields: [
			{name: "id", type: "string", mapping : "Id2"},
			{name: "StationId", mapping : "Id"},
			{name: "oldDescription"},
			{name: "Description"},
			{name: "Code"},
			{name: "Datatypes"},
			{name: "BasinId"},
			{name: "DatatypeId"},
			{name: "layers_update_time"},
			{name: "Status"}
	    ],
	}
});


var store = Ext.create("WERealtime.extraInfoStore", {
    model: 'WERealtime.model.Station',
    proxy: {
    	type: 'WERealtime.ajax',
        url: 'index.php',
        jsonData: {
    		request: "stationList",
    		format: 'json'
    	},
        reader: {
            type: 'json',
        }
    },
    sorters: 'description',
    grouper : function(record) {
		return record.get('Description')[0].toUpperCase();
    },
    loadCondition: function() {
    	return this.getData().length == 0;
    }
})
Ext.regStore("WERealtime.store.stationList", store);


var store = Ext.create("WERealtime.extraInfoStore", {
	model: 'WERealtime.model.Station2',
	proxy: {
		type: 'WERealtime.ajax',
		url: 'index.php',
		jsonData: {
			request: "stationList",
			format: 'json'
		},
		reader: {
			type: 'json',
		}
	},
	sorters: 'Description',
	grouper : {
		groupFn : function(record) {
			return record.get('BasinId') + '.' + record.get('DatatypeId');
		},
		sorterFn : function(r1, r2) {
			var basin = 0 + r1.get('BasinId') - r2.get('BasinId');
			var type = 0 + r1.get('DatatypeId') - r2.get('DatatypeId');
			
			return basin == 0 ? type : basin;
		}
	},
	loadCondition: function() {
		return this.getData().length == 0;
	}
})
store.checkUpdates = function(basin_id, datatype_id, callback) {
	var me = this;
	
	var group, groups = this.getGroups();
	var groupName = basin_id + '.' + datatype_id;
	
	for (var i = 0, len = groups.length; i < len; i++) {
		group = groups[i];
		if (group.name == groupName) {
			for (var j = 0, len2 = group.children.length; j < len2; j++) {
				group.children[j].beginEdit();
				group.children[j].set('Status', 'deleted');
				group.children[j].endEdit(true);
			}
			break;
		}
	}
	
	Ext.Ajax.request({
		url : 'index.php',
		jsonData : {
			request : 'checkStationList',
			basin_id : basin_id,
			datatype_id : datatype_id,
			format : 'json',
		},
		method : 'POST',
		timeout : 1000 * 1000,
		success : function(response) {
			var result = Ext.decode(response.responseText);
			
			if (Ext.isArray(result)) {
				var newRecords = [];
				for (var i = 0, len = result.length; i < len; i++) {
					var record = me.getById(result[i].id + '_' + basin_id + '_' + datatype_id);
					if (record) {
						var old_desc = record.get('Description');
						var new_desc = result[i].name;
						var status = new_desc == old_desc ? 'same' : 'changed';
						
						record.beginEdit();
						if (status == 'changed') {
							record.set('oldDescription', old_desc);
						}
						record.set('Description', new_desc);
						record.set('Status', status);
						record.set('Code', result[i].code);
						record.endEdit(true);
					} else {
						newRecords.push({
							Id : result[i].id,
							Description : result[i].name,
							Status : 'new',
							Code : result[i].code,
							BasinId : basin_id,
							DatatypeId : datatype_id,
						});
					}
				}
				
				me.suspendEvents();
				var reader = me.getProxy().getReader();
				var Model = me.getModel();
				var records = reader.extractData(newRecords);
				for ( var i = 0, record; record = records[i]; i++) {
					records[i] = new Model(record.data, record.id, record.node);
				}
				me.add(records);
				me.resumeEvents();
			}
			
			if (callback) {
				callback();
			}
		}
	});
}
Ext.regStore("WERealtime.store.stationList2", store);