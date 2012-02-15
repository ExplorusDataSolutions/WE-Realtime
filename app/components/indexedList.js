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