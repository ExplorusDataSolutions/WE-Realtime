Ext.define('WERealtime.dataview.List', {
    extend: 'Ext.dataview.List',
    xtype : 'WERealtime.list',

    doInitialize: function() {
        var me = this,
            container;

        me.on(me.getTriggerCtEvent(), me.onContainerTrigger, me);

        container = me.container = this.add(new WERealtime.dataview.IndexedList({
            baseCls: this.getBaseCls()
        }));
        container.dataview = me;

        container.on(me.getTriggerEvent(), me.onItemTrigger, me);

        container.element.on({
            delegate: '.' + this.getBaseCls() + '-disclosure',
            tap: 'handleItemDisclosure',
            scope: me
        });

        container.on({
            itemtouchstart: 'onItemTouchStart',
            itemtouchend: 'onItemTouchEnd',
            itemtap: 'onItemTap',
            itemtaphold: 'onItemTapHold',
            itemtouchmove: 'onItemTouchMove',
            itemdoubletap: 'onItemDoubleTap',
            itemswipe: 'onItemSwipe',
            scope: me
        });

        if (this.getStore()) {
            this.refresh();
        }
    },
	onIndex: function(indexBar, index) {
		var me = this,
		    key = index.toLowerCase(),
		    store = me.getStore(),
		    groups = store.getGroups(),
		    ln = groups.length,
		    scrollable = me.getScrollable(),
		    scroller, group, i, closest, id, item;
		
		if (scrollable) {
		    scroller = me.getScrollable().getScroller();
		}
		else {
		    return;
		}
		
		var compareAsNumber = false;
		if (key.match(/\d+\.\d*/)) {
			key = parseFloat(key);
			compareAsNumber = true;
		}
		for (i = 0; i < ln; i++) {
		    group = groups[i];
		    if (compareAsNumber) {
		    	id = parseFloat(group.name);
		    } else {
		    	id = group.name.toLowerCase();
		    }
		    if (id == key || id > key) {
		        closest = group;
		        break;
		    }
		    else {
		        closest = group;
		    }
		}
		
		if (scrollable && closest) {
		    item = me.container.getViewItems()[store.indexOf(closest.children[0])];
		
		    
		    scroller.stopAnimation();
		
		    
		    var containerSize = scroller.getContainerSize().y,
		        size = scroller.getSize().y,
		        maxOffset = size - containerSize,
		        offset = (item.offsetTop > maxOffset) ? maxOffset : item.offsetTop;
		
		    scroller.scrollTo(0, offset);
		}
	}
});

Ext.define('WERealtime.dataview.IndexedList', {
    extend: 'Ext.dataview.element.List',

    getItemElementConfig: function(index, data) {
    	data['i'] = index + 1;
        var me = this,
            dataview = me.dataview,
            config = {
                cls: me.itemClsShortCache,
                children: [{
                    cls: me.labelClsShortCache,
                    html: dataview.getItemTpl().apply(data)
                }]
            },
            iconSrc;

        if (dataview.getIcon()) {
            iconSrc = data.iconSrc;
            config.children.push({
                cls: me.iconClsShortCache,
                style: 'background-image: ' + iconSrc ? 'url("' + newSrc + '")' : ''
            });
        }

        if (dataview.getOnItemDisclosure()) {
            config.children.push({
                cls: me.disclosureClsShortCache + ((data.disclosure === false) ? me.hiddenDisplayCache : '')
            });
        }
        return config;
    },
});